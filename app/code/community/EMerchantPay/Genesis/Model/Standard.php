<?php

require_once Mage::getBaseDir('lib').DS.'Genesis'.DS.'vendor'.DS.'autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConf;

/**
 * Genesis Gateway Standard API
 *
 * This class requires the user to input
 * their CC data and as such requires PCI
 * compliance.
 *
 * @see http://magento.com/resources/pci
 * @extends Mage_Payment_Model_Method_Cc
 *
 * @category
 */
class EMerchantPay_Genesis_Model_Standard extends Mage_Payment_Model_Method_Cc
{
    protected $_code = 'emerchantpay_genesis';

    protected $_isGateway               = true;
    protected $_canAuthorize            = true;
    protected $_canCapture              = true;
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canVoid                 = true;
    protected $_canUseInternal          = true;
    protected $_canUseCheckout          = true;

    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;

	/**
	 * Use CcSave as it has the additional CcOwner field
	 */
	protected $_formBlockType = 'payment/form_ccsave';

	/**
	 * Set Genesis API Parameters
	 */
	public function __construct() {
		GenesisConf::setToken(
			Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_token')
		);
		GenesisConf::setUsername(
			Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_username')
		);
		GenesisConf::setPassword(
			Mage::helper('emerchantpay_genesis')->getConfigVal('genesis_password')
		);
		GenesisConf::setEnvironment(
			'sandbox'
		);
	}

    /**
     * Genesis Authorize Payment Method
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return mixed
     */
	public function authorize($payment, $amount)
	{
		try {
			$order = $payment->getOrder();

			$billing = $order->getBillingAddress();
			$shipping = $order->getShippingAddress();

			$transaction_id = Mage::helper('emerchantpay_genesis')->genTransactionId();
			$remote_address = Mage::helper('core/http')->getRemoteAddr(false);

			$usage = Mage::helper('emerchantpay_genesis')->getItemList();

			$genesis = new Genesis('Financial\Authorize');

			$genesis
				->request()
					->setTransactionId($transaction_id)
					->setRemoteIp($remote_address)
					->setUsage($usage)
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount)
					->setCardHolder($payment->getCcOwner())
					->setCardNumber($payment->getCcNumber())
					->setExpirationYear($payment->getCcExpYear())
					->setExpirationMonth($payment->getCcExpMonth())
					->setCvv($payment->getCcCid())
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

			$response = $genesis->response()->getResponseObject();

			if (!isset($response->status) || $response->status != 'approved') {
				throw new Exception($response->technical_message);
			}

			$payment->setCcTransId($response->unique_id);
			$payment->setTransactionId($response->unique_id);
			$payment->setIsTransactionClosed(false);

			$payment->setTransactionAdditionalInfo(
				Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
				$genesis->response()->getResponseRaw()
			);

		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('There was a problem processing your request, please try again or come back later!'));
		}

		return $this;
	}

	/**
	 * Cancel an order
	 *
	 * Before canceling an order, check if there is
	 * a transaction made previously (auth for example).
	 * If there is - void it
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 */
	public function cancel($payment)
	{
		if ($payment->getCcTransId()) {
			$this->void($payment);
		}

		return $this;
	}

	/**
	 * Capture a successful auth transaction
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function capture($payment, $amount)
	{
		if (!$payment->getCcTransId()) {
			return false;
		}

		try {
			$order = $payment->getOrder();

			$genesis = new Genesis('Financial\Capture');

			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp(Mage::helper('core/http')->getRemoteAddr(false))
					->setReferenceId($payment->getCcTransId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			if ($response->status != 'approved') {
				throw new Exception($response->technical_message);
			}

			$payment->setTransactionId(
				$response->unique_id
			);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Unsuccessful CAPTURE transaction: (' . $exception->getMessage() . ')!'));
		}

		return $this;
	}

	/**
	 * Refund the last successful transaction
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function refund($payment, $amount)
	{
		if (!$payment->getLastTransId()) {
			return false;
		}

		try{
			$order = $payment->getOrder();

			$genesis = new Genesis('Financial\Refund');

			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp(Mage::helper('core/http')->getRemoteAddr(false))
					->setReferenceId($payment->getLastTransId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			$response = $genesis->response()->getResponseObject();

			if ($response->status != 'approved') {
				throw new Exception($response->technical_message);
			}

			$payment->setTransactionId($response->unique_id);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Refund attempt error!'));
		}

		return $this;
	}

	/**
	 * Void the last successful transaction
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 *
	 * @return $this|Mage_Payment_Model_Abstract
	 * @throws Mage_Core_Exception
	 */
	public function void($payment)
	{
		if (!$payment->getLastTransId()) {
			return false;
		}

		try{
			$genesis = new Genesis('Financial\Void');

			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransactionId())
					->setRemoteIp(Mage::helper('core/http')->getRemoteAddr(false))
					->setReferenceId($payment->getLastTransId());

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				throw new Exception('There was a problem processing your request, please try again or come back later!');
			}

			$payment->setTransactionId($genesis->response()->getResponseObject()->unique_id);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(Mage::helper('emerchantpay_genesis')->__('Void attempt error!'));
		}

		return $this;
	}
}