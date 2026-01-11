<?php
/**
 * Cart Recovery Class
 *
 * Handles cart recovery via unique token URLs
 *
 * @package C8_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * C8CR_Cart_Recovery class
 */
class C8CR_Cart_Recovery {

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
        // Handle recovery URL - use template_redirect to ensure WooCommerce is fully loaded
        add_action( 'template_redirect', array( $this, 'handle_recovery_request' ) );

        // Add unsubscribe handling
        add_action( 'template_redirect', array( $this, 'handle_unsubscribe_request' ) );
    }

    /**
     * Handle cart recovery request
     */
    public function handle_recovery_request() {
        if ( ! isset( $_GET['c8cr_recover_cart'] ) ) {
            return;
        }

        // Ensure WooCommerce is available
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['c8cr_recover_cart'] ) );

        if ( empty( $token ) ) {
            return;
        }

        // Get the abandoned cart
        $cart = C8CR_Plugin::get_cart_by_token( $token );

        if ( ! $cart ) {
            wc_add_notice( __( 'Invalid or expired cart recovery link.', 'c8-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        // Check if cart is already recovered
        if ( $cart->status === 'recovered' ) {
            wc_add_notice( __( 'This cart has already been recovered.', 'c8-cart-recovery' ), 'notice' );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }

        // Check if cart is too old (7 days)
        $cart_age = strtotime( current_time( 'mysql' ) ) - strtotime( $cart->created_at );
        if ( $cart_age > 7 * DAY_IN_SECONDS ) {
            wc_add_notice( __( 'This cart recovery link has expired.', 'c8-cart-recovery' ), 'error' );
            wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
            exit;
        }

        // Restore the cart
        $this->restore_cart( $cart );

        // Mark cart as recovered
        $this->mark_as_recovered( $cart->id );

        // Track recovery
        do_action( 'c8cr_cart_recovered', $cart );

        // Add success notice
        wc_add_notice( __( 'Your cart has been restored! Complete your checkout below.', 'c8-cart-recovery' ), 'success' );

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
        // Ensure WooCommerce is available
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        // Initialize WooCommerce session if needed
        if ( is_null( WC()->session ) ) {
            WC()->initialize_session();
        }

        // Ensure customer session cookie is set
        if ( WC()->session && ! WC()->session->has_session() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        // Ensure cart is available
        if ( is_null( WC()->cart ) ) {
            WC()->cart = new WC_Cart();
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
        if ( ! isset( $_GET['c8cr_unsubscribe'] ) ) {
            return;
        }

        // Ensure WooCommerce is available
        if ( ! function_exists( 'WC' ) || ! WC() ) {
            return;
        }

        $token = sanitize_text_field( wp_unslash( $_GET['c8cr_unsubscribe'] ) );

        if ( empty( $token ) ) {
            return;
        }

        // Get the abandoned cart
        $cart = C8CR_Plugin::get_cart_by_token( $token );

        if ( ! $cart ) {
            wc_add_notice( __( 'Invalid unsubscribe link.', 'c8-cart-recovery' ), 'error' );
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

        wc_add_notice( __( 'You have been unsubscribed from cart reminder emails.', 'c8-cart-recovery' ), 'success' );

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
        return add_query_arg( 'c8cr_recover_cart', $token, home_url( '/' ) );
    }

    /**
     * Get unsubscribe URL for a cart
     *
     * @param string $token Recovery token.
     * @return string
     */
    public static function get_unsubscribe_url( $token ) {
        return add_query_arg( 'c8cr_unsubscribe', $token, home_url( '/' ) );
    }
}
