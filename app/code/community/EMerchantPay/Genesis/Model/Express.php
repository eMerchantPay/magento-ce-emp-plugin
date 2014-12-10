<?php

class EMerchantPay_Genesis_Model_Express extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * unique internal payment method identifier
	 *
	 * @var string [a-z0-9_]
	 */
	protected $_code = 'emerchantpay_express';

	protected $_formBlockType = 'emerchantpay/form_express';
	protected $_infoBlockType = 'emerchantpay/info_express';

	protected $_isGateway               = true;
	protected $_canAuthorize            = true;
	protected $_canCapture              = true;
	protected $_canCapturePartial       = true;
	protected $_canRefund               = true;
	protected $_canVoid                 = true;
	protected $_canUseInternal          = true;
	protected $_canUseCheckout          = true;

	protected $_canUseForMultishipping  = false;
	protected $_canSaveCc               = false;

	/**
	 * WPF Create method piggyback-ing the Magento's internal Authorize method
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param String $amount
	 *
	 * @return mixed
	 */
	public function authorize($payment, $amount)
	{
		try {
			$this->getHelper()->initClient();

			$order = $payment->getOrder();

			$billing = $order->getBillingAddress();
			$shipping = $order->getShippingAddress();

			$endpoints = array(
				'notify'    => $this->getHelper()->getNotifyURL('express'),
				'success'   => $this->getHelper()->getSuccessURL('express'),
				'failure'   => $this->getHelper()->getFailureURL('express'),
				'cancel'    => $this->getHelper()->getCancelURL('express')
			);

			$genesis = new \Genesis\Genesis('WPF\Create');

			$genesis
				->request()
					->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount)
					->setUsage('Magento Payment')
					->setDescription($this->getHelper()->getItemList($order))
					->setCustomerPhone($billing->getTelephone())
					->setCustomerEmail($order->getCustomerEmail())
					->setNotificationUrl($endpoints['notify'])
					->setReturnSuccessUrl($endpoints['success'])
					->setReturnFailureUrl($endpoints['failure'])
					->setReturnCancelUrl($endpoints['cancel'])
					->setBillingFirstName($billing->getData('firstname'))
					->setBillingLastName($billing->getData('lastname'))
					->setBillingAddress1($billing->getStreet(1))
					->setBillingAddress2($billing->getStreet(2))
					->setBillingZipCode($billing->getPostcode())
					->setBillingCity($billing->getCity())
					->setBillingState($billing->getRegion())
					->setBillingCountry($billing->getCountry())
					->setShippingFirstName($shipping->getData('firstname'))
					->setShippingLastName($shipping->getData('lastname'))
					->setShippingAddress1($shipping->getStreet(1))
					->setShippingAddress2($shipping->getStreet(2))
					->setShippingZipCode($shipping->getPostcode())
					->setShippingCity($shipping->getCity())
					->setShippingState($shipping->getRegion())
					->setShippinCountry($shipping->getCountry())
					->addTransactionType('authorize');

			$genesis->execute();

			if (!isset($genesis->response()->getResponseObject()->redirect_url)) {
				throw new Exception('Invalid Response.');
			}

			$payment->setIsTransactionPending(true);
			$payment->setSkipTransactionCreation(true);

			// Save the redirect url with our
			Mage::getSingleton('core/session')->setEmerchantPayExpressRedirectUrl(
				strval($genesis->response()->getResponseObject()->redirect_url)
			);

			/*
			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($response->technical_message);
			}

			$payment->setCcTransId($response->unique_id);
			$payment->setTransactionId($response->unique_id);
			$payment->setIsTransactionClosed(false);

			$payment->setTransactionAdditionalInfo(
				Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
				$genesis->response()->getResponseRaw()
			);
			*/
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException($this->getHelper()->__('Authorize attempt error!'));
		}

		return $this;
	}

	/**
	 * Execute a WPF Reconcile
	 *
	 * @param $unique_id
	 *
	 * @return $this
	 * @throws Mage_Core_Exception
	 */
	public function reconcile($unique_id)
	{
		try {
			$this->getHelper()->initClient();

			$genesis = new \Genesis\Genesis('WPF\Reconcile');

			$genesis->request()->setUniqueId($unique_id);

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			if (!isset($genesis->response()->getResponseObject()->status)) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			return $response;
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException($this->getHelper()->__('Reconcile attempt error!'));
		}

		return false;
	}

	/**
	 * Process a notification for Authorize-type Transaction
	 *
	 * @param $reconcile stdClass
	 *
	 * @return bool true/false based on successful/unsuccessful status
	 */
	public function processAuthNotification($reconcile)
	{
		try {
			$this->getHelper()->initClient();

			list($increment_id, $salt) = explode('-', $reconcile->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($reconcile as $key => $value) {
					$information[strval($key)] = strval($value);
				}

				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

				if ($reconcile->status == 'approved') {

					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCcTransId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(false)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->registerAuthorizationNotification(
							\Genesis\Utils\Currency::exponentToReal($reconcile->amount, $reconcile->currency)
						);

					// notify customer
					$invoice = $order->getPayment()->getCreatedInvoice();

					if ($invoice && !$order->getEmailSent()) {
						$order->addStatusHistoryComment(
							$this->getHelper()->__('Notified customer about invoice #%s.', $invoice->getIncrementId())
						);

						$order->sendNewOrderEmail()
						      ->setIsCustomerNotified(true)
						      ->save();
					}
					else {
						$order->save();
					}
				}
				else {
					// Add the transaction just in case
					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Failed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(true)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->addTransaction(
							'capture',
							null,
							false,
							$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $reconcile->message)
						);

					// Set status
					$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->setStatus(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->save();
				}

				return true;
			}
		}
		catch(Exception $exception) {
			Mage::logException($exception);
		}

		return false;
	}

	/**
	 * Process Sale-type (Auth/Capture) Transaction
	 *
	 * @param $reconcile
	 *
	 * @return bool true/false on successful/unsuccessful status
	 */
	public function processAuthCaptureNotification($reconcile)
	{
		try {
			list($increment_id, $salt) = explode('-', $reconcile->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($reconcile as $key => $value) {
					$information[strval($key)] = strval($value);
				}

				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

				if ($reconcile->status == 'approved') {

					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCcTransId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(false)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->registerCaptureNotification(
							\Genesis\Utils\Currency::exponentToReal($reconcile->amount, $reconcile->currency)
						);

					// notify customer
					$invoice = $order->getPayment()->getCreatedInvoice();

					if ($invoice && !$order->getEmailSent()) {
						$order->addStatusHistoryComment(
							$this->getHelper()->__('Notified customer about invoice #%s.', $invoice->getIncrementId())
						);

						$order->sendNewOrderEmail()
						      ->setIsCustomerNotified(true)
						      ->save();
					}
					else {
						$order->save();
					}
				}
				else {
					// Add the transaction just in case
					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Failed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(true)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->addTransaction(
							'capture',
							null,
							false,
							$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $reconcile->message)
						);

					// Set status
					$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->setStatus(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->save();
				}

				return true;
			}
		}
		catch (Exception $exception) {
			Mage::logException($exception);
		}

		return false;
	}

	/**
	 * Get URL to "Redirect" block
	 *
	 * @see EMerchantPay_Genesis_ExpressController
	 *
	 * @note In order for redirect to work, you must
	 * set the session variable "EmerchantPayGenesisExpressRedirectUrl"
	 *
	 * @return mixed
	 */
	public function getOrderPlaceRedirectUrl() {
		return $this->getHelper()->getRedirectUrl('express');
	}

	/**
	 * Get the helper or return its instance
	 *
	 * @param $helper string - Name of the helper, empty for the default class helper
	 *
	 * @return mixed
	 */
	private function getHelper( $helper = '' )
	{
		if (empty($helper)) {
			return Mage::helper('emerchantpay');
		}
		else {
			return Mage::helper($helper);
		}
	}
} 