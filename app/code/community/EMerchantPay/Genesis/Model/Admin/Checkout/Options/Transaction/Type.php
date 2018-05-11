<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
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
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * Class EMerchantPay_Genesis_Model_Admin_Transaction_Type
 *
 * Admin options Drop-down for Genesis Transaction Types
 */
class EMerchantPay_Genesis_Model_Admin_Checkout_Options_Transaction_Type
{
    /**
     * Pre-load the required files
     */
    public function __construct()
    {
        /** @var EMerchantPay_Genesis_Helper_Data $helper */
        $helper = Mage::helper('emerchantpay');

        $helper->initLibrary();
    }

    /**
     * Return the transaction types for an Options field
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();

        foreach ($this->getTransactionTypes() as $code => $name) {
            $options[] = array(
                'value' => $code,
                'label' => $name
            );
        }

        return $options;
    }

    /**
     * Get the transaction types as:
     *
     * key   = Code Name
     * value = Localized Name
     *
     * @return array
     */
    protected function getTransactionTypes()
    {
        return array(
            \Genesis\API\Constants\Transaction\Types::ABNIDEAL            =>
                Mage::helper('emerchantpay')->__('ABN iDEAL'),
            \Genesis\API\Constants\Transaction\Types::ALIPAY              =>
                Mage::helper('emerchantpay')->__('Alipay'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE           =>
                Mage::helper('emerchantpay')->__('Authorize'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D        =>
                Mage::helper('emerchantpay')->__('Authorize (3D-Secure)'),
            \Genesis\API\Constants\Transaction\Types::CASHU               =>
                Mage::helper('emerchantpay')->__('CashU'),
            \Genesis\API\Constants\Transaction\Types::CITADEL_PAYIN       =>
                Mage::helper('emerchantpay')->__('Citadel'),
            \Genesis\API\Constants\Payment\Methods::EPS                   =>
                Mage::helper('emerchantpay')->__('eps'),
            \Genesis\API\Constants\Transaction\Types::EZEEWALLET          =>
                Mage::helper('emerchantpay')->__('eZeeWallet'),
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY              =>
                Mage::helper('emerchantpay')->__('GiroPay'),
            \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN        =>
                Mage::helper('emerchantpay')->__('iDebit'),
            \Genesis\API\Constants\Transaction\Types::INPAY               =>
                Mage::helper('emerchantpay')->__('INPay'),
            \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN   =>
                Mage::helper('emerchantpay')->__('InstaDebit'),
            \Genesis\API\Constants\Payment\Methods::BCMC                  =>
                Mage::helper('emerchantpay')->__('Mr.Cash'),
            \Genesis\API\Constants\Payment\Methods::MYBANK                =>
                Mage::helper('emerchantpay')->__('MyBank'),
            \Genesis\API\Constants\Transaction\Types::NETELLER            =>
                Mage::helper('emerchantpay')->__('Neteller'),
            \Genesis\API\Constants\Transaction\Types::P24                 =>
                Mage::helper('emerchantpay')->__('P24'),
            \Genesis\API\Constants\Transaction\Types::PAYSAFECARD         =>
                Mage::helper('emerchantpay')->__('PaySafeCard'),
            \Genesis\API\Constants\Transaction\Types::PAYSEC_PAYIN        =>
                Mage::helper('emerchantpay')->__('PaySec'),
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE   =>
                Mage::helper('emerchantpay')->__('PayByVoucher (Sale)'),
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_YEEPAY =>
                Mage::helper('emerchantpay')->__('PayByVoucher (oBeP)'),
            \Genesis\API\Constants\Transaction\Types::PAYPAL_EXPRESS      =>
                Mage::helper('emerchantpay')->__('PayPal Express'),
            \Genesis\API\Constants\Transaction\Types::POLI                =>
                Mage::helper('emerchantpay')->__('POLi'),
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24            =>
                Mage::helper('emerchantpay')->__('Przelewy24'),
            \Genesis\API\Constants\Payment\Methods::QIWI                  =>
                Mage::helper('emerchantpay')->__('Qiwi'),
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY            =>
                Mage::helper('emerchantpay')->__('SafetyPay'),
            \Genesis\API\Constants\Transaction\Types::SALE                =>
                Mage::helper('emerchantpay')->__('Sale'),
            \Genesis\API\Constants\Transaction\Types::SALE_3D             =>
                Mage::helper('emerchantpay')->__('Sale (3D-Secure)'),
            \Genesis\API\Constants\Transaction\Types::SDD_SALE            =>
                Mage::helper('emerchantpay')->__('Sepa Direct Debit'),
            \Genesis\API\Constants\Transaction\Types::SOFORT              =>
                Mage::helper('emerchantpay')->__('SOFORT'),
            \Genesis\API\Constants\Payment\Methods::TELEINGRESO           =>
                Mage::helper('emerchantpay')->__('TeleIngreso'),
            \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE        =>
                Mage::helper('emerchantpay')->__('Trustly'),
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY             =>
                Mage::helper('emerchantpay')->__('TrustPay'),
            \Genesis\API\Constants\Transaction\Types::WEBMONEY            =>
                Mage::helper('emerchantpay')->__('WebMoney'),
            \Genesis\API\Constants\Transaction\Types::WECHAT              =>
                Mage::helper('emerchantpay')->__('WeChat'),
        );
    }
}
