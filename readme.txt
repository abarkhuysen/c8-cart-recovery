=== Creative8 Cart Recovery ===
Contributors: arthurbarkhuysen
Donate link: https://example.com/donate
Tags: woocommerce, abandoned cart, cart recovery, email, marketing
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Recover abandoned carts by sending reminder emails to customers who don't complete checkout.

== Description ==

Creative8 Cart Recovery helps you recover lost sales by automatically tracking abandoned shopping carts and sending recovery emails to customers who leave without completing their purchase.

**Features:**

* Automatic abandoned cart tracking for both guests and registered users
* Guest email capture on the checkout page
* Customizable abandonment time threshold
* WooCommerce email integration with customizable templates
* Admin dashboard to view and manage abandoned carts
* Bulk actions: delete carts and resend recovery emails
* Detailed statistics and recovery rate tracking
* Unique recovery links to restore cart contents
* Automatic cleanup of old abandoned cart data
* WooCommerce HPOS (High-Performance Order Storage) compatible

**How It Works:**

1. When a customer adds items to their cart, the plugin starts tracking their session
2. Guest email is captured when entered during checkout
3. If the customer doesn't complete the purchase within the configured time, the cart is marked as abandoned
4. An automatic recovery email is sent with a unique link to restore their cart
5. When the customer clicks the link, their cart is restored and they can complete the purchase
6. Statistics track your recovery rate and recovered revenue

== Installation ==

1. Upload the `c8-cart-recovery` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure WooCommerce is installed and activated
4. Go to WooCommerce > Abandoned Carts to configure settings

**Requirements:**

* WordPress 6.0 or higher
* PHP 8.1 or higher
* WooCommerce 8.0 or higher

== Frequently Asked Questions ==

= Does this plugin work with guest checkout? =

Yes! The plugin captures guest email addresses when they're entered during checkout, allowing recovery emails to be sent even for non-registered customers.

= How long before a cart is considered abandoned? =

By default, carts are marked as abandoned after 60 minutes of inactivity. You can customize this in the plugin settings (minimum 15 minutes, maximum 24 hours).

= Can I customize the recovery email? =

Yes, the recovery email integrates with WooCommerce's email system. You can customize the subject, heading, and content in WooCommerce > Settings > Emails > Abandoned Cart Recovery.

= Will this slow down my site? =

No, the plugin is optimized for performance. Cart tracking is lightweight and cleanup processes run via WordPress cron to avoid impacting page load times.

= Is it compatible with WooCommerce HPOS? =

Yes, the plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= How do I view abandoned carts? =

Navigate to WooCommerce > Abandoned Carts in your WordPress admin. You'll see a list of all abandoned carts with customer details, cart contents, and value.

= Can I manually resend recovery emails? =

Yes, from the abandoned carts list, you can resend recovery emails to individual customers or use bulk actions to email multiple customers at once.

== Screenshots ==

1. Abandoned Carts list view showing all tracked abandoned carts
2. Statistics dashboard with recovery rate and revenue metrics
3. Plugin settings page for configuring abandonment time and email options
4. Recovery email template customization in WooCommerce settings

== Changelog ==

= 1.0.0 =
* Initial release
* Abandoned cart tracking for guests and registered users
* Guest email capture functionality
* WooCommerce email integration
* Admin dashboard with list table
* Statistics and reporting
* Recovery link functionality
* Automatic cleanup of old data
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release of Creative8 Cart Recovery. Install to start recovering abandoned carts.
