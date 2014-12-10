<?php

class EMerchantPay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Check whether Genesis is initialized and init if not
	 *
	 * @see EMerchantpay_Genesis_Helper_Data::bootstrap
	 * @todo Remove hard-coded module settings
	 *
	 * @return void
	 */
	public function initClient() {
		if (!class_exists('\Genesis\Genesis')) {
			include Mage::getBaseDir( 'lib' ) . DS . 'Genesis' . DS . 'vendor' . DS . 'autoload.php';

			\Genesis\GenesisConfig::setToken( $this->getConfigVal( 'genesis_token', 'emerchantpay_standard' ) );
			\Genesis\GenesisConfig::setUsername( $this->getConfigVal( 'genesis_username', 'emerchantpay_standard' ) );
			\Genesis\GenesisConfig::setPassword( $this->getConfigVal( 'genesis_password', 'emerchantpay_standard' ) );

			$environment = intval( $this->getConfigVal( 'genesis_environment', 'emerchantpay_standard' ) ) == 1 ? 'sandbox' : 'production';

			\Genesis\GenesisConfig::setEnvironment( $environment );
		}
	}

	/**
	 * Get A Success URL
	 *
	 * @see Genesis API Documentation
	 *
	 * @return string
	 */
	public function getSuccessURL($model) {
		return Mage::getUrl( sprintf('emerchantpay/%s/success', $model), array( '_secure' => true ) );
	}

	/**
	 * Get A Failure URL
	 *
	 * @see Genesis API Documentation
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
	 * @return string
	 */
	public function getNotifyURL($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/notify', $model), array( '_secure' => true ) );
	}

	/**
	 * Get a Redirect URL for the module
	 *
	 * @param $model
	 *
	 * @return string
	 */
	public function getRedirectUrl($model)
	{
		return Mage::getUrl( sprintf('emerchantpay/%s/redirect', $model), array( '_secure' => true ) );
	}

	/**
	 * Get Module Configuration Key
	 *
	 * @param $key string The key you want to retrieve
	 *
	 * @return mixed The content of the requested key
	 */
	public function getConfigVal($key, $code)
	{
		return Mage::getStoreConfig( 'payment/' . $code . '/' . $key );
	}

	/**
	 * Generate Transaction Id based on the order id
	 * and salted to avoid duplication
	 *
	 * @return string
	 */
	public function genTransactionId($order_id = 0)
	{
		return sprintf('%s-%s', $order_id, strtoupper(md5(microtime(true) . ':' . mt_rand())));
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
				'qty'   => isset($productResult[$product->getSku()]['qty']) ? $productResult[$product->getSku()]['qty'] + 1 : 1,
			);
		}

		$description = '';

		foreach ($productResult as $product) {
			$description .= sprintf("%s (%s) x %d\r\n", $product['name'], $product['sku'], $product['qty']);
		}

		return $description;
	}
} 