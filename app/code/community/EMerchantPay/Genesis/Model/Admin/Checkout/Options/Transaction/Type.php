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
        $data = array();

        $transactionTypes = \Genesis\API\Constants\Transaction\Types::getWPFTransactionTypes();
        $excludedTypes    = array_map(
            function ($arr) {
                return $arr['value'];
            },
            (new EMerchantPay_Genesis_Model_Admin_Checkout_Options_Transaction_Recurring_Type())->toOptionArray()
        );
        // Exclude SDD Recurring
        array_push($excludedTypes, \Genesis\API\Constants\Transaction\Types::SDD_INIT_RECURRING_SALE);

        // Exclude PPRO transaction. This is not standalone transaction type
        array_push($excludedTypes, \Genesis\API\Constants\Transaction\Types::PPRO);

        // Exclude Transaction Types
        $transactionTypes = array_diff($transactionTypes, $excludedTypes);

        // Add PPRO types
        $pproTypes = array_map(
            function ($type) {
                return $type . EMerchantPay_Genesis_Helper_Data::PPRO_TRANSACTION_SUFFIX;
            },
            \Genesis\API\Constants\Payment\Methods::getMethods()
        );
        $transactionTypes = array_merge($transactionTypes, $pproTypes);
        asort($transactionTypes);

        foreach ($transactionTypes as $type) {
            $name = \Genesis\API\Constants\Transaction\Names::getName($type);
            if (!\Genesis\API\Constants\Transaction\Types::isValidTransactionType($type)) {
                $name = strtoupper($type);
            }

            $data[$type] = $this->getLanguageEntry($name);
        }

        return $data;
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
