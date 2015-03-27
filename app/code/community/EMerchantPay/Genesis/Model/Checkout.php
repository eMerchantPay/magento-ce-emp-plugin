<?php

class EMerchantPay_Genesis_Model_Checkout extends Mage_Payment_Model_Method_Abstract
{
	/**
	 * unique internal payment method identifier
	 *
	 * @var string [a-z0-9_]
	 */
	protected $_code = 'emerchantpay_checkout';

	protected $_formBlockType = 'emerchantpay/form_checkout';
	protected $_infoBlockType = 'emerchantpay/info_checkout';

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
	public function authorize(Varien_Object $payment, $amount)
	{
		try {
			$this->getHelper()->initClient($this->getCode());

			$order = $payment->getOrder();

			$billing    = $order->getBillingAddress();
			$shipping   = $order->getShippingAddress();

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
					->setNotificationUrl(
                        $this->getHelper()->getNotifyURL('checkout')
                    )
					->setReturnSuccessUrl(
                        $this->getHelper()->getSuccessURL('checkout')
                    )
					->setReturnFailureUrl(
                        $this->getHelper()->getFailureURL('checkout')
                    )
					->setReturnCancelUrl(
                        $this->getHelper()->getCancelURL('checkout')
                    )
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
					->setShippinCountry($shipping->getCountry());

            foreach ($this->getTransactionTypes() as $type) {
                $genesis->request()->addTransactionType($type);
            }

			$genesis->execute();

			if (!isset($genesis->response()->getResponseObject()->redirect_url)) {
				throw new Exception('Invalid Response.');
			}

			$payment->setIsTransactionPending(true);
			$payment->setSkipTransactionCreation(true);

			// Save the redirect url with our
			Mage::getSingleton('core/session')->setEmerchantPayCheckoutRedirectUrl(
				strval($genesis->response()->getResponseObject()->redirect_url)
			);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
            Mage::throwException($this->getHelper()->__('Authorize attempt error!'));
		}

		return $this;
	}

    /**
     * Refund the last successful transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return $this
     */
    public function refund(Varien_Object $payment, $amount)
    {
        Mage::log('Refund transaction for Order #' . $payment->getOrder()->getIncrementId());

        try{
            $this->getHelper()->initClient($this->getCode());

            $this->getHelper()->setTokenByPaymentTransaction($payment);

            $genesis = new \Genesis\Genesis('Financial\Refund');

            $genesis
                ->request()
                ->setTransactionId(
                    $this->getHelper()->genTransactionId(
                        $payment->getOrder()->getIncrementId()
                    )
                )
                ->setRemoteIp( $this->getHelper('core/http')->getRemoteAddr(false) )
                ->setReferenceId($payment->getRefundTransactionId())
                ->setCurrency($payment->getOrder()->getBaseCurrencyCode())
                ->setAmount($amount);

            $genesis->execute();

            if (!$genesis->response()->isSuccessful()) {
                throw new Exception($genesis->response()->getResponseObject()->technical_message);
            }

            $information = array();

            foreach ($genesis->response()->getResponseObject() as $key => $value) {
                $information[strval($key)] = strval($value);
            }

            $payment->setTransactionId($genesis->response()->getResponseObject()->unique_id)
                    ->setParentTransactionId( $payment->getRefundTransactionId() )
                    ->setTransactionAdditionalInfo(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                        $information
                    );
        }
        catch (Exception $exception) {
            Mage::logException($exception);
            Mage::throwException(
                $this->getHelper()->__('We were unable to refund the selected transaction. Please try again or contact us, if the problem persists!')
            );
        }

        return $this;
    }

