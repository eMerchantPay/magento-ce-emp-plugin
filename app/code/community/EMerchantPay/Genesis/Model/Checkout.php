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
 * EMerchantPay Checkout Payment Method Model
 *
 * Class EMerchantPay_Genesis_Model_Checkout
 */
class EMerchantPay_Genesis_Model_Checkout
    extends Mage_Payment_Model_Method_Abstract implements Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    protected $_code = 'emerchantpay_checkout';

    /**
     * Value has to be the same as XML tag in system.xml
     */
    const TOKENIZATION_ENABLED_CONFIG_KEY = 'tokenization_enabled';
    const LOG_FILE_NAME                   = 'emerchantpay_genesis.log';
    /**
     * Value has to be the same as defined in setup class EMerchantPay_Genesis_Model_Resource_Setup
     */
    const CONSUMER_ID_DATA_KEY            = 'consumer_id';

    protected $_formBlockType = 'emerchantpay/form_checkout';
    protected $_infoBlockType = 'emerchantpay/info_checkout';

    protected $_isGateway         = true;
    protected $_canOrder          = true;
    protected $_canAuthorize      = true;
    protected $_canCapture        = true;
    protected $_canCapturePartial = true;
    protected $_canRefund         = true;
    protected $_canVoid           = true;
    protected $_canUseInternal    = false;
    protected $_canUseCheckout    = true;

    protected $_canUseForMultishipping     = false;
    protected $_canFetchTransactionInfo    = true;
    protected $_canManageRecurringProfiles = true;

    /**
     * WPF Create method piggyback-ing the Magento's internal Authorize method
     *
     * @param Mage_Sales_Model_Order_Payment|Varien_Object $payment
     * @param String $amount
     * @return EMerchantPay_Genesis_Model_Checkout
     * @throws Mage_Core_Exception
     */
    public function order(Varien_Object $payment, $amount)
    {
        Mage::log('Checkout transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order $order */
            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('WPF\Create');

            $orderItemsList = $this->getHelper()->getItemList($order);

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId(
                            $order->getIncrementId()
                        )
                    )
                    ->setCurrency(
                        $order->getOrderCurrencyCode()
                    )
                    ->setAmount($amount)
                    ->setUsage(
                        $this->getHelper()->__('Magento Payment')
                    )
                    ->setDescription($orderItemsList)
                    ->setCustomerPhone($billing->getTelephone())
                    ->setCustomerEmail($order->getCustomerEmail())
                    ->setNotificationUrl(
                        $this->getHelper()->getNotifyURL('checkout')
                    )
                    ->setReturnSuccessUrl(
                        $this->getHelper()->getSuccessURL('checkout')
                    )
                    ->setReturnFailureUrl(
                        $this->getHelper()->getFailureURL('checkout')
                    )
                    ->setReturnCancelUrl(
                        $this->getHelper()->getCancelURL('checkout')
                    )
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
                    ->setShippingCountry($shipping->getCountry())
                    ->setLanguage($this->getHelper()->getLocale());

            $this->addTransactionTypesToGatewayRequest($genesis, $order);

            if ($this->isTokenizationEnabled()) {
                if (!$this->getHelper()->getCustomerSession()->isLoggedIn()) {
                    throw new LogicException(
                        $this->getHelper()->__('You cannot finish the payment as a guest, please login / register.')
                    );
                }

                $genesis->request()->setRememberCard(true);

                $customer = $this->getCustomerFromOrder($order);
                $consumerId = $this->getConsumerIdFromCustomer($customer);

                if (empty($consumerId)) {
                    $consumerId = $this->retrieveConsumerIdFromEmail($order->getCustomerEmail());
                }

                if ($consumerId) {
                    $genesis->request()->setConsumerId($consumerId);
                }
            }

            $genesis->execute();

            if (!empty($genesis->response()->getResponseObject()->consumer_id)) {
                $this->setConsumerIdForCustomer($customer, $genesis->response()->getResponseObject()->consumer_id);
            }

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setIsTransactionPending(true)
                ->addTransaction(
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
                );

            $payment->setSkipTransactionCreation(true);

            // Save the redirect url with our
            $this->getHelper()->getCheckoutSession()->setEmerchantPayCheckoutRedirectUrl(
                $genesis->response()->getResponseObject()->redirect_url
            );
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );
        }

        return $this;
    }

    /**
     * @return bool
     */
    protected function isTokenizationEnabled()
    {
        return $this->getHelper()->getConfigBoolValue(
            $this->_code,
            self::TOKENIZATION_ENABLED_CONFIG_KEY
        );
    }

    /**
     * @param Mage_Sales_Model_Order $order
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function getCustomerFromOrder(Mage_Sales_Model_Order $order)
    {
        return Mage::getModel('customer/customer')->load($order->getCustomerId());
    }

    /**
     * @param Mage_Core_Model_Abstract $customer
     *
     * @return string
     */
    protected function getConsumerIdFromCustomer(Mage_Core_Model_Abstract $customer)
    {
        return $customer->getData(self::CONSUMER_ID_DATA_KEY);
    }

    /**
     * @param Mage_Core_Model_Abstract $customer
     * @param string $consumerId
     *
     * @throws Exception
     */
    protected function setConsumerIdForCustomer(Mage_Core_Model_Abstract $customer, $consumerId)
    {
        $customer->setData(self::CONSUMER_ID_DATA_KEY, $consumerId);
        $customer->save();
    }

    /**
     * @param string $email
     *
     * @return null|int
     */
    protected function retrieveConsumerIdFromEmail($email)
    {
        try {
            $genesis = new \Genesis\Genesis('NonFinancial\Consumers\Retrieve');
            $genesis->request()->setEmail($email);

            $genesis->execute();

            $response = $genesis->response()->getResponseObject();

            if ($this->isErrorResponse($response)) {
                return null;
            }

            return $response->consumer_id;
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @param $response
     *
     * @return bool
     */
    protected function isErrorResponse($response)
    {
        $state = new \Genesis\API\Constants\Transaction\States($response->status);

        return $state->isError();
    }

    /**
     * @param \Genesis\Genesis $genesis
     * @param Mage_Sales_Model_Order $order
     * @return void
     */
    public function addTransactionTypesToGatewayRequest(\Genesis\Genesis $genesis, $order)
    {
        $types = $this->getTransactionTypes();

        foreach ($types as $transactionType) {
            if (is_array($transactionType)) {
                $genesis
                    ->request()
                        ->addTransactionType(
                            $transactionType['name'],
                            $transactionType['parameters']
                        );

                continue;
            }

            switch ($transactionType) {
                case \Genesis\API\Constants\Transaction\Types::PAYBYVOUCHER_SALE:
                    $parameters = array(
                        'card_type'   =>
                            \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\CardTypes::VIRTUAL,
                        'redeem_type' =>
                            \Genesis\API\Constants\Transaction\Parameters\PayByVouchers\RedeemTypes::INSTANT
                    );
                    break;
                case \Genesis\API\Constants\Transaction\Types::IDEBIT_PAYIN:
                case \Genesis\API\Constants\Transaction\Types::INSTA_DEBIT_PAYIN:
                    $parameters = array(
                        'customer_account_id' => $this->getHelper()->getCurrentUserIdHash()
                    );
                    break;
                case \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE:
                    $parameters = $this->getHelper()->getKlarnaCustomParamItems($order)->toArray();
                    break;
                case \Genesis\API\Constants\Transaction\Types::TRUSTLY_SALE:
                    $helper = $this->getHelper();
                    $userId = $helper::getCurrentUserId();
                    $trustlyUserId = empty($userId) ? $helper->getCurrentUserIdHash() : $userId;
                    $parameters = array(
                        'user_id' => $trustlyUserId
                    );
                    break;
            }

            if (!isset($parameters)) {
                $parameters = array();
            }

            $genesis
                ->request()
                    ->addTransactionType(
                        $transactionType,
                        $parameters
                    );
            
            unset($parameters);
        }
    }

    /**
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     * @return $this|bool
     * @throws Mage_Core_Exception
     */
    public function capture(Varien_Object $payment, $amount)
    {
        Mage::log('Capture transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $this->getHelper()->setTokenByPaymentTransaction($payment);

            $authorize = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
            
            /* Capture should only be possible, when Authorize Transaction Exists */
            if (!isset($authorize) || $authorize === false) {
                Mage::log(
                    'Capture transaction for order #' .
                    $payment->getOrder()->getIncrementId() .
                    ' cannot be finished (No Authorize Transaction exists)'
                );

                return $this;
            }

            $referenceId     = $authorize->getTxnId();
            $transactionType = $this->getHelper()->getGenesisPaymentTransactionType($authorize);

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
                        $this->getHelper('core/http')->getRemoteAddr(false)
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

            if ($transactionType === \Genesis\API\Constants\Transaction\Types::KLARNA_AUTHORIZE) {
                $genesis->request()->setItems($this->getHelper()->getKlarnaCustomParamItems($payment->getOrder()));
            }

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
     * @return EMerchantPay_Genesis_Model_Checkout
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

            $referenceId     = $capture->getTxnId();
            $transactionType = $this->getHelper()->getGenesisPaymentTransactionType($capture);

            $this->getHelper()->setTokenByPaymentTransaction($payment);

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
                        $this->getHelper('core/http')->getRemoteAddr(false)
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

            if ($transactionType === \Genesis\API\Constants\Transaction\Types::KLARNA_CAPTURE) {
                $genesis->request()->setItems($this->getHelper()->getKlarnaCustomParamItems($payment->getOrder()));
            }

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
     * @return EMerchantPay_Genesis_Model_Checkout
     */
    public function void(Varien_Object $payment)
    {
        Mage::log('Void transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $this->getHelper()->setTokenByPaymentTransaction($payment);

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
                        $this->getHelper()->genTransactionId(
                            $payment->getOrder()->getIncrementId()
                        )
                    )
                    ->setRemoteIp(
                        $this->getHelper('core/http')->getRemoteAddr(false)
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
                $exception->getMessage()
            );
        }

        return $this;
    }

    /**
     * Cancel payment abstract method
     *
     * @param Varien_Object $payment
     *
     * @return EMerchantPay_Genesis_Model_Checkout
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
     * @return array
     */
    public function fetchTransactionInfo(Mage_Payment_Model_Info $payment, $transactionId)
    {
        /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
        $transaction = Mage::getModel('sales/order_payment_transaction')->load($transactionId, 'txn_id');

        $checkoutTransaction = $transaction->getOrder()->getPayment()->lookupTransaction(
            null,
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
        );

        $reconcile = $this->reconcile(
            $checkoutTransaction->getTxnId()
        );

        // Get the current details
        $transactionDetails = $payment->getAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );

        // Try to extract transaction details from the Gateway response
        // @codingStandardsIgnoreStart
        if ($reconcile->unique_id == $transactionId) {
            $transactionDetails = $reconcile;
        } else {
            if ($reconcile->payment_transaction instanceof stdClass) {
                if ($reconcile->payment_transaction->unique_id == $transactionId) {
                    $transactionDetails = $reconcile->payment_transaction;
                    // @codingStandardsIgnoreEnd
                }
            }

            // @codingStandardsIgnoreStart
            if ($reconcile->payment_transaction instanceof ArrayObject) {
                foreach ($reconcile->payment_transaction as $paymentTransaction) {
                    if ($paymentTransaction->unique_id == $transactionId) {
                        // @codingStandardsIgnoreEnd
                        $transactionDetails = $paymentTransaction;
                    }
                }
            }
        }

        // Remove the current details
        $payment->unsAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );

        // Set the default/updated transaction details
        $payment->setAdditionalInformation(
            array(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                    $this->getHelper()->getArrayFromGatewayResponse(
                        $transactionDetails
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
     * Execute a WPF Reconcile
     *
     * @param string $uniqueId
     *
     * @return bool|stdClass
     *
     * @throws Mage_Core_Exception
     */
    public function reconcile($uniqueId)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $genesis = new \Genesis\Genesis('WPF\Reconcile');

            $genesis->request()->setUniqueId($uniqueId);

            $genesis->execute();

            return $genesis->response()->getResponseObject();
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $exception->getMessage()
            );
        }

        return false;
    }

    /**
     * Handle an incoming Genesis notification
     *
     * @param stdClass $checkoutTransaction
     * @return bool
     */
    // @codingStandardsIgnoreStart
    public function processNotification($checkoutTransaction)
    {
        // @codingStandardsIgnoreEnd
        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load(
                // @codingStandardsIgnoreStart
                $checkoutTransaction->unique_id,
                // @codingStandardsIgnoreEnd
                'txn_id'
            );

            $order = $transaction->getOrder();

            if (!$order) {
                return false;
            }

            $transaction
                ->setOrderPaymentObject(
                    $order->getPayment()
                )
                ->setAdditionalInformation(
                    Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                    $this->getHelper()->getArrayFromGatewayResponse(
                        $checkoutTransaction
                    )
                )
                ->save();

            // @codingStandardsIgnoreStart
            if (!isset($checkoutTransaction->payment_transaction)) {
                // @codingStandardsIgnoreEnd

                $this->getHelper()->setOrderState(
                    $order,
                    $checkoutTransaction->status
                );

                return false;
            }

            // @codingStandardsIgnoreStart
            $paymentTransaction = $checkoutTransaction->payment_transaction;
            // @codingStandardsIgnoreEnd

            $payment = $order->getPayment();

            $payment
                ->setTransactionId(
                    // @codingStandardsIgnoreStart
                    $paymentTransaction->unique_id
                    // @codingStandardsIgnoreEnd
                )
                ->setParentTransactionId(
                    // @codingStandardsIgnoreStart
                    $checkoutTransaction->unique_id
                    // @codingStandardsIgnoreEnd
                )
                ->setShouldCloseParentTransaction(true)
                ->setIsTransactionPending(false)
                ->resetTransactionAdditionalInfo()
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $paymentTransaction
                            )
                    ),
                    null
                );

            $trxStatus = new \Genesis\API\Constants\Transaction\States($paymentTransaction->status);

            if ($trxStatus->isApproved()) {
                $payment->setIsTransactionClosed(false);

                $this->registerNotifications($paymentTransaction, $payment);
            } else {
                $payment->setIsTransactionClosed(true);
            }

            // @codingStandardsIgnoreStart
            if ($this->getHelper()->getIsTransactionTypeInitRecurring($paymentTransaction->transaction_type)) {
                // @codingStandardsIgnoreEnd
                $recurringProfile = Mage::getModel('sales/recurring_profile')->load(
                    // @codingStandardsIgnoreStart
                    $checkoutTransaction->unique_id,
                    // @codingStandardsIgnoreEnd
                    'reference_id'
                );

                if ($recurringProfile && $recurringProfile->getId()) {
                    if ($recurringProfile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_PENDING) {
                        $recurringProfile->setState(
                            ($trxStatus->isApproved()
                                ? Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE
                                : Mage_Sales_Model_Recurring_Profile::STATE_CANCELED
                            )
                        );
                        $recurringProfile->save();
                    }
                }
            }

            $payment->save();

            $this->getHelper()->setOrderState(
                $order,
                $paymentTransaction->status
            );

            $order->queueNewOrderEmail();

            return true;
        } catch (Exception $exception) {
            Mage::logException($exception);
        }

        return false;
    }

    private function registerNotifications($paymentTransaction, $payment)
    {
        $isCapturable = \Genesis\API\Constants\Transaction\Types::canCapture($paymentTransaction->transaction_type);
        $isRefundable = \Genesis\API\Constants\Transaction\Types::canRefund($paymentTransaction->transaction_type);

        if (!$isCapturable && !$isRefundable) {
            $payment->setIsTransactionClosed(true);
        }

        if ($isCapturable) {
            $payment->registerAuthorizationNotification(
                $paymentTransaction->amount
            );
        } else {
            $payment->registerCaptureNotification(
                $paymentTransaction->amount
            );
        }
    }

    /**
     * Returns the available recurring transaction type for the Checkout PM
     * @return array
     */
    public function getRecurringTransactionTypes()
    {
        return array_map(
            'trim',
            explode(
                ',',
                $this->getConfigData('recurring_transaction_types')
            )
        );
    }

    /**
     * Get the selected transaction types in array
     *
     * @return array
     */
    public function getTransactionTypes()
    {
        $processedList = array();

        $selectedTypes = array_map(
            'trim',
            explode(
                ',',
                $this->getConfigData('genesis_types')
            )
        );

        $pproSuffix = EMerchantPay_Genesis_Helper_Data::PPRO_TRANSACTION_SUFFIX;
        $methods    = \Genesis\API\Constants\Payment\Methods::getMethods();

        foreach ($methods as $method) {
            $aliasMap[$method . $pproSuffix] = \Genesis\API\Constants\Transaction\Types::PPRO;
        }

        foreach ($selectedTypes as $selectedType) {
            if (array_key_exists($selectedType, $aliasMap)) {
                $transactionType = $aliasMap[$selectedType];

                $processedList[$transactionType]['name'] = $transactionType;

                $processedList[$transactionType]['parameters'][] = array(
                    'payment_method' => str_replace($pproSuffix, '', $selectedType)
                );
            } else {
                $processedList[] = $selectedType;
            }
        }

        return $processedList;
    }

    /**
     * Get URL to "Redirect" block
     *
     * @see EMerchantPay_Genesis_CheckoutController
     *
     * @note In order for redirect to work, you must
     * set the session variable:
     *
     * EmerchantPayGenesisCheckoutRedirectUrl
     *
     * @return mixed
     */
    public function getOrderPlaceRedirectUrl()
    {
        return $this->getHelper()->getRedirectUrl('checkout');
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
     * Determines if the Payment Method should be visible on the chechout page or not
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    public function isAvailable($quote = null)
    {
        return
            parent::isAvailable($quote) &&
            $this->getHelper()->getIsMethodAvailable(
                $this->getCode(),
                $quote,
                false,
                true
            );
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
     */
    public function submitRecurringProfile(
        Mage_Payment_Model_Recurring_Profile $profile,
        Mage_Payment_Model_Info $payment
    ) {
        $logFileName = $this->getConfigData('cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        $this->getHelper()->initClient($this->getCode());

        $genesis = new \Genesis\Genesis("WPF\\Create");

        $amount = $profile->getInitAmount() ?: 0;

        if (!$amount) {
            Mage::throwException(
                $this->getHelper()->__("Unable to create Init Recurring Transaction! Initial Fee must not be empty!")
            );
        }

        $genesis
            ->request()
                ->setTransactionId(
                    $profile->getInternalReferenceId()
                )
                ->setCurrency(
                    $payment->getQuote()->getStoreCurrencyCode()
                )
                ->setAmount(
                    $amount
                )
                ->setUsage(
                    $this->getHelper()->__('Magento Init Recurring Payment')
                )
                ->setDescription(
                    $this->getHelper()->getRecurringProfileItemDescription(
                        $profile
                    )
                )
                ->setCustomerPhone(
                    $profile->getBillingAddressInfo()['telephone']
                )
                ->setCustomerEmail(
                    $profile->getBillingAddressInfo()['email']
                )
                ->setNotificationUrl(
                    $this->getHelper()->getNotifyURL('checkout')
                )
                ->setReturnSuccessUrl(
                    $this->getHelper()->getSuccessURL('checkout')
                )
                ->setReturnFailureUrl(
                    $this->getHelper()->getFailureURL('checkout')
                )
                ->setReturnCancelUrl(
                    $this->getHelper()->getCancelURL('checkout')
                )
                //Billing
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
                )
                //Shipping
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
                )
                ->setLanguage(
                    $this->getHelper()->getLocale()
                );


        foreach ($this->getRecurringTransactionTypes() as $transactionType) {
            $genesis->request()->addTransactionType($transactionType);
        }

        try {
            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            // @codingStandardsIgnoreStart
            if (isset($responseObject->redirect_url)) {
                // @codingStandardsIgnoreEnd
                $this
                    ->getHelper()
                        ->getCheckoutSession()
                            ->setEmerchantPayCheckoutRedirectUrl(
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


            $productItemInfo = new Varien_Object;
            $productItemInfo->setPaymentType(
                Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_INITIAL
            );
            $productItemInfo->setPrice(
                $amount
            );

            $order = $profile->createOrder($productItemInfo);

            $order->setState(
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
            );

            $order->setStatus(
                Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW
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
                    true
                );
            $payment
                ->setIsTransactionClosed(
                    false
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
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
                );

            $order->save();
            $profile->addOrderRelation(
                $order->getId()
            );
            $order->save();
            $payment->save();

            $profile->setState(
                Mage_Sales_Model_Recurring_Profile::STATE_PENDING
            );
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
