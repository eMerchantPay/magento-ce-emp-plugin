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
 * Class EMerchantPay_Genesis_Observer_GenesisAutoLoader
 *
 * Handler for event "emerchantpay_genesis_init_library"
 */
class EMerchantPay_Genesis_Observer_GenesisAutoLoader
{
    public function addAutoLoad($observer)
    {
    		$event = $observer->getEvent();
    		$genesisAutoLoadParams = $event->getGenesisAutoLoadParams();
    		
				$mustCheckGenesisLibVersion = $genesisAutoLoadParams->getCheckGenesisLibVersion() == '1';
				
        // Mitigate PHP Bug #52339, as Magento already registers their AutoLoader
        if (!class_exists('\Genesis\Genesis', false)) {
            $vendorDir = $genesisAutoLoadParams->getMagentoRoot() . DS . 'vendor';
            $genesisGatewayVendorDir = $vendorDir . DS . $genesisAutoLoadParams->getGenesisComposerDir();
            $vendorAutoload = $vendorDir . DS . 'autoload.php';

            if (file_exists($vendorAutoload) && file_exists($genesisGatewayVendorDir))
                include $vendorAutoload;

            if (class_exists('Genesis\Genesis') && $mustCheckGenesisLibVersion)
               $this->checkGenesisLibVersion(
               		$genesisAutoLoadParams->getRequiredGenesisLibVersion()
               );

						if (!class_exists('Genesis\Genesis')) {
							$integratedGenesisLibAutoLoader = $genesisAutoLoadParams->getIntegratedGenesisLibAutoLoader();
							if (file_exists($integratedGenesisLibAutoLoader))
								include $integratedGenesisLibAutoLoader;
						}
        }
        elseif ($mustCheckGenesisLibVersion) {
           $this->checkGenesisLibVersion(
              $genesisAutoLoadParams->getRequiredGenesisLibVersion()
           );
        }
        
        return $this;
    }

    private function checkGenesisLibVersion($requiredVersion) {
    		$params = explode(' ', $requiredVersion);
    		$operator = $params[0];
    		$version = $params[1];
    		
        if (class_exists('\Genesis\Config') && !version_compare(\Genesis\Config::getVersion(), $version, $operator)) {
        		$currentGenesisVersion = \Genesis\Config::getVersion();
        		Mage::throwException(sprintf("Incompatible GenesisPHP Lib Version (Found %s; Required %s %s)", $currentGenesisVersion, $operator, $version));
    		}
    }
    
}