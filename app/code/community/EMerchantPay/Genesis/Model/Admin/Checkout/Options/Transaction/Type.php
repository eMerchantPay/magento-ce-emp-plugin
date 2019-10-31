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
            \Genesis\API\Constants\Transaction\Types::ABNIDEAL             =>
                $this->getLanguageEntry('ABN iDEAL'),
            \Genesis\API\Constants\Transaction\Types::ALIPAY               =>
                $this->getLanguageEntry('Alipay'),
            \Genesis\API\Constants\Transaction\Types::AURA                 =>
                $this->getLanguageEntry('Aura'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE            =>
                $this->getLanguageEntry('Authorize'),
            \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D         =>
                $this->getLanguageEntry('Authorize (3D-Secure)'),
            \Genesis\API\Constants\Transaction\Types::BALOTO               =>
                $this->getLanguageEntry('Baloto'),
            \Genesis\API\Constants\Transaction\Types::BANAMEX              =>
                $this->getLanguageEntry('Banamex'),
            \Genesis\API\Constants\Transaction\Types::BANCO_DE_OCCIDENTE   =>
                $this->getLanguageEntry('Banco de Occidente'),
            \Genesis\API\Constants\Transaction\Types::BANCO_DO_BRASIL      =>
                $this->getLanguageEntry('Banco do Brasil'),
            \Genesis\API\Constants\Transaction\Types::BANCOMER             =>
                $this->getLanguageEntry('Bancomer'),
            \Genesis\API\Constants\Payment\Methods::BCMC                   =>
                $this->getLanguageEntry('Mr.Cash'),
            \Genesis\API\Constants\Transaction\Types::BITPAY_SALE          =>
                $this->getLanguageEntry('BitPay'),
            \Genesis\API\Constants\Transaction\Types::BOLETO               =>
                $this->getLanguageEntry('Boleto'),
            \Genesis\API\Constants\Transaction\Types::BRADESCO             =>
                $this->getLanguageEntry('Bradesco'),
            \Genesis\API\Constants\Transaction\Types::CABAL                =>
                $this->getLanguageEntry('Cabal'),
            \Genesis\API\Constants\Transaction\Types::CASHU                =>
                $this->getLanguageEntry('CashU'),
            \Genesis\API\Constants\Transaction\Types::CENCOSUD             =>
                $this->getLanguageEntry('Cencosud'),
            \Genesis\API\Constants\Transaction\Types::EFECTY               =>
                $this->getLanguageEntry('Efecty'),
            \Genesis\API\Constants\Transaction\Types::ELO                  =>
                $this->getLanguageEntry('Elo'),
            \Genesis\API\Constants\Transaction\Types::ENTERCASH            =>
                $this->getLanguageEntry('Entercash'),
            \Genesis\API\Constants\Payment\Methods::EPS                    =>
                $this->getLanguageEntry('eps'),
            \Genesis\API\Constants\Transaction\Types::EZEEWALLET           =>
                $this->getLanguageEntry('eZeeWallet'),
            \Genesis\API\Constants\Payment\Methods::GIRO_PAY               =>
                $this->getLanguageEntry('GiroPay'),
            \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN         =>
                $this->getLanguageEntry('iDebit'),
            \Genesis\API\Constants\Transaction\Types::INPAY                =>
                $this->getLanguageEntry('INPay'),
            \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN    =>
                $this->getLanguageEntry('InstaDebit'),
            \Genesis\API\Constants\Transaction\Types::INSTANT_TRANSFER     =>
                $this->getLanguageEntry('Instant Transfer'),
            \Genesis\API\Constants\Transaction\Types::ITAU                 =>
                $this->getLanguageEntry('Itau'),
            \Genesis\API\Constants\Transaction\Types::MULTIBANCO           =>
                $this->getLanguageEntry('Multibanco'),
            \Genesis\API\Constants\Payment\Methods::MYBANK                 =>
                $this->getLanguageEntry('MyBank'),
            \Genesis\API\Constants\Transaction\Types::NARANJA              =>
                $this->getLanguageEntry('Naranja'),
            \Genesis\API\Constants\Transaction\Types::NATIVA               =>
                $this->getLanguageEntry('Nativa'),
            \Genesis\API\Constants\Transaction\Types::NETELLER             =>
                $this->getLanguageEntry('Neteller'),
            \Genesis\API\Constants\Transaction\Types::ONLINE_BANKING_PAYIN =>
                $this->getLanguageEntry('OnlineBanking'),
            \Genesis\API\Constants\Transaction\Types::OXXO                 =>
                $this->getLanguageEntry('OXXO'),
            \Genesis\API\Constants\Transaction\Types::P24                  =>
                $this->getLanguageEntry('P24'),
            \Genesis\API\Constants\Transaction\Types::PAGO_FACIL           =>
                $this->getLanguageEntry('Pago Facil'),
            \Genesis\API\Constants\Transaction\Types::PAYSAFECARD          =>
                $this->getLanguageEntry('PaySafeCard'),
            \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE    =>
                $this->getLanguageEntry('PayByVoucher (Sale)'),
            \Genesis\API\Constants\Transaction\Types::PAYPAL_EXPRESS       =>
                $this->getLanguageEntry('PayPal Express'),
            \Genesis\API\Constants\Transaction\Types::PAYU                 =>
                $this->getLanguageEntry('PayU'),
            \Genesis\API\Constants\Transaction\Types::POLI                 =>
                $this->getLanguageEntry('POLi'),
            \Genesis\API\Constants\Payment\Methods::PRZELEWY24             =>
                $this->getLanguageEntry('Przelewy24'),
            \Genesis\API\Constants\Payment\Methods::QIWI                   =>
                $this->getLanguageEntry('Qiwi'),
            \Genesis\API\Constants\Transaction\Types::RAPI_PAGO            =>
                $this->getLanguageEntry('RapiPago'),
            \Genesis\API\Constants\Transaction\Types::REDPAGOS             =>
                $this->getLanguageEntry('Redpagos'),
            \Genesis\API\Constants\Payment\Methods::SAFETY_PAY             =>
                $this->getLanguageEntry('SafetyPay'),
            \Genesis\API\Constants\Transaction\Types::SALE                 =>
                $this->getLanguageEntry('Sale'),
            \Genesis\API\Constants\Transaction\Types::SALE_3D              =>
                $this->getLanguageEntry('Sale (3D-Secure)'),
            \Genesis\API\Constants\Transaction\Types::SANTANDER            =>
                $this->getLanguageEntry('Santander'),
            \Genesis\API\Constants\Transaction\Types::SANTANDER_CASH       =>
                $this->getLanguageEntry('Santander Cash'),
            \Genesis\API\Constants\Transaction\Types::SDD_SALE             =>
                $this->getLanguageEntry('Sepa Direct Debit'),
            \Genesis\API\Constants\Transaction\Types::SOFORT               =>
                $this->getLanguageEntry('SOFORT'),
            \Genesis\API\Constants\Transaction\Types::TARJETA_SHOPPING     =>
                $this->getLanguageEntry('Tarjeta Shopping'),
            \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE         =>
                $this->getLanguageEntry('Trustly'),
            \Genesis\API\Constants\Payment\Methods::TRUST_PAY              =>
                $this->getLanguageEntry('TrustPay'),
            \Genesis\API\Constants\Transaction\Types::WEBMONEY             =>
                $this->getLanguageEntry('WebMoney'),
            \Genesis\API\Constants\Transaction\Types::WECHAT               =>
                $this->getLanguageEntry('WeChat'),
            \Genesis\API\Constants\Transaction\Types::ZIMPLER              =>
                $this->getLanguageEntry('Zimpler'),
        );
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function getLanguageEntry($key)
    {
        return Mage::helper('emerchantpay')->__($key);
    }
}
