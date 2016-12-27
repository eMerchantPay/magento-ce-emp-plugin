<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      eMerchantPay
 * @copyright   2016 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * Class EMerchantPay_Genesis_DirectController
 *
 * Front-end method for Direct method
 */
class EMerchantPay_Genesis_DirectController extends Mage_Core_Controller_Front_Action
{
    /** @var EMerchantPay_Genesis_Helper_Data $helper */
    protected $_helper;

    /** @var EMerchantPay_Genesis_Model_Direct $direct */
    protected $_direct;

    protected function _construct()
    {
        $this->_helper = Mage::helper('emerchantpay');

        $this->_direct = Mage::getModel('emerchantpay/direct');
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

        try {
            $this->_helper->initClient($this->_direct->getCode());

            $notification = new \Genesis\API\Notification(
                $this->getRequest()->getPost()
            );

            if ($notification->isAuthentic()) {
                $notification->initReconciliation();

                $reconcile = $notification->getReconciliationObject();

                // @codingStandardsIgnoreStart
                if (isset($reconcile->unique_id)) {
                    // @codingStandardsIgnoreEnd

                    $this->_direct->processNotification($reconcile);

                    $this->getResponse()->clearHeaders();
                    $this->getResponse()->clearBody();

                    $this->getResponse()->setHeader('Content-type', 'application/xml');

                    $this->getResponse()->setBody(
                        $notification->generateResponse()
                    );

                    $this->getResponse()->setHttpResponseCode(200);
                }
            }
        } catch (Exception $exception) {
            Mage::logException($exception);
        }
    }

    /**
     * When a customer has to be redirected, show
     * a "transition" page where you notify them,
     * that they will be redirected to a new website.
     *
     * @return void
     */
    public function redirectAction()
    {
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('emerchantpay/redirect_direct')->toHtml()
        );
    }

    /**
     * Customer landing page for successful payment
     *
     * @see Genesis_API_Documentation \ return_success_url
     *
     * @return void
     */
    public function successAction()
    {
        $this->_redirect('checkout/onepage/success', array('_secure' => true));
    }

    /**
     * Customer landing page for unsuccessful payment
     *
     * @see Genesis_API_Documentation \ return_failure_url
     *
     * @return void
     */
    public function failureAction()
    {
        $this->_helper->restoreQuote();

        $this->_helper->getCheckoutSession()->addError(
            $this->_helper->__('We were unable to process your payment! Please check your input or try again later.')
        );

        $this->_redirect('checkout/cart', array('_secure' => true));
    }
}