<?php
/*
 * Copyright (C) 2015 eMerchantPay Ltd.
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
 * @copyright   2015 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * Class EMerchantPay_Genesis_Helper_Data
 *
 * Helper functions for eMerchantPay Direct / Checkout
 */
class EMerchantPay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
    /**
     * Include Genesis library
     *
     * @return void
     */
    public function initLibrary()
    {
        // Mitigate PHP Bug #52339, as Magento already registers their AutoLoader
        if (!class_exists('\Genesis\Genesis', false)) {
            include Mage::getBaseDir('lib') . DS . 'Genesis' . DS . 'vendor' . DS . 'autoload.php';
        }
    }
    /**
     * Check whether Genesis is initialized and init if not
     *
     * @param string $model Name of the model, for which we query settings
     *
     * @return void
     */
    public function initClient($model)
    {
        $this->initLibrary();

        //\Genesis\Config::setEndpoint('emerchantpay');

        \Genesis\Config::setUsername($this->getConfigData($model, 'genesis_username'));
        \Genesis\Config::setPassword($this->getConfigData($model, 'genesis_password'));

        \Genesis\Config::setEnvironment($this->getConfigData($model, 'genesis_environment'));

        \Genesis\Config::setToken(
            is_null($this->getConfigData($model, 'genesis_token')) ? '' : $this->getConfigData($model, 'genesis_token')
        );
    }

    /**
     * Get Module Configuration Key
     *
     * @param string $model Name of the Model
     * @param string $key Configuration Key
     *
     * @return mixed The content of the requested key
     */
    public function getConfigData($model, $key)
    {
        return Mage::getStoreConfig(sprintf('payment/%s/%s', $model, $key));
    }

    /**
     * Get A Success URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getSuccessURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/success', $model), array('_secure' => true));
    }

    /**
     * Get A Failure URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getFailureURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/failure', $model), array('_secure' => true));
    }

    /**
     * Get A Cancel URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getCancelURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/cancel', $model), array('_secure' => true));
    }

    /**
     * Get A Notification URL
     *
     * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getNotifyURL($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/notify', $model), array('_secure' => true));
    }

    /**
     * Get a Redirect URL for the module
     *
     * @param string $model Name of the Model (Checkout/Direct)
     *
     * @return string
     */
    public function getRedirectUrl($model)
    {
        return Mage::getUrl(sprintf('emerchantpay/%s/redirect', $model), array('_secure' => true));
    }

    /**
     * Generate Transaction Id based on the order id
     * and salted to avoid duplication
     *
     * @param string|int $increment_id IncrementId of the Order
     *
     * @return string
     */
    public function genTransactionId($increment_id = 0)
    {
        return sprintf('%s-%s', $increment_id, strtoupper(md5(microtime(true) . ':' . mt_rand())));
    }

    /**
     * Get the current locale in 2-digit i18n format
     *
     * @return string
     */
    public function getLocale()
    {
        $locale = Mage::app()->getLocale()->getLocaleCode();

        return substr($locale, 0, 2);
    }

    /**
     * During "Checkout" we don't know have a Token,
     * however its required at a latter stage, which
     * means we have to extract it from the payment
     * data. We save the token when we receive a
     * notification from Genesis, then we only have
     * to find the earliest payment_transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return void
     */
    public function setTokenByPaymentTransaction($payment)
    {
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                          ->setOrderFilter($payment->getOrder())
                          ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_ASC);

        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        foreach ($collection as $transaction) {
            $information = $transaction->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );

            foreach ($information as $field => $value) {
                if ($field == 'terminal_token') {
                    \Genesis\Config::setToken($value);
                }
            }
        }
    }

    /**
     * Get an array of (k=>v) from stdClass Genesis response
     *
     * @param $response
     * @return array
     */
    public function getArrayFromGatewayResponse($response)
    {
        $transaction_details = array();

        foreach ($response as $key => $value) {
            if (is_string($value)) {
                $transaction_details[$key] = $value;
            }

            if ($value instanceof DateTime) {
                $transaction_details[$key] = $value->format('c');
            }
        }

        return $transaction_details;
    }

    /**
     * Get list of items in the order
     *
     * @see API parameter "Usage" or "Description"
     *
     * @param Mage_Sales_Model_Order_Payment $order
     *
     * @return string Formatted List of Items
     */
    public function getItemList($order)
    {
        $productResult = array();

        foreach ($order->getAllItems() as $item) {
            /** @var $item Mage_Sales_Model_Quote_Item */
            $product = $item->getProduct();

            $productResult[$product->getSku()] = array(
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'qty' => isset($productResult[$product->getSku()]['qty']) ? $productResult[$product->getSku()]['qty'] : 1,
            );
        }

        $description = '';

        foreach ($productResult as $product) {
            $description .= sprintf("%s (%s) x %d\r\n", $product['name'], $product['sku'], $product['qty']);
        }

        return $description;
    }

    /**
     * Restore customer Quote
     *
     * @param $shouldCancel
     * @return bool
     */
    public function restoreQuote($shouldCancel = false)
    {
        $order = $this->getCheckoutSession()->getLastRealOrder();

        if ($order->getId()) {
            $quote = $this->getQuote($order->getQuoteId());

            if ($shouldCancel && $order->canCancel()) {
                $order->cancel()->save();
            }

            if ($quote->getId()) {
                $quote->setIsActive(1)
                      ->setReservedOrderId(null)
                      ->save();
                $this->getCheckoutSession()
                     ->replaceQuote($quote)
                     ->unsLastRealOrderId();

                return true;
            }
        }

        return false;
    }

    /**
     * Return checkout session instance
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckoutSession()
    {
        return Mage::getSingleton('checkout/session');
    }

    /**
     * Return sales quote instance for specified ID
     *
     * @param int $quoteId Quote identifier
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote($quoteId)
    {
        return Mage::getModel('sales/quote')->load($quoteId);
    }

    /**
     * Redirect the visitor to the login page if
     * they are not logged in
     *
     * @param string $target Alternative target, if you don't want to redirect to login
     *
     * @return void
     */
    public function redirectIfNotLoggedIn($target = null)
    {
        /** @var Mage_Customer_Helper_Data $customer */
        $customer = Mage::helper('customer');

        if (!$customer->isLoggedIn()) {
            $url = $target ? $target : Mage::getUrl('customer/account/login', array('_secure' => true));

            Mage::app()
                ->getFrontController()
                ->getResponse()
                ->setRedirect($url)
                ->sendHeaders();

            exit(0);
        }
    }
} 