<?php

class EMerchantPay_Genesis_Block_Redirect_Direct extends Mage_Core_Block_Abstract
{
	public function _toHtml()
	{
        /** @var Mage_Core_Model_Session $target */
		$target = Mage::getSingleton('core/session')->getEmerchantPayDirectRedirectUrl();

		$form = new Varien_Data_Form();

		$form
			->setAction($target)
			->setId('emerchantpay_redirect_notification')
			->setName('emerchantpay_redirect_notification')
			->setMethod('POST')
			->setUseContainer(true);

		$button_id = sprintf('redirect_to_dest_%s', Mage::helper('core')->uniqHash());

		$submitButton = new Varien_Data_Form_Element_Submit(array(
			'value'    => $this->__('Click here, if you are not redirected within 10 seconds...'),
		));

		$submitButton->setId($button_id);

		$form->addElement($submitButton);

		$html = "<!DOCTYPE html>\n";

		$html.= '<html><head>';
		$html.= '<title>' . $this->__('Payment Redirect') . '</title>';
		$html.= '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">';
		$html.= '<style>html,body{margin:0;padding:0;width:100%;height:100%;display:table;}.wrapper{display:table-cell;vertical-align:middle;text-align:center;}.notice{padding:16px;}</style>';
		$html.= '</head><body>';
		$html.= '<div class="wrapper">';
		$html.= '<div class="notice"><p>' . $this->__("You will be redirected to our partner's gateway to process your payment.") . '</p></div>';
		$html.= '<div class="form">' . $form->toHtml() . '</div>';
		$html.= '</div>';
		$html.= '<script type="text/javascript">window.setTimeout(function() {document.getElementById("' . $button_id . '").click();}, 3000);</script>';
		$html.= '</body></html>';

		return $html;
	}
}