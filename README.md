Genesis client for Magento CE
=============================

This is a Payment Module for eMerchantPay that gives you the ability to process payments through eMerchantPay's Payment Gateway - Genesis.

Requirements
------------

* Magento Community Edition* > 1.7
* GenesisPHP 1.0

*Note: this module has been tested only with Magento Community Edition, it may not work
as intended with Magento Enterprise Edition

GenesisPHP Requirements
------------

* PHP version >= 5.3 (however since 5.3 is EoL, we recommend at least PHP v5.4)
* PHP with libxml
* PHP ext: cURL (optionally you can use StreamContext)
* Composer


Installation (auto)
---------------------

* Install [ModMan]
* Navigate to the root of your Magento installation
* run `modman init`
* and clone this repo `modman clone https://github.com/REPO_URL`
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