<?php
/**
 * Abandoned Cart Email Template (Plain Text)
 *
 * @package C8_Cart_Recovery
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

echo "=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo esc_html( wp_strip_all_tags( $email_heading ) );
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

printf(
    /* translators: %s: Customer first name */
    esc_html__( 'Hi %s,', 'c8-cart-recovery' ),
    esc_html( $cart_data->user_first_name ? $cart_data->user_first_name : __( 'there', 'c8-cart-recovery' ) )
);

echo "\n\n";

echo esc_html__( 'We noticed you left some items in your cart. Don\'t worry, we\'ve saved them for you!', 'c8-cart-recovery' );

echo "\n\n";

$cart_items = $email->get_cart_items();

if ( ! empty( $cart_items ) ) {
    echo "----------------------------------------\n";
    echo esc_html__( 'Your Cart', 'c8-cart-recovery' ) . "\n";
    echo "----------------------------------------\n\n";

    foreach ( $cart_items as $item ) {
        $product = $item['product'];

        printf(
            '%s x %d - %s',
            esc_html( $product->get_name() ),
            esc_html( $item['quantity'] ),
            wp_strip_all_tags( wc_price( $item['line_total'], array( 'currency' => $cart_data->currency ) ) )
        );
        echo "\n";
    }

    echo "\n";
    printf(
        /* translators: %s: Cart total */
        esc_html__( 'Total: %s', 'c8-cart-recovery' ),
        wp_strip_all_tags( wc_price( $cart_data->cart_total, array( 'currency' => $cart_data->currency ) ) )
    );
    echo "\n\n";
}

echo "----------------------------------------\n";
echo esc_html__( 'Complete Your Purchase:', 'c8-cart-recovery' ) . "\n";
echo esc_url( $recovery_url );
echo "\n----------------------------------------\n\n";

echo esc_html__( 'This link will restore your cart and take you directly to checkout.', 'c8-cart-recovery' );

echo "\n\n";

if ( $additional_content ) {
    echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) );
    echo "\n\n";
}

echo "----------------------------------------\n";
printf(
    /* translators: %s: Unsubscribe URL */
    esc_html__( 'Don\'t want to receive these emails? Unsubscribe: %s', 'c8-cart-recovery' ),
    esc_url( $unsubscribe_url )
);
echo "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
