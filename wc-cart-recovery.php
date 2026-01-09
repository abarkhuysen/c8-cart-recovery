<?php
/**
 * Plugin Name: WC Cart Recovery
 * Plugin URI: https://example.com/wc-cart-recovery
 * Description: Recover abandoned carts by sending reminder emails to customers who don't complete checkout.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-cart-recovery
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package WC_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'WCCR_VERSION', '1.0.0' );
define( 'WCCR_PLUGIN_FILE', __FILE__ );
define( 'WCCR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WCCR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCCR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function wccr_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wccr_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'WC Cart Recovery requires WooCommerce to be installed and activated.', 'wc-cart-recovery' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function wccr_init() {
    // Check for WooCommerce
    if ( ! wccr_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'wccr_woocommerce_missing_notice' );
        return;
    }

    // Load plugin classes
    require_once WCCR_PLUGIN_PATH . 'includes/class-wccr-plugin.php';

    // Initialize the plugin
    WCCR_Plugin::instance();
}
add_action( 'plugins_loaded', 'wccr_init' );

/**
 * Plugin activation hook
 */
function wccr_activate() {
    // Check for WooCommerce
    if ( ! wccr_is_woocommerce_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'WC Cart Recovery requires WooCommerce to be installed and activated.', 'wc-cart-recovery' ),
            'Plugin dependency check',
            array( 'back_link' => true )
        );
    }

    // Load plugin class for activation
    require_once WCCR_PLUGIN_PATH . 'includes/class-wccr-plugin.php';

    // Create database tables
    WCCR_Plugin::create_tables();

    // Schedule cron events
    WCCR_Plugin::schedule_cron();

    // Set default options
    add_option( 'wccr_enabled', 'yes' );
    add_option( 'wccr_abandonment_time', 60 ); // minutes
    add_option( 'wccr_email_enabled', 'yes' );
    add_option( 'wccr_cleanup_days', 30 ); // days to keep old records

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'wccr_activate' );

/**
 * Plugin deactivation hook
 */
function wccr_deactivate() {
    // Clear scheduled cron events
    wp_clear_scheduled_hook( 'wccr_process_abandoned_carts' );
    wp_clear_scheduled_hook( 'wccr_cleanup_old_carts' );
}
register_deactivation_hook( __FILE__, 'wccr_deactivate' );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
