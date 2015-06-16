<?php
/*
 * Copyright (C) 2015 eMerchantPay Ltd.
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
 * @author      eMerchantPay
 * @copyright   2015 eMerchantPay Ltd.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU General Public License, version 2 (GPL-2.0)
 */

class EMerchantPay_Genesis_Model_Checkout extends Mage_Payment_Model_Method_Abstract
{
    protected $_name = 'emerchantpay';

    protected $_code = 'emerchantpay_checkout';

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

    protected $_canUseForMultishipping  = true;
    protected $_canFetchTransactionInfo = true;
    protected $_canSaveCc               = false;

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

            $genesis
                ->request()
                    ->setTransactionId($order->getIncrementId())
                    ->setCurrency($order->getBaseCurrencyCode())
                    ->setAmount($amount)
                    ->setUsage(
                        $this->getHelper()->__('Magento Payment')
                    )
                    ->setDescription($this->getHelper()->getItemList($order))
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
                    ->setShippinCountry($shipping->getCountry())
                    ->setLanguage($this->getHelper()->getLocale());

            foreach ($this->getTransactionTypes() as $type) {
                $genesis->request()->addTransactionType($type);
            }

            $genesis->execute();

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setIsTransactionPending(true)
                ->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER)
                ->setSkipTransactionCreation(true);

            // Save the redirect url with our
            Mage::getSingleton('core/session')->setEmerchantPayCheckoutRedirectUrl(
                $genesis->response()->getResponseObject()->redirect_url
            );
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );

            return false;
        }

        return $this;
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

            /*
            $reference_id = '';

            $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                              ->setOrderFilter($payment->getOrder())
                              ->addPaymentIdFilter($payment->getId())
                              ->addTxnTypeFilter(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
            */

            /** @var Mage_Sales_Model_Order_Payment_Transaction $txn */
            /*
            foreach ($collection as $txn) {
                $txn->setOrderPaymentObject($payment);
                $reference_id = $txn->getTxnId();
            }
            */

            $authorize = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

            $reference_id = $authorize->getTxnId();

            $genesis = new \Genesis\Genesis('Financial\Capture');

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
                        $reference_id
                    )
                    ->setCurrency(
                        $payment->getOrder()->getBaseCurrencyCode()
                    )
                    ->setAmount(
                        $amount
                    );

            $genesis->execute();

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setParentTransactionId(
                    $reference_id
                )
                ->resetTransactionAdditionalInfo()
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $genesis->response()->getResponseObject()
                        )
                    ),
                    null
                )
                ->save();
        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $this->getHelper()->__($exception->getMessage())
            );

            return false;
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

            $this->getHelper()->setTokenByPaymentTransaction($payment);

            $reference_id = '';

            $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                              ->setOrderFilter($payment->getOrder())
                              ->addPaymentIdFilter($payment->getId())
                              ->addTxnTypeFilter(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);

            /** @var Mage_Sales_Model_Order_Payment_Transaction $txn */
            foreach ($collection as $txn) {
                $txn->setOrderPaymentObject($payment);
                $reference_id = $txn->getTxnId();
            }

            $genesis = new \Genesis\Genesis('Financial\Refund');

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
                    ->setReferenceId($reference_id)
                    ->setCurrency($payment->getOrder()->getBaseCurrencyCode())
                    ->setAmount($amount);

            $genesis->execute();

            $payment
                ->setTransactionId($genesis->response()->getResponseObject()->unique_id)
                ->setParentTransactionId($reference_id)
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $genesis->response()->getResponseObject()
                        )
                    ),
                    null
                )
                ->save();
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

            $reference_id = $payment->getAuthorizationTransaction()->getTxnId();

            $genesis = new \Genesis\Genesis('Financial\Void');

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId(
                            $payment->getOrder()->getIncrementId()
                        )
                    )
                    ->setRemoteIp($this->getHelper('core/http')->getRemoteAddr(false))
                    ->setReferenceId($reference_id);

            $genesis->execute();

            $payment
                ->setTransactionId($genesis->response()->getResponseObject()->unique_id)
                ->setParentTransactionId($reference_id)
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $genesis->response()->getResponseObject()
                        )
                    ),
                    null
                )
                ->save();

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

        $checkout_transaction = $transaction->getOrder()->getPayment()->lookupTransaction(
            null,
            Mage_Sales_Model_Order_Payment_Transaction::TYPE_ORDER
        );

        $reconcile = $this->reconcile($checkout_transaction->getTxnId());

        // Get the current details
        $transaction_details = $payment->getAdditionalInformation(
            Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS
        );

        // Try to extract transaction details from the Gateway response
        if ($reconcile->unique_id == $transactionId) {
            $transaction_details = $reconcile;
        }
        else {
            if ($reconcile->payment_transaction instanceof stdClass) {
                if ($reconcile->payment_transaction->unique_id == $transactionId) {
                    $transaction_details = $reconcile->payment_transaction;
                }
            }

            if ($reconcile->payment_transaction instanceof ArrayObject) {
                foreach ($reconcile->payment_transaction as $payment_transaction) {
                    if ($payment_transaction->unique_id == $transactionId) {
                        $transaction_details = $payment_transaction;
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
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                    $transaction_details
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
     * @param $unique_id
     *
     * @return EMerchantPay_Genesis_Model_Checkout
     *
     * @throws Mage_Core_Exception
     */
    public function reconcile($unique_id)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $genesis = new \Genesis\Genesis('WPF\Reconcile');

            $genesis->request()->setUniqueId($unique_id);

            $genesis->execute();

            return $genesis->response()->getResponseObject();
        }
        catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $exception->getMessage()
            );
        }

        return false;
    }

    /**
     * Process a notification for Authorize-type Transaction
     *
     * @param stdClass $checkout_transaction
     *
     * @return bool true/false based on successful/unsuccessful status
     */
    public function processAuthorizeNotification($checkout_transaction)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $payment_transaction = $checkout_transaction->payment_transaction;

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load($checkout_transaction->unique_id, 'txn_id');

            $transaction->setOrderPaymentObject(
                $transaction->getOrder()->getPayment()
            );

            $transaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                $this->getHelper()->getArrayFromGatewayResponse(
                    $checkout_transaction
                )
            );

            $transaction->save();

            $order = $transaction->getOrder();

            if ($order->getQuoteId()) {
                $payment = $order->getPayment();

                $payment->setTransactionId($payment_transaction->unique_id);

                $payment->setParentTransactionId($checkout_transaction->unique_id);

                $payment->setShouldCloseParentTransaction(true);

                $payment->resetTransactionAdditionalInfo();

                $payment->setIsTransactionPending(false);

                $payment->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $payment_transaction
                        )
                    ),
                    null
                );

                /*
                $payment->addTransaction(
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                    null,
                    true,
                    $payment_transaction->message
                );
                */

                //$payment->save();

                $payment->registerAuthorizationNotification($payment_transaction->amount, true);

                switch ($checkout_transaction->status) {
                    case \Genesis\API\Constants\Transaction\States::PENDING:
                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $order->setState(
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                                    $payment_transaction->message,
                                    false
                                )
                            ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::DECLINED:
                        $order->setState(
                                    Mage_Sales_Model_Order::STATE_HOLDED,
                                    Mage_Sales_Model_Order::STATE_HOLDED,
                                    $payment_transaction->message,
                                    true
                                )
                            ->save();
                        break;
                    default:
                        $order->save();
                        break;
                }

                return true;
            }
        } catch (Exception $exception) {
            Mage::logException($exception);
        }

        return false;
    }

    /**
     * Process Sale-type (Auth/Capture) Transaction
     *
     * @param $checkout_transaction
     *
     * @return bool true/false on successful/unsuccessful status
     */
    public function processCaptureNotification($checkout_transaction)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $payment_transaction = $checkout_transaction->payment_transaction;

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load($checkout_transaction->unique_id, 'txn_id');

            $transaction->setOrderPaymentObject(
                $transaction->getOrder()->getPayment()
            );

            $transaction->setAdditionalInformation(
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS,
                $this->getHelper()->getArrayFromGatewayResponse(
                    $checkout_transaction
                )
            );

            $transaction->save();

            $order = $transaction->getOrder();

            if ($order->getQuoteId()) {
                $payment = $order->getPayment();

                $payment->setTransactionId($payment_transaction->unique_id);

                $payment->setParentTransactionId($checkout_transaction->unique_id);

                $payment->setShouldCloseParentTransaction(true);

                $payment->resetTransactionAdditionalInfo();

                $payment->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $payment_transaction
                        )
                    ),
                    null
                );

                $payment->setIsTransactionPending(false);
                /*
                $payment->addTransaction(
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE,
                    null,
                    true,
                    $payment_transaction->message
                );
               */

                $payment->registerCaptureNotification($payment_transaction->amount, true);

                $payment->save();

                switch ($checkout_transaction->status) {
                    case \Genesis\API\Constants\Transaction\States::PENDING:
                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $order->setState(
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                                    Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                                    $payment_transaction->message,
                                    false
                                )
                               ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::DECLINED:
                        $order->setState(
                                    Mage_Sales_Model_Order::STATE_HOLDED,
                                    Mage_Sales_Model_Order::STATE_HOLDED,
                                    $payment_transaction->message,
                                    true
                                )
                              ->save();
                        break;
                    default:
                        $order->save();
                        break;
                }

                /*
                switch ($checkout_transaction->status) {
                    case \Genesis\API\Constants\Transaction\States::APPROVED:

                        $orderStatus = $this->getConfigData('order_status');

                        if (!$orderStatus || $order->getIsVirtual()) {
                            $orderStatus = $order->getConfig()
                                                 ->getStateDefaultStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                        }

                        $order->setState(
                            $orderStatus,
                            $orderStatus,
                            $payment_transaction->message,
                            true
                        )
                              ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::PENDING:
                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            $payment_transaction->message,
                            false
                        )
                              ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::DECLINED:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            $payment_transaction->message,
                            true
                        )
                              ->save();
                        break;
                    default:
                        $order->save();
                        break;
                }
                */

                return true;
            }
        } catch (Exception $exception) {
            Mage::logException($exception);
        }

        return false;
    }

    /**
     * Get the selected transaction types in array
     *
     * @return array
     */
    public function getTransactionTypes()
    {
        return array_filter(explode(',', $this->getConfigData('genesis_types')));
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
    private function getHelper($helper = '')
    {
        if (empty($helper)) {
            return Mage::helper($this->_name);
        } else {
            return Mage::helper($helper);
        }
    }
}