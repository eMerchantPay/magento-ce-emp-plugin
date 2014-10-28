<?php

class EMerchantpay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
	protected function getSuccessURL()
	{
		return Mage::getUrl('checkout/success', array('_secure' => true));
	}

	protected function getFailureURL()
	{
		return Mage::getUrl('checkout/failure', array('_secure' => true));
	}

	protected function getCancelURL()
	{
		return Mage::getUrl('emerchantpay/genesis/error', array('_secure' => true));
	}

	protected function getNotifyURL()
	{
		return Mage::getUrl('emerchantpay/genesis/nofify/', array('_secure' => true));
	}
} 