<?php
/**
 * Admin List Table Class
 *
 * Displays abandoned carts in a WordPress admin table
 *
 * @package C8_Cart_Recovery
 */

namespace Creative8\CartRecovery\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load WP_List_Table if not loaded
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * ListTable class
 */
class ListTable extends \WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'abandoned_cart',
				'plural'   => 'abandoned_carts',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'customer'   => __( 'Customer', 'c8-cart-recovery' ),
			'email'      => __( 'Email', 'c8-cart-recovery' ),
			'cart_total' => __( 'Cart Total', 'c8-cart-recovery' ),
			'products'   => __( 'Products', 'c8-cart-recovery' ),
			'status'     => __( 'Status', 'c8-cart-recovery' ),
			'created_at' => __( 'Abandoned', 'c8-cart-recovery' ),
			'email_sent' => __( 'Email Sent', 'c8-cart-recovery' ),
			'actions'    => __( 'Actions', 'c8-cart-recovery' ),
		);
	}

	/**
	 * Get sortable columns
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'cart_total' => array( 'cart_total', false ),
			'created_at' => array( 'created_at', true ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Get bulk actions
	 *
	 * @return array
	 */
	public function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'c8-cart-recovery' ),
			'resend' => __( 'Resend Email', 'c8-cart-recovery' ),
		);
	}

	/**
	 * Prepare items for display
	 */
	public function prepare_items() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'c8cr_abandoned_carts';

		// Columns
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination
		$per_page     = 20;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Sorting
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) && 'asc' === strtolower( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) ? 'ASC' : 'DESC';

		// Filtering
		$status_filter = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		// Build query
		$where = '1=1';
		if ( ! empty( $status_filter ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status_filter );
		}

		// Get total count
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total_items = $wpdb->get_var( "SELECT COUNT(*) FROM $table_name WHERE $where" );

		// Get items
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE $where ORDER BY $orderby $order LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// Set pagination
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Extra table navigation (filters)
	 *
	 * @param string $which Top or bottom.
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current_status = isset( $_REQUEST['status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['status'] ) ) : '';

		?>
		<div class="alignleft actions">
			<select name="status">
				<option value=""><?php esc_html_e( 'All statuses', 'c8-cart-recovery' ); ?></option>
				<option value="abandoned" <?php selected( $current_status, 'abandoned' ); ?>><?php esc_html_e( 'Abandoned', 'c8-cart-recovery' ); ?></option>
				<option value="email_sent" <?php selected( $current_status, 'email_sent' ); ?>><?php esc_html_e( 'Email Sent', 'c8-cart-recovery' ); ?></option>
				<option value="recovered" <?php selected( $current_status, 'recovered' ); ?>><?php esc_html_e( 'Recovered', 'c8-cart-recovery' ); ?></option>
				<option value="unsubscribed" <?php selected( $current_status, 'unsubscribed' ); ?>><?php esc_html_e( 'Unsubscribed', 'c8-cart-recovery' ); ?></option>
			</select>
			<?php submit_button( __( 'Filter', 'c8-cart-recovery' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Checkbox column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="cart_ids[]" value="%s" />',
			esc_attr( $item->id )
		);
	}

	/**
	 * Customer column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_customer( $item ) {
		$name = $item->user_first_name ? $item->user_first_name : __( 'Guest', 'c8-cart-recovery' );

		if ( $item->user_id ) {
			$user = get_userdata( $item->user_id );
			if ( $user ) {
				$name = $user->display_name;
			}
		}

		return esc_html( $name );
	}

	/**
	 * Email column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_email( $item ) {
		if ( empty( $item->user_email ) ) {
			return '<em>' . esc_html__( 'Not captured', 'c8-cart-recovery' ) . '</em>';
		}

		return sprintf(
			'<a href="mailto:%s">%s</a>',
			esc_attr( $item->user_email ),
			esc_html( $item->user_email )
		);
	}

	/**
	 * Cart total column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_cart_total( $item ) {
		return wp_kses_post( wc_price( $item->cart_total, array( 'currency' => $item->currency ) ) );
	}

	/**
	 * Products column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_products( $item ) {
		$cart_contents = maybe_unserialize( $item->cart_contents );

		if ( empty( $cart_contents ) || ! is_array( $cart_contents ) ) {
			return '-';
		}

		$products = array();
		foreach ( $cart_contents as $cart_item ) {
			$product_id = isset( $cart_item['variation_id'] ) && $cart_item['variation_id']
				? $cart_item['variation_id']
				: $cart_item['product_id'];

			$product = wc_get_product( $product_id );
			if ( $product ) {
				$products[] = sprintf(
					'%s x %d',
					$product->get_name(),
					$cart_item['quantity']
				);
			}
		}

		if ( empty( $products ) ) {
			return '-';
		}

		return '<small>' . esc_html( implode( ', ', $products ) ) . '</small>';
	}

	/**
	 * Status column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_status( $item ) {
		$statuses = array(
			'abandoned'    => '<span class="c8cr-status c8cr-status-abandoned">' . esc_html__( 'Abandoned', 'c8-cart-recovery' ) . '</span>',
			'email_sent'   => '<span class="c8cr-status c8cr-status-email-sent">' . esc_html__( 'Email Sent', 'c8-cart-recovery' ) . '</span>',
			'recovered'    => '<span class="c8cr-status c8cr-status-recovered">' . esc_html__( 'Recovered', 'c8-cart-recovery' ) . '</span>',
			'unsubscribed' => '<span class="c8cr-status c8cr-status-unsubscribed">' . esc_html__( 'Unsubscribed', 'c8-cart-recovery' ) . '</span>',
		);

		return isset( $statuses[ $item->status ] ) ? $statuses[ $item->status ] : esc_html( $item->status );
	}

	/**
	 * Created at column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_created_at( $item ) {
		$time_diff = human_time_diff( strtotime( $item->created_at ), current_time( 'timestamp' ) );

		return sprintf(
			'<abbr title="%s">%s %s</abbr>',
			esc_attr( $item->created_at ),
			esc_html( $time_diff ),
			esc_html__( 'ago', 'c8-cart-recovery' )
		);
	}

	/**
	 * Email sent column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_email_sent( $item ) {
		if ( $item->email_sent_count < 1 ) {
			return '<em>' . esc_html__( 'Not sent', 'c8-cart-recovery' ) . '</em>';
		}

		$output = sprintf(
			esc_html__( 'Sent %d time(s)', 'c8-cart-recovery' ),
			$item->email_sent_count
		);

		if ( $item->last_email_sent ) {
			$output .= '<br /><small>' . esc_html( human_time_diff( strtotime( $item->last_email_sent ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'c8-cart-recovery' ) . '</small>';
		}

		return $output;
	}

	/**
	 * Actions column
	 *
	 * @param object $item Item.
	 * @return string
	 */
	public function column_actions( $item ) {
		$actions = array();

		// Resend email action (only if email is available and not unsubscribed)
		if ( ! empty( $item->user_email ) && 'unsubscribed' !== $item->status && 'recovered' !== $item->status ) {
			$resend_url = wp_nonce_url(
				add_query_arg(
					array(
						'action'  => 'resend',
						'cart_id' => $item->id,
					),
					admin_url( 'admin.php?page=c8cr-abandoned-carts' )
				),
				'c8cr_action_' . $item->id
			);

			$actions[] = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $resend_url ),
				esc_html__( 'Resend Email', 'c8-cart-recovery' )
			);
		}

		// Delete action
		$delete_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'delete',
					'cart_id' => $item->id,
				),
				admin_url( 'admin.php?page=c8cr-abandoned-carts' )
			),
			'c8cr_action_' . $item->id
		);

		$actions[] = sprintf(
			'<a href="%s" class="c8cr-delete" onclick="return confirm(\'%s\');">%s</a>',
			esc_url( $delete_url ),
			esc_js( __( 'Are you sure you want to delete this abandoned cart?', 'c8-cart-recovery' ) ),
			esc_html__( 'Delete', 'c8-cart-recovery' )
		);

		return implode( ' | ', $actions );
	}

	/**
	 * No items message
	 */
	public function no_items() {
		esc_html_e( 'No abandoned carts found.', 'c8-cart-recovery' );
	}
}
