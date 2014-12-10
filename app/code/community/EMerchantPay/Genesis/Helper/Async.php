<?php

class EMerchantPay_Genesis_Helper_Async extends Mage_Core_Helper_Abstract
{
	/**
	 * If the current visitor (for example on return)
	 * is logged out, redirect them to login page
	 *
	 * @return void
	 */
	public function redirectToLogin()
	{
		$this->setFlag('', 'no-dispatch', true);
		$this->getResponse()->setRedirect(
			Mage::helper('core/url')->addRequestParam(
				Mage::helper('customer')->getLoginUrl(),
				array('context' => 'checkout')
			)
		);
	}

	/**
	 * Return checkout session object
	 *
	 * @return Mage_Checkout_Model_Session
	 */
	protected function _getCheckoutSession()
	{
		return Mage::getSingleton('checkout/session');
	}

	/**
	 * Return checkout quote object
	 *
	 * @return Mage_Sales_Model_Quote
	 */
	private function _getQuote()
	{
		if (!$this->_quote) {
			$this->_quote = $this->_getCheckoutSession()->getQuote();
		}
		return $this->_quote;
	}
}