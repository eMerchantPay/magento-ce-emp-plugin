<?php
/*
 * Copyright (C) 2016 eMerchantPay Ltd.
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
 * @copyright   2016 eMerchantPay Ltd.
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
class EMerchantPay_Genesis_Model_Direct extends Mage_Payment_Model_Method_Cc implements Mage_Payment_Model_Recurring_Profile_MethodInterface
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
                    ->setRemoteIp($this->getHelper()->getRemoteAddress())
                    ->setUsage($this->getHelper()->getItemList($order))
                    ->setCurrency(
                        $order->getOrderCurrencyCode()
                    )
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
                    false
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

            $payment->save();

            if ($this->getGenesisResponse()->status == \Genesis\API\Constants\Transaction\States::DECLINED) {
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
     * Genesis Authorize Payment Method with 3D-Secure
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _authorize3d($payment, $amount)
    {
        Mage::log('Authorize 3D-Secure transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Authorize3D');

            $genesis
                ->request()
                    ->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
                    ->setRemoteIp($this->getHelper()->getRemoteAddress())
                    ->setUsage($this->getHelper()->getItemList($order))
                    ->setCurrency(
                        $order->getOrderCurrencyCode()
                    )
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

            $this->setGenesisResponse(
                $genesis->response()->getResponseObject()
            );

            $payment
                ->setTransactionId(
                    $this->getGenesisResponse()->unique_id
                )
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(true)
                ->setPreparedMessage('3D-Secure: Redirecting customer to a verification page.')
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $this->getGenesisResponse()
                            )
                    ),
                    null
                );

            $payment->save();

            if ($this->getGenesisResponse()->status == \Genesis\API\Constants\Transaction\States::DECLINED) {
                throw new \Genesis\Exceptions\ErrorAPI(
                    $this->getGenesisResponse()->message
                );
            }

            // Save the redirect url with our
            $this->getHelper()->getCheckoutSession()->setEmerchantPayDirectRedirectUrl(
                $this->getGenesisResponse()->redirect_url
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
                    ->setRemoteIp($this->getHelper()->getRemoteAddress())
                    ->setUsage($this->getHelper()->getItemList($order))
                    ->setCurrency(
                        $order->getOrderCurrencyCode()
                    )
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

            $this->setGenesisResponse(
                $genesis->response()->getResponseObject()
            );

            $payment
                ->setTransactionId(
                    $this->getGenesisResponse()->unique_id
                )
                ->setCurrencyCode(
                    $this->getGenesisResponse()->currency
                )
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setTransactionAdditionalInfo(
                    array(
                        Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS =>
                            $this->getHelper()->getArrayFromGatewayResponse(
                                $this->getGenesisResponse()
                            )
                    ),
                    null
                );

            $payment->save();

            if ($this->getGenesisResponse()->status == \Genesis\API\Constants\Transaction\States::DECLINED) {
                throw new \Genesis\Exceptions\ErrorAPI(
                    $this->getGenesisResponse()->message
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
     * Genesis Sale (Auth/Capture) Payment Method with 3D-Secure
     *
     * @param Varien_Object|Mage_Sales_Model_Order_Payment $payment
     * @param String $amount
     *
     * @return EMerchantPay_Genesis_Model_Direct
     */
    private function _sale3d($payment, $amount)
    {
        Mage::log('Sale 3D-Secure transaction for order #' . $payment->getOrder()->getIncrementId());

        try {
            $this->getHelper()->initClient($this->getCode());

            $order = $payment->getOrder();

            $billing  = $order->getBillingAddress();
            $shipping = $order->getShippingAddress();

            $genesis = new \Genesis\Genesis('Financial\Cards\Sale3D');

            $genesis
                ->request()
                    ->setTransactionId($this->getHelper()->genTransactionId($order->getIncrementId()))
                    ->setRemoteIp($this->getHelper()->getRemoteAddress())
                    ->setUsage($this->getHelper()->getItemList($order))
                    ->setCurrency(
                        $order->getOrderCurrencyCode()
                    )
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

            $this->setGenesisResponse(
                $genesis->response()->getResponseObject()
            );

            // Hold transaction creation
            $payment
                ->setTransactionId(
                    $this->getGenesisResponse()->unique_id
                )
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(true)
                ->setPreparedMessage(
                    $this->getHelper()->__('3D-Secure: Redirecting customer to a verification page.')
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

            $payment->save();

            if ($this->getGenesisResponse()->status == \Genesis\API\Constants\Transaction\States::DECLINED) {
                throw new \Genesis\Exceptions\ErrorAPI(
                    $this->getGenesisResponse()->message
                );
            }

            // Save the redirect url with our
            $this->getHelper()->getCheckoutSession()->setEmerchantPayDirectRedirectUrl(
                $this->getGenesisResponse()->redirect_url
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
                        $this->getHelper()->genTransactionId(
                            $payment->getOrder()->getIncrementId()
                        )
                    )
                    ->setRemoteIp(
                        $this->getHelper()->getRemoteAddress()
                    )
                    ->setReferenceId(
                        $reference_id
                    )
                    ->setCurrency(
                        $payment->getOrder()->getOrderCurrencyCode()
                    )
                    ->setAmount(
                        $amount
                    );

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    $responseObject->unique_id
                )
                ->setParentTransactionId(
                    $reference_id
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

            $capture = $payment->lookupTransaction(null, Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);

            $reference_id = $capture->getTxnId();

            $genesis = new \Genesis\Genesis('Financial\Refund');

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
                        $reference_id
                    )
                    ->setCurrency(
                        $payment->getOrder()->getOrderCurrencyCode()
                    )
                    ->setAmount($amount);

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    $responseObject->unique_id
                )
                ->setParentTransactionId(
                    $reference_id
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

            $transactions = $this->getHelper()->getTransactionFromPaymentObject($payment, array(
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH,
                Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
            ));

            $reference_id = $transactions ? reset($transactions)->getTxnId() : null;

            $genesis = new \Genesis\Genesis('Financial\Void');

            $genesis
                ->request()
                    ->setTransactionId(
                        $this->getHelper()->genTransactionId($payment->getOrder()->getIncrementId())
                    )
                    ->setRemoteIp(
                        $this->getHelper()->getRemoteAddress()
                    )
                    ->setReferenceId(
                        $reference_id
                    );

            $genesis->execute();

            $responseObject = $genesis->response()->getResponseObject();

            $payment
                ->setTransactionId(
                    $responseObject->unique_id
                )
                ->setParentTransactionId(
                    $reference_id
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
     * Handle an incoming Genesis notification
     *
     * @param stdClass $reconcile
     * @return bool
     */
    public function processNotification($reconcile)
    {
        try {
            $this->getHelper()->initClient($this->getCode());

            /** @var Mage_Sales_Model_Order_Payment_Transaction $transaction */
            $transaction = Mage::getModel('sales/order_payment_transaction')->load(
                $reconcile->unique_id,
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

                switch ($reconcile->transaction_type) {
                    case \Genesis\API\Constants\Transaction\Types::AUTHORIZE:
                    case \Genesis\API\Constants\Transaction\Types::AUTHORIZE_3D:
                        $payment->registerAuthorizationNotification($reconcile->amount);
                        break;
                    case \Genesis\API\Constants\Transaction\Types::SALE:
                    case \Genesis\API\Constants\Transaction\Types::SALE_3D:
                    case \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE:
                    case \Genesis\API\Constants\Transaction\Types::INIT_RECURRING_SALE_3D:
                        $payment->setShouldCloseParentTransaction(true);
                        $payment->registerCaptureNotification($reconcile->amount);
                        break;
                    default:
                        break;
                }

                if ($this->getHelper()->getIsTransactionTypeInitRecurring($reconcile->transaction_type)) {
                    $recurringProfile = Mage::getModel('sales/recurring_profile')->load(
                        $reconcile->unique_id,
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
        if ($this->is3dEnabled()) {
            return $this->getHelper()->getRedirectUrl('direct');
        }

        return false;
    }

    /**
     * Check whether we're doing 3D transactions,
     * based on the module configuration
     *
     * @return bool
     */
    private function is3dEnabled()
    {
        return
            $this->getHelper()->getIsTransaction3dSecure(
                $this->getConfigData('genesis_type')
            );
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

        $transactionType = $this->getConfigData('recurring_transaction_type');
        $transactionClassName = $this->getHelper()->getGenesisTransactionClassName(
            $transactionType
        );

        $genesis = new \Genesis\Genesis("Financial\\Cards\\Recurring\\{$transactionClassName}");

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

            if (isset($responseObject->redirect_url)) {
                $this->getHelper()
                    ->getCheckoutSession()
                        ->setEmerchantPayCheckoutRedirectUrl(
                            $responseObject->redirect_url
                        );
            }

            $profile->setReferenceId(
                $responseObject->unique_id
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
                            $responseObject->unique_id
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
