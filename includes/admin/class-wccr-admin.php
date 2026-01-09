<?php
/**
 * Admin Class
 *
 * Handles admin pages and settings
 *
 * @package WC_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCCR_Admin class
 */
class WCCR_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Enqueue admin scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

        // Handle admin actions
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add admin menu under WooCommerce
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Abandoned Carts', 'wc-cart-recovery' ),
            __( 'Abandoned Carts', 'wc-cart-recovery' ),
            'manage_woocommerce',
            'wccr-abandoned-carts',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_wccr-abandoned-carts' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'wccr-admin',
            WCCR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WCCR_VERSION
        );
    }

    /**
     * Handle admin actions (delete, resend email, etc.)
     */
    public function handle_admin_actions() {
        // Check for single actions
        if ( isset( $_GET['action'] ) && isset( $_GET['cart_id'] ) && isset( $_GET['_wpnonce'] ) ) {
            $action  = sanitize_text_field( wp_unslash( $_GET['action'] ) );
            $cart_id = absint( wp_unslash( $_GET['cart_id'] ) );
            $nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

            if ( ! wp_verify_nonce( $nonce, 'wccr_action_' . $cart_id ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            switch ( $action ) {
                case 'delete':
                    $this->delete_cart( $cart_id );
                    break;
                case 'resend':
                    $this->resend_email( $cart_id );
                    break;
            }

            wp_safe_redirect( admin_url( 'admin.php?page=wccr-abandoned-carts' ) );
            exit;
        }

        // Handle bulk actions
        if ( isset( $_POST['action'] ) && isset( $_POST['cart_ids'] ) && isset( $_POST['_wpnonce'] ) ) {
            $action   = sanitize_text_field( wp_unslash( $_POST['action'] ) );
            $cart_ids = array_map( 'absint', (array) wp_unslash( $_POST['cart_ids'] ) );
            $nonce    = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) );

            if ( ! wp_verify_nonce( $nonce, 'bulk-abandoned_carts' ) ) {
                return;
            }

            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                return;
            }

            if ( 'delete' === $action || 'delete2' === $action ) {
                foreach ( $cart_ids as $cart_id ) {
                    $this->delete_cart( $cart_id );
                }
            } elseif ( 'resend' === $action || 'resend2' === $action ) {
                foreach ( $cart_ids as $cart_id ) {
                    $this->resend_email( $cart_id );
                }
            }
        }
    }

    /**
     * Delete a cart
     *
     * @param int $cart_id Cart ID.
     */
    private function delete_cart( $cart_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        $wpdb->delete(
            $table_name,
            array( 'id' => $cart_id ),
            array( '%d' )
        );
    }

    /**
     * Resend email for a cart
     *
     * @param int $cart_id Cart ID.
     */
    private function resend_email( $cart_id ) {
        $cart = WCCR_Plugin::get_cart( $cart_id );

        if ( ! $cart || ! is_email( $cart->user_email ) ) {
            return;
        }

        // Get WooCommerce email instance
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        if ( isset( $emails['WCCR_Email_Abandoned_Cart'] ) ) {
            $emails['WCCR_Email_Abandoned_Cart']->trigger( $cart );
        }
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'wccr_settings', 'wccr_enabled' );
        register_setting( 'wccr_settings', 'wccr_abandonment_time' );
        register_setting( 'wccr_settings', 'wccr_email_enabled' );
        register_setting( 'wccr_settings', 'wccr_cleanup_days' );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'carts';

        ?>
        <div class="wrap wccr-admin-wrap">
            <h1><?php esc_html_e( 'Abandoned Carts', 'wc-cart-recovery' ); ?></h1>

            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wccr-abandoned-carts&tab=carts' ) ); ?>"
                   class="nav-tab <?php echo 'carts' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Abandoned Carts', 'wc-cart-recovery' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wccr-abandoned-carts&tab=stats' ) ); ?>"
                   class="nav-tab <?php echo 'stats' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Statistics', 'wc-cart-recovery' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wccr-abandoned-carts&tab=settings' ) ); ?>"
                   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'wc-cart-recovery' ); ?>
                </a>
            </nav>

            <div class="wccr-admin-content">
                <?php
                switch ( $current_tab ) {
                    case 'stats':
                        $this->render_stats_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    default:
                        $this->render_carts_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the carts tab
     */
    private function render_carts_tab() {
        $list_table = new WCCR_Admin_List_Table();
        $list_table->prepare_items();

        ?>
        <form method="post">
            <?php
            $list_table->display();
            ?>
        </form>
        <?php
    }

    /**
     * Render the statistics tab
     */
    private function render_stats_tab() {
        $stats = WCCR_Cron_Handler::get_statistics();

        ?>
        <div class="wccr-stats-grid">
            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Total Abandoned', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number"><?php echo esc_html( $stats['total_abandoned'] ); ?></span>
            </div>

            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Total Recovered', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number wccr-stat-success"><?php echo esc_html( $stats['total_recovered'] ); ?></span>
            </div>

            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Recovery Rate', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number"><?php echo esc_html( $stats['recovery_rate'] ); ?>%</span>
            </div>

            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Emails Sent', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number"><?php echo esc_html( $stats['total_email_sent'] ); ?></span>
            </div>

            <div class="wccr-stat-box wccr-stat-box-wide">
                <h3><?php esc_html_e( 'Abandoned Cart Value', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number wccr-stat-warning"><?php echo wp_kses_post( wc_price( $stats['abandoned_value'] ) ); ?></span>
            </div>

            <div class="wccr-stat-box wccr-stat-box-wide">
                <h3><?php esc_html_e( 'Recovered Value', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number wccr-stat-success"><?php echo wp_kses_post( wc_price( $stats['recovered_value'] ) ); ?></span>
            </div>
        </div>

        <h2><?php esc_html_e( 'Today', 'wc-cart-recovery' ); ?></h2>
        <div class="wccr-stats-grid">
            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Abandoned Today', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number"><?php echo esc_html( $stats['today_abandoned'] ); ?></span>
            </div>

            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Recovered Today', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number wccr-stat-success"><?php echo esc_html( $stats['today_recovered'] ); ?></span>
            </div>
        </div>

        <h2><?php esc_html_e( 'This Week', 'wc-cart-recovery' ); ?></h2>
        <div class="wccr-stats-grid">
            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Abandoned This Week', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number"><?php echo esc_html( $stats['week_abandoned'] ); ?></span>
            </div>

            <div class="wccr-stat-box">
                <h3><?php esc_html_e( 'Recovered This Week', 'wc-cart-recovery' ); ?></h3>
                <span class="wccr-stat-number wccr-stat-success"><?php echo esc_html( $stats['week_recovered'] ); ?></span>
            </div>
        </div>
        <?php
    }

    /**
     * Render the settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields( 'wccr_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="wccr_enabled"><?php esc_html_e( 'Enable Cart Recovery', 'wc-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <select name="wccr_enabled" id="wccr_enabled">
                            <option value="yes" <?php selected( get_option( 'wccr_enabled', 'yes' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'wc-cart-recovery' ); ?>
                            </option>
                            <option value="no" <?php selected( get_option( 'wccr_enabled', 'yes' ), 'no' ); ?>>
                                <?php esc_html_e( 'No', 'wc-cart-recovery' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Enable or disable cart tracking and recovery emails.', 'wc-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wccr_abandonment_time"><?php esc_html_e( 'Abandonment Time', 'wc-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wccr_abandonment_time" id="wccr_abandonment_time"
                               value="<?php echo esc_attr( get_option( 'wccr_abandonment_time', 60 ) ); ?>"
                               min="15" max="1440" step="15" class="small-text" />
                        <?php esc_html_e( 'minutes', 'wc-cart-recovery' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Time after which a cart is considered abandoned. Minimum 15 minutes.', 'wc-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wccr_email_enabled"><?php esc_html_e( 'Send Recovery Emails', 'wc-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <select name="wccr_email_enabled" id="wccr_email_enabled">
                            <option value="yes" <?php selected( get_option( 'wccr_email_enabled', 'yes' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'wc-cart-recovery' ); ?>
                            </option>
                            <option value="no" <?php selected( get_option( 'wccr_email_enabled', 'yes' ), 'no' ); ?>>
                                <?php esc_html_e( 'No', 'wc-cart-recovery' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Automatically send recovery emails to customers with abandoned carts.', 'wc-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="wccr_cleanup_days"><?php esc_html_e( 'Data Retention', 'wc-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="wccr_cleanup_days" id="wccr_cleanup_days"
                               value="<?php echo esc_attr( get_option( 'wccr_cleanup_days', 30 ) ); ?>"
                               min="7" max="365" step="1" class="small-text" />
                        <?php esc_html_e( 'days', 'wc-cart-recovery' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Number of days to keep abandoned cart data before automatic cleanup.', 'wc-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Email Settings', 'wc-cart-recovery' ); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %s: Link to WooCommerce email settings */
                esc_html__( 'Configure the abandoned cart email template in %s.', 'wc-cart-recovery' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=wccr_email_abandoned_cart' ) ) . '">' .
                esc_html__( 'WooCommerce Email Settings', 'wc-cart-recovery' ) . '</a>'
            );
            ?>
        </p>
        <?php
    }
}
