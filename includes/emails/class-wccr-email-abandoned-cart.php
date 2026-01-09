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
            $product_id   = isset( $cart_item['variation_id'] ) && $cart_item['variation_id']
                ? $cart_item['variation_id']
                : $cart_item['product_id'];
            $quantity     = isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1;
            $product      = wc_get_product( $product_id );

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
}
