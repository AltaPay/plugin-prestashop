# AltaPay for PrestaShop
## Supported versions

PrestaShop 1.6.x, 1.7.x and 8.x

# Changelog

## [4.0.2]
### Added
- Add support for Checkout Design v2.

## [4.0.1]
### Fixed
- Triggered order update when saving missing transaction data.

## [4.0.0]
### Fixed
- Streamline and simplify management of order lines and amounts.
- Support for the new PayPal Integration.

## [3.9.3]
### Fixed
- Fix: Apple Pay success page not displaying in PrestaShop 8.2.

## [3.9.2]
### Fixed
- Automatically create templates for each active language for email translations.
- Use the customer's order language when generating the payment link.

## [3.9.1]
### Added
- Add support for generating multiple payment links.
### Fixed
- Fix: Missing translation support in payment templates.

## [3.9.0]
### Fixed
- Fix: Amount reserved upon payment link generation.

## [3.8.9]
### Fixed
- Fix: Order changes status from "Payment Received" to "Cancelled" due to duplicate cart.
- Fix: Display an error when the cart is updated after payment completion.

## [3.8.8]
### Fixed
- Fix: Late callback failure changes order status to "Cancelled."
- Show payment method name instead of payment type in the invoice and payment section for backorders.

## [3.8.7]
### Fixed
- Fix: Allow to generate payment link for all shops.
- Fix: When the order is captured due to status change, it captures the total reserved amount, not the order size.
- Enhance page speed by loading the media exclusively on the checkout page.
- Fix: Display the credit card form in the customer's language for payment links.
- Fix: Ensure payment link emails are sent in the customer language.

## [3.8.6]
### Fixed
- Fix: Terminal sorting by position in Prestashop 1.6.x.
- Fix: Klarna order lines for PrestaShop version 1.6.x.

## [3.8.5]
### Added
- Configure terminal logo automatically.

## [3.8.4]
### Added
- Add option to generate a payment link for AltaPay for orders placed using different payment methods.
### Fixed
- Fix: unit price issue in order lines for PrestaShop version 1.6.x.

## [3.8.3]
### Fixed
- Capture the payment only from the reserved amount on status update.
- Fixed the Smarty template issue when redirecting to the success page.

## [3.8.2]
### Fixed
- Load plugin javascript only on Checkout page.
- Gracefully handle & log errors in adding or updating PrestaShop Order Payment.

## [3.8.1]
### Added
- Generate a payment link to capture the additional amount after the order update.

## [3.8.0]
### Added
- Add configuration to support asynchronous processing of AltaPay payment callbacks.
### Fixed
- Fix: Display nonformatted amount in capture input.

## [3.7.8]
### Fixed
- Fix: Full amount is being captured instead of the updated order total amount after the order has been modified.

## [3.7.7]
### Added
- Sync missing order data from gateway.
### Fixed
- Fix: Duplicate ok callback triggers order status change.
- Change order status to cancelled if callback fail is received for an existing order.

## [3.7.6]
### Added
- Add configurations to enable/disable order status change upon Capture and Refund from AltaPay grid.

## [3.7.5]
### Added
- Add support for Trustly.
- Create order on fail callback if reserve amount is greater than zero.

## [3.7.4]
### Fixed
- Fix: Improve exception handling and error logging.
- Fix: Round off unit price in order line to 3 decimal digits.
- Fix: Do not change order status on capture when triggered by the order hook for status shipped or delivered.

## [3.7.3]
### Fixed
- Fix: Payment fail issue when multiple discounts are applied.
- Fix: Apple Pay payment mismatch issue with `thecheckout` module.
- Fix: Apple Pay terminal sorting issue.
 
## [3.7.2]
### Added
- Add terminal logo for SEPA

## [3.7.1]
### Added
- Add terminal logo for Twint
### Fixed
- The currency dropdown on the terminal configuration page shows duplicate values
- Same order status being set multiple times.
- Rare error on success page if ok & notification callbacks are received simultaneously. 

## [3.7.0]
### Added
- Add support for PrestaShop version 1.6.0.1

## [3.6.12]
### Added
- Module version update notifications in AltaPay module configuration & order detail pages.

## [3.6.11]
### Added
- Option to configure order status for authorized payments from back office.
### Fixed
- Error message not displaying when payment failed in PrestaShop 1.7

