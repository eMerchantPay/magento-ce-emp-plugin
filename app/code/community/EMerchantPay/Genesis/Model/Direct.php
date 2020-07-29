<?php
/*
 * Copyright (C) 2018 emerchantpay Ltd.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * @author      emerchantpay
 * @copyright   2018 emerchantpay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

/**
 * emerchantpay Direct Payment Method
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
class EMerchantPay_Genesis_Model_Direct
    extends Mage_Payment_Model_Method_Cc implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    // Variables
    protected $_code = 'emerchantpay_direct';

    protected $_formBlockType = 'emerchantpay/form_direct';
    protected $_infoBlockType = 'emerchantpay/info_direct';

    // Configurations
    protected $_isGateway         = true;
    protected $_canAuthorize      = true;
    protected $_canCapture        = true;
    protected $_canCapturePartial = true;
    protected $_canRefund         = true;
    protected $_canVoid           = true;
    protected $_canUseInternal    = false;
    protected $_canUseCheckout    = true;

    protected $_isInitializeNeeded      = false;

    protected $_canFetchTransactionInfo = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;

    /**
     * Determines if the Payment Method should be available on the checkout page
     * @param Mage_Sales_Model_Quote $quote
     * @param int|null $checksBitMask
     * @return bool
     */
    public function isApplicableToQuote($quote, $checksBitMask)
    {
        return
            parent::isApplicableToQuote($quote, $checksBitMask) ||
            (
                ($checksBitMask & self::CHECK_ORDER_TOTAL_MIN_MAX) &&
                $this->getHelper()->validateRecurringMethodMinMaxOrderTotal(
                    $this->getCode(),
                    $quote
                )
            );
    }

    /**
     * Check if we're on a secure page and run
     * the parent verification
     *
     * @param Mage_Sales_Model_Quote|null $quote
     *
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return
            parent::isAvailable($quote) &&
            $this->getHelper()->getIsMethodAvailable(
                $this->getCode(),
                $quote,
                true,
                true
            );
    }

    /**
     * Assign the incoming $data to internal variables
     *
     * @param mixed $data
     * @return $this
     */
    public function assignData($data)
    {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }

        $info = $this->getInfoInstance();

        $info->setCcOwner($data->getCcOwner())
             ->setCcNumber($data->getCcNumber())
             ->setCcCid($data->getCcCid())
             ->setCcExpMonth($data->getCcExpMonth())
             ->setCcExpYear($data->getCcExpYear())
             ->setCcType($data->getCcType());

        return $this;
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|Mage_Core_Model_Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if (($field == 'order_status') && (!$this->getIsThreeDEnabled())) {
            //This will force Mage_Sales_Model_Order_Payment to load the default status for State
            //Look into: Mage_Sales_Model_Order_Payment->place()
            return null;
        } else {
            return parent::getConfigData($field, $storeId);
        }
    }

    /**
     * Retrieves the Module Transaction Type Setting
     *
     * @return string
     */
    protected function getConfigTransactionType()
    {
        return $this->getConfigData('genesis_type');
    }

    /**
     * Check whether we're doing 3D transactions,
     * based on the module configuration
     *
     * @return bool
     */
    protected function getIsThreeDEnabled()
    {
        return
            $this->getHelper()->getIsTransaction3dSecure(
                $this->getConfigTransactionType()
            );
    }

    /**
     * Builds full Request Class Name by Transaction Type
     * @param string $transactionType
     * @return string
     */
    protected function getTransactionTypeRequestClassName($transactionType)
    {
        return \Genesis\API\Constants\Transaction\Types::getFinancialRequestClassForTrxType($transactionType);
    }

    /**
     * Payment action getter compatible with payment model
     *
     * @see Mage_Sales_Model_Order_Payment::place()
     *
     * @return string
     */
    public function getConfigPaymentAction()
    {
        $this->getHelper()->initLibrary();

        if (\Genesis\API\Constants\Transaction\Types::isAuthorize($this->getConfigTransactionType())) {
            return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
        }

        return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
    }

    /**
     * Authorize transaction type
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    public function authorize(Varien_Object $payment, $amount)
    {
        if ($this->getIsThreeDEnabled()) {
            Mage::log('Authorize 3D-Secure transaction for order #' . $payment->getOrder()->getIncrementId());
        } else {
            Mage::log('Authorize transaction for order #' . $payment->getOrder()->getIncrementId());
        }

        return $this->processTransaction($payment, $amount);
    }

    /**
     * Capture transaction type
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    public function capture(Varien_Object $payment, $amount)
    {
        $authorize = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

        if ($authorize) {
            return $this->doCapture($payment, $amount);
        } else {
            if ($this->getIsThreeDEnabled()) {
                Mage::log('Sale 3D-Secure transaction for order #' . $payment->getOrder()->getIncrementId());
            } else {
                Mage::log('Sale transaction for order #' . $payment->getOrder()->getIncrementId());
            }

            return $this->processTransaction($payment, $amount);
        }
    }

    /**
     * Sends a transaction to the Gateway
     *    - Authorize
     *    - Authorize (3D Secure)
     *    - Sale
     *    - Sale (3D Secure)
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    protected function processTransaction(Varien_Object $payment, $amount)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $transactionType = $this->getConfigTransactionType();

            $isThreeDEnabled = $this->getIsThreeDEnabled();

            /** @var Mage_Sales_Model_Order $order */
            $order = $payment->getOrder();

            $billing = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis(
                $this->getTransactionTypeRequestClassName(
                    $transactionType
                )
            );

            $genesis
                ->request()
                ->setTransactionId(
                    $this->getHelper()->genTransactionId(
                        $order->getIncrementId()
                    )
                )
                ->setRemoteIp(
                    $this->getHelper()->getRemoteAddress()
                )
                ->setUsage(
                    $this->getHelper()->getItemList($order)
                )
                ->setCurrency(
                    $order->getOrderCurrencyCode()
                )
                ->setAmount(
                    $amount
                )
                ->setCardHolder(
                    $payment->getCcOwner()
                )
                ->setCardNumber(
                    $payment->getCcNumber()
                )
                ->setExpirationYear(
                    $payment->getCcExpYear()
                )
                ->setExpirationMonth(
                    $payment->getCcExpMonth()
                )
                ->setCvv(
                    $payment->getCcCid()
                )
                ->setCustomerEmail(
                    $order->getCustomerEmail()
                )
                ->setCustomerPhone(
                    $billing->getTelephone()
                );

            //Billing Information
            $genesis
                ->request()
                ->setBillingFirstName(
                    $billing->getData('firstname')
                )
                ->setBillingLastName(
                    $billing->getData('lastname')
                )
                ->setBillingAddress1(
                    $billing->getStreet(1)
                )
                ->setBillingAddress2(
                    $billing->getStreet(2)
                )
                ->setBillingZipCode(
                    $billing->getPostcode()
                )
                ->setBillingCity(
                    $billing->getCity()
                )
                ->setBillingState(
                    $billing->getRegion()
                )
                ->setBillingCountry(
                    $billing->getCountry()
                );

            //Shipping Information
            $genesis
                ->request()
                ->setShippingFirstName(
                    $shipping->getData('firstname')
                )
                ->setShippingLastName(
                    $shipping->getData('lastname')
                )
                ->setShippingAddress1(
                    $shipping->getStreet(1)
                )
                ->setShippingAddress2(
                    $shipping->getStreet(2)
                )
                ->setShippingZipCode(
                    $shipping->getPostcode()
                )
                ->setShippingCity(
                    $shipping->getCity()
                )
                ->setShippingState(
                    $shipping->getRegion()
                )
                ->setShippingCountry(
                    $shipping->getCountry()
                );

            if ($isThreeDEnabled) {
                $genesis
                    ->request()
                    ->setNotificationUrl(
                        $this->getHelper()->getNotifyURL('direct')
                    )
                    ->setReturnSuccessUrl(
                        $this->getHelper()->getSuccessURL('direct')
                    )
                    ->setReturnFailureUrl(
                        $this->getHelper()->getFailureURL('direct')
                    );
            }

            $genesis->execute();

            $this->setGenesisResponse(
                $genesis->response()->getResponseObject()
            );

            $payment
                ->setTransactionId(
                    $this->getGenesisResponse()->unique_id
                )
                ->setIsTransactionClosed(
                    false
                )
                ->setIsTransactionPending(
                    $isThreeDEnabled
                )
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $this->getGenesisResponse()
                            )
                    ),
                    null
                );

            if ($isThreeDEnabled) {
                $payment->setPreparedMessage(
                    $this->getHelper()->__('3D-Secure: Redirecting customer to a verification page.')
                );
            }

            $payment->save();

            $gatewayStatus = new \Genesis\API\Constants\Transaction\States(
                $this->getGenesisResponse()->status
            );

            if ($gatewayStatus->isPendingAsync()) {
                // Save the redirect url with our
                $this->getHelper()->getCheckoutSession()->setEmerchantPayDirectRedirectUrl(
                    $this->getGenesisResponse()->redirect_url
                );
            } elseif (!$gatewayStatus->isApproved()) {
                throw new \Genesis\Exceptions\ErrorAPI(
                    $this->getGenesisResponse()->message
                );
            }
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );
        }

        return $this;
    }

    /**
     * Capture a successful auth transaction
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     *
     * @throws Mage_Core_Exception
     */
    protected function doCapture($payment, $amount)
    {
        Mage::log('Capture transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $authorize = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

            $referenceId = $authorize->getTxnId();

            $rawDetails = $authorize->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );
            $transactionType = $rawDetails['transaction_type'];

            $genesis = new \Genesis\Genesis(
                \Genesis\API\Constants\Transaction\Types::getCaptureTransactionClass($transactionType)
            );

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId(
                            $payment->getOrder()->getIncrementId()
                        )
                    )
                    ->setRemoteIp(
                        $this->getHelper()->getRemoteAddress()
                    )
                    ->setReferenceId(
                        $referenceId
                    )
                    ->setCurrency(
                        $payment->getOrder()->getOrderCurrencyCode()
                    )
                    ->setAmount(
                        $amount
                    )
                    ->setUsage(
                        $this->getHelper()->__('Magento Capture')
                    );

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    // @codingStandardsIgnoreStart
                    $responseObject->unique_id
                    // @codingStandardsIgnoreEnd
                )
                ->setParentTransactionId(
                    $referenceId
                )
                ->setIsTransactionClosed(
                    false
                )
                ->setShouldCloseParentTransaction(
                    true
                )
                ->resetTransactionAdditionalInfo(

                )
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $responseObject
                            )
                    ),
                    null
                );

            $payment->save();

            if ($responseObject->status == \Genesis\API\Constants\Transaction\States::APPROVED) {
                $this->getHelper()->getAdminSession()->addSuccess(
                    $responseObject->message
                );
            } else {
                $this->getHelper()->getAdminSession()->addError(
                    $responseObject->message
                );
            }
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );
        }

        return $this;
    }

    /**
     * Refund the last successful transaction
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    public function refund(Varien_Object $payment, $amount)
    {
        Mage::log('Refund transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $capture = $this->getHelper()->getCaptureForRefund($payment);
            if ($capture === null) {
                throw new Exception(
                    $this->getHelper()->__('Cannot Refund')
                );
            }

            $referenceId = $capture->getTxnId();

            $rawDetails = $capture->getAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
            );
            $transactionType = $rawDetails['transaction_type'];

            $genesis = new \Genesis\Genesis(
                \Genesis\API\Constants\Transaction\Types::getRefundTransactionClass($transactionType)
            );

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId(
                            $payment->getOrder()->getIncrementId()
                        )
                    )
                    ->setRemoteIp(
                        $this->getHelper()->getRemoteAddress()
                    )
                    ->setReferenceId(
                        $referenceId
                    )
                    ->setCurrency(
                        $payment->getOrder()->getOrderCurrencyCode()
                    )
                    ->setAmount(
                        $amount
                    )
                    ->setUsage(
                        $this->getHelper()->__('Magento Refund')
                    );

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    // @codingStandardsIgnoreStart
                    $responseObject->unique_id
                    // @codingStandardsIgnoreEnd
                )
                ->setParentTransactionId(
                    $referenceId
                )
                ->setShouldCloseParentTransaction(
                    true
                )
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $responseObject
                            )
                    ),
                    null
                );

            $payment->save();

            if ($responseObject->status == \Genesis\API\Constants\Transaction\States::APPROVED) {
                $this->getHelper()->getAdminSession()->addSuccess(
                    $responseObject->message
                );

                if (isset($capture) && $capture !== false) {
                    $canceledProfileReferenceId = $this->getHelper()->checkAndCancelRecurringProfile(
                        $capture
                    );

                    if (isset($canceledProfileReferenceId)) {
                        $this->getHelper()->getAdminSession()->addNotice(
                            $this->getHelper()->__(
                                sprintf(
                                    "Profile #%s has been canceled!",
                                    $canceledProfileReferenceId
                                )
                            )
                        );
                    }
                }
            } else {
                $this->getHelper()->getAdminSession()->addError(
                    $responseObject->message
                );
            }
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $exception->getMessage()
            );
        }

        return $this;
    }

    /**
     * Void the last successful transaction
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    public function void(Varien_Object $payment)
    {
        Mage::log('Void transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $transactions = $this->getHelper()->getTransactionFromPaymentObject(
                $payment,
                array(
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
                )
            );

            $referenceId = $transactions ? reset($transactions)->getTxnId() : null;

            $genesis = new \Genesis\Genesis('Financial\Cancel');

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId())
                    )
                    ->setRemoteIp(
                        $this->getHelper()->getRemoteAddress()
                    )
                    ->setReferenceId(
                        $referenceId
                    )
                    ->setUsage(
                        $this->getHelper()->__('Magento Void')
                    );

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    // @codingStandardsIgnoreStart
                    $responseObject->unique_id
                    // @codingStandardsIgnoreEnd
                )
                ->setParentTransactionId(
                    $referenceId
                )
                ->setShouldCloseParentTransaction(
                    true
                )
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $responseObject
                            )
                    ),
                    null
                );

            $payment->save();

            if ($responseObject->status == \Genesis\API\Constants\Transaction\States::APPROVED) {
                $this->getHelper()->getAdminSession()->addSuccess(
                    $responseObject->message
                );
            } else {
                $this->getHelper()->getAdminSession()->addError(
                    $responseObject->message
                );
            }
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $exception->getMessage()
            );
        }

        return $this;
    }

    /**
     * Cancel order
     *
     * @param Varien_Object $payment
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    public function cancel(Varien_Object $payment)
    {
        return $this->void($payment);
    }

    /**
     * Fetch transaction details info
     *
     * @param Mage_Payment_Model_Info $payment
     * @param string $transactionId
     *
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        $reconcile = $this->reconcile($transactionId);

        // Remove the current details
        $payment->unsAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );

        // Set the default/updated transaction details
        $payment->setAdditionalInformation(
            array(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                    $this->getHelper()->getArrayFromGatewayResponse(
                        $reconcile
                    )
            ),
            null
        );

        $payment->save();

        return $payment->getAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );
    }

    /**
     * Reconcile (Get Transaction) from Genesis Gateway
     *
     * @see EMerchantPay_Genesis_DirectController::notifyAction
     *
     * @param $uniqueId
     * @return mixed
     */
    public function reconcile($uniqueId)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $genesis = new \Genesis\Genesis('NonFinancial\Reconcile\Transaction');

            $genesis->request()->setUniqueId($uniqueId);

            $genesis->execute();

            return $genesis->response()->getResponseObject();
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );
        }

        return false;
    }

    /**
     * Handle an incoming Genesis notification
     *
     * @param stdClass $reconcile
     * @return $this
     */
    // @codingStandardsIgnoreStart
    public function processNotification($reconcile)
    {
        // @codingStandardsIgnoreEnd
        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load(
                // @codingStandardsIgnoreStart
                $reconcile->unique_id,
                // @codingStandardsIgnoreEnd
                'txn_id'
            );

            $order = $transaction->getOrder();

            if ($order) {
                $payment = $order->getPayment();

                $transaction->setOrderPaymentObject($payment);

                $transaction->unsAdditionalInformation(
                    Mage_Sales_Model_Order_Payment_transaction::RAW_DETAILS
                );

                $transaction->setAdditionalInformation(
                    Mage_Sales_Model_Order_Payment_transaction::RAW_DETAILS,
                    $this->getHelper()->getArrayFromGatewayResponse(
                        $reconcile
                    )
                );

                $isTransactionApproved =
                    ($reconcile->status == \Genesis\API\Constants\Transaction\States::APPROVED);

                $transaction->setIsClosed(!$isTransactionApproved);

                $transaction->save();

                $isCapturable = \Genesis\API\Constants\Transaction\Types::isAuthorize($reconcile->transaction_type);

                if ($isCapturable) {
                    $payment->registerAuthorizationNotification($reconcile->amount);
                } else {
                    $payment->setShouldCloseParentTransaction(true);
                    $payment->setTransactionId(
                    // @codingStandardsIgnoreStart
                        $reconcile->unique_id
                    // @codingStandardsIgnoreEnd
                    );

                    $payment->registerCaptureNotification($reconcile->amount);
                }

                // @codingStandardsIgnoreStart
                if ($this->getHelper()->getIsTransactionTypeInitRecurring($reconcile->transaction_type)) {
                    // @codingStandardsIgnoreEnd
                    $recurringProfile = Mage::getModel('sales/recurring_profile')->load(
                        // @codingStandardsIgnoreStart
                        $reconcile->unique_id,
                        // @codingStandardsIgnoreEnd
                        'reference_id'
                    );

                    if ($recurringProfile && $recurringProfile->getId()) {
                        if ($recurringProfile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_PENDING) {
                            $recurringProfile->setState(
                                $isTransactionApproved
                                    ? Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE
                                    : Mage_Sales_Model_Recurring_Profile::STATE_PENDING
                            );
                            $recurringProfile->save();
                        }
                    }
                }

                $payment->save();

                $this->getHelper()->setOrderState(
                    $order,
                    $reconcile->status,
                    $reconcile->message
                );

                $order->queueNewOrderEmail();
            }
        } catch (Exception $exception) {
            Mage::logException($exception);
        }

        return $this;
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
    public function getOrderPlaceRedirectUrl()
    {
        if ($this->getIsThreeDEnabled()) {
            return $this->getHelper()->getRedirectUrl('direct');
        }

        return false;
    }

    /**
     * Get the helper or return its instance
     *
     * @param $helper string - Name of the helper, empty for the default class helper
     *
     * @return EMerchantPay_Genesis_Helper_Data|mixed
     */
    protected function getHelper($helper = '')
    {
        if (!$helper) {
            return Mage::helper('emerchantpay');
        } else {
            return Mage::helper($helper);
        }
    }

    /**
     * Validate RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $logFileName = $this->getConfigData('cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        return $this;
    }

    /**
     * Submit RP to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $payment
     * @return $this
     * @throws Mage_Core_Exception
     */
    // @codingStandardsIgnoreStart
    public function submitRecurringProfile(
        Mage_Payment_Model_Recurring_Profile $profile,
        Mage_Payment_Model_Info $payment
    ) {
        // @codingStandardsIgnoreEnd
        $logFileName = $this->getConfigData('cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        $this->getHelper()->initClient($this->getCode());

        $transactionType = $this->getConfigData('recurring_transaction_type');

        $genesis = new \Genesis\Genesis(
            $this->getTransactionTypeRequestClassName(
                $transactionType
            )
        );

        $amount = $profile->getInitAmount() ?: 0;

        $genesis
            ->request()
                ->setTransactionId(
                    $profile->getInternalReferenceId()
                )
                ->setUsage('Magento Init Recurring Payment')
                ->setMoto('')
                ->setRemoteIp(
                    $this->getHelper()->getRemoteAddress()
                )
                ->setCurrency(
                    $payment->getQuote()->getStoreCurrencyCode()
                )
                ->setAmount(
                    $amount
                )
                ->setCardHolder(
                    $payment->getCcOwner()
                )
                ->setCardNumber(
                    $payment->getCcNumber()
                )
                ->setExpirationYear(
                    $payment->getCcExpYear()
                )
                ->setExpirationMonth(
                    $payment->getCcExpMonth()
                )
                ->setCvv(
                    $payment->getCcCid()
                )
                ->setCustomerEmail(
                    $profile->getBillingAddressInfo()['email']
                )
                ->setCustomerPhone(
                    $profile->getBillingAddressInfo()['telephone']
                );
// Billing
        $genesis
            ->request()
                ->setBillingFirstName(
                    $profile->getBillingAddressInfo()['firstname']
                )
                ->setBillingLastName(
                    $profile->getBillingAddressInfo()['lastname']
                )
                ->setBillingAddress1(
                    $profile->getBillingAddressInfo()['street']
                )
                ->setBillingZipCode(
                    $profile->getBillingAddressInfo()['postcode']
                )
                ->setBillingCity(
                    $profile->getBillingAddressInfo()['city']
                )
                ->setBillingState(
                    $profile->getBillingAddressInfo()['region']
                )
                ->setBillingCountry(
                    $profile->getBillingAddressInfo()['country_id']
                );
// Shipping
        $genesis
            ->request()
                ->setShippingFirstName(
                    $profile->getShippingAddressInfo()['firstname']
                )
                ->setShippingLastName(
                    $profile->getShippingAddressInfo()['lastname']
                )
                ->setShippingAddress1(
                    $profile->getShippingAddressInfo()['street']
                )
                ->setShippingZipCode(
                    $profile->getShippingAddressInfo()['postcode']
                )
                ->setShippingCity(
                    $profile->getShippingAddressInfo()['city']
                )
                ->setShippingState(
                    $profile->getShippingAddressInfo()['region']
                )
                ->setShippingCountry(
                    $profile->getShippingAddressInfo()['country_id']
                );

        if ($this->getHelper()->getIsTransaction3dSecure($transactionType)) {
            $genesis
                ->request()
                    ->setNotificationUrl(
                        $this->getHelper()->getNotifyURL('direct')
                    )
                    ->setReturnSuccessUrl(
                        $this->getHelper()->getSuccessURL('direct')
                    )
                    ->setReturnFailureUrl(
                        $this->getHelper()->getFailureURL('direct')
                    );
        }

        try {
            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            // @codingStandardsIgnoreStart
            if (isset($responseObject->redirect_url)) {
                // @codingStandardsIgnoreEnd
                $this->getHelper()->getCheckoutSession()->setEmerchantPayCheckoutRedirectUrl(
                    // @codingStandardsIgnoreStart
                    $responseObject->redirect_url
                    // @codingStandardsIgnoreEnd
                );
            }

            $profile->setReferenceId(
                // @codingStandardsIgnoreStart
                $responseObject->unique_id
                // @codingStandardsIgnoreEnd
            );

            $payment->setSkipTransactionCreation(true);

            $isInitRecurringApproved =
                $responseObject->status == \Genesis\API\Constants\Transaction\States::APPROVED;

            switch ($responseObject->status) {
                case \Genesis\API\Constants\Transaction\States::PENDING:
                case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                case \Genesis\API\Constants\Transaction\States::APPROVED:
                    $productItemInfo = new Varien_Object;
                    $productItemInfo->setPaymentType(
                        Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_INITIAL
                    );
                    $productItemInfo->setPrice(
                        $amount
                    );

                    $order = $profile->createOrder($productItemInfo);

                    $order->setState(
                        $isInitRecurringApproved
                            ? Mage_Sales_Model_Order::STATE_PROCESSING
                            : Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
                    );

                    $order->setStatus(
                        $isInitRecurringApproved
                            ? Mage_Sales_Model_Order::STATE_PROCESSING
                            : Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
                    );

                    $payment = $order->getPayment();

                    $payment
                        ->setTransactionId(
                            // @codingStandardsIgnoreStart
                            $responseObject->unique_id
                            // @codingStandardsIgnoreEnd
                        );
                    $payment
                        ->setIsTransactionPending(
                            !$isInitRecurringApproved
                        );
                    $payment
                        ->setIsTransactionClosed(
                            $isInitRecurringApproved
                        );
                    $payment
                        ->setTransactionAdditionalInfo(
                            array(
                                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                                    $this->getHelper()->getArrayFromGatewayResponse(
                                        $responseObject
                                    )
                            ),
                            null
                        );
                    $payment
                        ->addTransaction(
                            Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
                        );

                    if ($isInitRecurringApproved &&
                        $transactionType == \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE) {
                        $payment->registerCaptureNotification(
                            $responseObject->amount
                        );
                    }

                    $order->save();
                    $profile->addOrderRelation(
                        $order->getId()
                    );
                    $order->save();
                    $payment->save();

                    $profile->setState(
                        $isInitRecurringApproved
                            ? Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE
                            : Mage_Sales_Model_Recurring_Profile::STATE_PENDING
                    );

                    return $this;

                default:
                    if (!$profile->getInitMayFail()) {
                        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
                        $profile->save();
                    }

                    Mage::throwException(
                        $responseObject->message
                    );
                    break;
            }
        } catch (Exception $e) {
            Mage::throwException(
                $e->getMessage()
            );
        }

        return $this;
    }

    /**
     * Fetch RP details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     * @return $this
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result)
    {
        return $this;
    }

    /**
     * Whether can get recurring profile details
     * @return bool
     */
    public function canGetRecurringProfileDetails()
    {
        return false;
    }

    /**
     * Update RP data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $logFileName = $this->getConfigData('cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        return $this;
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @return $this
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile)
    {
        $logFileName = $this->getConfigData('cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        return $this;
    }
}
