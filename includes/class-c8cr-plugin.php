<?php
/**
 * Main plugin class
 *
 * @package C8_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * C8CR_Plugin class
 */
class C8CR_Plugin {

    /**
     * Single instance of the class
     *
     * @var C8CR_Plugin
     */
    protected static $instance = null;

    /**
     * Cart tracker instance
     *
     * @var C8CR_Cart_Tracker
     */
    public $tracker;

    /**
     * Cart recovery instance
     *
     * @var C8CR_Cart_Recovery
     */
    public $recovery;

    /**
     * Cron handler instance
     *
     * @var C8CR_Cron_Handler
     */
    public $cron;

    /**
     * Admin instance
     *
     * @var C8CR_Admin
     */
    public $admin;

    /**
     * Main instance
     *
     * @return C8CR_Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once C8CR_PLUGIN_PATH . 'includes/class-c8cr-cart-tracker.php';
        require_once C8CR_PLUGIN_PATH . 'includes/class-c8cr-cart-recovery.php';
        require_once C8CR_PLUGIN_PATH . 'includes/class-c8cr-cron-handler.php';
        // Email class is loaded via woocommerce_email_classes filter to ensure WC_Email exists

        if ( is_admin() ) {
            require_once C8CR_PLUGIN_PATH . 'includes/admin/class-c8cr-admin.php';
            require_once C8CR_PLUGIN_PATH . 'includes/admin/class-c8cr-admin-list-table.php';
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize components
        add_action( 'init', array( $this, 'init_components' ) );

        // Register email class with WooCommerce
        add_filter( 'woocommerce_email_classes', array( $this, 'register_email_class' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );

        // Register AJAX handlers
        add_action( 'wp_ajax_c8cr_capture_email', array( $this, 'ajax_capture_email' ) );
        add_action( 'wp_ajax_nopriv_c8cr_capture_email', array( $this, 'ajax_capture_email' ) );

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        $this->tracker  = new C8CR_Cart_Tracker();
        $this->recovery = new C8CR_Cart_Recovery();
        $this->cron     = new C8CR_Cron_Handler();

        if ( is_admin() ) {
            $this->admin = new C8CR_Admin();
        }
    }

    /**
     * Register the abandoned cart email class with WooCommerce
     *
     * @param array $email_classes WooCommerce email classes.
     * @return array
     */
    public function register_email_class( $email_classes ) {
        // Load the email class here when WC_Email is available
        require_once C8CR_PLUGIN_PATH . 'includes/emails/class-c8cr-email-abandoned-cart.php';
        $email_classes['C8CR_Email_Abandoned_Cart'] = new C8CR_Email_Abandoned_Cart();
        return $email_classes;
    }

    /**
     * Enqueue frontend scripts for email capture
     */
    public function enqueue_frontend_scripts() {
        // Only on checkout page
        if ( ! is_checkout() ) {
            return;
        }

        // Check if plugin is enabled
        if ( get_option( 'c8cr_enabled', 'yes' ) !== 'yes' ) {
            return;
        }

        wp_enqueue_script(
            'c8cr-guest-email-capture',
            C8CR_PLUGIN_URL . 'assets/js/guest-email-capture.js',
            array( 'jquery' ),
            C8CR_VERSION,
            true
        );

        wp_localize_script(
            'c8cr-guest-email-capture',
            'c8cr_params',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'c8cr_capture_email' ),
            )
        );
    }

    /**
     * AJAX handler for capturing guest email
     */
    public function ajax_capture_email() {
        check_ajax_referer( 'c8cr_capture_email', 'nonce' );

        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
        $phone      = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'Invalid email address' ) );
        }

        // Update the abandoned cart record with email
        if ( $this->tracker ) {
            $result = $this->tracker->update_cart_email( $email, $first_name, $phone );
            if ( $result ) {
                wp_send_json_success( array( 'message' => 'Email captured' ) );
            }
        }

        wp_send_json_error( array( 'message' => 'Failed to capture email' ) );
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'c8-cart-recovery',
            false,
            dirname( C8CR_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Create database tables
     */
    public static function create_tables() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'wc_abandoned_carts';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(64) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            user_email varchar(200) DEFAULT NULL,
            user_first_name varchar(100) DEFAULT NULL,
            user_phone varchar(50) DEFAULT NULL,
            cart_contents longtext NOT NULL,
            cart_total decimal(10,2) NOT NULL DEFAULT '0.00',
            currency varchar(10) NOT NULL DEFAULT 'USD',
            recovery_token varchar(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'abandoned',
            email_sent_count int(11) NOT NULL DEFAULT '0',
            last_email_sent datetime DEFAULT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            recovered_at datetime DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            UNIQUE KEY recovery_token (recovery_token),
            KEY user_id (user_id),
            KEY user_email (user_email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'c8cr_db_version', C8CR_VERSION );
    }

    /**
     * Schedule cron events
     */
    public static function schedule_cron() {
        // Add custom cron interval
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_interval' ) );

        // Schedule abandoned cart processing
        if ( ! wp_next_scheduled( 'c8cr_process_abandoned_carts' ) ) {
            wp_schedule_event( time(), 'fifteen_minutes', 'c8cr_process_abandoned_carts' );
        }

        // Schedule cleanup of old carts
        if ( ! wp_next_scheduled( 'c8cr_cleanup_old_carts' ) ) {
            wp_schedule_event( time(), 'daily', 'c8cr_cleanup_old_carts' );
        }
    }

    /**
     * Add custom cron interval
     *
     * @param array $schedules Cron schedules.
     * @return array
     */
    public static function add_cron_interval( $schedules ) {
        $schedules['fifteen_minutes'] = array(
            'interval' => 900, // 15 minutes
            'display'  => __( 'Every 15 Minutes', 'c8-cart-recovery' ),
        );
        return $schedules;
    }

    /**
     * Get abandoned cart by ID
     *
     * @param int $cart_id Cart ID.
     * @return object|null
     */
    public static function get_cart( $cart_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $cart_id
            )
        );
    }

    /**
     * Get abandoned cart by recovery token
     *
     * @param string $token Recovery token.
     * @return object|null
     */
    public static function get_cart_by_token( $token ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE recovery_token = %s",
                $token
            )
        );
    }

    /**
     * Get abandoned cart by session ID
     *
     * @param string $session_id Session ID.
     * @return object|null
     */
    public static function get_cart_by_session( $session_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE session_id = %s",
                $session_id
            )
        );
    }

    /**
     * Generate a unique recovery token
     *
     * @return string
     */
    public static function generate_recovery_token() {
        return wp_generate_password( 32, false, false ) . bin2hex( random_bytes( 16 ) );
    }

    /**
     * Get recovery URL for a cart
     *
     * @param string $token Recovery token.
     * @return string
     */
    public static function get_recovery_url( $token ) {
        return add_query_arg( 'c8cr_recover_cart', $token, home_url( '/' ) );
    }
}
