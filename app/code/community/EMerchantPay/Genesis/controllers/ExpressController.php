<?php

class EMerchantPay_Genesis_ExpressController extends Mage_Core_Controller_Front_Action
{
	/**
	 * Process an incoming Genesis Notification
	 * If it appears valid, do a reconcile and
	 * use the reconcile data to save details
	 * about the transaction
	 *
	 * @see Genesis_API_Documentation \ notification_url
	 *
	 * @return void
	 */
	public function notifyAction()
	{
		// Notifications are only POST, deny everything else
		if (!$this->getRequest()->isPost()) {
			return;
		}

		/** @var EMerchantPay_Genesis_Helper_Data $helper */
		$helper     = Mage::helper('emerchantpay');
		/** @var EMerchantPay_Genesis_Model_Standard $standard */
		$standard   = Mage::getModel('emerchantpay/express');

		try {
			$helper->initClient();

			$notification = new \Genesis\API\Notification();
			$notification->parseNotification( $this->getRequest()->getPost() );

			if ( $notification->isAuthentic() ) {
				$reconcile = $standard->reconcile($notification->getParsedNotification()->wpf_unique_id);

				if (isset($reconcile->payment_transaction->status) && !empty($reconcile->payment_transaction->status)) {

					// Process the notification based on its type
					switch($reconcile->payment_transaction->transaction_type) {
						case 'authorize':
						case 'authorize3d':
							// Authorization workflow completion
							$status = $standard->processAuthNotification($reconcile->payment_transaction);
							break;
						case 'sale':
						case 'sale3d':
							// Sale (Auth/Capture) workflow completion
							$status = $standard->processAuthCaptureNotification($reconcile->payment_transaction);
							break;
						default:
							// Unsupported transaction type
							$status = false;
							break;
					}

					// Acknowledge notification
					if ($status) {
						$this->getResponse()->setHeader('Content-type', 'application/xml');
						$this->getResponse()->setBody($notification->getEchoResponse());
					}
				}
			}
		}
		catch (Exception $exception) {
			Mage::logException($exception);
		}
	}

	/**
	 * When a customer chooses eMerchantPay Express on
	 * Checkout/Payment page, show them a "transition"
	 * page where you notify them, that they will be
	 * redirected to a new website.
	 *
	 * @see Genesis_API_Documentation \ notification_url
	 *
	 * @return void
	 */
	public function redirectAction()
	{
		$session = Mage::getSingleton('checkout/session');
		$session->setEmerchantPayExpressQuoteId($session->getQuoteId());
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('emerchantpay/redirect_express')->toHtml()
		);
		$session->unsQuoteId();
		$session->unsEmerchantPayExpressRedirectUrl();
	}

	/**
	 * Customer action when the user returns to the
	 * store, after successful transaction. However
	 * we still have no confirmation, so redirect
	 * the user, but wait for the Notification to
	 * complete the order.
	 *
	 * @see Genesis_API_Documentation \ return_success_url
	 *
	 * @return void
	 */
	public function successAction()
	{
		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEmerchantPayExpressQuoteId(true));
		Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		$this->_redirect('checkout/onepage/success', array('_secure'=>true));
	}

	/**
	 * Customer action when the transaction failed.
	 *
	 * @see Genesis_API_Documentation \ return_failure_url
	 *
	 * @return void
	 */
	public function failureAction()
	{
		$helper = Mage::helper('emerchantpay');
		$session = Mage::getSingleton('checkout/session');

		$session->setQuoteId($session->getEmerchantPayExpressQuoteId(true));
		Mage::getSingleton('checkout/session')->getQuote()->setIsActive(true)->save();
		Mage::getSingleton('core/session')->addError(
			$helper->__('We were unable to process your payment. Please check your input or try again later')
		);
		$this->_redirect('checkout/cart', array('_secure'=>true));
	}

	/**
	 * Customer action when the user canceled the
	 * transaction. Just Mark the order as canceled
	 * and go back to the Cart.
	 *
	 * @see Genesis_API_Documentation \ return_cancel_url
	 *
	 * @return void
	 */
	public function cancelAction()
	{
		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEmerchantPayExpressQuoteId(true));
		if ($session->getLastRealOrderId()) {
			$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
			if ($order->getId()) {
				$order->cancel()->save();
			}
			Mage::helper('emerchantpay')->restoreQuote();
		}
		$this->_redirect('checkout/cart');
	}
}