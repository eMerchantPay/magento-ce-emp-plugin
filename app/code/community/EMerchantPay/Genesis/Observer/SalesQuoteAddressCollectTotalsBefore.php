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
 * EMerchantPay Recurring Checkout Observer
 * Sets the Default Init Recurring Fee if not defined for product
 *
 * Class EMerchantPay_Genesis_Observer_SalesQuoteAddressCollectTotalsBefore
 */
class EMerchantPay_Genesis_Observer_SalesQuoteAddressCollectTotalsBefore
{
    private $_methodCodes = array(
        'emerchantpay_checkout',
        'emerchantpay_direct'
    );

    /**
     * Observer Event Handler
     * @param Varien_Event_Observer $observer
     */
    public function handleAction($observer)
    {
        $event = $observer->getEvent();
        $quoteAddress = $event->getQuoteAddress();

        if (is_object($quoteAddress) && is_object($quoteAddress->getQuote()->getPayment())) {
            $paymentMethodCode = $quoteAddress->getQuote()->getPayment()->getMethod();

            if (isset($paymentMethodCode) && in_array($paymentMethodCode, $this->getMethodCodes())) {

                if ($this->getHelper()->getIsMethodAvailable($paymentMethodCode, $quoteAddress->getQuote())) {
                    foreach ($quoteAddress->getAllNominalItems() as $item) {
                        $product = $item->getProduct();

                        if (is_object($product) && $product->getIsRecurring() && is_array($product->getRecurringProfile())) {
                            $productRecurringProfile = $product->getRecurringProfile();

                            if ($this->getMustOverrideProfileInitAmount($productRecurringProfile)) {
                                $productRecurringProfile['init_amount'] =
                                    $this->getHelper()->getMethodInitRecurringFee(
                                        $paymentMethodCode
                                    );
                                $product->setRecurringProfile(
                                    $productRecurringProfile
                                );
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Return the available payment methods
     * @return array
     */
    protected function getMethodCodes()
    {
        return $this->_methodCodes;
    }

    /**
     * @return EMerchantPay_Genesis_Helper_Data
     */
    protected function getHelper()
    {
        return Mage::helper('emerchantpay');
    }

    /**
     * Returns true if no Initial Fee is defined for the Nominal Item
     *
     * @param Mage_Sales_Model_Recurring_Profile $recurringProfile
     * @return bool
     */
    protected function getMustOverrideProfileInitAmount($recurringProfile)
    {
        return
            is_array($recurringProfile) &&
            (
                !isset($recurringProfile['init_amount']) ||
                empty($recurringProfile['init_amount']) ||
                ($recurringProfile['init_amount'] <= 0)
            );
    }
}