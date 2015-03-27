<?php

class EMerchantPay_Genesis_Block_Info_Checkout extends Mage_Payment_Block_Info
{
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('emerchantpay/info/checkout.phtml');
	}

	public function getMethodCode()
	{
		return $this->getInfo()->getMethodInstance()->getCode();
	}
}