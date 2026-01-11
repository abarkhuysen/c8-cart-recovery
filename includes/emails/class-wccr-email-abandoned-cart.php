<?php
/**
 * Abandoned Cart Email Class
 *
 * Extends WC_Email to send cart abandonment reminder emails
 *
 * @package WC_Cart_Recovery
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WCCR_Email_Abandoned_Cart class
 */
class WCCR_Email_Abandoned_Cart extends WC_Email {

    /**
     * Cart data object
     *
     * @var object
     */
    public $cart_data;

    /**
     * Recovery URL
     *
     * @var string
     */
    public $recovery_url;

    /**
     * Unsubscribe URL
     *
     * @var string
     */
    public $unsubscribe_url;

    /**
     * Whether we are in preview mode
     *
     * @var bool
     */
    protected $is_preview = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->id             = 'wccr_abandoned_cart';
        $this->customer_email = true;
        $this->title          = __( 'Abandoned Cart Reminder', 'wc-cart-recovery' );
        $this->description    = __( 'Reminder emails sent to customers who abandon their cart.', 'wc-cart-recovery' );
        $this->template_html  = 'emails/abandoned-cart.php';
        $this->template_plain = 'emails/plain/abandoned-cart.php';
        $this->template_base  = WCCR_PLUGIN_PATH . 'templates/';

        $this->placeholders = array(
            '{customer_first_name}' => '',
            '{cart_total}'          => '',
            '{recovery_url}'        => '',
            '{site_title}'          => $this->get_blogname(),
        );

        // Call parent constructor
        parent::__construct();

