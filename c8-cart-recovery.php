<?php
/**
 * Plugin Name: C8 Cart Recovery
 * Plugin URI: https://github.com/abarkhuysen/c8-cart-recovery
 * Description: Recover abandoned carts by sending reminder emails to customers who don't complete checkout.
 * Version: 1.1.0
 * Author: Arthur Barkhuysen
 * Author URI: https://github.com/arthurbarkhuysen
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: c8-cart-recovery
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 *
 * @package C8_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'C8CR_VERSION', '1.1.0' );
define( 'C8CR_PLUGIN_FILE', __FILE__ );
define( 'C8CR_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'C8CR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'C8CR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Add settings link to plugin action links
 *
 * @param array $links Plugin action links.
 * @return array
 */
function c8cr_plugin_action_links( $links ) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        admin_url( 'admin.php?page=c8cr-abandoned-carts&tab=settings' ),
        __( 'Settings', 'c8-cart-recovery' )
    );
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'c8cr_plugin_action_links' );

/**
 * Run database migrations if needed
 */
function c8cr_maybe_run_migrations() {
    $current_version = get_option( 'c8cr_db_version', '1.0.0' );

    // Migration from 1.0.0 to 1.1.0: Rename table from wc_abandoned_carts to c8cr_abandoned_carts
    if ( version_compare( $current_version, '1.1.0', '<' ) ) {
        c8cr_migrate_1_1_0();
        update_option( 'c8cr_db_version', '1.1.0' );
    }
}
add_action( 'plugins_loaded', 'c8cr_maybe_run_migrations', 5 );

/**
 * Migration 1.1.0: Rename database table from wc_abandoned_carts to c8cr_abandoned_carts
 */
function c8cr_migrate_1_1_0() {
    global $wpdb;

    $old_table = $wpdb->prefix . 'wc_abandoned_carts';
    $new_table = $wpdb->prefix . 'c8cr_abandoned_carts';

    // Check if old table exists
    $old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) ) === $old_table;

    // Check if new table already exists
    $new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) ) === $new_table;

    if ( $old_exists && ! $new_exists ) {
        // Rename the table
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
    }
}

/**
 * Check if WooCommerce is active
 */
function c8cr_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function c8cr_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e( 'C8 Cart Recovery requires WooCommerce to be installed and activated.', 'c8-cart-recovery' ); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function c8cr_init() {
    // Check for WooCommerce
    if ( ! c8cr_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'c8cr_woocommerce_missing_notice' );
        return;
    }

    // Load plugin classes
    require_once C8CR_PLUGIN_PATH . 'includes/class-c8cr-plugin.php';

    // Initialize the plugin
    C8CR_Plugin::instance();
}
add_action( 'plugins_loaded', 'c8cr_init' );

/**
 * Plugin activation hook
 */
function c8cr_activate() {
    // Check for WooCommerce
    if ( ! c8cr_is_woocommerce_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'C8 Cart Recovery requires WooCommerce to be installed and activated.', 'c8-cart-recovery' ),
            'Plugin dependency check',
            array( 'back_link' => true )
        );
    }

    // Load plugin class for activation
    require_once C8CR_PLUGIN_PATH . 'includes/class-c8cr-plugin.php';

    // Create database tables
    C8CR_Plugin::create_tables();

    // Schedule cron events
    C8CR_Plugin::schedule_cron();

    // Set default options
    add_option( 'c8cr_enabled', 'yes' );
    add_option( 'c8cr_abandonment_time', 60 ); // minutes
    add_option( 'c8cr_email_enabled', 'yes' );
    add_option( 'c8cr_cleanup_days', 30 ); // days to keep old records

    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'c8cr_activate' );

/**
 * Plugin deactivation hook
 */
function c8cr_deactivate() {
    // Clear scheduled cron events
    wp_clear_scheduled_hook( 'c8cr_process_abandoned_carts' );
    wp_clear_scheduled_hook( 'c8cr_cleanup_old_carts' );
}
register_deactivation_hook( __FILE__, 'c8cr_deactivate' );

/**
 * Declare HPOS compatibility
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );
