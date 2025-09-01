=== CBO Payment Gateway ===
Contributors: cobalttech
Tags: woocommerce, payment gateway, cobalt tech, panama
Requires at least: 4.4
Tested up to: 6.8
Requires PHP: 7.2.0
Stable tag: 2.4.2
License: GPLv2
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html#SEC1

Woocommerce plugin for CBO Payment Gateway

== Description ==
This plugin enables secure online payments for WooCommerce stores in Panama using the Cobalt Tech payment gateway.

Accept credit and debit card payments directly on your website through Visa, Mastercard, and Clave cards. The plugin connects your WooCommerce store to the Cobalt Tech payment platform, allowing merchants based in Panama to process transactions efficiently and securely.

**Features:**

- Seamless integration with WooCommerce.
- Support for Visa, Mastercard, and Clave cards.
- Secure transaction handling via the Cobalt Tech platform.
- Only available for stores with a Panama billing address.

Whether you\'re launching a new online store or expanding your payment options in Panama, this plugin offers a reliable solution for accepting card payments.


== Installation ==
1. Upload the plugin files to the `/wp-content/plugins/cobalt-tech-gateway` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the \'Plugins\' screen in WordPress.
3. Go to **WooCommerce > Settings > Payments** and enable the \"Cobalt Tech Gateway\".
4. Configure your Cobalt Tech credentials and settings.

== Frequently Asked Questions ==

= Who can use this plugin? =

Only WooCommerce stores located in Panama are supported.

= What payment methods are supported? =

Visa, Mastercard, and Clave cards.

= Do I need a Cobalt Tech merchant account? =

Yes. You must be an approved merchant with Cobalt Tech to use this plugin.

== Changelog ==
2025.07.14 - version 2.4.1
* Show 3DS Challenge on Popup
* Added support for refunds
* Deleted support for API v1
* Added metadata for identify origin

2025.07.3 - version 2.3.1
* Added support for WordPress block mode

2025.04.10 - version 2.2.0
* Added APIv2 for eClave payments
* Fix support for 3DS payments

2025.01.30 - version 2.1.0
* Added support for 3DS payments

2024.11.23 - version 2.0.0
 * Adapted for API V2

2024.10.15 - version 1.2.0
 * Added native payments for VISA and Mastercard
 * Added translations

2024.03.11 - version 1.1.0
 * Enable VISA and MASTERCARD payments

2024.02.19 - version 1.0.4
 * Fix create Clave payments
 * Added deploy script

2023.12.20 - version 1.0.3
 * Fix Version update

2023.11.20 - version 1.0.2
 * Auto-update Production URL to metrobank.cobalt.tech after November 30th, 2023
 * Added error logs

2023.07.03 - version 1.0.1
 * Fix webhook response for Clave payments

2023.02.04 - version 1.0.0
 * First Release