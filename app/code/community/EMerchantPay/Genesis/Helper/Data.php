<?php

class EMerchantpay_Genesis_Helper_Data extends Mage_Core_Helper_Abstract {
	public function getSuccessURL() {
		return Mage::getUrl( 'checkout/success', array( '_secure' => true ) );
	}

	public function getFailureURL()
	{
		return Mage::getUrl( 'checkout/failure', array( '_secure' => true ) );
	}

	public function getCancelURL()
	{
		return Mage::getUrl( 'emerchantpay/genesis/error', array( '_secure' => true ) );
	}

	public function getNotifyURL()
	{
		return Mage::getUrl( 'emerchantpay/genesis/nofify/', array( '_secure' => true ) );
	}

	public function getConfigVal($key)
	{
		return Mage::getStoreConfig( 'payment/emerchantpay_genesis/' . $key );
	}

	public function genTransactionId()
	{
		return strtoupper(md5(microtime(1)));
	}

	/**
	 * @param Mage_Sales_Model_Order_Payment $order
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