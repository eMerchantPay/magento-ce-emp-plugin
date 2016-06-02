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
 * EMerchantPay Recurring Module Observer
 * Class EMerchantPay_Genesis_Model_Task_Recurring
 */
class EMerchantPay_Genesis_Model_Task_Recurring
{
    /**
     * @var EMerchantPay_Genesis_Helper_Data
     */
    protected $_helper;

    /**
     * Cron job Observer Method for Checkout PM
     * @param Mage_Cron_Model_Schedule $schedule
     * @return array
     */
    public function processCheckout(Mage_Cron_Model_Schedule $schedule)
    {
        return $this->run($schedule, 'checkout');
    }

    /**
     * Cron job Observer Method for Direct PM
     * @param Mage_Cron_Model_Schedule $schedule
     * @return array
     */
    public function processDirect(Mage_Cron_Model_Schedule $schedule)
    {
        return $this->run($schedule, 'direct');
    }

    /**
     * Cron job method to charge recurring profiles
     *
     * @param Mage_Cron_Model_Schedule $schedule
     * @param string $methodCode
     * @return array
     */
    protected function run(Mage_Cron_Model_Schedule $schedule, $methodCode)
    {
        $result = array();

        $vendorName = "emerchantpay";

        $methodCode = "{$vendorName}_{$methodCode}";

        $this->_helper = Mage::helper($vendorName);

        if (!$this->getHelper()->getIsMethodActive($methodCode)) {
            return false;
        }

        if (!$this->getHelper()->getConfigBoolValue($methodCode, 'recurring_enabled')) {
            return false;
        }

        $logFileName = $this->getHelper()->getConfigData($methodCode, 'cron_recurring_log_file');
        $isLogEnabled = !empty($logFileName);

        $msgCheckForProfilesToCharge =
            $this->getHelper()->__("Checking for Profiles to charge ...");

        $result[] = $msgCheckForProfilesToCharge;

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Method #' . $methodCode, null, $logFileName);
            Mage::log($msgCheckForProfilesToCharge, null, $logFileName);
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('read');

        $select = $adapter->select();
        $select
            ->from(
                $resource->getTableName('sales_recurring_profile')
            )
            ->where('method_code = :method_code')
            ->where('state = :state')
            ->where('updated_at <= :now')
            ->where('start_datetime <= :now')
            ->where('(
                      ((start_datetime >= updated_at) and (:now >= start_datetime))
                       or
                      ((start_datetime < updated_at) and :now >= CASE period_unit
                        WHEN "day" 			THEN DATE_ADD(updated_at, INTERVAL period_frequency DAY)
                        WHEN "week" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency WEEK)
                        WHEN "semi_month" 	THEN DATE_ADD(updated_at, INTERVAL (period_frequency * 2) WEEK)
                        WHEN "month" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency MONTH)
                        WHEN "year" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency YEAR)
                    END))');

        $binds = array(
            'method_code' =>
                $methodCode,
            'state'       =>
                Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE,
            'now'         =>
                $this->getHelper()->formatDateTimeToMySQLDateTime(
                    time()
                )
        );

        $chargedProfiles = 0;

        foreach ($adapter->fetchAll($select, $binds) as $profileArr) {
            if (!isset($profileArr['profile_id'])) {
                continue;
            }

            $profile = Mage::getModel('sales/recurring_profile')->load(
                $profileArr['profile_id']
            );

            $orders = $profile->getResource()->getChildOrderIds($profile);
            $countBillingCycles = count($orders);

            if ($profile->getInitAmount()) {
                $countBillingCycles--;
            }

            $msgChargingProfile =
                $this->getHelper()->__("Charging Recurring Profile #") .
                $profile->getReferenceId();

            $result[] = $msgChargingProfile;

            if ($isLogEnabled) {
                Mage::log($msgChargingProfile, null, $logFileName);
            }

            $mustSetUpdateDateToNextPeriod = (bool) $countBillingCycles > 0;
            $this->chargeRecurringProfile($methodCode, $profile, $mustSetUpdateDateToNextPeriod);
            $chargedProfiles++;
            $countBillingCycles++;

            if ($this->doCheckAndSuspendRecurringProfile($profile, $countBillingCycles)) {
                $msgChargingProfile =
                    $this->getHelper()->__("Billing Cycles reached. Suspending Recurring Profile #") .
                    $profile->getReferenceId();

                $result[] = $msgChargingProfile;

                if ($isLogEnabled) {
                    Mage::log($msgChargingProfile, null, $logFileName);
                }
            }
        }

        if ($chargedProfiles == 0) {
            $result[] = $this->getHelper()->__("No Profiles have been charged!");
        }

        return $result;
    }

    /**
     * Check and suspend Recurring Profile if Recurring Cycles reached
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param int $billingCycles
     * @return bool (true if profile suspended)
     */
    protected function doCheckAndSuspendRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, $billingCycles)
    {
        if (!empty($profile->getPeriodMaxCycles()) && $billingCycles >= $profile->getPeriodMaxCycles()) {
            $profile->setState(
                Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED
            );
            $profile->save();
        }

        return
            $profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED;
    }

    /**
     * @return EMerchantPay_Genesis_Helper_Data
     */
    protected function getHelper()
    {
        return $this->_helper;
    }

    /**
     * Creates a RecurringSale transaction to the Payment Gateway
     * @param string $methodCode
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param bool $mustSetUpdateDateToNextPeriod
     * @throws Exception
     * @throws Mage_Core_Exception
     */
    protected function chargeRecurringProfile(
        $methodCode,
        Mage_Payment_Model_Recurring_Profile $profile,
        $mustSetUpdateDateToNextPeriod
    ) {
        $logFileName = $this->getHelper()->getConfigData(
            $methodCode,
            'cron_recurring_log_file'
        );
        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__.'; Profile #'.$profile->getId(), null, $logFileName);
        }

        $this->getHelper()->initClient($methodCode);

        $initRecurringCaptureTransaction = $this->getProfileInitRecurringTransaction(
            $profile
        );

        if (!is_object($initRecurringCaptureTransaction) || !$initRecurringCaptureTransaction->getId()) {
            Mage::throwException(
                $this->getHelper()->__("Could not find Init Recurring Capture Transaction!")
            );
        }

        if (empty(\Genesis\Config::getToken())) {
            \Genesis\Config::setToken(
                $this->getHelper()->getGenesisPaymentTransactionToken(
                    $initRecurringCaptureTransaction
                )
            );
        }

        if (empty(\Genesis\Config::getToken())) {
            Mage::throwException(
                $this->getHelper()->__(
                    "Could not extract Terminal Token from Init Recurring Transaction"
                )
            );
        }

        $genesis = new \Genesis\Genesis("Financial\\Cards\\Recurring\\RecurringSale");

        $genesis
            ->request()
                ->setTransactionId(
                    $this->getHelper()->genTransactionId()
                )
                ->setReferenceId(
                    $initRecurringCaptureTransaction->getTxnId()
                )
                ->setUsage('Magento Recurring Transaction')
                ->setRemoteIp(
                    $this->getHelper()->getRemoteAddress()
                )
                ->setCurrency(
                    $profile->getCurrencyCode()
                )
                ->setAmount(
                    $profile->getTaxAmount() +
                    $profile->getBillingAmount() +
                    $profile->getShippingAmount()
                );

        $genesis->execute();

        $responseObject = $genesis->response()->getResponseObject();


        $isTransactionApproved =
            $responseObject->status == \Genesis\API\Constants\Transaction\States::APPROVED;

        $productItemInfo = new Varien_Object;
        $productItemInfo->setPaymentType(
            Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR
        );

        $productItemInfo->setPrice(
            $profile->getTaxAmount() +
            $profile->getBillingAmount()
        );

        $order = $profile->createOrder($productItemInfo);
        $order->setState(
            $isTransactionApproved
                ? Mage_Sales_Model_Order::STATE_PROCESSING
                : Mage_Sales_Model_Order::STATE_CANCELED
        );
        $order->setStatus(
            $isTransactionApproved
                ? Mage_Sales_Model_Order::STATE_PROCESSING
                : Mage_Sales_Model_Order::STATE_CANCELED
        );

        $trans_id = $responseObject->unique_id;

        $responseObject->terminal_token = $this->getHelper()->getGenesisPaymentTransactionToken(
            $initRecurringCaptureTransaction
        );

        $payment = $order->getPayment();
        $payment
            ->setTransactionId(
                $trans_id
            )
            ->setIsTransactionClosed(
                true
            )
            ->setIsTransactionPending(
                !$isTransactionApproved
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

        if ($isTransactionApproved) {
            $payment->registerCaptureNotification(
                $responseObject->amount
            );
        }

        $order->save();

        $profile->addOrderRelation(
            $order->getId()
        );
        $payment->save();

        if (!$isTransactionApproved) {
            $profile->setState(
                Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED
            );
        }

        $profile->save();

        $updatedAt = ($mustSetUpdateDateToNextPeriod ? null : time());

        $this->updateRecurringProfileDateToNextPeriod(
            $methodCode,
            $profile->getId(),
            $updatedAt
        );
    }

    /**
     * Prepares the Recurring Profile for the next recurring period
     * @param string $methodCode
     * @param int $profile_id
     * @param int|null $updatedAt
     * @return mixed
     */
    protected function updateRecurringProfileDateToNextPeriod($methodCode, $profile_id, $updatedAt = null)
    {
        $logFileName = $this->getHelper()->getConfigData(
            $methodCode,
            'cron_recurring_log_file'
        );

        $isLogEnabled = !empty($logFileName);

        if ($isLogEnabled) {
            Mage::log(__METHOD__ . '; Profile #' . $profile_id, null, $logFileName);
        }

        $_resource = Mage::getSingleton('core/resource');
        $sql = '
			UPDATE '.$_resource->getTableName('sales_recurring_profile').
            ($updatedAt
                ? ' SET updated_at = :updated_at '
                : ' SET updated_at = CASE period_unit
                        WHEN "day" 			THEN DATE_ADD(updated_at, INTERVAL period_frequency DAY)
                        WHEN "week" 		THEN DATE_ADD(updated_at, INTERVAL (period_frequency*7) DAY)
                        WHEN "semi_month" 	THEN DATE_ADD(updated_at, INTERVAL (period_frequency*14) DAY)
                        WHEN "month" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency MONTH)
                        WHEN "year" 		THEN DATE_ADD(updated_at, INTERVAL period_frequency YEAR)
                    END ') . '
            WHERE
                (profile_id = :pid)';

        $connection = $_resource->getConnection('core_write');
        $pdoStatement = $connection->prepare($sql);
        $pdoStatement->bindValue(':pid', $profile_id);
        if ($updatedAt) {
            $pdoStatement->bindValue(
                ':updated_at',
                $this->getHelper()->formatDateTimeToMySQLDateTime(
                    $updatedAt
                )
            );
        }
        return $pdoStatement->execute();
    }


    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @return Mage_Sales_Model_Order_Payment_Transaction|null
     */
    protected function getProfileInitRecurringTransaction($profile)
    {
        foreach ($profile->getChildOrderIds() as $orderId) {
            $order = Mage::getModel("sales/order")->load($orderId);

            if (is_object($order) && $order->getId() && is_object($order->getPayment())) {
                $captureTransaction = $order->getPayment()->lookupTransaction(
                    null,
                    Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE
                );

                $genesisTransactionType = $this->getHelper()->getGenesisPaymentTransactionType(
                    $captureTransaction
                );

                if ($this->getHelper()->getIsTransactionTypeInitRecurring($genesisTransactionType)) {
                    return $captureTransaction;
                }
            }
        }

        return null;
    }
}
