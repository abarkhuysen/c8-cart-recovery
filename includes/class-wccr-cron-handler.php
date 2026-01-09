<?php
/**
 * Cron Handler Class
 *
 * Handles scheduled tasks for processing abandoned carts and sending emails
 *
 * @package WC_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCCR_Cron_Handler class
 */
class WCCR_Cron_Handler {

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
        // Add custom cron interval
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );

        // Process abandoned carts
        add_action( 'wccr_process_abandoned_carts', array( $this, 'process_abandoned_carts' ) );

        // Cleanup old carts
        add_action( 'wccr_cleanup_old_carts', array( $this, 'cleanup_old_carts' ) );
    }

    /**
     * Add custom cron interval
     *
     * @param array $schedules Cron schedules.
     * @return array
     */
    public function add_cron_interval( $schedules ) {
        if ( ! isset( $schedules['fifteen_minutes'] ) ) {
            $schedules['fifteen_minutes'] = array(
                'interval' => 900, // 15 minutes
                'display'  => __( 'Every 15 Minutes', 'wc-cart-recovery' ),
            );
        }
        return $schedules;
    }

    /**
     * Process abandoned carts and send emails
     */
    public function process_abandoned_carts() {
        // Check if plugin and email are enabled
        if ( get_option( 'wccr_enabled', 'yes' ) !== 'yes' ) {
            return;
        }

        if ( get_option( 'wccr_email_enabled', 'yes' ) !== 'yes' ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        // Get abandonment threshold (in minutes)
        $abandonment_time = absint( get_option( 'wccr_abandonment_time', 60 ) );
        $threshold_time   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$abandonment_time} minutes" ) );

        // Find carts that:
        // 1. Have an email address
        // 2. Haven't been updated in X minutes
        // 3. Status is 'abandoned'
        // 4. Haven't had email sent yet
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $abandoned_carts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name
                WHERE user_email IS NOT NULL
                AND user_email != ''
                AND status = 'abandoned'
                AND email_sent_count = 0
                AND updated_at < %s
                ORDER BY updated_at ASC
                LIMIT 50",
                $threshold_time
            )
        );

        if ( empty( $abandoned_carts ) ) {
            return;
        }

        // Get WooCommerce email instance
        $mailer = WC()->mailer();
        $emails = $mailer->get_emails();

        if ( ! isset( $emails['WCCR_Email_Abandoned_Cart'] ) ) {
            return;
        }

        $email = $emails['WCCR_Email_Abandoned_Cart'];

        foreach ( $abandoned_carts as $cart ) {
            // Double-check email is valid
            if ( ! is_email( $cart->user_email ) ) {
                continue;
            }

            // Check if cart has items
            $cart_contents = maybe_unserialize( $cart->cart_contents );
            if ( empty( $cart_contents ) ) {
                continue;
            }

            // Send email
            $email->trigger( $cart );

            // Small delay between emails to avoid rate limiting
            usleep( 100000 ); // 0.1 seconds
        }
    }

    /**
     * Cleanup old abandoned carts
     */
    public function cleanup_old_carts() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        // Get cleanup threshold (in days)
        $cleanup_days   = absint( get_option( 'wccr_cleanup_days', 30 ) );
        $threshold_time = gmdate( 'Y-m-d H:i:s', strtotime( "-{$cleanup_days} days" ) );

        // Delete old abandoned and recovered carts
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table_name
                WHERE created_at < %s",
                $threshold_time
            )
        );

        // Log cleanup action
        do_action( 'wccr_carts_cleaned_up', $cleanup_days );
    }

    /**
     * Get statistics about abandoned carts
     *
     * @return array
     */
    public static function get_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats = array(
            'total_abandoned'        => $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'abandoned'" ),
            'total_recovered'        => $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE status = 'recovered'" ),
            'total_email_sent'       => $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE email_sent_count > 0" ),
            'abandoned_value'        => $wpdb->get_var( "SELECT SUM(cart_total) FROM $table_name WHERE status = 'abandoned'" ),
            'recovered_value'        => $wpdb->get_var( "SELECT SUM(cart_total) FROM $table_name WHERE status = 'recovered'" ),
            'recovery_rate'          => 0,
            'today_abandoned'        => 0,
            'today_recovered'        => 0,
            'week_abandoned'         => 0,
            'week_recovered'         => 0,
        );

        // Calculate recovery rate
        if ( $stats['total_email_sent'] > 0 ) {
            $stats['recovery_rate'] = round( ( $stats['total_recovered'] / $stats['total_email_sent'] ) * 100, 1 );
        }

        // Today's stats
        $today = gmdate( 'Y-m-d' );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats['today_abandoned'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = %s AND status = 'abandoned'",
                $today
            )
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats['today_recovered'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(recovered_at) = %s",
                $today
            )
        );

        // This week's stats
        $week_start = gmdate( 'Y-m-d', strtotime( 'monday this week' ) );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats['week_abandoned'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE created_at >= %s AND status = 'abandoned'",
                $week_start
            )
        );
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $stats['week_recovered'] = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE recovered_at >= %s",
                $week_start
            )
        );

        // Ensure numeric values
        foreach ( $stats as $key => $value ) {
            $stats[ $key ] = $value ? $value : 0;
        }

        return $stats;
    }

    /**
     * Mark email as sent for a cart
     *
     * @param int $cart_id Cart ID.
     */
    public static function mark_email_sent( $cart_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_name
                SET email_sent_count = email_sent_count + 1,
                    last_email_sent = %s,
                    status = 'email_sent'
                WHERE id = %d",
                current_time( 'mysql' ),
                $cart_id
            )
        );
    }
}
