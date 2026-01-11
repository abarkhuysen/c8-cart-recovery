<?php
/**
 * Abandoned Cart Email Template (HTML)
 *
 * @package C8_Cart_Recovery
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
    <?php
    printf(
        /* translators: %s: Customer first name */
        esc_html__( 'Hi %s,', 'c8-cart-recovery' ),
        esc_html( $cart_data->user_first_name ? $cart_data->user_first_name : __( 'there', 'c8-cart-recovery' ) )
    );
    ?>
</p>

<p>
    <?php esc_html_e( 'We noticed you left some items in your cart. Don\'t worry, we\'ve saved them for you!', 'c8-cart-recovery' ); ?>
</p>

<?php
$cart_items = $email->get_cart_items();

if ( ! empty( $cart_items ) ) :
    ?>
    <h2><?php esc_html_e( 'Your Cart', 'c8-cart-recovery' ); ?></h2>

    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; border: 1px solid #e5e5e5; margin-bottom: 20px;" border="1">
        <thead>
            <tr>
                <th class="td" scope="col" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                    <?php esc_html_e( 'Product', 'c8-cart-recovery' ); ?>
                </th>
                <th class="td" scope="col" style="text-align: center; border: 1px solid #e5e5e5; padding: 12px;">
                    <?php esc_html_e( 'Quantity', 'c8-cart-recovery' ); ?>
                </th>
                <th class="td" scope="col" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">
                    <?php esc_html_e( 'Price', 'c8-cart-recovery' ); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $cart_items as $item ) : ?>
                <tr>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;">
                        <?php
                        $product = $item['product'];
                        $thumbnail = $product->get_image( array( 50, 50 ) );

                        if ( $thumbnail ) {
                            echo wp_kses_post( $thumbnail ) . ' ';
                        }

                        echo esc_html( $product->get_name() );
                        ?>
                    </td>
                    <td class="td" style="text-align: center; border: 1px solid #e5e5e5; padding: 12px;">
                        <?php echo esc_html( $item['quantity'] ); ?>
                    </td>
                    <td class="td" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">
                        <?php echo wp_kses_post( wc_price( $item['line_total'], array( 'currency' => $cart_data->currency ) ) ); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <th class="td" scope="row" colspan="2" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px;">
                    <?php esc_html_e( 'Total:', 'c8-cart-recovery' ); ?>
                </th>
                <td class="td" style="text-align: right; border: 1px solid #e5e5e5; padding: 12px; font-weight: bold;">
                    <?php echo wp_kses_post( wc_price( $cart_data->cart_total, array( 'currency' => $cart_data->currency ) ) ); ?>
                </td>
            </tr>
        </tfoot>
    </table>
<?php else : ?>
    <p><em><?php esc_html_e( 'Your cart items are no longer available. Click the button below to browse our products.', 'c8-cart-recovery' ); ?></em></p>
<?php endif; ?>

<p style="margin: 30px 0; text-align: center;">
    <a href="<?php echo esc_url( $recovery_url ); ?>" style="background-color: #841d95; color: #ffffff; padding: 15px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">
        <?php esc_html_e( 'Complete Your Purchase', 'c8-cart-recovery' ); ?>
    </a>
</p>

<p>
    <?php esc_html_e( 'This link will restore your cart and take you directly to checkout.', 'c8-cart-recovery' ); ?>
</p>

<?php if ( $additional_content ) : ?>
    <p><?php echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) ); ?></p>
<?php endif; ?>

<?php
/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