## [3.6.10]
### Fixed
- Klarna payments failing when cart promo discounts are applied.
- Remove file locking which prevents concurrent actions resulting in order callback failures.

## [3.6.9]
### Added
- Support for thirty bees.
### Fixed
- Order payment pending page for PrestaShop 1.6.x

## [3.6.8]
### Added
- Support for PrestaShop 8.x.
- Support for Open Banking (Using Finshark).

## [3.6.7]
### Fixed
- Do not create order for callback_open

## [3.6.6]
### Added
- Add terminal logo for Bancontact, Przelewy24 & Bank payments.
- Add new Klarna's main logo (pink).
- Add horizontal variation for MobilePay & Swish terminal logos.
- Resized the checkout terminal logos.

## [3.6.5] - 2023-10-10
### Fixed
- Sync gateway order status in admin panel orders grid. 

## [3.6.4] - 2023-10-03
### Fixed
- Exclude order saving for open status in Notification Callback.

## [3.6.3] - 2023-09-26
### Added
- Add styling for the payment redirect page.

## [3.6.2] - 2023-09-20
### Added
- Show AltaPay Order ID in orders grid in admin panel.

## [3.6.1] - 2023-08-30
### Fixed
- Fix: Duplicate terminal creation upon editing a terminal.

## [3.6.0] - 2023-08-16
### Fixed
- Fix: Multiple payments issue against the same order.

## [3.5.9] - 2023-08-09
### Added
- Add new design option with modern look for Credit Card form.
- Set the **checkout** design of the Credit Card form by default for new installations.

## [3.5.8] - 2023-07-18
### Added
- Make Apple Pay compatible with **The Checkout** (One Page Checkout module)
### Fixed
- Fix: Resetting payment status on late Ok or Notification Callback.

## [3.5.7] - 2023-07-05
### Added
- Support for checksum validation functionality.

## [3.5.6] - 2023-06-16
### Fixed
- Fix: Redirection issue with order confirmation

## [3.5.5] - 2023-06-13
### Fixed
- Fix: Make form page compatible with PrestaShop 1.7

## [3.5.4] - 2023-05-26
### Fixed
- Fix: Callback issue triggered by premature notification callback or callback_ok

## [3.5.3] - 2023-05-22
### Fixed
- Fix: the terminal display priority field values when editing in a multistore.

## [3.5.2] - 2023-05-09
### Fixed
- Updated 'nature' text field by removing default value, which previously prevented merchants from saving terminal data to the table.

## [3.5.1] - 2023-04-28
### Fixed
- Fix: Order is created before payment has been made.
- Supports API changes from 20230412

## [3.5.0] - 2023-04-19
### Added
- Add support for fraud detection

## [3.4.9] - 2023-02-21
### Added
- Add support for recurring payments

## [3.4.8] - 2023-02-15
### Added
- Support Apple Pay functionality

## [3.4.7] - 2023-02-15
### Added
- Add text field under terminal name for custom message

## [3.4.6] - 2023-01-09
### Added
- Add support for multistore

## [3.4.5] - 2023-01-09
### Added
- Support to export order reconciliation data via PrestaShop 'SQL Manager'.

## [3.4.4] - 2022-12-30
### Added
- Added support for verifycard functionality
### Fixed
- Fixed workflow issue due to the and/isolated package has been deprecated

## [3.4.3] - 2022-12-12
### Fixed
- Sanitize data for SQL query

## [3.4.2] - 2022-12-09
### Fixed
- Fixed order total mismatch between Prestashop and gateway in case multiple tabs are used for ordering

## [3.4.1] - 2022-10-26
### Added
- Support for payment reconciliation.

## [3.4.0] - 2022-09-05
### Added
- Support for PrestaShop version 1.7.8.7

## [3.3.9] - 2022-06-20
### Fixed
- Fix: Duplicate orders issue with iDEAL payments

## [3.3.8] - 2022-01-03
### Added
- Support for PrestaShop version 1.7.8.3

## [3.3.7] - 2022-01-03
### Added
- Enable possibility to synchronize terminals based on store country with a Button in Prestashop

## [3.3.6] - 2021-07-05
### Fixed
- Add configuration to enable/disable CVV field
- Support notification callback with different transaction statuses

## [3.3.5] - 2021-06-14
### Added
- Handle "failed" and "error" status as a failed order

