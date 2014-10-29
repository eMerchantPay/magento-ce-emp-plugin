<?php

class EMerchantpay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract {
	public function getSuccessURL() {
		return Mage::getUrl( 'checkout/success', array( '_secure' => true ) );
	}

	public function getFailureURL()
	{
		return Mage::getUrl( 'checkout/failure', array( '_secure' => true ) );
	}

	public function getCancelURL()
	{
		return Mage::getUrl( 'emerchantpay/genesis/error', array( '_secure' => true ) );
	}

	public function getNotifyURL()
	{
		return Mage::getUrl( 'emerchantpay/genesis/nofify/', array( '_secure' => true ) );
	}

	public function getConfigVal($key)
	{
		return Mage::getStoreConfig( 'payment/emerchantpay_genesis/' . $key );
	}

	public function genTransactionId()
	{
		return strtoupper(md5(microtime(1)));
	}
} 