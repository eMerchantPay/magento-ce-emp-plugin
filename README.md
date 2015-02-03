Genesis client for Magento CE
=============================

This is a Payment Module for eMerchantPay that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* Magento Community Edition* > 1.7
* GenesisPHP 1.0.1

*Note: this module has been tested only with Magento Community Edition, it may not work
as intended with Magento Enterprise Edition

GenesisPHP Requirements
------------

* PHP >= 5.3 (built w/ libxml)
* PHP Extensions: cURL (optionally you can use Streams, but its not recommended on PHP < 5.6)
* Composer


Installation (auto)
---------------------

* Install [ModMan]
* Navigate to the root of your Magento installation
* run `modman init`
* and clone this repo `modman clone https://github.com/E-ComProcessing/genesis_php`
* Login inside the Admin Panel and go to System -> Configuration -> Payment Methods
* Check "Enable" and set the correct credentials, select your prefered payment method and click "Save config"

You're now ready to process payments through our gateway.


Installation (manual)
---------------------

* Copy the files to the root folder of your Magento installation
* Login inside the Admin Panel and go to System -> Configuration -> Payment Methods
* Check "Enable" and set the correct credentials, select your prefered payment method and click "Save config"

You're now ready to process payments through our gateway.

[ModMan]: https://github.com/colinmollenhour/modman