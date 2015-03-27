<?php

/**
 * Class EMerchantPay_Genesis_Model_Admin_Transaction_Type
 *
 * Get code/name of the available transaction types
 */
class EMerchantPay_Genesis_Model_Admin_Options_Transaction_Type
{
    /**
     * Return the transaction types for an Options field
     *
     * @return array
     */
	public function toOptionArray()
	{
        $options =  array();

        foreach (static::getTransactionTypes() as $code => $name) {
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
    static function getTransactionTypes()
    {
        return array(
            EMerchantPay_Genesis_Model_Direct::GENESIS_TRANSACTION_AUTHORIZE     =>
                Mage::helper('emerchantpay')->__('Authorize'),
            EMerchantPay_Genesis_Model_Direct::GENESIS_TRANSACTION_AUTHORIZE3D   =>
                Mage::helper('emerchantpay')->__('Authorize (3D-Secure)'),
            EMerchantPay_Genesis_Model_Direct::GENESIS_TRANSACTION_SALE          =>
                Mage::helper('emerchantpay')->__('Sale'),
            EMerchantPay_Genesis_Model_Direct::GENESIS_TRANSACTION_SALE3D        =>
                Mage::helper('emerchantpay')->__('Sale (3D-Secure)'),
        );
    }
}