<?php

class EMerchantPay_Genesis_Block_Info_Direct extends Mage_Payment_Block_Info
{
	protected function _construct()
	{
		parent::_construct();
		$this->setTemplate('emerchantpay/info/direct.phtml');
	}

	public function getMethodCode()
	{
		return $this->getInfo()->getMethodInstance()->getCode();
	}
}