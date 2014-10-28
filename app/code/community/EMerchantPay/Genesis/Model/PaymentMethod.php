<?php

/**
* Our test CC module adapter
*/
require_once Mage::getBaseDir('lib').DS.'Genesis'.DS.'vendor'.DS.'autoload.php';

class EMerchantPay_Genesis_Model_PaymentMethod extends Mage_Payment_Model_Method_Cc
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'genesis';

    /**
     * Here are examples of flags that will determine functionality availability
     * of this module to be used by frontend and backend.
     *
     * @see all flags and their defaults in Mage_Payment_Model_Method_Abstract
     *
     * It is possible to have a custom dynamic logic by overloading
     * public function can* for each flag respectively
     */

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture              = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;

    /**
     * Can refund online?
     */
    protected $_canRefund               = true;

    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = false;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

	public function __construct() {
		GenesisConfiguration
	}
    /**
     * Here you will need to implement authorize, capture and void public methods
     *
     * @see examples of transaction specific public methods such as
     * authorize, capture and void in Mage_Paygate_Model_Authorizenet
     */

	public function authorize(Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();

		$billing = $order->getBillingAddress();
		$shipping = $order->getShippingAddress();

		$genesis = new Genesis('Financial\Authorize');

		try {
			$genesis
				->request()
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount)
					->setCardHolder($payment->getCcOwner())
					->setCardNumber($payment->getCcNumber())
					->setCvv($payment->getCcCid())
					->setExpirationYear($payment->getCcExpYear())
					->setExpirationMonth($payment->getCcExpMonth())
					->setCustomerPhone()
					->setCustomerEmail()
					->setBillingFirstName()
					->setBillingLastName()
					->setBillingAddress1($billing->getStreet(1))
					->setBillingAddress2($billing->getStreet(2))
					->setBillingZipCode($billing->getPostcode())
					->setBillingCity($billing->getCity())
					->setBillingState($billing->getRegion())
					->setShippingFirstName()
					->setShippingLastName()
					->setShippingAddress1($shipping->getStreet(1))
					->setShippingAddress2($shipping->getStreet(2))
					->setShippingZipCode($shipping->getPostcode())
					->setShippingCity($shipping->getCity())
					->setShippingState($shipping->getRegion());
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Authorize attempt error!'));
		}

		return $genesis;
	}

	public function capture($payment)
	{
		$genesis = new Genesis('Financial\Capture');

		try {
			$genesis
				->request()
					->setTransactionId()
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId()
					->setCurrency()
					->setAmount();
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Capture attempt error!'));
		}

		return $genesis;
	}

	public function refund($payment)
	{
		$genesis = new Genesis('Financial\Refund');

		try{
			$genesis
				->request()
					->setTransactionId()
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId()
					->setCurrency()
					->setAmount();
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Refund attempt error!'));
		}

		return $genesis;
	}

	public function void($payment)
	{
		$genesis = new Genesis('Financial\Void');

		try{
			$genesis
				->request()
					->setTransactionId()
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId();
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Void attempt error!'));
		}

		return $genesis;
	}
}
?>