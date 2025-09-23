=== Kickbox Integration ===
Contributors: munjr llc
Tags: email verification, kickbox, checkout, customer management
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates Kickbox email verification service with WooCommerce for real-time email validation during checkout and batch verification for existing customers.

== Description ==

The Kickbox Integration plugin provides seamless email verification functionality for your WooCommerce store. It integrates with the Kickbox email verification service to help you maintain clean email lists and reduce bounce rates.

= Key Features =

* **Real-time Email Verification**: Verify customer emails during checkout process
* **Batch Verification**: Verify existing customer emails in bulk
* **Flexible Action Configuration**: Configure different actions for different verification results
* **Admin Dashboard**: Comprehensive settings page with statistics
* **Customer Management Integration**: View verification status in user management
* **React-powered Interface**: Modern, responsive admin interface
* **Sandbox Mode**: Test the integration without using credits

= Verification Results =

The plugin handles four types of verification results:

* **Deliverable**: Email address is confirmed to be valid and deliverable
* **Undeliverable**: Email address is confirmed to be invalid or undeliverable
* **Risky**: Email address may be risky or suspicious
* **Unknown**: Unable to determine the status of the email address

= Action Configuration =

For each verification result, you can configure the following actions:

* **Allow Checkout**: Customer can complete their purchase regardless of verification result
* **Block Checkout**: Customer will be prevented from completing their purchase
* **Allow but Flag for Review**: Customer can complete their purchase, but the order will be flagged for admin review

= Requirements =

* WordPress 6.2 or higher
* WooCommerce 10.2 or higher
* PHP 7.4 or higher
* Valid Kickbox API key

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/kickbox-integration` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Kickbox Integration to configure your API key
4. Set up your verification actions and enable the features you need

== Frequently Asked Questions ==

= Do I need a Kickbox account? =

Yes, you need a Kickbox account and API key to use this plugin. You can sign up for a free account at [kickbox.com](https://kickbox.com) which includes 100 free verifications.

= How much does Kickbox cost? =

Kickbox offers various pricing plans. Check their [pricing page](https://kickbox.com/pricing) for current rates. The plugin includes a sandbox mode for testing without using credits.

= Can I test the integration before going live? =

Yes! The plugin includes a sandbox mode that allows you to test the integration without using your verification credits.

= What happens if the API is unavailable? =

If the Kickbox API is unavailable, the plugin will log the error but won't block the checkout process, ensuring your customers can still complete their purchases.

== Screenshots ==

1. Admin settings page with API configuration
2. Verification actions configuration
3. Statistics dashboard
4. Customer management with verification status
5. Checkout process with email verification

== Changelog ==

= 1.0.0 =
* Initial release
* Real-time email verification during checkout
* Batch verification for existing customers
* Admin settings page with React interface
* Customer management integration
* Flexible action configuration
* Statistics dashboard
* Sandbox mode for testing

== Upgrade Notice ==

= 1.0.0 =
Initial release of WooCommerce Kickbox Integration.

== Support ==

For support, please visit the [plugin support forum](https://wordpress.org/support/plugin/kickbox-integration) or contact the plugin author.

== Privacy Policy ==

This plugin integrates with Kickbox's email verification service. When verifying emails, the email addresses are sent to Kickbox's servers for verification. Please review Kickbox's privacy policy for information about how they handle your data.

== Technical Details ==

* Built with React for modern admin interface
* Uses Webpack for asset compilation
* Integrates with WooCommerce hooks and filters
* Follows WordPress coding standards
* Includes comprehensive error handling
* Supports both single and batch verification APIs
