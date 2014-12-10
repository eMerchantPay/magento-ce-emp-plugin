<?php

class EMerchantPay_Genesis_StandardController extends Mage_Core_Controller_Front_Action
{
	/**
	 * When a customer chooses eMerchantPay Express on
	 * Checkout/Payment page, show them a "transition"
	 * page where you notify them, that they will be
	 * redirected to a new website.
	 *
	 * @return void
	 */
	public function redirectAction()
	{
		$session = Mage::getSingleton('checkout/session');

		$session->setEMerchantPayStandardQuoteId($session->getQuoteId());
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('emerchantpay/redirect_standard')->toHtml()
		);
		$session->unsQuoteId();
		$session->unsEmerchantPayStandardRedirectUrl();
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
		$session->setQuoteId($session->getEMerchantPayStandardQuoteId(true));
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
		$helper  = Mage::helper('emerchantpay');
		$session = Mage::getSingleton('checkout/session');

		$session->setQuoteId($session->getEMerchantPayStandardQuoteId(true));
		Mage::getSingleton('checkout/session')->getQuote()->setIsActive(true)->save();
		Mage::getSingleton('core/session')->addError($helper->__('Payment attempt has failed. Please check your data and try again!'));
		$this->_redirect('checkout/cart', array('_secure'=>true));
	}

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
		$standard   = Mage::getModel('emerchantpay/standard');

		try {
			$helper->initClient();

			$notification = new \Genesis\API\Notification();
			$notification->parseNotification( $this->getRequest()->getPost() );

			if ( $notification->isAuthentic() ) {
				$reconcile = $standard->reconcile($notification->getParsedNotification()->unique_id);

				if (isset($reconcile->status) && !empty($reconcile->status)) {

					// Process the notification based on its type
					switch($reconcile->transaction_type) {
						case 'authorize':
						case 'authorize3d':
							// Authorization workflow completion
							$status = $standard->processAuthNotification($reconcile);
							break;
						case 'sale':
						case 'sale3d':
							// Sale (Auth/Capture) workflow completion
							$status = $standard->processAuthCaptureNotification($reconcile);
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
}