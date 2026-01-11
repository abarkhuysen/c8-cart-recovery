<?php
/**
 * Cart Tracker Class
 *
 * Tracks cart activity and saves abandoned cart data
 *
 * @package C8_Cart_Recovery
 */

namespace Creative8\CartRecovery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CartTracker class
 */
class CartTracker {

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
		// Check if plugin is enabled
		if ( get_option( 'c8cr_enabled', 'yes' ) !== 'yes' ) {
			return;
		}

		// Track cart additions
		add_action( 'woocommerce_add_to_cart', array( $this, 'track_cart' ), 10, 6 );

		// Track cart updates
		add_action( 'woocommerce_cart_item_removed', array( $this, 'track_cart_update' ) );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'track_cart_update' ) );
		add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'track_cart_update' ) );

		// Update cart total after calculations
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_cart_total' ), 10, 1 );

		// Mark cart as recovered when order is created
		add_action( 'woocommerce_checkout_order_created', array( $this, 'mark_cart_recovered' ), 10, 1 );

		// Also mark as recovered when order is processed
		add_action( 'woocommerce_thankyou', array( $this, 'mark_cart_recovered_on_thankyou' ), 10, 1 );

		// Handle cart emptied
		add_action( 'woocommerce_cart_emptied', array( $this, 'handle_cart_emptied' ) );
	}

	/**
	 * Get the current session ID
	 *
	 * @return string
	 */
	private function get_session_id() {
		if ( is_user_logged_in() ) {
			return 'user_' . get_current_user_id();
		}

		// For guests, use WooCommerce session
		if ( WC()->session ) {
			$session_id = WC()->session->get_customer_id();
			if ( $session_id ) {
				return 'guest_' . $session_id;
			}
		}

		return '';
	}

	/**
	 * Track cart when item is added
	 *
	 * @param string $cart_item_key Cart item key.
	 * @param int    $product_id Product ID.
	 * @param int    $quantity Quantity.
	 * @param int    $variation_id Variation ID.
	 * @param array  $variation Variation data.
	 * @param array  $cart_item_data Cart item data.
	 */
	public function track_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {
		$this->save_cart_data();
	}

	/**
	 * Track cart updates
	 */
	public function track_cart_update() {
		$this->save_cart_data();
	}

	/**
	 * Update cart total after calculations
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function update_cart_total( $cart ) {
		// Avoid infinite loops
		static $updating = false;
		if ( $updating ) {
			return;
		}
		$updating = true;

		$this->save_cart_data();

		$updating = false;
	}

	/**
	 * Save cart data to database
	 */
	private function save_cart_data() {
		// Don't track if cart is empty
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$session_id = $this->get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'c8cr_abandoned_carts';

		// Serialize cart contents
		$cart_contents = array();
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			$cart_contents[ $key ] = array(
				'product_id'   => $item['product_id'],
				'quantity'     => $item['quantity'],
				'variation_id' => isset( $item['variation_id'] ) ? $item['variation_id'] : 0,
				'variation'    => isset( $item['variation'] ) ? $item['variation'] : array(),
				'line_total'   => isset( $item['line_total'] ) ? $item['line_total'] : 0,
			);
		}

		$cart_total = WC()->cart->get_cart_contents_total();
		$currency   = get_woocommerce_currency();

		// Check if cart already exists for this session
		$existing_cart = Plugin::get_cart_by_session( $session_id );

		// Get user data if logged in
		$user_id    = is_user_logged_in() ? get_current_user_id() : null;
		$user_email = null;
		$first_name = null;

		if ( $user_id ) {
			$user       = get_userdata( $user_id );
			$user_email = $user->user_email;
			$first_name = $user->first_name;
		}

		// Get customer email if available
		if ( ! $user_email && WC()->customer ) {
			$user_email = WC()->customer->get_billing_email();
			$first_name = WC()->customer->get_billing_first_name();
		}

		$ip_address = $this->get_client_ip();

		if ( $existing_cart ) {
			// Update existing cart
			$wpdb->update(
				$table_name,
				array(
					'cart_contents'   => maybe_serialize( $cart_contents ),
					'cart_total'      => $cart_total,
					'currency'        => $currency,
					'user_email'      => $user_email ? $user_email : $existing_cart->user_email,
					'user_first_name' => $first_name ? $first_name : $existing_cart->user_first_name,
					'ip_address'      => $ip_address,
					'status'          => 'abandoned', // Reset status on cart update
				),
				array( 'id' => $existing_cart->id ),
				array( '%s', '%f', '%s', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			// Create new cart record
			$recovery_token = Plugin::generate_recovery_token();

			$wpdb->insert(
				$table_name,
				array(
					'session_id'      => $session_id,
					'user_id'         => $user_id,
					'user_email'      => $user_email,
					'user_first_name' => $first_name,
					'cart_contents'   => maybe_serialize( $cart_contents ),
					'cart_total'      => $cart_total,
					'currency'        => $currency,
					'recovery_token'  => $recovery_token,
					'status'          => 'abandoned',
					'ip_address'      => $ip_address,
					'created_at'      => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Update cart with email address (called from AJAX)
	 *
	 * @param string $email Email address.
	 * @param string $first_name First name.
	 * @param string $phone Phone number.
	 * @return bool
	 */
	public function update_cart_email( $email, $first_name = '', $phone = '' ) {
		$session_id = $this->get_session_id();
		if ( empty( $session_id ) ) {
			return false;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'c8cr_abandoned_carts';

		$existing_cart = Plugin::get_cart_by_session( $session_id );

		if ( $existing_cart ) {
			$update_data   = array( 'user_email' => $email );
			$update_format = array( '%s' );

			if ( ! empty( $first_name ) ) {
				$update_data['user_first_name'] = $first_name;
				$update_format[]                = '%s';
			}

			if ( ! empty( $phone ) ) {
				$update_data['user_phone'] = $phone;
				$update_format[]           = '%s';
			}

			$result = $wpdb->update(
				$table_name,
				$update_data,
				array( 'id' => $existing_cart->id ),
				$update_format,
				array( '%d' )
			);

			return $result !== false;
		}

		return false;
	}

	/**
	 * Mark cart as recovered when order is created
	 *
	 * @param \WC_Order $order Order object.
	 */
	public function mark_cart_recovered( $order ) {
		$session_id = $this->get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		$this->mark_recovered_by_session( $session_id, $order->get_id() );
	}

	/**
	 * Mark cart as recovered on thank you page
	 *
	 * @param int $order_id Order ID.
	 */
	public function mark_cart_recovered_on_thankyou( $order_id ) {
		$session_id = $this->get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		$this->mark_recovered_by_session( $session_id, $order_id );
	}

	/**
	 * Mark cart as recovered by session ID
	 *
	 * @param string $session_id Session ID.
	 * @param int    $order_id Order ID.
	 */
	private function mark_recovered_by_session( $session_id, $order_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'c8cr_abandoned_carts';

		$wpdb->update(
			$table_name,
			array(
				'status'       => 'recovered',
				'recovered_at' => current_time( 'mysql' ),
			),
			array( 'session_id' => $session_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Handle cart emptied event
	 */
	public function handle_cart_emptied() {
		$session_id = $this->get_session_id();
		if ( empty( $session_id ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'c8cr_abandoned_carts';

		// Delete the cart record when cart is manually emptied
		$wpdb->delete(
			$table_name,
			array(
				'session_id' => $session_id,
				'status'     => 'abandoned',
			),
			array( '%s', '%s' )
		);
	}

	/**
	 * Get client IP address
	 *
	 * @return string
	 */
	private function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		// Handle multiple IPs
		if ( strpos( $ip, ',' ) !== false ) {
			$ips = explode( ',', $ip );
			$ip  = trim( $ips[0] );
		}

		return $ip;
	}
}
