emerchantpay Gateway Module for Magento CE
=============================

This is a Payment Module for Magento Community Edition, that gives you the ability to process payments through emerchantpay's Payment Gateway - Genesis.

Requirements
------------

* Magento Community Edition > 1.7 (Tested up to: __1.9.3.1__)
* [GenesisPHP v1.18.4](https://github.com/GenesisGateway/genesis_php/releases/tag/1.18.4) - (Integrated in Module)
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
    * __Argencard__
    * __Aura__
    * __Authorize__
    * __Authorize (3D-Secure)__
    * __Baloto__
    * __Bancomer__
    * __Bancontact__
    * __Banco de Occidente__
    * __Banco do Brasil__
    * __BitPay__
    * __Boleto__
    * __Bradesco__
    * __Cabal__
    * __CashU__
    * __Cencosud__
    * __Davivienda__
    * __Efecty__
    * __Elo__
    * __eps__
    * __eZeeWallet__
    * __Fashioncheque__
    * __GiroPay__
    * __iDeal__
    * __iDebit__
    * __InstaDebit__
    * __InstantTransfer__
    * __InitRecurringSale__
    * __InitRecurringSale (3D-Secure)__
    * __Intersolve__
    * __Itau__
    * __Klarna__
    * __Multibanco__
    * __MyBank__
    * __Naranja__
    * __Nativa__
    * __Neosurf__
    * __Neteller__
    * __Online Banking__
    * __OXXO__
    * __P24__
    * __Pago Facil__
    * __PayPal Express__
    * __PaySafeCard__
    * __PayU__
    * __POLi__
    * __PPRO__
    * __PSE__
    * __Qiwi__
    * __RapiPago__
    * __Redpagos__
    * __SafetyPay__
    * __Sale__
    * __Sale (3D-Secure)__
    * __Santander__
    * __Santander Cash__
    * __Sepa Direct Debit__
    * __SOFORT__
    * __Tarjeta Shopping__
    * __TCS__
    * __Trustly__
    * __TrustPay__
    * __UPI__
    * __WebMoney__
    * __WebPay__
    * __WeChat__
    * __Zimpler__

_Note_: If you have trouble with your credentials or terminal configuration, get in touch with our [support] team

You're now ready to process payments through our gateway.

[ModMan]: https://github.com/colinmollenhour/modman
[emerchantpay Payment Gateway - Magento Connect]: https://www.magentocommerce.com/magento-connect/catalog/product/view/id/31438/s/emerchantpay-payment-gateway/
[support]: mailto:tech-support@emerchantpay.net