        // Hook into email preview preparation
        add_filter( 'woocommerce_prepare_email_for_preview', array( $this, 'prepare_for_preview' ) );
    }

    /**
     * Get default subject
     *
     * @return string
     */
    public function get_default_subject() {
        return __( 'You left something behind at {site_title}', 'wc-cart-recovery' );
    }

    /**
     * Get default heading
     *
     * @return string
     */
    public function get_default_heading() {
        return __( 'Complete your purchase', 'wc-cart-recovery' );
    }

    /**
     * Get default additional content
     *
     * @return string
     */
    public function get_default_additional_content() {
        return __( 'Need help? Contact us anytime.', 'wc-cart-recovery' );
    }

    /**
     * Prepare the email for preview mode
     *
     * @param WC_Email $email The email object being prepared.
     * @return WC_Email
     */
    public function prepare_for_preview( $email ) {
        if ( $email->id !== $this->id ) {
            return $email;
        }

        $this->is_preview      = true;
        $this->cart_data       = $this->get_dummy_cart_data();
        $this->recovery_url    = home_url( '/?wccr_recover_cart=preview_token_example' );
        $this->unsubscribe_url = home_url( '/?wccr_unsubscribe=preview_token_example' );

        // Set placeholders for preview
        $this->placeholders['{customer_first_name}'] = 'John';
        $this->placeholders['{cart_total}']          = wc_price( $this->cart_data->cart_total, array( 'currency' => $this->cart_data->currency ) );
        $this->placeholders['{recovery_url}']        = $this->recovery_url;

        return $this;
    }

    /**
     * Get dummy cart data for email preview
     *
     * @return object
     */
    protected function get_dummy_cart_data() {
        $dummy_cart = new stdClass();

        $dummy_cart->id              = 12345;
        $dummy_cart->user_email      = 'customer@example.com';
        $dummy_cart->user_first_name = 'John';
        $dummy_cart->user_phone      = '555-555-5555';
        $dummy_cart->cart_total      = 149.97;
        $dummy_cart->currency        = get_woocommerce_currency();
        $dummy_cart->recovery_token  = 'preview_token_example';
        $dummy_cart->created_at      = current_time( 'mysql' );

        // Create dummy cart contents with preview products
        $dummy_cart->cart_contents = serialize( array(
            array(
                'product_id'   => 0,
                'variation_id' => 0,
                'quantity'     => 2,
                'line_total'   => 49.98,
                'preview_name' => __( 'Sample Product', 'wc-cart-recovery' ),
                'preview_price' => 24.99,
            ),
            array(
                'product_id'   => 0,
                'variation_id' => 0,
                'quantity'     => 1,
                'line_total'   => 99.99,
                'preview_name' => __( 'Premium Course Bundle', 'wc-cart-recovery' ),
                'preview_price' => 99.99,
            ),
        ) );

        /**
         * Filter the dummy cart data used in email preview.
         *
         * @param object $dummy_cart The dummy cart object.
         */
        return apply_filters( 'wccr_email_preview_dummy_cart', $dummy_cart );
    }

    /**
     * Trigger the email
     *
     * @param object $cart Cart data object.
     */
    public function trigger( $cart ) {
        $this->setup_locale();

        if ( is_object( $cart ) ) {
            $this->cart_data       = $cart;
            $this->recipient       = $cart->user_email;
            $this->recovery_url    = WCCR_Cart_Recovery::get_recovery_url( $cart->recovery_token );
            $this->unsubscribe_url = WCCR_Cart_Recovery::get_unsubscribe_url( $cart->recovery_token );

            // Set placeholders
            $this->placeholders['{customer_first_name}'] = $cart->user_first_name ? $cart->user_first_name : __( 'there', 'wc-cart-recovery' );
            $this->placeholders['{cart_total}']          = wc_price( $cart->cart_total, array( 'currency' => $cart->currency ) );
            $this->placeholders['{recovery_url}']        = $this->recovery_url;
        }

        if ( $this->is_enabled() && $this->get_recipient() ) {
            $result = $this->send(
                $this->get_recipient(),
                $this->get_subject(),
                $this->get_content(),
                $this->get_headers(),
                $this->get_attachments()
            );

            if ( $result ) {
                // Mark email as sent
                WCCR_Cron_Handler::mark_email_sent( $cart->id );

                // Action hook for tracking
                do_action( 'wccr_email_sent', $cart );
            }
        }

        $this->restore_locale();
    }

    /**
     * Get email content HTML
     *
     * @return string
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'cart_data'          => $this->cart_data,
                'recovery_url'       => $this->recovery_url,
                'unsubscribe_url'    => $this->unsubscribe_url,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => false,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Get email content plain text
     *
     * @return string
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'cart_data'          => $this->cart_data,
                'recovery_url'       => $this->recovery_url,
                'unsubscribe_url'    => $this->unsubscribe_url,
                'email_heading'      => $this->get_heading(),
                'additional_content' => $this->get_additional_content(),
                'sent_to_admin'      => false,
                'plain_text'         => true,
                'email'              => $this,
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Initialize form fields for settings
     */
    public function init_form_fields() {
        /* translators: %s: list of available placeholders */
        $placeholder_text = sprintf(
            __( 'Available placeholders: %s', 'wc-cart-recovery' ),
            '<code>{site_title}, {customer_first_name}, {cart_total}, {recovery_url}</code>'
        );

        $this->form_fields = array(
            'enabled'            => array(
                'title'   => __( 'Enable/Disable', 'wc-cart-recovery' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable this email notification', 'wc-cart-recovery' ),
                'default' => 'yes',
            ),
            'subject'            => array(
                'title'       => __( 'Subject', 'wc-cart-recovery' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_subject(),
                'default'     => '',
            ),
            'heading'            => array(
                'title'       => __( 'Email heading', 'wc-cart-recovery' ),
                'type'        => 'text',
                'desc_tip'    => true,
                'description' => $placeholder_text,
                'placeholder' => $this->get_default_heading(),
                'default'     => '',
            ),
            'additional_content' => array(
                'title'       => __( 'Additional content', 'wc-cart-recovery' ),
                'description' => __( 'Text to appear below the main email content.', 'wc-cart-recovery' ) . ' ' . $placeholder_text,
                'css'         => 'width:400px; height: 75px;',
                'placeholder' => $this->get_default_additional_content(),
                'type'        => 'textarea',
                'default'     => '',
                'desc_tip'    => true,
            ),
            'email_type'         => array(
                'title'       => __( 'Email type', 'wc-cart-recovery' ),
                'type'        => 'select',
                'description' => __( 'Choose which format of email to send.', 'wc-cart-recovery' ),
                'default'     => 'html',
                'class'       => 'email_type wc-enhanced-select',
                'options'     => $this->get_email_type_options(),
                'desc_tip'    => true,
            ),
        );
    }

    /**
     * Get cart items for display
     *
     * @return array
     */
    public function get_cart_items() {
        $items = array();

        if ( ! $this->cart_data || empty( $this->cart_data->cart_contents ) ) {
            return $items;
        }

        $cart_contents = maybe_unserialize( $this->cart_data->cart_contents );

        if ( ! is_array( $cart_contents ) ) {
            return $items;
        }

        foreach ( $cart_contents as $cart_item ) {
            $quantity = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;

            // Handle preview mode with dummy products
            if ( $this->is_preview && isset( $cart_item['preview_name'] ) ) {
                $items[] = array(
                    'product'    => $this->create_dummy_product( $cart_item['preview_name'], $cart_item['preview_price'] ),
                    'quantity'   => $quantity,
                    'line_total' => isset( $cart_item['line_total'] ) ? $cart_item['line_total'] : $cart_item['preview_price'] * $quantity,
                );
                continue;
            }

            // Normal mode - fetch real products
            $product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id']
                ? $cart_item['variation_id']
                : $cart_item['product_id'];
            $product    = wc_get_product( $product_id );

            if ( ! $product ) {
                continue;
            }

            $items[] = array(
                'product'    => $product,
                'quantity'   => $quantity,
                'line_total' => isset( $cart_item['line_total'] ) ? $cart_item['line_total'] : $product->get_price() * $quantity,
            );
        }

        return $items;
    }

    /**
     * Create a dummy product for email preview
     *
     * @param string $name  Product name.
     * @param float  $price Product price.
     * @return WC_Product
     */
    protected function create_dummy_product( $name, $price ) {
        $product = new WC_Product();
        $product->set_name( $name );
        $product->set_price( $price );

        return $product;
    }
}
