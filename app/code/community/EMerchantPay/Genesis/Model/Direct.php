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
    protected $_isGateway         = true;
    protected $_canAuthorize      = true;
    protected $_canCapture        = true;
    protected $_canCapturePartial = true;
    protected $_canRefund         = true;
    protected $_canVoid           = true;
    protected $_canUseInternal    = true;
    protected $_canUseCheckout    = true;

    protected $_canFetchTransactionInfo = true;
    protected $_canUseForMultishipping  = false;
    protected $_canSaveCc               = false;

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

        switch ($this->getConfigData('genesis_type')) {
            default:
            case \Genesis\API\Constants\Transaction\Types::AUTHORIZE:
            case \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE;
                break;
            case \Genesis\API\Constants\Transaction\Types::SALE:
            case \Genesis\API\Constants\Transaction\Types::SALE_3D:
                return Mage_Payment_Model_Method_Abstract::ACTION_AUTHORIZE_CAPTURE;
                break;
        }
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
        if ($this->is3dEnabled()) {
            return $this->_authorize3d($payment, $amount);
        } else {
            return $this->_authorize($payment, $amount);
        }
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
            return $this->_capture($payment, $amount);
        } else {
            if ($this->is3dEnabled()) {
                return $this->_sale3d($payment, $amount);
            } else {
                return $this->_sale($payment, $amount);
            }
        }
    }

    /**
     * Genesis Authorize Payment Method
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _authorize($payment, $amount)
    {
        Mage::log('Authorize transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order $order */
            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Authorize');

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

            $payment
                ->setTransactionId($genesis->response()->getResponseObject()->unique_id)
                ->setIsTransactionClosed(true)
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
        }

        return $this;
    }

    /**
     * Genesis Authorize Payment Method with 3D-Secure
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _authorize3d($payment, $amount)
    {
        Mage::log('Authorize 3D-Secure transaction for Order#' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Authorize3D');

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
                    ->setReturnFailureUrl($this->getHelper()->getFailureURL('direct'));

            $genesis->execute();

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setIsTransactionPending(true)
                ->setSkipTransactionCreation(true)
                ->setPreparedMessage('3D-Secure: Init.');

            // Save the redirect url with our
            $this->getHelper()->getCheckoutSession()->setEmerchantPayDirectRedirectUrl(
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
     * Genesis Sale (Auth/Capture) Payment Method
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _sale($payment, $amount)
    {
        Mage::log('Sale transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Sale');

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

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setCurrencyCode(
                    $genesis->response()->getResponseObject()->currency
                )
                ->setIsTransactionClosed(true)
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $genesis->response()->getResponseObject()
                        )
                    ),
                    null
                );

        } catch (Exception $exception) {
            Mage::logException($exception);

            Mage::throwException(
                $exception->getMessage()
            );
        }

        return $this;
    }

    /**
     * Genesis Sale (Auth/Capture) Payment Method with 3D-Secure
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _sale3d($payment, $amount)
    {
        Mage::log('Sale 3D-Secure transaction for Order#' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Sale3D');

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

            // Hold transaction creation
            $payment
                ->setIsTransactionPending(true)
                ->setSkipTransactionCreation(true)
                ->setPreparedMessage($this->getHelper()->__('3D-Secure: Init.'));

            // Save the redirect url with our
            Mage::getSingleton('core/session')->setEmerchantPayDirectRedirectUrl(
                strval($genesis->response()->getResponseObject()->redirect_url)
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
     * Capture a successful auth transaction
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param float $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     *
     * @throws Mage_Core_Exception
     */
    private function _capture($payment, $amount)
    {
        Mage::log('Capture transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $authorize = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);

            $reference_id = $authorize->getTxnId();

            $genesis = new \Genesis\Genesis('Financial\Capture');

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId())
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

            $payment->setTransactionId(
                        $genesis->response()->getResponseObject()->unique_id
                    )
                    ->setParentTransactionId(
                        $payment->getCcTransId()
                    )
                    ->setIsTransactionClosed(true)
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

            $reference_trx = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);

            $reference_id = $reference_trx->getTxnId();

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
                    ->setReferenceId(
                        $reference_id
                    )
                    ->setCurrency(
                        $payment->getOrder()->getBaseCurrencyCode()
                    )
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
                );
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

            $reference_id = $payment->getAuthorizationTransaction()->getTxnId();

            $genesis = new \Genesis\Genesis('Financial\Void');

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId())
                    )
                    ->setRemoteIp(
                        $this->getHelper('core/http')->getRemoteAddr(false)
                    )
                    ->setReferenceId(
                        $reference_id
                    );

            $genesis->execute();

            $payment
                ->setTransactionId(
                    $genesis->response()->getResponseObject()->unique_id
                )
                ->setParentTransactionId(
                    $reference_id
                )
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $genesis->response()->getResponseObject()
                        )
                    ),
                    null
                );
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
                Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
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
     * @param $unique_id
     * @return mixed
     */
    public function reconcile($unique_id)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            $genesis = new \Genesis\Genesis('NonFinancial\Reconcile\Transaction');

            $genesis->request()->setUniqueId($unique_id);

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

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load($reconcile->unique_id, 'txn_id');

            $order = $transaction->getOrder();

            if ($order->getQuoteId()) {
                $payment = $order->getPayment();

                $payment->setTransactionId($reconcile->unique_id);

                $payment->setIsTransactionPending(false);

                $payment->resetTransactionAdditionalInfo();

                $payment->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $reconcile
                        )
                    ),
                    null
                );

                $payment->registerAuthorizationNotification($reconcile->amount, true);

                switch ($reconcile->status) {
                    case \Genesis\API\Constants\Transaction\States::PENDING:
                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            $reconcile->message,
                            false
                        )
                              ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::DECLINED:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            $reconcile->message,
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
     * @param $reconcile
     *
     * @return bool true/false on successful/unsuccessful status
     */
    public function processCaptureNotification($reconcile)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load($reconcile->unique_id, 'txn_id');

            $order = $transaction->getOrder();

            if ($order->getQuoteId()) {
                $payment = $order->getPayment();

                $payment->setTransactionId($reconcile->unique_id);

                $payment->resetTransactionAdditionalInfo();

                $payment->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS => $this->getHelper()->getArrayFromGatewayResponse(
                            $reconcile
                        )
                    ),
                    null
                );

                $payment->setIsTransactionPending(false);

                $payment->registerCaptureNotification($reconcile->amount, true);

                $payment->save();

                switch ($reconcile->status) {
                    case \Genesis\API\Constants\Transaction\States::PENDING:
                    case \Genesis\API\Constants\Transaction\States::PENDING_ASYNC:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW,
                            $reconcile->message,
                            false
                        )
                              ->save();
                        break;
                    case \Genesis\API\Constants\Transaction\States::DECLINED:
                        $order->setState(
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            Mage_Sales_Model_Order::STATE_HOLDED,
                            $reconcile->message,
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
        if ($this->is3dEnabled()) {
            return $this->getHelper()->getRedirectUrl('direct');
        }

        return false;
    }

    /**
     * Check whether we're doing 3D transactions,
     * based on the module configuration
     *
     * TODO: add support for "potential" synchronous 3d
     *
     * @return bool
     */
    private function is3dEnabled()
    {
        $this->getHelper()->initLibrary();

        switch ($this->getConfigData('genesis_type')) {
            default:
                return false;
                break;
            case \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D:
            case \Genesis\API\Constants\Transaction\Types::SALE_3D:
                return true;
                break;
        }
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
            return Mage::helper('emerchantpay');
        } else {
            return Mage::helper($helper);
        }
    }
}