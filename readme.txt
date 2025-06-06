=== WooCommerce Square ===
Contributors: woocommerce, automattic
Tags: credit card, square, woocommerce, inventory sync
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 4.9.3
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

= 4.9.3 - 2025-06-09 =
* Add - Support for Square's EMV 3-D Secure authentication flow to comply with countries that have 3DS mandates and improve payment security.
* Add - Pre-sync validations for Product Variations.
* Add - Digit handling for country-specific currencies to prevent incorrect division by 100 for currencies like Japanese Yen.
* Add - Indicate customer initiated payments in Square API requests.
* Fix - Only sync attributes used for variations to Square, preventing item option mismatch errors when WooCommerce products have unused attributes.
* Fix - Ensure consistent error messages between the Block Checkout and the Shortcode Checkout.
* Dev - Bump Square PHP SDK version from `35.1.0.20240320` to `40.0.0.20250123`.

= 4.9.2 - 2025-05-27 =
* Dev - Bump WooCommerce "tested up to" version 9.9.
* Dev - Bump WooCommerce minimum supported version to 9.7.

= 4.9.1 - 2025-05-05 =
* Add - Set the onboarding URL for the "Complete setup" button on the new payment settings page.
* Fix - Issue with Product Price override update logic.
* Fix - Show detailed error messages on Checkout Page based on Debug Mode settings.
* Fix - Ensure that debug logs for payment gateways are being generated as expected.
* Dev - Add in performance logging during the checkout process.
* Dev - Update E2E tests to accommodate payment modernization changes in WooCommerce Core.

= 4.9.0 - 2025-04-07 =
* Add - Support for syncing multiple Product Variations.
* Add - Support for WooCommerce's new Email Improvements feature.
* Fix - Digital Wallets and Cash App payment issues in Woo 9.8.
* Fix - Deprecated PHP error for Gift Cards.
* Fix - jQuery error on Cart & Checkout pages when a Subscription product is added to the cart, as Digital Wallets cannot be used for Subscription products.
* Fix - Remove duplicate onboarding notice.
* Dev - Bump WooCommerce "tested up to" version 9.8.
* Dev - Bump WooCommerce minimum supported version to 9.6.
* Dev - Bump WordPress minimum supported version to 6.6.
* Dev - Bump WordPress "tested up to" version 6.8.
* Dev - Updates to E2E tests setup.
* Dev - Update all third-party actions our workflows rely on to use versions based on specific commit hashes.

= 4.8.7 - 2025-03-06 =
* Add - Support for syncing the "Mark as Sold Out" field value during inventory sync.
* Fix - Ensure payment methods display the correct buttons and statuses in the new WooCommerce Payments settings.
* Fix - Ensure that Cash App Pay works as expected on mobile devices.
* Fix - Ensure that no blank order is created via the "Add Payment Method" when HPOS is enabled.
* Dev - Disabled warning checks from WordPress Plugin Check Action.

= 4.8.6 - 2025-02-10 =
* Fix - Resolved "translation loading was triggered too early" issue in WordPress 6.7.
* Fix - Refresh brand assets.
* Dev - Bump WooCommerce "tested up to" version 9.7.
* Dev - Bump WooCommerce minimum supported version to 9.5.
* Dev - Bump WordPress minimum supported version to 6.6.
* Dev - Add the WordPress Plugin Check GitHub Action.

= 4.8.5 - 2025-01-20 =
* Fix - Unblock the Checkout page UI, and show a generic error when buyer verification fails.
* Fix - Ensure inventory sync works correctly for variable products when Square sync is enabled through quick or bulk edit.
* Dev - Bump WooCommerce "tested up to" version 9.6.
* Dev - Bump WooCommerce minimum supported version to 9.4.
* Dev - Use the `@woocommerce/e2e-utils-playwright` NPM package for E2E tests.
* Dev - Updates GitHub `actions/cache`, `actions/upload` and `actions/download` to v4 due to deprecation.

= 4.8.4 - 2024-12-09 =
* Fix - Resolved the product duplication issue on the Square side when WooCommerce is set as the SOR.
* Fix - Remove `woocommerce_loop_add_to_cart_link` filter to standardize "Buy Gift Card" and "Add to Cart" button styles across themes.
* Fix - Reposition payment error messages close to Payment Method form on the Classic Checkout page.
* Dev - Bump WooCommerce "tested up to" version 9.5.
* Dev - Bump WooCommerce minimum supported version to 9.3.
* Dev - Group E2E tests using tags and run each group in parallel within the GitHub Actions workflow.
* Dev - Resolved some failing E2E tests.

= 4.8.3 - 2024-11-11 =
* Fix – Ensure Square sync works without issues when using cached API responses.
* Fix - Avoid a potential infinite loop during inventory pull.
* Tweak - Change the maximum object retrieval limit from 100 to 50 to avoid timeout issues.
* Dev - Bump WordPress "tested up to" version 6.7.
* Dev - Bump WordPress minimum supported version to 6.5.

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

[View historical changelog details here](https://github.com/woocommerce/woocommerce-square/blob/trunk/changelog.txt).

== Upgrade Notice ==

= 3.5.0 =
* Note that this version bumps the minimum PHP version from 7.2 to 7.4.

= 1.0.25 =
* Public Release!
