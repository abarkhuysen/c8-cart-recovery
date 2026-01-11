<?php
/**
 * Admin Class
 *
 * Handles admin pages and settings
 *
 * @package C8_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * C8CR_Admin class
 */
class C8CR_Admin {

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
            __( 'Abandoned Carts', 'c8-cart-recovery' ),
            __( 'Abandoned Carts', 'c8-cart-recovery' ),
            'manage_woocommerce',
            'c8cr-abandoned-carts',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_c8cr-abandoned-carts' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'c8cr-admin',
            C8CR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            C8CR_VERSION
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

            if ( ! wp_verify_nonce( $nonce, 'c8cr_action_' . $cart_id ) ) {
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

            wp_safe_redirect( admin_url( 'admin.php?page=c8cr-abandoned-carts' ) );
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
        $cart = C8CR_Plugin::get_cart( $cart_id );

        if ( ! $cart || ! is_email( $cart->user_email ) ) {
            return;
        }

        // Get WooCommerce email instance
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        if ( isset( $emails['C8CR_Email_Abandoned_Cart'] ) ) {
            $emails['C8CR_Email_Abandoned_Cart']->trigger( $cart );
        }
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting( 'c8cr_settings', 'c8cr_enabled' );
        register_setting( 'c8cr_settings', 'c8cr_abandonment_time' );
        register_setting( 'c8cr_settings', 'c8cr_email_enabled' );
        register_setting( 'c8cr_settings', 'c8cr_cleanup_days' );
    }

    /**
     * Render the admin page
     */
    public function render_admin_page() {
        // Get current tab
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'carts';

        ?>
        <div class="wrap c8cr-admin-wrap">
            <h1><?php esc_html_e( 'Abandoned Carts', 'c8-cart-recovery' ); ?></h1>

            <nav class="nav-tab-wrapper woo-nav-tab-wrapper">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=c8cr-abandoned-carts&tab=carts' ) ); ?>"
                   class="nav-tab <?php echo 'carts' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Abandoned Carts', 'c8-cart-recovery' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=c8cr-abandoned-carts&tab=stats' ) ); ?>"
                   class="nav-tab <?php echo 'stats' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Statistics', 'c8-cart-recovery' ); ?>
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=c8cr-abandoned-carts&tab=settings' ) ); ?>"
                   class="nav-tab <?php echo 'settings' === $current_tab ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e( 'Settings', 'c8-cart-recovery' ); ?>
                </a>
            </nav>

            <div class="c8cr-admin-content">
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
        $list_table = new C8CR_Admin_List_Table();
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
        $stats = C8CR_Cron_Handler::get_statistics();

        ?>
        <div class="c8cr-stats-grid">
            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Total Abandoned', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number"><?php echo esc_html( $stats['total_abandoned'] ); ?></span>
            </div>

            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Total Recovered', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number c8cr-stat-success"><?php echo esc_html( $stats['total_recovered'] ); ?></span>
            </div>

            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Recovery Rate', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number"><?php echo esc_html( $stats['recovery_rate'] ); ?>%</span>
            </div>

            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Emails Sent', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number"><?php echo esc_html( $stats['total_email_sent'] ); ?></span>
            </div>

            <div class="c8cr-stat-box c8cr-stat-box-wide">
                <h3><?php esc_html_e( 'Abandoned Cart Value', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number c8cr-stat-warning"><?php echo wp_kses_post( wc_price( $stats['abandoned_value'] ) ); ?></span>
            </div>

            <div class="c8cr-stat-box c8cr-stat-box-wide">
                <h3><?php esc_html_e( 'Recovered Value', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number c8cr-stat-success"><?php echo wp_kses_post( wc_price( $stats['recovered_value'] ) ); ?></span>
            </div>
        </div>

        <h2><?php esc_html_e( 'Today', 'c8-cart-recovery' ); ?></h2>
        <div class="c8cr-stats-grid">
            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Abandoned Today', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number"><?php echo esc_html( $stats['today_abandoned'] ); ?></span>
            </div>

            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Recovered Today', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number c8cr-stat-success"><?php echo esc_html( $stats['today_recovered'] ); ?></span>
            </div>
        </div>

        <h2><?php esc_html_e( 'This Week', 'c8-cart-recovery' ); ?></h2>
        <div class="c8cr-stats-grid">
            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Abandoned This Week', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number"><?php echo esc_html( $stats['week_abandoned'] ); ?></span>
            </div>

            <div class="c8cr-stat-box">
                <h3><?php esc_html_e( 'Recovered This Week', 'c8-cart-recovery' ); ?></h3>
                <span class="c8cr-stat-number c8cr-stat-success"><?php echo esc_html( $stats['week_recovered'] ); ?></span>
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
            <?php settings_fields( 'c8cr_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="c8cr_enabled"><?php esc_html_e( 'Enable Cart Recovery', 'c8-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <select name="c8cr_enabled" id="c8cr_enabled">
                            <option value="yes" <?php selected( get_option( 'c8cr_enabled', 'yes' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'c8-cart-recovery' ); ?>
                            </option>
                            <option value="no" <?php selected( get_option( 'c8cr_enabled', 'yes' ), 'no' ); ?>>
                                <?php esc_html_e( 'No', 'c8-cart-recovery' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Enable or disable cart tracking and recovery emails.', 'c8-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="c8cr_abandonment_time"><?php esc_html_e( 'Abandonment Time', 'c8-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="c8cr_abandonment_time" id="c8cr_abandonment_time"
                               value="<?php echo esc_attr( get_option( 'c8cr_abandonment_time', 60 ) ); ?>"
                               min="15" max="1440" step="15" class="small-text" />
                        <?php esc_html_e( 'minutes', 'c8-cart-recovery' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Time after which a cart is considered abandoned. Minimum 15 minutes.', 'c8-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="c8cr_email_enabled"><?php esc_html_e( 'Send Recovery Emails', 'c8-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <select name="c8cr_email_enabled" id="c8cr_email_enabled">
                            <option value="yes" <?php selected( get_option( 'c8cr_email_enabled', 'yes' ), 'yes' ); ?>>
                                <?php esc_html_e( 'Yes', 'c8-cart-recovery' ); ?>
                            </option>
                            <option value="no" <?php selected( get_option( 'c8cr_email_enabled', 'yes' ), 'no' ); ?>>
                                <?php esc_html_e( 'No', 'c8-cart-recovery' ); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php esc_html_e( 'Automatically send recovery emails to customers with abandoned carts.', 'c8-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="c8cr_cleanup_days"><?php esc_html_e( 'Data Retention', 'c8-cart-recovery' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="c8cr_cleanup_days" id="c8cr_cleanup_days"
                               value="<?php echo esc_attr( get_option( 'c8cr_cleanup_days', 30 ) ); ?>"
                               min="7" max="365" step="1" class="small-text" />
                        <?php esc_html_e( 'days', 'c8-cart-recovery' ); ?>
                        <p class="description">
                            <?php esc_html_e( 'Number of days to keep abandoned cart data before automatic cleanup.', 'c8-cart-recovery' ); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Email Settings', 'c8-cart-recovery' ); ?></h2>
        <p>
            <?php
            printf(
                /* translators: %s: Link to WooCommerce email settings */
                esc_html__( 'Configure the abandoned cart email template in %s.', 'c8-cart-recovery' ),
                '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&section=c8cr_email_abandoned_cart' ) ) . '">' .
                esc_html__( 'WooCommerce Email Settings', 'c8-cart-recovery' ) . '</a>'
            );
            ?>
        </p>
        <?php
    }
}
