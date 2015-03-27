<?php

class EMerchantPay_Genesis_DirectController extends Mage_Core_Controller_Front_Action
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
        /** @var EMerchantPay_Genesis_Model_Direct $direct */
        $direct   = Mage::getModel('emerchantpay/direct');

        try {
            $helper->initClient($direct->getCode());

            $notification = new \Genesis\API\Notification();
            $notification->parseNotification( $this->getRequest()->getPost() );

            if ( $notification->isAuthentic() ) {
                $reconcile = $direct->reconcile($notification->getParsedNotification()->unique_id);

                if (isset($reconcile->status) && !empty($reconcile->status)) {

                    // Process the notification based on its type
                    switch($reconcile->transaction_type) {
                        case 'authorize':
                        case 'authorize3d':
                            // Authorization workflow completion
                            $status = $direct->processAuthNotification($reconcile);
                            break;
                        case 'sale':
                        case 'sale3d':
                            // Sale (Auth/Capture) workflow completion
                            $status = $direct->processAuthCaptureNotification($reconcile);
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
	 * When a customer chooses eMerchantPay Checkout on
	 * Checkout/Payment page, show them a "transition"
	 * page where you notify them, that they will be
	 * redirected to a new website.
	 *
	 * @return void
	 */
	public function redirectAction()
	{
        /** @var EMerchantPay_Genesis_Helper_Data $helper */
        Mage::helper('emerchantpay')->redirectIfNotLoggedIn();

		$session = Mage::getSingleton('checkout/session');

		$session->setEMerchantPayDirectQuoteId($session->getQuoteId());
		$this->getResponse()->setBody(
			$this->getLayout()->createBlock('emerchantpay/redirect_direct')->toHtml()
		);
		$session->unsQuoteId();
		$session->unsEMerchantPayDirectRedirectUrl();
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
        /** @var EMerchantPay_Genesis_Helper_Data $helper */
        $helper = Mage::helper('emerchantpay');
        $helper->redirectIfNotLoggedIn();

        /** @var Mage_Core_Model_Session $target */
		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEMerchantPayDirectQuoteId(true));

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
        /** @var EMerchantPay_Genesis_Helper_Data $helper */
		$helper  = Mage::helper('emerchantpay');
        $helper->redirectIfNotLoggedIn();

        /** @var Mage_Core_Model_Session $target */
		$session = Mage::getSingleton('checkout/session');
		$session->setQuoteId($session->getEMerchantPayDirectQuoteId(true));

		Mage::getSingleton('checkout/session')->getQuote()->setIsActive(true)->save();

		Mage::getSingleton('core/session')->addError($helper->__('Payment attempt has failed. Please check your data and try again!'));

		$this->_redirect('checkout/cart', array('_secure'=>true));
	}
}