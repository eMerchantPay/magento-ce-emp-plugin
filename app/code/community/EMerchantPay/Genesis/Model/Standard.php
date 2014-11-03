<?php

/**
* Our test CC module adapter
*/
require_once Mage::getBaseDir('lib').DS.'Genesis'.DS.'vendor'.DS.'autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConf;

class EMerchantPay_Genesis_Model_Standard extends Mage_Payment_Model_Method_Cc
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'emerchantpay_genesis';

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
    protected $_canCapturePartial       = true;

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
    protected $_canUseCheckout          = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = false;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

	/**
	 * Use CcSave as it has the additional Owner field
	 */
	protected $_formBlockType = 'payment/form_ccsave';

	/**
	 * Unique Id
	 */
	protected $_genesisTrxUniqueId = 'genesis_trx_unique_id';

	public function __construct() {
		GenesisConf::setToken(Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_token'));
		GenesisConf::setUsername(Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_username'));
		GenesisConf::setPassword(Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_password'));
		GenesisConf::setEnvironment('sandbox');
	}
    /**
     * Here you will need to implement authorize, capture and void public methods
     *
     * @see examples of transaction specific public methods such as
     * authorize, capture and void in Mage_Paygate_Model_Authorizenet
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return mixed
     */

	public function authorize(Varien_Object $payment, $amount)
	{
		// Make sure transaction is open
		$payment->setIsTransactionClosed(false);

		$order = $payment->getOrder();

		$billing = $order->getBillingAddress();
		$shipping = $order->getShippingAddress();

		$genesis = new Genesis('Financial\Authorize');

		try {
			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount)
					->setCardHolder($payment->getCcOwner())
					->setCardNumber($payment->getCcNumber())
					->setCvv($payment->getCcCid())
					->setExpirationYear($payment->getCcExpYear())
					->setExpirationMonth($payment->getCcExpMonth())
					->setCustomerEmail($order->getCustomerEmail())
					->setCustomerPhone($billing->getTelephone())
					->setBillingFirstName($billing->getData('firstname'))
					->setBillingLastName($billing->getData('lastname'))
					->setBillingAddress1($billing->getStreet(1))
					->setBillingAddress2($billing->getStreet(2))
					->setBillingZipCode($billing->getPostcode())
					->setBillingCity($billing->getCity())
					->setBillingState($billing->getRegion())
					->setBillingCountry($billing->getCountry())
					->setShippingFirstName($shipping->getData('firstname'))
					->setShippingLastName($shipping->getData('lastname'))
					->setShippingAddress1($shipping->getStreet(1))
					->setShippingAddress2($shipping->getStreet(2))
					->setShippingZipCode($shipping->getPostcode())
					->setShippingCity($shipping->getCity())
					->setShippingState($shipping->getRegion())
					->setShippinCountry($shipping->getCountry());

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}

			/*
			$payment
				->setAdditionalInformation(
					$this->_genesisTrxUniqueId,
					$genesis->response()->getResponseObject()->unique_id
				);

			$payment
				->addTransaction(
					Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
					null,
					false,
					null
				);
			*/

			$payment->setCcTransId($genesis->response()->getResponseObject()->unique_id);

			/*
			$payment->setTransactionAdditionalInfo(
				Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
				$genesis->response()->getResponseRaw()
			);
			*/
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Authorize attempt error!' . $exception->getMessage()));
		}

		return $this;
	}

	public function cancel(Varien_Object $payment)
	{
		$this->void($payment);

		return $this;
	}

	/**
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function capture(Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();

		$order->getBaseCurrencyCode();

		$genesis = new Genesis('Financial\Capture');

		try {
			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId($payment->getCcTransId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}

			$payment->setTransactionId(
				$genesis->response()->getResponseObject()->unique_id
			);

			/*
			$payment->setTransactionAdditionalInfo(
				$this->_genesisTrxUniqueId,
				$genesis->response()->getResponseObject()->unique_id
			);

			$payment->setTransactionAdditionalInfo(
				Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
				$genesis->response()->getResponseRaw()
			);
			*/
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Capture attempt error (' . $exception->getMessage() . ')!'));
		}

		return $this;
	}

	/**
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function refund(Varien_Object $payment, $amount)
	{
		$order = $payment->getOrder();

		if (!$payment->getLastTransId()) {
			return false;
		}

		$genesis = new Genesis('Financial\Refund');

		try{
			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId($payment->getLastTransId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}

			$payment->setTransactionId($genesis->response()->getResponseObject()->unique_id);
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Refund attempt error!'));
		}

		return $this;
	}

	/**
	 * @param Mage_Sales_Model_Order_Payment $payment
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function void(Varien_Object $payment)
	{
		if (!$payment->getLastTransId()) {
			return false;
		}

		$genesis = new Genesis('Financial\Void');

		try{
			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp($_SERVER['REMOTE_ADDR'])
					->setReferenceId($payment->getLastTransId());

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}

			$payment->setTransactionId($genesis->response()->getResponseObject()->unique_id);
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Void attempt error!'));
		}

		return $this;
	}
}
?>