## [3.3.4] - 2021-04-13
### Added
- Support for CVV/CVV Less card

## [3.3.3] - 2021-02-19
 
### Fixed
- Fix issue with cancel functionality
- Fix price mismatch issue in case of fixed discount using Klana payment method

## [3.3.2] - 2021-02-10
### Added
- Support for terminal sorting
 
### Fixed
- Fix issue with E-payment order statuses
- Fix terminal configuration saving issue

## [3.3.1] - 2020-01-27
### Added
- Support for payment captures full reservation after discount is applied from the backend
 
### Fixed
- Fix shipping order lines issue in full capture
- Fix total amount issue after cart change
- Fix discount issue in virtual product type
- Fix price mismatch issue
- Fix issue with catalog discount

## [3.3.0] - 2020-11-24
### Changed
- Modify licence file with MIT License

### Fixed
- Fix price mismatch issue in reservation call
- Fix issue with catalog discounts

## [3.2.0] - 2020-09-21
### Added
- Support for for cart rules based on amount
- Support for free gift with cart rule
- Support for PrestaShop version 1.7.6.5

### Changed
- Rebranding from Valitor to Altapay

## [3.1.0] - 2020-05-26
### Added
- Support for gift-wrapping

## [3.0.0] - 2020-05-20
### Added
- Support for PrestaShop version 1.6.1.24 and 1.7.6.4
- Support for Klarna Payments (Klarna reintegration)
- Plugin disclaimer
- Major refactoring for improving the source code quality
- Added release payment functionality by * using release payment button from valitor actions panel * changing order status to canceled state
- Support for multiple cart rules
- Support for partial cart rules (selected products only)
- Send shipping information including eco-tax
- Order ID used as the one generated by PrestaShop

## [2.6.1] - 2020-01-06
### Fixed
- Fix for the total amount when coupons are used

## [2.6.0] - 2020-01-03
### Added
- Auto capture triggered based on the selected order status (available in plugin settings)

## [2.5.0] - 2019-12-05
### Added
- Auto capture when the order status changes to Delivered * no additional capture for partial amount
- Enhancements on the error messages
- Added support for PrestaShop version: 1.7.6.2

### Fixed
- Small fixtures and code refactoring

## [2.4.0] - 2019-09-01
### Added
- Added support for variable products
- Improved the partial captures on orderlines

## [2.3.0] - 2019-10-08
### Added
- Added support for coupons * shipping in not included, as is not supported by the standard checkout

### Fixed
- Fix partial captures and refunds when Klarna used

## [2.2.2] - 2019-09-04
### Added
- Added support for PrestaShop version: 1.7.6.1

## [2.2.1] - 2020-07-31
### Fixed
- Fix the price rule handling
- Fix the path issue for the lock file

## [2.2.0]
### Added
- Added support for the latest PrestaShop version: 1.7.6
- Added support for coupons
- Added meaningful messages at failed payment gateway connection

### Changed
- Improved the unit price fetcher

### Removed
- Code improvements - removed a bunch of warnings for the webserver

### Fixed
- Fix duplicate of the terminals on Save

## [2.1.0]
### Added
- Fix the issue with the autoloader
- Fix multiple warnings from the webserver

### Changed
- Improved the payment gateway connection details

## [2.0.0]
### Added
- Support for PrestaShop 1.7.5.2

### Changed
- SDK rebranding from Altapay to Valitor

## [1.9.0]
### Added
- Platform and plugin versioning information being sent to the payment gateway
- Payment method icon displayed in the checkout

## [1.8.0]
### Added
- Added improvements according to the validator report

### Fixed
- Fix the issue with displaying the payment method logo

## [1.7.0]
### Added
- Added support for 1.7 version, including backward compatibility for 1.6
- Restrict IP functionality removed

### Changed
- Rebranding from Altapay to Valitor

## [1.6.2]
### Changed
- Set the min supported version to 1.6.0.5
- Set the max supported version to 1.6.1.23
- PHP SDK reference updated

## [1.6.1]
### Changed
- PHP SDK updated

### Fixed
- Fix issue with multiple orders and ViaBill

### Changed
- PHP SDK updated

## [1.6.0]
### Changed
- PHP SDK updated

## [1.5.1]
### Added
- Added cart information to the payment view

## [1.5.0]
### Added
- Imported PHP Client Library via Composer
