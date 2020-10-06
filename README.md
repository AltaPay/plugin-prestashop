# Altapay-Omni for Prestashop

## Supported versions
Prestashop 1.6.x and 1.7.x

## Changelog

- 3.2.0
  
    * Added support for for cart rules based on amount
    * Added support for free gift with cart rule 
  

- 3.1.1

    * Added support for Prestashop version 1.7.6.5 
    * Rebranding from Valitor to Altapay
  

- 3.1.0
  
    * Added support for gift-wrapping
  

* 3.0.0
  
    * Added support for Prestashop version 1.6.1.24 and 1.7.6.4
    * Added support for Klarna Payments (Klarna reintegration)
    * Added plugin disclaimer
    * Major refactoring for improving the source code quality
    * Added release payment functionality by
          * using release payment button from valitor actions panel
          * changing order status to canceled state
    * Added support for multiple cart rules
    * Added support for partial cart rules (selected products only)
    * Send shipping information including eco-tax
    * Order ID used as the one generated by PrestaShop
  

* 2.6.1
  
    * Fix for the total amount when coupons are used  
    

* 2.6.0
  
    * Auto capture triggered based on the selected order status (available in plugin settings)


* 2.5.0
  
    * Auto capture when the order status changes to Delivered
          * no additional capture for partial amount
    * Enhancements on the error messages
    * Small fixtures and code refactoring
    * Added support for PrestaShop version: 1.7.6.2


* 2.4.0
  
    * Added support for variable products
    * Improved the partial captures on orderlines


* 2.3.0
  
    * Added support for coupons
          * shipping in not included, as is not supported by the standard checkout
    * Fix partial captures and refunds when Klarna used
      

* 2.2.2
  
    * Added support for PrestaShop version: 1.7.6.1


* 2.2.1
  
    * Fix the price rule handling
    * Fix the path issue for the lock file
  

* 2.2.0
  
    * Added support for the latest PrestaShop version: 1.7.6
    * Code improvements - removed a bunch of warnings for the webserver
    * Fix - duplicate of the terminals on Save
    * Added support for coupons
    * Improved the unit price fetcher
    * Added meaningful messages at failed payment gateway connection


* 2.1.0
  
    * Improved the payment gateway connection details
    * Fix the issue with the autoloader
    * Fix multiple warnings from the webserver
  
    
* 2.0.0
  
    * SDK rebranding from Altapay to Valitor
    * Support for PrestaShop 1.7.5.2
  

* 1.9.0

    * Platform and plugin versioning information being sent to the payment gateway
    * Payment method icon displayed in the checkout


* 1.8.0
  
    * Added improvements according to the validator report
    * Fix the issue with displaying the payment method logo
  

* 1.7.0
  
    * Rebranding from Altapay to Valitor
    * Added support for 1.7 version, including backward compatibility for 1.6
    * Restrict IP functionality removed


* 1.6.2
  
    * Set the min supported version to 1.6.0.5
    * Set the max supported version to 1.6.1.23
    * PHP SDK reference updated


* 1.6.1
  
    * Fix issue with multiple orders and ViaBill
    * PHP SDK updated


* 1.6.0
  
    * PHP SDK updated


* 1.5.1
  
    * Added cart information to the payment view


* 1.5.0
  
    * Imported PHP Client Library via Composer

    