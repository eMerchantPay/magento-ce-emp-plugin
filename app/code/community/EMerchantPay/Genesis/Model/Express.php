<?php

require_once Mage::getBaseDir('lib').DS.'Genesis'.DS.'vendor'.DS.'autoload.php';

use \Genesis\Genesis as Genesis;
use \Genesis\GenesisConfig as GenesisConf;

class EMerchantPay_Genesis_Model_Express extends Mage_Payment_Model_Method_Abstract
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
	protected $_canUseCheckout          = true;

	/**
	 * Is this payment method suitable for multi-shipping checkout?
	 */
	protected $_canUseForMultishipping  = true;

	/**
	 * Can save credit card information for future processing?
	 */
	protected $_canSaveCc = false;

	/**
	 * Here you will need to implement authorize, capture and void public methods
	 *
	 * @see examples of transaction specific public methods such as
	 * authorize, capture and void in Mage_Paygate_Model_Authorizenet
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param String $amount
	 */

	public function authorize($payment, $amount)
	{
		$order = $payment->getOrder();

		$billing = $order->getBillingAddress();
		$shipping = $order->getShippingAddress();

		$genesis = new Genesis('WPF\Create');

		try {
			$genesis
				->request()
					->setTransactionId(Mage::helper('emerchantpay_genesis')->genTransationId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount)
					->setUsage(' ')
					->setDescription(' ')
					->setCustomerPhone($billing->getCustomerPhone())
					->setCustomerEmail($billing->getCustomerEmail())
					->setNotificationUrl(Mage::helper('emechantpay_genesis')->getNotifyURL())
					->setReturnSuccessUrl(Mage::helper('emechantpay_genesis')->getSuccessURL())
					->setReturnFailureUrl(Mage::helper('emechantpay_genesis')->getFailureURL())
					->setReturnCancelUrl(Mage::helper('emechantpay_genesis')->getCancelURL())
					->setBillingFirstName($billing->getData('firstname'))
					->setBillingLastName($billing->getData('lastname'))
					->setBillingAddress1($billing->getStreet(1))
					->setBillingAddress2($billing->getStreet(2))
					->setBillingZipCode($billing->getPostcode())
					->setBillingCity($billing->getCity())
					->setBillingState($billing->getRegion())
					->setShippingFirstName($shipping->getData('firstname'))
					->setShippingLastName($shipping->getData('lastname'))
					->setShippingAddress1($shipping->getStreet(1))
					->setShippingAddress2($shipping->getStreet(2))
					->setShippingZipCode($shipping->getPostcode())
					->setShippingCity($shipping->getCity())
					->setShippingState($shipping->getRegion())
					->addTransactionType('authorize');

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Authorize attempt error!'));
		}

		return $genesis;
	}

	public function reconcile($unique_id)
	{
		$genesis = new Genesis('WPF\Reconcile');

		try {
			$genesis
				->request()
					->setUniqueId($unique_id);

			$genesis->execute();

			if ($genesis->response()->getResponseObject()->status != 'approved') {
				return false;
			}
		}
		catch (Exception $exception) {
			$this->debugData($exception->getMessage());
			Mage::throwException(Mage::helper('genesis')->__('Reconcile attempt error!'));
		}
	}
} 