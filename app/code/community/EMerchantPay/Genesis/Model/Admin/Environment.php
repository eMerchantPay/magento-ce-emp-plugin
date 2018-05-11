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
 * Class EMerchantPay_Genesis_Model_Admin_Environment
 *
 * Admin options Drop-down for Gateway environment
 */
class EMerchantPay_Genesis_Model_Admin_Environment
{
    /**
     * Return the environment types for an Options field
     *
     * @return array
     */
    public function toOptionArray()
    {
        $options = array();

        foreach ($this->getEnvironmentOptions() as $code => $name) {
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
    protected function getEnvironmentOptions()
    {
        return array(
            'sandbox'       => Mage::helper('emerchantpay')->__('Yes'),
            'production'    => Mage::helper('emerchantpay')->__('No'),
        );
    }
}
