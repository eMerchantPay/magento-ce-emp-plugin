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
 * Class EMerchantPay_Genesis_Block_Redirect_Checkout
 *
 * Redirect Block for Checkout method
 */
class EMerchantPay_Genesis_Block_Redirect_Checkout extends Mage_Core_Block_Template
{
    /** @var String */
    protected $_uniqueId;
    /** @var EMerchantPay_Genesis_Helper_Data $helper */
    protected $_helper;

    protected function _construct()
    {
        parent::_construct();

        $this->setHelper();

        $this->setUniqueId();

        $this->setTemplate('emerchantpay/redirect/checkout.phtml');
    }

    /**
     * Generate HTML form
     *
     * @return string
     */
    public function generateForm()
    {
        $form = new Varien_Data_Form();

        $form
            ->setAction(
                $this->_helper->getCheckoutSession()->getEmerchantPayCheckoutRedirectUrl()
            )
            ->setId('emerchantpay_redirect_notification')
            ->setName('emerchantpay_redirect_notification')
            ->setMethod('GET')
            ->setUseContainer(true);

        $submitButton = new Varien_Data_Form_Element_Submit(
            array(
                'value' => $this->__('Click here, if you are not redirected within 10 seconds...'),
            )
        );

        $submitButton->setId(
            $this->getButtonId()
        );

        $form->addElement($submitButton);

        return $form->toHtml();
    }

    /**
     * Get the button id
     *
     * @return string
     */
    public function getButtonId()
    {
        return sprintf('redirect_to_dest_%s', $this->_uniqueId);
    }

    /**
     * Set Helper
     */
    protected function setHelper()
    {
        $this->_helper = Mage::helper('emerchantpay');
    }

    /**
     * Set Unique Id
     */
    protected function setUniqueId()
    {
        $this->_uniqueId = Mage::helper('core')->uniqHash();
    }
}
