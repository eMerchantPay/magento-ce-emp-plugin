<?php

/**
 * eMerchantPay Direct Payment Method
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
class EMerchantPay_Genesis_Model_Direct extends Mage_Payment_Model_Method_Cc
{
	// Variables
    protected $_code = 'emerchantpay_direct';

	//protected $_formBlockType = 'emerchantpay/form_direct';
	protected $_formBlockType = 'payment/form_ccsave';
	protected $_infoBlockType = 'emerchantpay/info_direct';

	// Configurations
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

	// Transactions
	const GENESIS_TRANSACTION_AUTHORIZE     = 'authorize';
	const GENESIS_TRANSACTION_AUTHORIZE3D   = 'authorize3d';
	const GENESIS_TRANSACTION_SALE          = 'sale';
	const GENESIS_TRANSACTION_SALE3D        = 'sale3d';

	// Statuses
	const GENESIS_STATUS_APPROVED           = 'approved';
	const GENESIS_STATUS_DECLINED           = 'declined';
	const GENESIS_STATUS_PENDING            = 'pending';
	const GENESIS_STATUS_PENDING_ASYNC      = 'pending_async';
	const GENESIS_STATUS_ERROR              = 'error';
	const GENESIS_STATUS_REFUNDED           = 'refunded';
	const GENESIS_STATUS_VOIDED             = 'voided';

	/**
	 * Payment action getter compatible with payment model
	 *
	 * @see Mage_Sales_Model_Payment::place()
	 * @return string
	 */
	public function getConfigPaymentAction()
	{
		switch($this->getConfigData('genesis_type')) {
			default:
			case self::GENESIS_TRANSACTION_AUTHORIZE:
			case self::GENESIS_TRANSACTION_AUTHORIZE3D:
				return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
				break;
			case self::GENESIS_TRANSACTION_SALE:
			case self::GENESIS_TRANSACTION_SALE3D:
				return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
				break;
		}
	}

	/**
	 * Authorize transaction type
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return mixed
	 */
	public function authorize(Varien_Object $payment, $amount)
	{
		if ($this->is3dEnabled()) {
			return $this->_authorize3d($payment, $amount);
		}
		else {
			return $this->_authorize($payment, $amount);
		}
	}

	/**
	 * Capture transaction type
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return mixed
	 */
	public function capture(Varien_Object $payment, $amount)
	{
		if ($payment->getCcTransId()) {
			return $this->_capture($payment, $amount);
		}
		else {
			if ($this->is3dEnabled()) {
				return $this->_sale3d($payment, $amount);
			}
			else {
				return $this->_sale($payment, $amount);
			}
		}
	}

    /**
     * Genesis Authorize Payment Method
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return mixed
     */
	private function _authorize($payment, $amount)
	{
		Mage::log('Authorize transaction for Order#' . $payment->getOrder()->getIncrementId());

		try {
			$this->getHelper()->initClient($this->getCode());

			$order      = $payment->getOrder();

			$billing    = $order->getBillingAddress();
			$shipping   = $order->getShippingAddress();

			$genesis = new \Genesis\Genesis('Financial\Authorize');

			$genesis
				->request()
					->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
					->setRemoteIp(Mage::helper('core/http')->getRemoteAddr(false))
					->setUsage($this->getHelper()->getItemList($order))
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

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			$information = array();

			foreach ($genesis->response()->getResponseObject() as $key => $value) {
				$information[strval($key)] = strval($value);
			}

			$payment->setCcTransId($genesis->response()->getResponseObject()->unique_id)
					->setTransactionId($genesis->response()->getResponseObject()->unique_id)
					->setIsTransactionClosed(false)
					->setTransactionAdditionalInfo(
						Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						$information
					);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to process your payment. Please check your input or try again later')
			);
		}

		return $this;
	}

	/**
	 * Genesis Authorize Payment Method with 3D-Secure
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param String $amount
	 *
	 * @return mixed
	 */
	private function _authorize3d($payment, $amount)
	{
		Mage::log('Authorize 3D-Secure transaction for Order#' . $payment->getOrder()->getIncrementId());

		try {
			$this->getHelper()->initClient($this->getCode());

			$order      = $payment->getOrder();

			$billing    = $order->getBillingAddress();
			$shipping   = $order->getShippingAddress();

			$genesis = new \Genesis\Genesis('Financial\Authorize3D');

			$genesis
				->request()
					->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
					->setRemoteIp(Mage::helper('core/http')->getRemoteAddr(false))
					->setUsage($this->getHelper()->getItemList($order))
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
					->setShippinCountry($shipping->getCountry())
					->setNotificationUrl($this->getHelper()->getNotifyURL('direct'))
					->setReturnSuccessUrl($this->getHelper()->getSuccessURL('direct'))
					->setReturnFailureUrl($this->getHelper()->getFailureURL('direct'));;

			$genesis->execute();

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			// No redirect url? - can't continue
			// @TODO rework if Sync 3DS is required
			if (!isset($genesis->response()->getResponseObject()->redirect_url)) {
				throw new Exception('Invalid Response.');
			}

			// Hold transaction creation
			$payment->setIsTransactionPending(true)
			        ->setSkipTransactionCreation(true)
			        ->setPreparedMessage('3D-Secure: Init.');

			// Save the redirect url with our
			Mage::getSingleton('core/session')->setEmerchantPayDirectRedirectUrl(
				strval($genesis->response()->getResponseObject()->redirect_url)
			);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to process your payment. Please check your input or try again later')
			);
		}

		return $this;
	}

	/**
	 * Genesis Sale (Auth/Capture) Payment Method
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param String $amount
	 *
	 * @return $this
	 */
	private function _sale($payment, $amount)
	{
		Mage::log('Sale transaction for Order#' . $payment->getOrder()->getIncrementId());

		try {
			$this->getHelper()->initClient($this->getCode());

			$order      = $payment->getOrder();

			$billing    = $order->getBillingAddress();
			$shipping   = $order->getShippingAddress();

			$genesis = new \Genesis\Genesis('Financial\Sale');

			$genesis
				->request()
					->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
					->setRemoteIp($this->getHelper('core/http')->getRemoteAddr(false))
					->setUsage($this->getHelper()->getItemList($order))
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

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			$information = array();

			foreach ($genesis->response()->getResponseObject() as $key => $value) {
				$information[strval($key)] = strval($value);
			}

			$payment->setCcTransId($genesis->response()->getResponseObject()->unique_id)
					->setTransactionId($genesis->response()->getResponseObject()->unique_id)
			        ->setCurrencyCode($genesis->response()->getResponseObject()->currency)
					->setIsTransactionClosed(true)
					->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, $information);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to process your payment. Please check your input or try again later')
			);
		}

		return $this;
	}

	/**
	 * Genesis Sale (Auth/Capture) Payment Method with 3D-Secure
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param String $amount
	 *
	 * @return $this
	 */
	private function _sale3d($payment, $amount)
	{
		Mage::log('Sale 3D-Secure transaction for Order#' . $payment->getOrder()->getIncrementId());

		try {
			$this->getHelper()->initClient($this->getCode());

			$order      = $payment->getOrder();

			$billing    = $order->getBillingAddress();
			$shipping   = $order->getShippingAddress();

			$genesis = new \Genesis\Genesis('Financial\Sale3D');

			$genesis
				->request()
					->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
					->setRemoteIp($this->getHelper('core/http')->getRemoteAddr(false))
					->setUsage($this->getHelper()->getItemList($order))
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
					->setShippinCountry($shipping->getCountry())
					->setNotificationUrl($this->getHelper()->getNotifyURL('direct'))
					->setReturnSuccessUrl($this->getHelper()->getSuccessURL('direct'))
					->setReturnFailureUrl($this->getHelper()->getFailureURL('direct'));

			$genesis->execute();

			// Unsuccessful transaction - warn the customer
			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			// No redirect url? - can't continue
			// @TODO rework if Sync 3DS is required
			if (!isset($genesis->response()->getResponseObject()->redirect_url)) {
				throw new Exception($this->getHelper()->__('Invalid Response.'));
			}

			// Hold transaction creation
			$payment->setIsTransactionPending(true)
					->setSkipTransactionCreation(true)
					->setPreparedMessage($this->getHelper()->__('3D-Secure: Init.'));

			// Save the redirect url with our
			Mage::getSingleton('core/session')->setEmerchantPayDirectRedirectUrl(
				strval($genesis->response()->getResponseObject()->redirect_url)
			);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to process your payment. Please check your input or try again later')
			);
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
	private function _capture($payment, $amount)
	{
		Mage::log('Capture transaction for Order#' . $payment->getOrder()->getIncrementId());

		try {
			$this->getHelper()->initClient($this->getCode());

			$order = $payment->getOrder();

			$genesis = new \Genesis\Genesis('Financial\Capture');

			$genesis
				->request()
					->setTransactionId( $this->getHelper()->genTransactionId($order->getIncrementId()) )
					->setRemoteIp( Mage::helper('core/http')->getRemoteAddr(false) )
					->setReferenceId($payment->getCcTransId())
					->setCurrency($order->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			$information = array();

			foreach ($genesis->response()->getResponseObject() as $key => $value) {
				$information[strval($key)] = strval($value);
			}

			$payment->setTransactionId( $genesis->response()->getResponseObject()->unique_id )
					->setParentTransactionId( $payment->getCcTransId() )
					->setIsTransactionClosed(true)
					->setTransactionAdditionalInfo(
						Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						$information
					);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to capture the selected transaction. Please try again or contact us, if the problem persists!')
			);
		}

		return $this;
	}

	/**
	 * Refund the last successful transaction
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 * @param float $amount
	 *
	 * @return $this
	 */
	public function refund(Varien_Object $payment, $amount)
	{
		Mage::log('Refund transaction for Order#' . $payment->getOrder()->getIncrementId());

		try{
			$this->getHelper()->initClient($this->getCode());

			$genesis = new \Genesis\Genesis('Financial\Refund');

			$genesis
				->request()
					->setTransactionId( $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId()) )
					->setRemoteIp( $this->getHelper('core/http')->getRemoteAddr(false) )
					->setReferenceId($payment->getRefundTransactionId())
					->setCurrency($payment->getOrder()->getBaseCurrencyCode())
					->setAmount($amount);

			$genesis->execute();

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			$information = array();

			foreach ($genesis->response()->getResponseObject() as $key => $value) {
				$information[strval($key)] = strval($value);
			}

			$payment->setTransactionId($genesis->response()->getResponseObject()->unique_id)
					->setParentTransactionId( $payment->getRefundTransactionId() )
					->setTransactionAdditionalInfo(
						Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						$information
					);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to refund the selected transaction. Please try again or contact us, if the problem persists!')
			);
		}

		return $this;
	}

	/**
	 * Void the last successful transaction
	 *
	 * @param Mage_Sales_Model_Order_Payment $payment
	 *
	 * @return mixed
	 */
	public function void(Varien_Object $payment)
	{
		try{
			$this->getHelper()->initClient($this->getCode());

			$genesis = new \Genesis\Genesis('Financial\Void');

			$genesis
				->request()
					->setTransactionId( $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId()) )
					->setRemoteIp( $this->getHelper('core/http')->getRemoteAddr(false) )
					->setReferenceId($payment->getTransactionId());

			$genesis->execute();

			if (!$genesis->response()->isSuccessful()) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			$information = array();

			foreach ($genesis->response()->getResponseObject() as $key => $value) {
				$information[strval($key)] = strval($value);
			}

			$payment->setTransactionId($genesis->response()->getResponseObject()->unique_id)
					->setParentTransactionId( $payment->getTransactionId() )
					->setTransactionAdditionalInfo(
						Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
						$information
					);
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException(
				$this->getHelper()->__('We were unable to cancel (void) the selected transaction. Please try again or contact us, if the problem persists!')
			);
		}

		return $this;
	}

	/**
	 * Reconcile (Get Transaction) from Genesis Gateway
	 *
	 * @see EMerchantPay_Genesis_DirectController::notifyAction
	 *
	 * @param $unique_id
	 * @return mixed
	 */
	public function reconcile($unique_id)
	{
		try {
			$this->getHelper()->initClient($this->getCode());

			$genesis = new \Genesis\Genesis('Reconcile\Transaction');

			$genesis->request()->setUniqueId($unique_id);

			$genesis->execute();

			if (!isset($genesis->response()->getResponseObject()->status)) {
				throw new Exception($genesis->response()->getResponseObject()->technical_message);
			}

			return $genesis->response()->getResponseObject();
		}
		catch (Exception $exception) {
			Mage::logException($exception);
			Mage::throwException($this->getHelper()->__($exception->getMessage()));
		}

		return false;
	}

	/**
	 * Process a notification for Authorize-type Transaction
	 *
	 * @param $reconcile stdClass
	 *
	 * @return bool true/false based on successful/unsuccessful status
	 */
	public function processAuthNotification($reconcile)
	{
		try {
			$this->getHelper()->initClient($this->getCode());

			list($increment_id, $salt) = explode('-', $reconcile->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($reconcile as $key => $value) {
					$information[strval($key)] = strval($value);
				}

				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

				if (self::GENESIS_STATUS_APPROVED == $reconcile->status) {

					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCcTransId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(false)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->registerAuthorizationNotification(
							\Genesis\Utils\Currency::exponentToReal($reconcile->amount, $reconcile->currency)
						);

					// notify customer
					$invoice = $order->getPayment()->getCreatedInvoice();

					if ($invoice && !$order->getEmailSent()) {
						$order->addStatusHistoryComment(
							$this->getHelper()->__('Notified customer about invoice #%s.', $invoice->getIncrementId())
						);

						$order->sendNewOrderEmail()
						      ->setIsCustomerNotified(true)
						      ->save();
					}
					else {
						$order->save();
					}
				}
				else {
					// Add the transaction just in case
					$order
						->getPayment()
						->setPreparedMessage($this->getHelper()->__('3D-Secure: Failed.'))
						->setTransactionId(strval($reconcile->unique_id))
						->setCurrencyCode(strval($reconcile->currency))
						->setIsTransactionClosed(true)
						->setParentTransactionId(false)
						->setIsTransactionPending(false)
						->setSkipTransactionCreation(false)
						->addTransaction(
							Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
							null,
							false,
							$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $reconcile->message)
						);

					// Set status
					$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->setStatus(Mage_Sales_Model_Order::STATE_CANCELED)
					      ->save();
				}

				return true;
			}
		}
		catch(Exception $exception) {
			Mage::logException($exception);
		}

		return false;
	}

	/**
	 * Process Sale-type (Auth/Capture) Transaction
	 *
	 * @param $reconcile
	 *
	 * @return bool true/false on successful/unsuccessful status
	 */
	public function processAuthCaptureNotification($reconcile)
	{
		try {
			list($increment_id, $salt) = explode('-', $reconcile->transaction_id);

			/** @var Mage_Sales_Model_Order $order */
			$order = Mage::getModel('sales/order')->loadByIncrementId($increment_id);

			if ($order->getId()) {

				$information = array();

				foreach ($reconcile as $key => $value) {
					$information[strval($key)] = strval($value);
				}

				$order->getPayment()->setTransactionAdditionalInfo(
					Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
					$information
				);

				if (self::GENESIS_STATUS_APPROVED == $reconcile->status) {

					$order
						->getPayment()
							->setPreparedMessage($this->getHelper()->__('3D-Secure: Completed.'))
							->setTransactionId(strval($reconcile->unique_id))
							->setCcTransId(strval($reconcile->unique_id))
							->setCurrencyCode(strval($reconcile->currency))
							->setIsTransactionClosed(false)
							->setParentTransactionId(false)
							->setIsTransactionPending(false)
							->setSkipTransactionCreation(false)
							->registerCaptureNotification(
								\Genesis\Utils\Currency::exponentToReal($reconcile->amount, $reconcile->currency)
							);

					// notify customer
					$invoice = $order->getPayment()->getCreatedInvoice();

					if ($invoice && !$order->getEmailSent()) {
						$order->addStatusHistoryComment(
							$this->getHelper()->__('Notified customer about invoice #%s.', $invoice->getIncrementId())
						);

						$order->sendNewOrderEmail()
						      ->setIsCustomerNotified(true)
						      ->save();
					}
					else {
						$order->save();
					}
				}
				else {
					// Add the transaction just in case
					$order
						->getPayment()
							->setPreparedMessage($this->getHelper()->__('3D-Secure: Failed.'))
							->setTransactionId(strval($reconcile->unique_id))
							->setCurrencyCode(strval($reconcile->currency))
							->setIsTransactionClosed(true)
							->setParentTransactionId(false)
							->setIsTransactionPending(false)
							->setSkipTransactionCreation(false)
							->addTransaction(
								Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
								null,
								false,
								$this->getHelper()->__('3D-Secure: Failed. Reason: %s', $reconcile->message)
							);

					// Set status
					$order->setState(Mage_Sales_Model_Order::STATE_CANCELED)
						  ->setStatus(Mage_Sales_Model_Order::STATE_CANCELED)
						  ->save();
				}

				return true;
			}
		}
		catch (Exception $exception) {
			Mage::logException($exception);
		}

		return false;
	}

	/**
	 * Get URL to "Redirect" block
	 *
	 * @see EMerchantPay_Genesis_DirectController
	 *
	 * @note In order for redirect to work, you must
	 * set the session variable "EmerchantPayGenesisDirectRedirectUrl"
	 *
	 * @return mixed
	 */
	public function getOrderPlaceRedirectUrl() {
		if ($this->is3dEnabled()) {
			return $this->getHelper()->getRedirectUrl( 'direct' );
		}
	}

	/**
	 * Check whether we're doing 3D transactions,
	 * based on the module configuration
	 *
	 * @todo add support for "potential" synchronous 3d
	 *
	 * @return bool
	 */
	private function is3dEnabled()
	{
		return (stripos($this->getConfigData('genesis_type'), '3d') === false) ? false : true;
	}

	/**
	 * Get the helper or return its instance
	 *
	 * @param $helper string - Name of the helper, empty for the default class helper
	 *
	 * @return mixed
	 */
	private function getHelper( $helper = '' )
	{
		if (empty($helper)) {
			return Mage::helper('emerchantpay');
		}
		else {
			return Mage::helper($helper);
		}
	}
}