    /**
     * Void the last successful transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return mixed
     */
    public function void(Varien_Object $payment)
    {
        try{
            $this->getHelper()->initClient($this->getCode());

            $this->getHelper()->setTokenByPaymentTransaction($payment);

            $genesis = new \Genesis\Genesis('Financial\Void');

            $genesis
                ->request()
                ->setTransactionId(
                    $this->getHelper()->genTransactionId(
                        $payment->getOrder()->getIncrementId()
                    )
                )
                ->setRemoteIp( $this->getHelper('core/http')->getRemoteAddr(false) )
                ->setReferenceId($payment->getTransactionId());

            $genesis->execute();

            if (!$genesis->response()->isSuccessful()) {
                throw new Exception($genesis->response()->getResponseObject()->technical_message);
            }

            $information = array();

            foreach ($genesis->response()->getResponseObject() as $key => $value) {
                $information[strval($key)] = strval($value);
            }

            $payment->setTransactionId($genesis->response()->getResponseObject()->unique_id)
                    ->setParentTransactionId( $payment->getTransactionId() )
                    ->setTransactionAdditionalInfo(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                        $information
                    );
        }
        catch (Exception $exception) {
            Mage::logException($exception);
            Mage::throwException(
                $this->getHelper()->__('We were unable to cancel (void) the selected transaction. Please try again or contact us, if the problem persists!')
            );
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
			$this->getHelper()->initClient($this->getCode());

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
	 * @param stdClass $payment_transaction
     *
	 * @return bool true/false based on successful/unsuccessful status
	 */
	public function processAuthNotification($payment_transaction)
	{
		try {
			$this->getHelper()->initClient($this->getCode());

			list($increment_id, $salt) = explode('-', $payment_transaction->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($payment_transaction as $key => $value) {
					$information[strval($key)] = strval($value);
				}

                // Transaction data
				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

                // Token
                $order->getPayment()->setTransactionAdditionalInfo(
                    'token',
                    $payment_transaction->terminal_token
                );

				if ($payment_transaction->status == 'approved') {
					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
						->setTransactionId(strval($payment_transaction->unique_id))
						->setCcTransId(strval($payment_transaction->unique_id))
						->setCurrencyCode(strval($payment_transaction->currency))
						->setIsTransactionClosed(false)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->registerAuthorizationNotification(
							\Genesis\Utils\Currency::exponentToReal($payment_transaction->amount, $payment_transaction->currency)
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
						->setTransactionId(strval($payment_transaction->unique_id))
						->setCurrencyCode(strval($payment_transaction->currency))
						->setIsTransactionClosed(true)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->addTransaction(
							'capture',
							null,
							false,
							$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $payment_transaction->message)
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
            Mage::throwException('SHIT HAPPENED YO: ' . $exception->getMessage());
		}

		return false;
	}

	/**
	 * Process Sale-type (Auth/Capture) Transaction
	 *
	 * @param stdClass $payment_transaction
	 *
	 * @return bool true/false on successful/unsuccessful status
	 */
	public function processAuthCaptureNotification($payment_transaction)
	{
		try {
            $this->getHelper()->initClient($this->getCode());

			list($increment_id, $salt) = explode('-', $payment_transaction->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($payment_transaction as $key => $value) {
					$information[strval($key)] = strval($value);
				}

                // Transaction data
				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

                // Token
                $order->getPayment()->setTransactionAdditionalInfo(
                    'token',
                    $payment_transaction->terminal_token
                );

				if ($payment_transaction->status == 'approved') {

					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
						->setTransactionId(strval($payment_transaction->unique_id))
						->setCcTransId(strval($payment_transaction->unique_id))
						->setCurrencyCode(strval($payment_transaction->currency))
						->setIsTransactionClosed(false)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->registerCaptureNotification(
							\Genesis\Utils\Currency::exponentToReal($payment_transaction->amount, $payment_transaction->currency)
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
						->setTransactionId(strval($payment_transaction->unique_id))
						->setCurrencyCode(strval($payment_transaction->currency))
						->setIsTransactionClosed(true)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->addTransaction(
							'capture',
							null,
							false,
							$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $payment_transaction->message)
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
     * Get the selected transaction types in array
     *
     * @return array
     */
    public function getTransactionTypes()
    {
        return array_filter(explode(',', $this->getConfigData('genesis_types')));
    }


	/**
	 * Get URL to "Redirect" block
	 *
	 * @see EMerchantPay_Genesis_CheckoutController
	 *
	 * @note In order for redirect to work, you must
	 * set the session variable "EmerchantPayGenesisCheckoutRedirectUrl"
	 *
	 * @return mixed
	 */
	public function getOrderPlaceRedirectUrl()
    {
		return $this->getHelper()->getRedirectUrl('checkout');
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