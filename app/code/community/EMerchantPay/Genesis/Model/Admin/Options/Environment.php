<?php

/**
 * Get code/name of the available environments
 */
class EMerchantPay_Genesis_Model_Admin_Options_Environment
{
    /**
     * Return the environment types for an Options field
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options =  array();

        foreach (static::getEnvironmentOptions() as $code => $name) {
            $options[] = array(
                'value' => $code,
                'label' => $name
            );
        }

        return $options;
    }

    /**
     * Get the available environment types
     *
     * @return array
     */
    static function getEnvironmentOptions()
    {
        return array(
            'sandbox'       => Mage::helper('emerchantpay')->__('Yes'),
            'production'    => Mage::helper('emerchantpay')->__('No'),
        );
    }
}