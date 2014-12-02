<?php

class EMerchantpay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Get Module Configuration Key
	 *
	 * @param $key string The key you want to retrieve
	 *
	 * @return mixed The content of the requested key
	 */
	public function getConfigVal($key)
	{
		return Mage::getStoreConfig( 'payment/emerchantpay_genesis/' . $key );
	}

	/**
	 * Generate Transaction Id
	 *
	 * @return string
	 */
	public function genTransactionId()
	{
		return strtoupper(md5(microtime(true) . ':' . mt_rand()));
	}

	/**
	 * Get list of items in the order
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