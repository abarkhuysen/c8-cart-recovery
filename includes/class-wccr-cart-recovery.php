<?php
/**
 * Cart Recovery Class
 *
 * Handles cart recovery via unique token URLs
 *
 * @package WC_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCCR_Cart_Recovery class
 */
class WCCR_Cart_Recovery {

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
        // Handle recovery URL
        add_action( 'init', array( $this, 'handle_recovery_request' ), 5 );

        // Add unsubscribe handling
        add_action( 'init', array( $this, 'handle_unsubscribe_request' ), 5 );
    }

    /**
     * Handle cart recovery request
     */
    public function handle_recovery_request() {
        if ( ! isset( $_GET['wccr_recover_cart'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['wccr_recover_cart'] ) );

        if ( empty( $token ) ) {
            return;
        }

        // Get the abandoned cart
        $cart = WCCR_Plugin::get_cart_by_token( $token );

        if ( ! $cart ) {
            wc_add_notice( __( 'Invalid or expired cart recovery link.', 'wc-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        // Check if cart is already recovered
        if ( $cart->status === 'recovered' ) {
            wc_add_notice( __( 'This cart has already been recovered.', 'wc-cart-recovery' ), 'notice' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // Check if cart is too old (7 days)
        $cart_age = strtotime( current_time( 'mysql' ) ) - strtotime( $cart->created_at );
        if ( $cart_age > 7 * DAY_IN_SECONDS ) {
            wc_add_notice( __( 'This cart recovery link has expired.', 'wc-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        // Restore the cart
        $this->restore_cart( $cart );

        // Mark cart as recovered
        $this->mark_as_recovered( $cart->id );

        // Track recovery
        do_action( 'wccr_cart_recovered', $cart );

        // Add success notice
        wc_add_notice( __( 'Your cart has been restored! Complete your checkout below.', 'wc-cart-recovery' ), 'success' );

        // Redirect to checkout
        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    /**
     * Restore cart from abandoned cart data
     *
     * @param object $cart Cart object from database.
     */
    private function restore_cart( $cart ) {
        // Initialize WooCommerce session if needed
        if ( ! WC()->session ) {
            WC()->initialize_session();
        }

        // Clear current cart
        WC()->cart->empty_cart();

        // Unserialize cart contents
        $cart_contents = maybe_unserialize( $cart->cart_contents );

        if ( empty( $cart_contents ) || ! is_array( $cart_contents ) ) {
            return;
        }

        // Add each item back to cart
        foreach ( $cart_contents as $item ) {
            $product_id   = isset( $item['product_id'] ) ? absint( $item['product_id'] ) : 0;
            $quantity     = isset( $item['quantity'] ) ? absint( $item['quantity'] ) : 1;
            $variation_id = isset( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
            $variation    = isset( $item['variation'] ) ? (array) $item['variation'] : array();

            if ( ! $product_id ) {
                continue;
            }

            // Check if product still exists and is purchasable
            $product = wc_get_product( $variation_id ? $variation_id : $product_id );

            if ( ! $product || ! $product->is_purchasable() ) {
                continue;
            }

            // Check if product is in stock
            if ( ! $product->is_in_stock() ) {
                continue;
            }

            // Add to cart
            WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variation );
        }
    }

    /**
     * Mark cart as recovered
     *
     * @param int $cart_id Cart ID.
     */
    private function mark_as_recovered( $cart_id ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        $wpdb->update(
            $table_name,
            array(
                'status'       => 'recovered',
                'recovered_at' => current_time( 'mysql' ),
            ),
            array( 'id' => $cart_id ),
            array( '%s', '%s' ),
            array( '%d' )
        );
    }

    /**
     * Handle unsubscribe request
     */
    public function handle_unsubscribe_request() {
        if ( ! isset( $_GET['wccr_unsubscribe'] ) ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['wccr_unsubscribe'] ) );

        if ( empty( $token ) ) {
            return;
        }

        // Get the abandoned cart
        $cart = WCCR_Plugin::get_cart_by_token( $token );

        if ( ! $cart ) {
            wc_add_notice( __( 'Invalid unsubscribe link.', 'wc-cart-recovery' ), 'error' );
            wp_safe_redirect( home_url() );
            exit;
        }

        // Mark as unsubscribed
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_abandoned_carts';

        $wpdb->update(
            $table_name,
            array( 'status' => 'unsubscribed' ),
            array( 'id' => $cart->id ),
            array( '%s' ),
            array( '%d' )
        );

        wc_add_notice( __( 'You have been unsubscribed from cart reminder emails.', 'wc-cart-recovery' ), 'success' );

        wp_safe_redirect( home_url() );
        exit;
    }

    /**
     * Get recovery URL for a cart
     *
     * @param string $token Recovery token.
     * @return string
     */
    public static function get_recovery_url( $token ) {
        return add_query_arg( 'wccr_recover_cart', $token, home_url( '/' ) );
    }

    /**
     * Get unsubscribe URL for a cart
     *
     * @param string $token Recovery token.
     * @return string
     */
    public static function get_unsubscribe_url( $token ) {
        return add_query_arg( 'wccr_unsubscribe', $token, home_url( '/' ) );
    }
}
