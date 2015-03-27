<?php

class EMerchantPay_Genesis_CheckoutController extends Mage_Core_Controller_Front_Action
{
	/**
	 * Process an incoming Genesis Notification
	 * If it appears valid, do a reconcile and
	 * use the reconcile data to save details
	 * about the transaction
	 *
	 * @see Genesis_API_Documentation notification_url
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
		/** @var EMerchantPay_Genesis_Model_Checkout $checkout */
		$checkout   = Mage::getModel('emerchantpay/checkout');

		try {
			$helper->initClient($checkout->getCode());

			$notification = new \Genesis\API\Notification();
			$notification->parseNotification( $this->getRequest()->getPost() );

			if ( $notification->isAuthentic() ) {
                /** @var stdClass $reconcile */
				$reconcile = $checkout->reconcile($notification->getParsedNotification()->wpf_unique_id);

				if (isset($reconcile->payment_transaction)) {

					switch($reconcile->payment_transaction->transaction_type) {
						case 'authorize':
						case 'authorize3d':
							$status = $checkout->processAuthNotification($reconcile->payment_transaction);
							break;
						case 'sale':
						case 'sale3d':
							$status = $checkout->processAuthCaptureNotification($reconcile->payment_transaction);
							break;
						default:
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
	 * When a customer chooses eMerchantPay Checkout on
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
		$session->setEmerchantPayCheckoutQuoteId($session->getQuoteId());

		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('emerchantpay/redirect_checkout')->toHtml()
		);

		$session->unsQuoteId();
		$session->unsEmerchantPayCheckoutRedirectUrl();
	}

	/**
	 * Customer action when the user returns to the
	 * store, after successful transaction. However
	 * we still have no confirmation, so redirect
	 * the user, but wait for the Notification to
	 * complete the order.
	 *
	 * @see Genesis_API_Documentation return_success_url
	 *
	 * @return void
	 */
	public function successAction()
	{
        Mage::helper('emerchantpay')->redirectIfNotLoggedIn();

		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEmerchantPayCheckoutQuoteId(true));
		Mage::getSingleton('checkout/session')->getQuote()->setIsActive(false)->save();
		$this->_redirect('checkout/onepage/success', array('_secure'=>true));
	}

	/**
	 * Customer action when the transaction failed.
	 *
	 * @see Genesis_API_Documentation return_failure_url
	 *
	 * @return void
	 */
	public function failureAction()
	{
        $helper = Mage::helper('emerchantpay');
        $helper->redirectIfNotLoggedIn();

		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEmerchantPayCheckoutQuoteId(true));

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
	 * @see Genesis_API_Documentation return_cancel_url
	 *
	 * @return void
	 */
	public function cancelAction()
	{
        $helper = Mage::helper('emerchantpay');
        $helper->redirectIfNotLoggedIn();

        $session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEmerchantPayCheckoutQuoteId(true));

		if ($session->getLastRealOrderId()) {
			$order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
			if ($order->getId()) {
				$order->cancel()->save();
			}
			$helper->restoreQuote();
		}

		$this->_redirect('checkout/cart');
	}
}