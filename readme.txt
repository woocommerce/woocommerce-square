=== WooCommerce Square ===
Contributors: woocommerce, automattic
Tags: credit card, square, woocommerce, inventory sync
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 4.8.2
License: GPL-3.0-or-later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Securely accept payments, synchronize sales, and seamlessly manage inventory and product data between WooCommerce and Square POS.

== Description ==

This plugin allows you to securely accept payments, synchronize sales, and seamlessly manage inventory and product data between WooCommerce and Square POS.

The Square plugin is PCI and SAQ A-level compliant.

= Accept payments anywhere, anytime =

- The Square plugin extends WooCommerce to allow you to accept payments via Square - including support for [Apple Pay®](https://www.apple.com/apple-pay/), [Google Pay](https://www.google.com/payments/solutions/), [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) and [WooCommerce Pre-Orders](https://woocommerce.com/products/woocommerce-pre-orders/).
- Recurring customers can save payment methods and use them at checkout.
- Customize payment forms with automatic formatting, mobile-friendly options, and retina card icons.

= Manage your business all in one place =

Sync your product and inventory information from WooCommerce to Square, or from Square to WooCommerce — set everything up once, and whenever you make a sale, your inventory automatically updates on both platforms.

- If you sell mainly online, set WooCommerce as your system of record so WooCommerce pushes product name, inventory, prices, categories, and images to Square.
- If you sell in multiple locations and online, set Square as your system of record so Square pushes product name, inventory, prices, categories, and images to WooCommerce.

== Installation ==

You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/woocommerce-square/developers/).

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To
automatically install WooCommerce Square, log in to your WordPress dashboard, navigate to the Plugins menu, and click **Add New**.

In the search field type "WooCommerce Square" and click **Search Plugins**. Once you've found our plugin you can install it by clicking **Install Now**, as well as view details about it such as the point release, rating, and description.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

= Note =
If running PHP 8.2+, you may see some deprecation notices being logged. These notices are known and do not impact any plugin functionality.

== Frequently Asked Questions ==

= Does this require an SSL certificate? =

Yes! An SSL certificate must be installed on your site to use Square.

= Where can I find documentation? =

