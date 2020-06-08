emerchantpay Gateway Module for Magento CE
=============================

This is a Payment Module for Magento Community Edition, that gives you the ability to process payments through emerchantpay's Payment Gateway - Genesis.

Requirements
------------

* Magento Community Edition > 1.7 (Tested up to: __1.9.3.1__)
* [GenesisPHP v1.18.3](https://github.com/GenesisGateway/genesis_php/releases/tag/1.18.3) - (Integrated in Module)
* PCI-certified server in order to use ```emerchantpay Direct```

*Note:* This module has been tested only with Magento __Community Edition__, it may not work
as intended with Magento __Enterprise Edition__

*Note:* If you are using Tokenization with the Checkout module, Guest Checkout must be disabled.

GenesisPHP Requirements
------------

* PHP version 5.5.9 or newer
* PHP Extensions:
    * [BCMath](https://php.net/bcmath)
    * [CURL](https://php.net/curl) (required, only if you use the curl network interface)
    * [Filter](https://php.net/filter)
    * [Hash](https://php.net/hash)
    * [XMLReader](https://php.net/xmlreader)
    * [XMLWriter](https://php.net/xmlwriter)

Installation (via Modman)
---------------------

* Install [ModMan]
* Navigate to the root of your Magento installation
* run ```modman init```
* and clone this repo ```modman clone --copy https://github.com/eMerchantPay/magento-ce-emp-plugin```
* Login inside the Admin Panel and go to ```System``` -> ```Configuration``` -> ```Payment Methods```
* If one of the Payment Methods ```emerchantpay Direct``` or ```emerchantpay Checkout``` is not yet available, 
  go to  ```System``` -> ```Cache Management``` and clear Magento Cache by clicking on ```Flush Magento Cache``` 
* Check ```Enable```, set the correct credentials, select your prefered payment method and click ```Save config```

Update emerchantpay extension via Modman
* Navigate to the root of your Magento installation
* run ```modman update --force magento-ce-emp-plugin```

Installation (manual)
---------------------

* Upload the contents of the folder (excluding ```README.md```) to the ```<root>``` folder of your Magento installation
* Login inside the __Admin Panel__ and go to ```System``` -> ```Configuration``` -> ```Payment Methods```
* If one of the Payment Methods ```emerchantpay Direct``` or ```emerchantpay Checkout``` is not yet available, 
  go to  ```System``` -> ```Cache Management``` and clear Magento Cache by clicking on ```Flush Magento Cache```
* Check ```Enable```, set the correct credentials, select your prefered payment method and click ```Save config```

Configure Magento over secured HTTPS Connection
---------------------
This configuration is needed in order to use the ```emerchantpay Direct``` Payment Method.

Steps:
* Ensure you have installed a valid __SSL Certificate__ on your __Web Server__ & you have configured your __Virtual Host__ correctly.
* Login to Magento Admin Panel
* Navigate to ```System``` -> ```Configuration``` -> ```General``` -> ```Web```
* Expand the __Secure__ panel and set ```Use Secure URLs in Frontend``` and ```Use Secure URLs in Admin``` to Yes
* Set your Secure ```Base URL``` and click ```Save Config```
* It is recommended to add a __Rewrite Rule__ from ```http``` to ```https``` or to configure a __Permanent Redirect__ to ```https``` in your virtual host

Supported Transactions
---------------------
* ```emerchantpay Direct``` Payment Method
	* __Authorize__
	* __Authorize (3D-Secure)__
	* __InitRecurringSale__
	* __InitRecurringSale (3D-Secure)__
	* __RecurringSale__
	* __Sale__
	* __Sale (3D-Secure)__

* ```emerchantpay Checkout``` Payment Method
  * __Alternative Payment Methods__
    * __P24__
    * __POLi__
    * __PPRO__
      * __eps__
      * __GiroPay__
      * __Mr.Cash__
      * __MyBank__
      * __Przelewy24__
      * __Qiwi__
      * __SafetyPay__
      * __TrustPay__
    * __SOFORT__
    * __Trustly Sale__
    * __PayPal Express__
  * __Credit Cards__
    * __Aura__
    * __Authorize__
    * __Authorize (3D-Secure)__
    * __Cabal__
    * __Cencosud__
    * __Elo__
    * __Naranja__
    * __Nativa__
    * __Sale__
    * __Sale (3D-Secure)__
    * __Tarjeta Shopping__
    * __Recurring__
      * __InitRecurringSale__
      * __InitRecurringSale (3D-Secure)__
      * __RecurringSale__
  * __Cash Payments__
    * __Baloto__
    * __Banamex__
    * __Banco de Occidente__
    * __Boleto__
    * __Efecty__
    * __OXXO__
    * __Pago Facil__
    * __Redpagos__
    * __Santander Cash__
  * __Crypto__
    * __BitPay__
  * __Sepa Direct Debit__
    * __SDD Sale__
  * __Online Banking Payments__
    * __Alipay__
    * __Banco do Brasil__
    * __Bancomer__
    * __Bradesco__
    * __Entercash__
    * __iDebit Payin__
    * __INPay__
    * __InstaDebit Payin__
    * __InstantTransfer__
    * __Itau__
    * __Multibanco__
    * __OnlineBanking__
    * __PayU__
    * __RapiPago__
    * __Santander__
    * __WeChat__
  * __Vouchers__
    * __CashU__
    * __PayByVoucher (Sale)__
    * __PaySafeCard__
  * __Electronic Wallets__
    * __eZeeWallet__
    * __Neteller__
    * __WebMoney__
    * __Zimpler__

_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[ModMan]: https://github.com/colinmollenhour/modman
[emerchantpay Payment Gateway - Magento Connect]: https://www.magentocommerce.com/magento-connect/catalog/product/view/id/31438/s/emerchantpay-payment-gateway/
[support]: mailto:tech-support@emerchantpay.net
