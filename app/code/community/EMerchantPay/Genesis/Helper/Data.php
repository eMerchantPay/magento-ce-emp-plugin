<?php

class EMerchantPay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Check whether Genesis is initialized and init if not
     *
     * @param string $model Name of the model, for which we query settings
	 *
	 * @return void
	 */
	public function initClient($model)
    {
		// Mitigate PHP Bug #52339, as Magento already registers their AutoLoader
		if (!class_exists('\Genesis\Genesis', false)) {
			include Mage::getBaseDir( 'lib' ) . DS . 'Genesis' . DS . 'vendor' . DS . 'autoload.php';

			\Genesis\GenesisConfig::setUsername( $this->getConfigData( $model, 'genesis_username' ) );
            \Genesis\GenesisConfig::setPassword( $this->getConfigData( $model, 'genesis_password' ) );

            if ('emerchantpay_direct' == $model) {
                \Genesis\GenesisConfig::setToken( $this->getConfigData( $model, 'genesis_token' ) );
            }

			\Genesis\GenesisConfig::setEnvironment( $this->getConfigData($model, 'genesis_environment') );
		}
	}

    /**
     * Get Module Configuration Key
     *
     * @param string $model Name of the Model
     * @param string $key   Configuration Key
     *
     * @return mixed The content of the requested key
     */
    public function getConfigData($model, $key)
    {
        return Mage::getStoreConfig( sprintf('payment/%s/%s', $model, $key) );
    }

	/**
	 * Get A Success URL
	 *
	 * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
	 *
	 * @return string
	 */
	public function getSuccessURL($model)
    {
		return Mage::getUrl( sprintf('emerchantpay/%s/success', $model), array( '_secure' => true ) );
	}

	/**
	 * Get A Failure URL
	 *
	 * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
	 *
	 * @return string
	 */
	public function getFailureURL($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/failure', $model), array( '_secure' => true ) );
	}

	/**
	 * Get A Cancel URL
	 *
	 * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
	 *
	 * @return string
	 */
	public function getCancelURL($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/cancel', $model), array( '_secure' => true ) );
	}

	/**
	 * Get A Notification URL
	 *
	 * @see Genesis API Documentation
     *
     * @param string $model Name of the Model (Checkout/Direct)
	 *
	 * @return string
	 */
	public function getNotifyURL($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/notify', $model), array( '_secure' => true ) );
	}

	/**
	 * Get a Redirect URL for the module
	 *
	 * @param string $model Name of the Model (Checkout/Direct)
	 *
	 * @return string
	 */
	public function getRedirectUrl($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/redirect', $model), array( '_secure' => true ) );
	}

	/**
	 * Generate Transaction Id based on the order id
	 * and salted to avoid duplication
     *
     * @param string|int $increment_id IncrementId of the Order
	 *
	 * @return string
	 */
	public function genTransactionId($increment_id = 0)
	{
		return sprintf('%s-%s', $increment_id, strtoupper(md5(microtime(true) . ':' . mt_rand())));
	}

    /**
     * During "Checkout" we don't know have a Token,
     * however its required at a latter stage, which
     * means we have to extract it from the payment
     * data. We save the token when we receive a
     * notification from Genesis, then we only have
     * to find the earliest payment_transaction
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     *
     * @return void
     */
    public function setTokenFromPaymentTransaction($payment)
    {
        $collection = Mage::getModel('sales/order_payment_transaction')->getCollection()
                          ->setOrderFilter($payment->getOrder())
                          ->setOrder('created_at', Varien_Data_Collection::SORT_ORDER_ASC)
                          ->setLimit(1);

        foreach ($collection as $payment_transaction) {
            $token = $payment_transaction->getTransactionAdditionalInfo('token');

            if (isset($token) && !empty($token)) {
                \Genesis\GenesisConfig::setToken( $token );
            }
        }
    }

	/**
	 * Get list of items in the order
	 *
	 * @see API parameter "Usage" or "Description"
	 *
	 * @param Mage_Sales_Model_Order_Payment $order
	 *
	 * @return string Formatted List of Items
	 */
	public function getItemList($order)
	{
		$productResult = array();

		foreach ($order->getAllItems() as $item) {
			/** @var $item Mage_Sales_Model_Quote_Item */
			$product = $item->getProduct();

			$productResult[$product->getSku()] = array(
				'sku'   => $product->getSku(),
				'name'  => $product->getName(),
				'qty'   => isset($productResult[$product->getSku()]['qty']) ? $productResult[$product->getSku()]['qty'] : 1,
			);
		}

		$description = '';

		foreach ($productResult as $product) {
			$description .= sprintf("%s (%s) x %d\r\n", $product['name'], $product['sku'], $product['qty']);
		}

		return $description;
	}

    /**
     * Redirect the visitor to the login page if
     * they are not logged in
     *
     * @param string $target Alternative target, if you don't want to redirect to login
     *
     * @return void
     */
    public function redirectIfNotLoggedIn($target = '')
    {
        if(!Mage::helper('customer')->isLoggedIn()){
            $login = empty($target) ? Mage::getUrl('customer/account/login', array( '_secure' => true )) : $target;

            Mage::app()->getFrontController()->getResponse()->setRedirect($login)->sendHeaders();

            exit(0);
        }
    }
} 