For help setting up and configuring the plugin, please refer to our [user guide](https://woo.com/document/woocommerce-square/).

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the [Plugin Forum](https://wordpress.org/support/plugin/woocommerce-square/).

== Screenshots ==

1. The main plugin settings.
2. The payment gateway settings.

== Changelog ==

= 4.8.2 - 2024-10-14 =
* Add - Title, Description, and Gateway ID props to the express payment method.
* Dev - Bump WooCommerce "tested up to" version 9.4.
* Dev - Bump WooCommerce minimum supported version to 9.2.
* Dev - Bump WordPress minimum supported version to 6.5.

= 4.8.1 - 2024-09-23 =
* Fix - Confirmation popup no longer appears when saving the Square settings.
* Fix - Connection settings now persist previous connection when toggling between Production and Sandbox.
* Fix - Prevent gift card image from being forced upon a site.
* Fix - Update URL linking to Square Developer dashboard in sandbox settings.
* Dev - Bump WooCommerce "tested up to" version 9.3.
* Dev - Bump WooCommerce minimum supported version to 9.1.

= 4.8.0 - 2024-09-16 =
* Add - Support for the WooCommerce Product Block Editor.
* Fix - Address a potential infinite loop issue with the `pull_inventory` step when running a manual synchronization.
* Fix - Cancelling Google Pay on checkout shows validation errors.
* Fix - Missing gift card order item meta during re-order.
* Fix - Ensure we don't hardcode the database prefix in queries we run.
* Fix - Replace the use of deprecated hook `wcs_renewal_order_meta` with `wc_subscriptions_renewal_order_data`.
* Update - Change the business location button text based on the location count.
* Dev - Bump WooCommerce "tested up to" version 9.2.
* Dev - Bump WooCommerce minimum supported version to 9.0.

= 4.7.3 - 2024-08-19 =
* Fix - Inconsistency in the height of Express Payment Button and compliance with the new Woo Express Payment Method Styling API.
* Fix - Ensure the "Uncaught TypeError" JavaScript console error does not occur for out-of-stock products.
* Fix - Ensure compatibility with WooPayments extension.
* Dev - Bump WooCommerce "tested up to" version 9.1.
* Dev - Bump WooCommerce minimum supported version to 8.9.
* Dev - Update NPM packages and node version to v20 to modernize developer experience.

= 4.7.2 - 2024-07-29 =
* Fix - Check if dependencies are loaded before showing onboarding process.
* Fix - Ensure the product category syncs properly between the Square and WooCommerce store.
* Dev - Fix QIT E2E tests and add support for a few new test types.

= 4.7.1 - 2024-07-22 =
* Fix - Remove double encoding from the redirect_url param in the oauth connect url.
* Dev - Bump WordPress "tested up to" version 6.6.

= 4.7.0 - 2024-06-27 =
* Add - New Merchant Onboarding experience with a new wizard flow & settings pages.
* Add - Support for WooCommerce Product Blocks.
* Dev - Bump WooCommerce "tested up to" version 9.0.
* Dev - Bump WooCommerce minimum supported version to 8.8.
* Dev - Bump WordPress minimum supported version to 6.4.

= 4.6.3 - 2024-06-17 =
* Add - ESLint GitHub Action workflow to enforce ESLint rules on pull requests.
* Dev - Bump Square PHP SDK version from `29.0.0.20230720` to `35.1.0.20240320`.
* Dev - Improved codebase by addressing PHPCS errors.
* Dev - Improved codebase by resolving issues reported by ESLint.
* Dev - Address QIT PHPStan test errors.
* Dev - Address QIT Security test errors.
* Fix - A fatal error could occur when running incompatible versions of WooCommerce.
* Fix - Apple Pay button is now available on Cart and Checkout Block pages.
* Fix - Ensure that the 'Sync stock from Square' and 'Sync inventory' links work properly on the edit product screen.
* Fix - Prevent cancelled Digital Wallet payments from blocking the Checkout form.

= 4.6.2 - 2024-05-23 =
* Dev - Bump WooCommerce "tested up to" version 8.9.
* Dev - Bump WooCommerce minimum supported version to 8.7.
* Fix - Update the Apple Pay domain verification file.

= 4.6.1 - 2024-04-10 =
* Fix - Problem with connection to Square warning.
* Update - Refreshed plugin marketing copy in readme file.
* Update - Replace the middleware URL from `connect.woocommerce.com` to `api.woocommerce.com/integrations`.

= 4.6.0 - 2024-03-27 =
* Add - Support for buying a Square Gift Card using Cash App Pay.
* Add - Support for splitting payments between a Square Gift Card and Cash App Pay.
* Add - Support for the “Charge” and “Authorization” transaction types in the Cash App Pay payment method.
* Dev - Declare compatibility with PHP 8.3.
* Dev - Bump WooCommerce "tested up to" version 8.7.
* Dev - Bump WooCommerce minimum supported version to 8.5
* Dev - Bump WordPress "tested up to" version 6.5.
* Fix - Ensure a Gift Card is created when the Credit Card transaction type is "Authorization," and "Charge Virtual-Only Orders" is enabled.
* Fix - npm ERR! Missing script: "test:e2e".
* Fix - Issue with Square payment gateway being shown for unsupported currencies.

= 4.5.1 - 2024-02-27 =
* Fix - Address the repetitive creation of `wc_square_init_payment_token_migration` actions in the payment token migration process.
* Dev - Bump WooCommerce "tested up to" version 8.6.
* Dev - Bump WooCommerce minimum supported version to 8.4.

= 4.5.0 - 2024-01-31 =
* Add - Support for the Cash App Pay payment method.
* Dev - Added end-to-end (E2E) tests for the Cash App Pay payment method.
* Dev - Bump WooCommerce "tested up to" version 8.5.
* Dev - Bump WooCommerce minimum supported version to 8.3.
* Dev - Bump WordPress minimum supported version to 6.3.
* Fix - Issue with syncing products that have a description more than 4096 characters.

= 4.4.1 - 2024-01-18 =
* Fix - Resolved the issue of double-counted inventory drops when WooPayments is used as the payment processor.
* Dev - Bump WooCommerce "tested up to" version 8.4.
* Dev - Bump WooCommerce minimum supported version to 8.2.
* Dev - Update WordPress "tested up to" version to 6.4.0.
* Dev - Ensure that all end-to-end tests pass.
* Tweak - Updated placement of Express payment buttons (e.g., Apple Pay, Google Pay) on cart and product pages to align with WooCommerce Express payment button standards.
* Remove - Action Scheduler dependency removed as it now comes bundled as part of WooCommerce core.

= 4.4.0 - 2023-11-30 =
* Fix - Fatal error caused by Digital Wallets when multiple shipping packages exist in a cart.
* Fix - Digital Wallets not rendering on Single Product and Cart pages.
* Fix - Disable Gift Card functionality with Pre-Orders "Charge Upon Release" products.
* Fix - Import value of "Sync with Square" field if it is not set in the CSV.
* Fix - Card name in transaction order notes when order is placed through Block Checkout.
* Dev - Bump WooCommerce "tested up to" version 8.3.
* Dev - Bump WooCommerce minimum supported version to 8.1.
* Dev - Bump WordPress "tested up to" version 6.4.
* Dev - Bump WordPress minimum supported version to 6.2.
* Dev - Add Playwright e2e coverage for Cart Block and Checkout Block.
* Tweak - Admin settings colour to match admin theme colour scheme.

= 4.3.1 - 2023-11-06 =
* Fix - Fatal error caused when the Action Scheduler API method `as_has_scheduled_action` is used for migrating payment tokens.
* Fix - Missing payment token and customer ID in subscription orders when HPOS is enabled.
* Fix - Issue with changing payment method for subscriptions when HPOS is enabled.
* Fix - Fatal error when Digital Wallet is enabled and product quantity is 0 or price field is unset.
* Add - Support for submitting custom required fields data via Digital Wallets based checkout.

= 4.3.0 - 2023-10-25 =
* Add - Support for Digital Wallets on Block-based Cart and Checkout pages.
* Fix - Issue with product import and sync not updating product title and description fields.
* Dev - Add compatibility support for PHP 8.2.
* Dev - Bump WooCommerce "tested up to" version 8.1.
* Dev - Bump WooCommerce minimum supported version to 7.9.
* Dev - Bump woocommerce-sniffs to 1.0.0.

= 4.2.2 - 2023-10-11 =
* Dev - Update PHPCS and PHPCompatibility GitHub Actions.
* Tweak - Timing of the Apple Pay domain registration warning message.
* Dev - Bump WooCommerce "tested up to" version 8.1.
* Dev - Bump WooCommerce minimum supported version to 7.9.

= 4.2.1 - 2023-09-18 =
* Fix - Inventory tracking logic in the Square product import.
* Tweak - Remove redundant code.
* Dev - Bump WooCommerce "tested up to" version 8.0.
* Dev - Bump WooCommerce minimum supported version to 7.8.
* Dev - Bump WordPress "tested up to" version 6.3.
* Dev - Bump WordPress minimum supported version to 6.1.
* Dev - Update JS docblock to indicate moving away from WooCommerce Checkout Block payment integration to Web Payments SDK.
* Dev - Resolve PHPCS errors.

= 4.2.0 - 2023-08-22 =
* Add - Ability to purchase a new gift card.
* Add - Ability to add funds to an existing gift card.
* Add - Capability to refund a Gift Card order.
* Add - Retry mechanism for RATE LIMIT errors.
* Changed: Removed the gift card beta label.
* Dev - Bump WordPress minimum supported version from 5.8 to 6.0.
* Dev - Bump WooCommerce minimum supported version from 6.8 to 7.7.
* Dev - Bump WooCommerce "tested up to" version from 7.8 to 7.9.
* Dev - Bump Square PHP SDK version from 24.0.0.20221116 to 29.0.0.20230720.
* Fix - Allow guest customer to update billing address for manual order if country is not set.
* Fix - Console error when checkout is performed with empty credit card fields.
* Fix - Block Checkout using saved cards.
* Fix - Auto-disconnection issue due to salt rotation.
* Fix - Issue with displaying limited saved payment methods due to posts_per_page setting.

= 4.1.0 - 2023-07-31 =
* Add - Support for inventory sync of individual products and variations.
* Add - Inventory sync improvements.
* Fix - Product Add-Ons compatibility with total on single product page.
* Fix - Updated implementation logic of functions to better sanitize input data.
* Tweak - Improve Square credit card payment form initialization on checkout page.

= 4.0.0 - 2023-07-05 =
* Add - Store Owner details in the `Additional Content` field to the `Sync Complete` and `Access Token` email notifications.
* Dev - Project maintenance automation via GitHub Actions.
* Dev - Refactored logic for how HTML description on product import and sync are handled.
* Dev - Remove WooCommerce copyright from source code.
* Dev - Removed redundant `WP_Job_Batch_Handler` code.
* Dev - Resolve PHP warning caused due to running foreach on `null`.
* Dev - `wc_square_enable_html_description` filter to toggle support for HTML descriptions.
* Fix - Compatibility issue with the Automatic Order Printing extension.
* Fix - Inventory sync for orders placed via other payment gateways using Block based checkout.
* Fix - Inventory sync for stock updates through product editing from Woo store.
* Fix - Issue caused by using correct type while sanitizing payment tokens.
* Fix - Issue that allowed adding non-numeric characters in the "last 4" and "expiry date" fields in the token editor.
* Fix - Issue that caused some simple and variable products to be excluded from sync.
* Fix - Issue that caused the "Sync with Square" checkbox to be unchecked for variable products.

= 3.9.0 - 2023-06-19 =
* Add - Support for splitting payments between a Square Gift Card and a Credit Card.
* Add - Sync Health Check action to start stuck sync jobs.
* Fix - Hide the "Sync with Square" meta fields when the product type is not simple or variable.
* Fix - Incorrect inventory sync when the inventory is not tracked in Square.
* Fix - Issue with WooCommerce Import CSV not automatically assigning columns when Square is active.
* Fix - Product Images not syncing from Square to WooCommerce.
* Fix - Refresh connection with Square during "UNAUTHORIZED" API error.
* Fix - Update sync interval properly updates the interval of scheduled action.
* Tweak - Added synced products/categories count in the complete step job logs.
* Tweak - Fix PHPCS issues and semgrep warnings.
* Tweak - WC 7.8.0 compatibility.

= 3.8.3 - 2023-06-06 =
* Fix - Renewals failing due to old payment token transient.

= 3.8.2 - 2023-05-29 =
* Dev - Fix phpcs warnings to improve code quality.
* Fix - Don't enqueue the Square payment token editor styles and scripts on every WP Admin dashboard.

= 3.8.1 - 2023-05-08 =
* Fix - Clear token cache when a user creates a new token via their my account page to prevent fatal errors when locating newly added tokens.
* Fix - Newly saved cards not working on checkout for customers have more than 10 saved payment methods across any gateway.
* Fix - Resolved fatal error when processing subscription renewals and pre-order payments caused by undefined function get_last_four().

= 3.8.0 - 2023-05-04 =
* Dev - Bump WooCommerce "tested up to" version 7.6.
* Dev - Bump WordPress minimum supported version from 5.6 to 5.8.
* Dev - Ignore false positives for phpcs nonce check.
* Fix - Incorrect inventory value for product import.
* Update - Migrate Payment Tokens from user meta to WC_Payment_Tokens to support card expiry alerts through Automate Woo and also to align with WooCommerce Core.

= 3.7.1 - 2023-04-21 =
* Add – Show a `Beta` notice in Gift Card settings UI to discourage Production usage.
* Fix - 500 error when trying to use Gift Cards on Production environment.
* Fix - Force-disable beta Gift Card functionality on upgrade to 3.7.1 and show a notice to inform merchant.

= 3.7.0 - 2023-04-06 =
* Add - Support for Square Gift Cards as single form of payment on an order.
* Dev - Bump WooCommerce minimum supported version from 6.0 to 6.8.
* Fix - Allow update product inventory in quick edit mode if "Sync Inventory" is disabled.
* Fix - Correct documentation link in the error message when `opcache.save_comments` is disabled.
* Fix - Issue with Apple Pay domain registration.
* Fix - Issue with sync caused due to incorrect data type.
* Fix - Modified Order Totals wrongly captured in Square with Authorization.
* Fix - Orders are now captured as per the WooCommerce settings to include/exclude the tax amount in prices.
* Fix - Saved card payment issue in block-based checkout.
* Tweak - WC 7.5.0 compatibility.
* Tweak - WP 6.2.0 compatibility.

= 3.6.1 - 2023-03-03 =
* Fix - Fatal error on PHP 8 caused while importing products.

= 3.6.0 - 2023-03-01 =
* Add - Sync interval frequency admin setting.
* Add - UnionPay as an option in Accepted Card Logos.
* Fix - Fix an issue that prevented being able to fetch stock for variations.
* Fix - Fix an issue that caused a subscription renewal failure for manual subscriptions when the customer is changed via admin.
* Fix - URL rendering within admin notices.

= 3.5.0 - 2023-01-31 =
* Add - Make saving credit cards optional for subscription products.
* Add - Support for Woo to Square sync of variable products with multiple attributes when Woo is set as SOR.
* Add - Attribution for SkyVerge as per GPL 3.0 and clarify GPLv3-or-later as extension license.
* Fix - Auto-renewal issue for guest users when trying to purchase subscription products.
* Fix - Checkout when a country does not have list of states.
* Fix - Display all error messages during Checkout instead only the first one.
* Fix - Error caused due to accessing method on `null` while creating or updating an order.
* Fix - Incorrect inventory value when stock status value is not equal to `IN_STOCK`.
* Fix - Incorrect stock information in Woo during sync when the inventory is not tracked in Square.
* Update - Bump WooCommerce "tested up to" from 7.1 to 7.3.
* Update - Bump WordPress version "tested up to" from 6.0 to 6.1.
* Update - Upgrade Square PHP SDK from 19.0.1.20220512 to 24.0.0.20221116.
* Update - Bump minimum required PHP version from 7.2 to 7.4 due to Square PHP SDK requirement.

= 3.4.2 - 2022-12-20 =
* Fix - Apple Pay button disabled and infinitely loading.
* Fix - Fatal error "Call to a member function getPayment() on null".

= 3.4.1 - 2022-12-07 =
* Fix - Check for server opcache.save_comments was restored to the more lenient check used in 3.3.0.

= 3.4.0 - 2022-12-05 =
* Add - Ability to export the Sync with Square status while exporting products.
* Add - Link to support forum under Sync Setting.
* Dev - Bump NPM version from 6.0.0 to 8.0.0.
* Dev - Bump node version from 12.0.0 to 16.0.0.
* Dev - Remove redundant build-zip npm command.
* Fix - Address issue with Square variation names containing a pipe character.
* Update - Blocks forms on the product, cart and checkout pages when a digital wallet payment is being processed.
* Update - Square PHP SDK to version 19.0.1.20220512.
* Fix - Ensure we show more accurate error messages.
* Fix - Hide variation in WooCommerce when the variation is deleted from Square.
* Fix - Incomplete product import issue when the number of products is very high.
* Fix - Incorrect coupon discount calculation which results into incorrect tax calculation.
* Fix - JS error thrown by Digital Wallets on Cart and Checkout pages due to unsupported product types.
* Fix - Remove unnecessary calls to `getLocation` when doing a manual sync.
* Fix - Set price money based on price overrides for a location.
* Update - Bump minimum supported PHP version to 7.2.
* Update - Bump minimum supported WooCommerce version to 6.0.
* Update - Bump minimum supported WordPress version to 5.6.
* Update - Rename System of Record to Sync Setting.

= 3.3.0 - 2022-11-07 =
* Add - Adds digital wallet buttons in the Pay for Order page.
* Add - Declare support for High-performance Order Systems ("HPOS").
* Add - Digital Wallet support for Australia.
* Add - Filter 'wc_square_update_product_set_description' to override product description during update from Square.
* Add - Filter 'wc_square_update_product_set_name' to override product name during update from Square.
* Add - Filter 'wc_square_update_product_set_variation_name' to override variation name during update from Square.
* Add - Support to store transaction ID while using High-performance Order Storage ("HPOS") (formerly known as Custom Order Tables, "COT").
* Dev - Plugin version constant added to the main file.
* Fix - Broken detailed decline messages after migration to Payments API.
* Fix - Checkout without required state field being empty.
* Fix - Digital wallet errors on Checkout page.
* Fix - Error caused while fetching variation SKU.
* Fix - Failed payment due to 3D secure errors.
* Fix - Fixes variations shown for WooCommerce simple products in Square dashboard.
* Fix - Inability to sync products created in both Square and WooCommerce.
* Fix - Incorrectly set quantity during Product Import.
* Fix - Issue with checkout using digital wallet when company name is required.
* Fix - Issue with stock status during product import when the inventory count is 0.
* Fix - Manage stock value of variation products when the product is not synced with Square.
* Fix - Stock status visibility under inventory for variable products.
* Update - Replaces dynamic strings with string literals.
* Update - WC tested up to 7.1

= 3.2.0 - 2022-09-14 =
* Add - Ensure the `via WooCommerce` text can be translated and introduce a new filter to update that text while paying with Apple Pay.
* Add - Missing square_customers table.
* Dev - Remove unnecessary build process files.
* Fix - Add notice when trying to import CSV and product sync is turned on.
* Fix - Invalid object with ID error while syncing variable products with Woo as SOR.
* Fix - Issue where `woocommerce_order_status_refunded` hook previously ran twice after successful refund.
* Fix - Multiple digital wallet buttons shown when quantity is updated.
* Fix - Tax computation error caused by tax ID set to 0.
* Fix - The `Repeated field must have at least one value` error.
* Update - Deprecates the method `get_max_objects_per_batch`.

= 3.1.0 - 2022-06-09 =
* Fix - Hide shipping options in Google Pay form for virtual products. #800
* Fix - State-specific tax calculation when using Google Pay. #778
* Add - Validation that Business Location or SOR is set and Square settings are saved before enabling Import Products button. #463

= 3.0.3 - 2022-06-01 =
* Fix - Customer Token Editor wasn't loading for some stores after updating to Square 3.0.0. #799
* Fix - No such file or directory warnings on case-sensitive filesystems. #799
* Dev - Bump compatibility to WP 6.0. #812

= 3.0.2 - 2022-05-17 =
* Fix - Prevent fatal error caused by stale transient (cached location data from previous Square API). #788
* Fix - Disable all plugin features if server requirements are not met, to prevent fatal errors. #793

= 3.0.1 - 2022-05-09 =
* Fix - Checkout stuck processing when using a card that doesn't require a postal code. #782
* Dev - Update minimum PHP version requirement to 7.2. #787

= 3.0.0 - 2022-05-04 =
* New - Upgrade the payment form on checkout to use the latest Square Web Payments SDK. PR#668
* Fix - Compatibility issues with WooCommerce 6.1.0. PR#715
* Fix - Sync issues caused by product variations having an empty SKU and incorrectly being set to the variable product (parent product) SKU value. PR#764
* Update - Remove admin notice warning of v3.0.0 release. PR#744
* Dev - Product importing is now handled by Action Scheduler. PR#698
* Dev - Syncing is now handled by Action Scheduler. PR#699
* Dev - Manual Sync is now handled by Action Scheduler. PR#710
* Dev - Upgrade Action Scheduler to 3.4.0. PR#762
* Dev - Updated Square Connect to Square SDK v 15.0.0. PR#673 PR#670 PR#668 PR#664 PR#659 PR#657
* Dev - Remove SkyVerge framework. PR#690 PR#689 PR#688 PR#687 PR#684 PR#683 PR#681 PR#678

= 2.9.1 - 2022.03.17 =
* Fix - Fatal error while deactivating WooCommerce before WooCommerce Square.

= 2.9.0 - 2022.02.17 =
* New - Added admin notice about v3 major update. PR#707
* Fix - Trim spaces from postal code at prefix and suffix positions. PR#654

= 2.8.0 - 2022.01.24 =
* New - Add support for Square stores located in Spain. PR#704

= 2.7.0 - 2021.11.09 =
* New - `wc_square_beta_country_support_spain` filter to add Spain as a supported country. PR#663
* Fix - Visibility of digital wallet buttons based on account & privacy settings. PR#652

= 2.6.0 - 2021.09.29 =
* New - Add support for Square stores located in France. PR#650
* Fix - PHP warning when trying to load the Square digital wallet (Apple/Google Pay buttons) on a product page that isn't available for purchase. PR#640

= 2.5.3 - 2021.07.23 =
* Fix - Failed orders with error "Square Payment Failed (Status code VALUE_TOO_LOW)" caused by incorrect line item calculations on orders with discounts/coupons (introduced in 2.5.2). PR#635

= 2.5.2 - 2021.07.21 =
* Fix - Product and inventory data not being synced due to duplicate/orphaned product metadata in database. PR#625
* Fix - Bypass SSL verification when checking background job processing eligibility. PR#624
* Fix - Correctly apply taxes in the Order API request and report accurate percentages. PR#559

= 2.5.1 - 2021.05.18 =
* Fix - Pay for Order and Add Payment Method forms sometimes not working when choosing another payment method other than Square. PR#618

= 2.5.0 - 2021.05.13 =
* New - Add support for WooCommerce Checkout blocks. PR#604
* New - Add support for Square stores located in Ireland. PR#609
* Fix - Improve manual sync performance and reduce stream timeout responses from Square on stores with large catalogs. PR#612

= 2.4.1 - 2021.03.30 =
* Fix - Variable products are now properly importing from Square on newer versions of WooCommerce. PR#605

= 2.4.0 - 2021.03.23 =
* Fix - Update jQuery 3 deprecated functions. PR#560
* Fix - Don't display digital wallet buttons when the cart contains a subscription product as Square does not yet support tokenization through digital wallets. PR#597
* Dev - Replace CoffeeScript with plain JS. PR#560

= 2.3.4 - 2021.02.11 =
* Fix - Handle exceptions when loading digital wallet buttons on product pages with no stock or other serviceable issues. PR#591

= 2.3.3 - 2021.02.09 =
* Fix - Uncaught PHP error when attempting to setup Apple Pay and Square is not properly connected (i.e. no valid access token found). PR#587
* Fix - Improve error logging when the request to verify the store's domain with Square/Apple Pay fails. PR#587
* Fix - Allow variable products to be previewed when Square is active. PR#554

= 2.3.2 - 2021.02.04 =
* Fix - PHP error on the My Account > Payment Methods page when saving a new card. PR#585

= 2.3.1 - 2021.02.03 =
* Fix - Add the correct variation to the cart when purchasing with Apple Pay and Google Pay from the product page. PR#581

= 2.3.0 - 2021.02.02 =
* Feature - Apple Pay and Google Pay support (US, UK and CA stores only). PR#547
* Fix - Duplicate `idempotency_key` issues caused by order IDs being re-used on the same store URL (i.e. after restoring from a backup). PR#563
* Fix - Don't import item variations from Square that are not available at your store's business location. PR#562
* Fix - Restore stock in Square when processing partial refunds (previously was only restoring stock for full refunds). PR#565
* Fix - Only restore stock if the "Restock refunded items" option is checked when refunding an order. PR#565
* Fix - Fatal errors during the sync and import process caused by unexpected/invalid Square API responses. PR#500
* Fix - Sends only one sync complete email per update to products that are synced with Square. PR#552
* Fix - Allow products with large numbers of categories (600+) to sync to Square when WooCommerce is SOR. PR#568
* Fix - Database related errors with creating the Square customer's table when first installing Square. PR#558
* Fix - Allow variable products with valid variations to import when variations with missing skus are present. PR#573
* Tweak - Update the Customer Profile setting description to make it clear that this setting enables tokenization. PR#576

= 2.2.5 - 2020.11.24 =
* Fix - Correctly saves inventory sync time when sync fails so items are re-synced on next attempt. PR#448
* Fix - Fixes warnings introduced with PHP 8. PR#533
* Fix - Corrects the plugin support URL. PR#539
* Fix - Allows imports containing products with variable pricing to complete successfully. PR#540
* Tweak - Updates assets to reflect WooCommerce color change. PR#544

= 2.2.4 - 2020.10.30 =
* Fix - Prevents logging anything if logging is disabled. PR#493
* Fix - Fixes a bug where products are imported even when it is not available at the store's location. PR#537

= 2.2.3 - 2020.10.23 =
* Fix - Display the correct stock quantity amount on all variations when product data is sent to Square. PR#503
* Fix - Avoid IDEMPOTENCY_KEY_REUSED API errors when syncing product data from WooCommerce to Square by using a more unique API request key. PR#528
* Fix - Added customer_id to Orders API to link Customers & Transactions on Square Dashboard and Transactions CSV Export. PR#527
* Fix - Issues with the postal code not matching WooCommerce data while saving cards. PR#501
* Fix - Prevents the "Send product data to Square" checkbox from being enabled when products and variations contain empty or duplicate SKUs. PR#525
* Fix - Issues that caused the Square Payment Form to be unclickable on the checkout page. PR#530
* Fix - Compatibility issues with the Square Payment form and conditional payment gateway extensions. PR#530

= 2.2.2 - 2020.09.15 =
* Fix - Don't import a new copy of each product image from Square when updating products during the import process. PR#513

= 2.2.1 - 2020.09.11 =
* New - Make the "Update existing products" part of the new import process optional by adding a new checkbox on Import Products modal. PR#508
* Fix - Stop the import process from getting stuck in a loop when reaching the time limit. PR#511
* Fix - Don't import/update categories from Square that are attached to products that cannot be found in WooCommerce. PR#511
* Fix - "idempotency_key must not be greater than 45 length" errors returned by some payment requests on stores using custom order number plugins. PR#507

= 2.2.0 - 2020.08.31 =
* Feature - Import new product variations from square to existing products in WooCommerce. PR#475
* Feature - Variations that are removed from Square will now be removed from products in WooCommerce during import. PR#475
* Feature - Upgrade to the Square Payments and Refunds API. PR#408
* Feature - New orders can be refunded up to one year after payment (up from 120 days). PR#408
* Fix - Only import products from Square that have non-empty SKUs. PR#475
* Fix - Empty product categories imported from Square into WooCommerce. PR#475
* Fix - Assign existing products to new categories imported from Square. PR#475
* Fix - Prevents loading of Square Assets on all pages except My Account -> Payment Methods & Checkout. PR#469
* Fix - Square Product Import & Product Manual Sync not triggering on mobile browsers. PR#472
* Fix - 3D Secure Verification Token is missing, Intent mismatch and other checkout errors related to SCA for merchants outside of the EU. PR#471
* Fix - Updated some of our documentation and support links in admin notices so they no longer redirect to an old URL or a 404 page. PR#474
* Fix - Use pagination to fetch inventory counts from Square. PR#478
* Fix - Display WooCommerce checkout validation errors along with Square payment form errors. PR#476
* Fix - Switching between sandbox and production environments will now show correct business locations. PR#462
* Fix - Don't wipe a customer's saved cards on when we receive an API error. PR#460
* Fix - Exclude draft and pending products from syncing from WooCommerce to Square. PR#484
* Fix - DevTools errors caused by missing minified JS files.
* Fix - PHP errors when syncing large amounts of products (`get_data() on null` and `getCursor() on a string`). PR#497

= 2.1.6 - 2020.07.15 =
* Fix - Make the "Sync Now" button disabled when no business location is set in Square settings.
* Fix - Enable checking/unchecking the Manage Stock setting for all variations.
* Fix - Refunding an order paid with another payment gateway will no longer sync inventory with Square when "Do not sync product data" is selected.
* Fix - Imported variation products that are out-of-stock will no longer show on the shop page when "Hide out of stock items from the catalog" is selected.
* Fix - Product images will now sync when Square is in Sandbox mode.
* Fix - Damaged stock adjustments will now sync properly to WooCommerce when multiple stock adjustments are made.
* Fix - Improve performance when manually syncing large amount of stock adjustments from Square (some inventory updates were missing).
* Fix - Quick editing products no longer sets incorrect stock quantities or disables syncing.
* Fix - Existing customer that have been removed from the connected Square account, or can't be found will now be able to save a new card on the checkout.
* Fix - When the System of Record is set to WooCommerce, product images will now properly sync to Square.
* Tweak - Use CSC consistently in all error messages when referring to the Card Security Code.
* Tweak - Change to using WordPress core methods to import/sync images from Square.

= 2.1.5 - 2020.05.15 =
* Fix - Fatal errors caused by incorrectly fetching locations before plugin init.
* Fix - WordPress database error when creating the Square Customers table on servers using utf8mb4.

= 2.1.4 - 2020.05.05 =
* Fix - Make sure that Square credit card fields are editable after checkout form refresh.

= 2.1.3 - 2020.04.30 =
* Fix - Persistent caching of locations to prevent unnecessary refetching and rate limiting.

= 2.1.2 - 2020.04.29 =
* Fix - INTENT_MISMATCH errors when guest customers save a card and registration is disabled.
* Fix - Improve checkout compatibility with password managers such as 1Password. This also avoids payment for reload on address change.
* Fix - Pass valid address values even if checkout fields are not present.
* Tweak - Sandbox mode can be turned on in the settings, no more need for setting the constant.
* Tweak - Change location URL to refer to our docs.

= 2.1.1 - 2020.03.23 =
* Fix - Inventory/Stock updates as a result of checkout via PayPal Standard does not reflect on the Square item.
* Fix - Error when trying to save an external product with the modified 'sync with square' value.
* Fix - Move product check on a possibly invalid product out of the try block avoiding potential further errors.

= 2.1.0 - 2020.02.11 =
* Feature - Add support for SCA (3D Secure 2)
* Fix     - Minor fixes to the Sync completed emails
* Tweak   - Add email notifications when connection issues are detected
* Fix     - Category sync when WooCommerce is the System of Record and there have been changes in Square

= 2.0.8 - 2019.12.09 =
* Fix   - Inventory changes through payments and refunds from other gateways not reflected on Square.
* Fix   - Fatal error on versions of WooCommerce before 4.3.
* Fix   - Sandbox API calls by passing is_sandbox flag to the Gateway API.
* Fix   - Quick edit view when editing a variable product without all variations SKU.
* Fix   - Verify if the product can be synced with Square before enabling sync when bulk/quick updating.
* Fix   - Disable sync for products that should not be synced after a REST API update.
* Fix   - Unable to create products during import.
* Fix   - Product inventory sync issue when WooCommerce is set as the Source of Record.
* Fix   - Inventory not updated when purchased through another gateway.
* Fix   - Category and description data not updated in a sync from Square.
* Fix   - Transactions on multiple stores connected to the same Square account would appear to succeed without actually charging the customer.
* Fix   - When making multiple partial refunds on the same order, only the first one would work.
* Tweak - Include product ID on failed sync record message.
* Tweak - Remove notices for refresh token when sandbox is enabled.
* Tweak - Prevent refreshing a token token when sandbox is enabled.

= 2.0.7 - 2019.11.18 =
* Fix   - No longer automatically disconnect on unexpected authorization errors
* Fix   - Bump compatibility for WooCommerce 3.8 and WordPress 5.3
* Fix   - Correct cents rounding that was causing invalid value errors
* Fix   - Fix encrypted token handling
* Fix   - No longer call revoke when disconnecting - just disconnect the site

= 2.0.6 - 2019.11.07 =
* Fix   - Access token renewal schedule action duplication.

= 2.0.5 - 2019.10.16 =
* Fix   - Access token renewal by adding support for refresh tokens as per the new Square API
* Fix   - Variable pricing import and adding an alert when these type of products are ignored.
* Fix   - Line item discounts and other adjustments being ignored.
* Tweak - Add a notice when a refresh token is not present to warn users to re-connect their accounts.
* Feature - Added support for Sandbox accounts.

= 2.0.4 - 2019.09.03 =
* Fix - Add adjustments to Square order in the event of discrepancy with WooCommerce total

= 2.0.3 - 2019.08.19 =
* Tweak - Re-introduce the "inventory sync" toggle to allow syncing product data without affecting inventory
* Fix - Adjust v1 upgrades to properly toggle inventory sync when not enabled in v1
* Fix - Ensure product prices are correctly converted to and from cents regardless of the decimal place setting
* Fix - Don't block the product stock management UI when product sync is disabled
* Fix - Ensure products that have multiple attributes that aren't used for variations can be synced
* Misc - Add support for WooCommerce 3.7

= 2.0.2 - 2019.08.13 =
* Tweak – WC 3.7 compatibility.

= 2.0.1 - 2019.07.23 =
* Fix - Don't display the "unsupported" payment processing admin notice for UK-based merchants

= 2.0.0 - 2019.07.22 =
* Feature - Support Square customer profiles for saved payment methods
* Feature - Customers can label their saved payment methods for easy identification when choosing how to pay
* Feature - Support enhanced payment form with auto formatting and retina card icons
* Feature - Show detailed decline messages when possible in place of generic errors
* Feature - Add support for WooCommerce Subscriptions
* Feature - Add support for WooCommerce Pre-Orders
* Feature - Orders with only virtual items can force a charge instead of authorization
* Feature - Void authorizations from WooCommerce
* Feature - Itemize Square transactions for improved reporting in Square
* Feature - Add sync records to notify admins of failed product syncs
* Feature - Changed "Synced with Square" option while bulk editing products
* Tweak - Introduce "System of Record" settings to control product data sync
* Tweak - Remove items from Square locations when deleted in WooCommerce (if WC is the system of record)
* Tweak - Allow users to hide WooCommerce products if removed from the linked Square location (if Square is the system of record)
* Tweak - Import images from Square when not set in WooCommerce (if Square is the system of record)
* Tweak - Remove Square postcode field when a postcode can be used from the checkout form
* Fix - Ensure connection tokens are refreshed ahead of expiration
* Fix - Always ensure settings are displayed in multisite
* Fix - Ensure Square prices update WooCommerce regular price, not sale price
* Fix - Remove usages of `$HTTP_RAW_POST_DATA`, which is deprecated
* Fix - Do not allow multiple sync processes to run simultaneously
* Fix - Avoid submitting duplicate orders with Checkout for WC plugin
* Misc - Upgrade to Square Connect v2 APIs
* Misc - Background process product sync for improved scalability
* Misc - Refactor for other miscellaneous fixes and improved reliability

= 1.0.38 - 2019-07-05 =
* Fix - Re-deploy due to erroneous inclusion of trunk folder

= 1.0.37 – 2019-04-16 =
* Fix – Use correct assets loading scheme.

= 1.0.36 – 2019-04-15 =
* Tweak – WC 3.6 compatibility.

= 1.0.35 - 2019-02-01 =
* Fix - Idempotency key reuse issue when checking out.

= 1.0.34 - 2018-11-07 =
* Update - Fieldset tag to div tag in payment box to prevent unwanted styling.
* Fix - Provide unique idempotency ID to the order instead of random unique number.
* Update - WP tested up to version 5.0

= 1.0.33 - 2018-09-27 =
* Update - WC tested up to version 3.5

= 1.0.32 - 2018-08-23 =
* Fix - UK/GB localed does not support Diners/Discover, so do not show these brands on checkout.

== Upgrade Notice ==

= 3.5.0 =
* Note that this version bumps the minimum PHP version from 7.2 to 7.4.

= 1.0.25 =
* Public Release!
