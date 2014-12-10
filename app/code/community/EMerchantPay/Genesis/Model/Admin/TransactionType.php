<?php

class EMerchantPay_Genesis_Model_Admin_TransactionType
{
	public function toOptionArray()
	{
		return array(
			EMerchantPay_Genesis_Model_Standard::GENESIS_TRANSACTION_AUTHORIZE     => Mage::helper('emerchantpay')->__('Authorize'),
			EMerchantPay_Genesis_Model_Standard::GENESIS_TRANSACTION_AUTHORIZE3D   => Mage::helper('emerchantpay')->__('Authorize with 3D-Secure'),
			EMerchantPay_Genesis_Model_Standard::GENESIS_TRANSACTION_SALE          => Mage::helper('emerchantpay')->__('Sale'),
			EMerchantPay_Genesis_Model_Standard::GENESIS_TRANSACTION_SALE3D        => Mage::helper('emerchantpay')->__('Sale with 3D-Secure'),
		);
	}
}