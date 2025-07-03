<?php
/**
 * Order Tagging System.
 *
 * Handles tagging and differentiation of imported Square orders.
 *
 * @package WooCommerce\Square\Sync
 */

namespace WooCommerce\Square\Sync;

defined( 'ABSPATH' ) || exit;

/**
 * Order Tagging Class.
 *
 * @since x.x.x
 */
class Order_Tagging {

	/**
	 * WooCommerce order meta field constants.
	 *
	 * @since x.x.x
	 */
	const WC_META_SQUARE_ORDER_ID           = '_square_order_id';
	const WC_META_SQUARE_SYNC_STATUS        = '_square_sync_status';
	const WC_META_ORDERED_VIA_SQUARE        = '_ordered_via_square';
	const WC_META_SQUARE_FULFILLMENT_ID     = '_square_fulfillment_id';
	const WC_META_SQUARE_SYNC_LAST_ATTEMPT  = '_square_sync_last_attempt';
	const WC_META_SQUARE_SYNC_ERROR         = '_square_sync_error';
	const WC_META_SQUARE_LOCATION_ID        = '_square_location_id';
	const WC_META_SQUARE_VERSION            = '_square_version';
	const WC_META_SQUARE_IMPORT_SOURCE      = '_square_import_source';
	const WC_META_SQUARE_IMPORT_DATE        = '_square_import_date';
	const WC_META_SQUARE_REFERENCE_ID       = '_square_reference_id';
	const WC_META_SQUARE_SYNC_TIMESTAMP     = '_square_sync_timestamp';

	/**
	 * Square order metadata field constants.
	 *
	 * @since x.x.x
	 */
	const SQUARE_META_ORDERED_VIA_WOO       = 'orderedViaWoo';
	const SQUARE_META_WOO_ORDER_ID          = 'wooOrderId';
	const SQUARE_META_WOO_ORDER_KEY         = 'wooOrderKey';
	const SQUARE_META_SYNC_VERSION          = 'syncVersion';
	const SQUARE_META_SYNC_TIMESTAMP        = 'syncTimestamp';
	const SQUARE_META_BILLING_COMPANY       = 'billing_company';
	const SQUARE_META_BILLING_EMAIL         = 'billing_email';
	const SQUARE_META_CUSTOMER_NOTE         = 'customer_note';
	const SQUARE_META_PAYMENT_METHOD        = 'payment_method';

	/**
	 * Order sync status constants.
	 *
	 * @since x.x.x
	 */
	const SYNC_STATUS_PENDING    = 'pending';
	const SYNC_STATUS_COMPLETED  = 'completed';
	const SYNC_STATUS_FAILED     = 'failed';
	const SYNC_STATUS_SYNCING    = 'syncing';
	const SYNC_STATUS_CONFLICT   = 'conflict';

	/**
	 * Initialize the order tagging system.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_custom_order_status' ) );
		add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_status' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_order_source_meta_box' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_source_column' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'display_order_source_column' ), 10, 2 );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_order_source_action' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'display_order_source_info' ) );
		add_filter( 'woocommerce_order_list_table_columns', array( $this, 'add_hpos_order_source_column' ) );
		add_action( 'woocommerce_order_list_table_custom_column', array( $this, 'display_hpos_order_source_column' ), 10, 2 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
	}

	/**
	 * Register custom order status for Square orders.
	 *
	 * @since x.x.x
	 */
	public function register_custom_order_status() {
		register_post_status(
			'wc-square-imported',
			array(
				'label'                     => _x( 'Square Imported', 'Order status', 'woocommerce-square' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of orders */
				'label_count'               => _n_noop( 'Square Imported <span class="count">(%s)</span>', 'Square Imported <span class="count">(%s)</span>', 'woocommerce-square' ),
			)
		);
	}

	/**
	 * Add custom order status to WooCommerce order statuses.
	 *
	 * @since x.x.x
	 * @param array $order_statuses Order statuses.
	 * @return array
	 */
	public function add_custom_order_status( $order_statuses ) {
		$order_statuses['wc-square-imported'] = _x( 'Square Imported', 'Order status', 'woocommerce-square' );
		return $order_statuses;
	}

	/**
	 * Add meta box to display order source information.
	 *
	 * @since x.x.x
	 */
	public function add_order_source_meta_box() {
		add_meta_box(
			'wc-square-order-source',
			__( 'Square Order Information', 'woocommerce-square' ),
			array( $this, 'display_order_source_meta_box' ),
			'shop_order',
			'side',
			'high'
		);
	}

	/**
	 * Display order source meta box content.
	 *
	 * @since x.x.x
	 * @param \WP_Post $post Post object.
	 */
	public function display_order_source_meta_box( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}

		$is_square_order = $order->get_meta( self::WC_META_ORDERED_VIA_SQUARE );
		$square_order_id = $order->get_meta( self::WC_META_SQUARE_ORDER_ID );
		$sync_status     = $order->get_meta( self::WC_META_SQUARE_SYNC_STATUS );
		$square_location_id = $order->get_meta( self::WC_META_SQUARE_LOCATION_ID );
		$import_source   = $order->get_meta( self::WC_META_SQUARE_IMPORT_SOURCE );
		$import_date     = $order->get_meta( self::WC_META_SQUARE_IMPORT_DATE );
		$sync_error      = $order->get_meta( self::WC_META_SQUARE_SYNC_ERROR );

		echo '<div class="wc-square-order-source-info">';
		
		if ( $is_square_order ) {
			echo '<p><strong>' . esc_html__( 'Source:', 'woocommerce-square' ) . '</strong> ' . esc_html__( 'Square', 'woocommerce-square' ) . '</p>';
			
			if ( $square_order_id ) {
				echo '<p><strong>' . esc_html__( 'Square Order ID:', 'woocommerce-square' ) . '</strong> <code>' . esc_html( $square_order_id ) . '</code></p>';
			}

			if ( $square_location_id ) {
				echo '<p><strong>' . esc_html__( 'Square Location:', 'woocommerce-square' ) . '</strong> <code>' . esc_html( $square_location_id ) . '</code></p>';
			}
			
			if ( $sync_status ) {
				$status_class = 'completed' === $sync_status ? 'success' : ( 'failed' === $sync_status ? 'error' : 'warning' );
				echo '<p><strong>' . esc_html__( 'Sync Status:', 'woocommerce-square' ) . '</strong> <span class="wc-square-status-' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $sync_status ) ) . '</span></p>';
			}

			if ( $import_source ) {
				echo '<p><strong>' . esc_html__( 'Import Source:', 'woocommerce-square' ) . '</strong> ' . esc_html( ucfirst( $import_source ) ) . '</p>';
			}

			if ( $import_date ) {
				echo '<p><strong>' . esc_html__( 'Import Date:', 'woocommerce-square' ) . '</strong> ' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $import_date ) ) ) . '</p>';
			}

			if ( $sync_error ) {
				echo '<p><strong>' . esc_html__( 'Last Error:', 'woocommerce-square' ) . '</strong><br><span class="wc-square-error">' . esc_html( $sync_error ) . '</span></p>';
			}
			
			echo '<p class="description">' . esc_html__( 'This order was imported from Square.', 'woocommerce-square' ) . '</p>';
		} else {
			$woo_to_square_synced = $square_order_id ? true : false;
			
			if ( $woo_to_square_synced ) {
				echo '<p><strong>' . esc_html__( 'Source:', 'woocommerce-square' ) . '</strong> ' . esc_html__( 'WooCommerce', 'woocommerce-square' ) . '</p>';
				echo '<p><strong>' . esc_html__( 'Square Order ID:', 'woocommerce-square' ) . '</strong> <code>' . esc_html( $square_order_id ) . '</code></p>';
				
				if ( $sync_status ) {
					$status_class = 'completed' === $sync_status ? 'success' : ( 'failed' === $sync_status ? 'error' : 'warning' );
					echo '<p><strong>' . esc_html__( 'Sync Status:', 'woocommerce-square' ) . '</strong> <span class="wc-square-status-' . esc_attr( $status_class ) . '">' . esc_html( ucfirst( $sync_status ) ) . '</span></p>';
				}

				if ( $sync_error ) {
					echo '<p><strong>' . esc_html__( 'Last Error:', 'woocommerce-square' ) . '</strong><br><span class="wc-square-error">' . esc_html( $sync_error ) . '</span></p>';
				}
				
				echo '<p class="description">' . esc_html__( 'This order was created in WooCommerce and synced to Square.', 'woocommerce-square' ) . '</p>';
			} else {
				echo '<p>' . esc_html__( 'This order was created in WooCommerce and has not been synced to Square.', 'woocommerce-square' ) . '</p>';
			}
		}
		
		echo '</div>';
	}

	/**
	 * Add order source column to orders list.
	 *
	 * @since x.x.x
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_order_source_column( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( 'order_status' === $key ) {
				$new_columns['order_source'] = __( 'Source', 'woocommerce-square' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Display order source in column.
	 *
	 * @since x.x.x
	 * @param string $column Column name.
	 * @param int    $post_id Post ID.
	 */
	public function display_order_source_column( $column, $post_id ) {
		if ( 'order_source' !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$this->render_order_source_indicator( $order );
	}

	/**
	 * Add order source action to order actions.
	 *
	 * @since x.x.x
	 * @param array    $actions Order actions.
	 * @param \WC_Order $order Order object.
	 * @return array
	 */
	public function add_order_source_action( $actions, $order ) {
		$sync_status = $order->get_meta( self::WC_META_SQUARE_SYNC_STATUS );
		
		if ( 'failed' === $sync_status ) {
			$actions['square_retry_sync'] = array(
				'url'    => wp_nonce_url( 
					admin_url( 'admin-ajax.php?action=wc_square_retry_order_sync&order_id=' . $order->get_id() ), 
					'square_retry_sync' 
				),
				'name'   => __( 'Retry Square Sync', 'woocommerce-square' ),
				'action' => 'square_retry_sync',
			);
		}

		return $actions;
	}

	/**
	 * Display order source information in order details.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order Order object.
	 */
	public function display_order_source_info( $order ) {
		$is_square_order = $order->get_meta( self::WC_META_ORDERED_VIA_SQUARE );
		$square_order_id = $order->get_meta( self::WC_META_SQUARE_ORDER_ID );
		
		if ( $is_square_order || $square_order_id ) {
			echo '<div class="wc-square-order-info-banner">';
			
			if ( $is_square_order ) {
				echo '<p class="wc-square-info"><span class="dashicons dashicons-external"></span> ' . esc_html__( 'This order was imported from Square.', 'woocommerce-square' ) . '</p>';
			} elseif ( $square_order_id ) {
				echo '<p class="wc-square-info"><span class="dashicons dashicons-upload"></span> ' . esc_html__( 'This order was synced to Square.', 'woocommerce-square' ) . '</p>';
			}
			
			echo '</div>';
		}
	}

	/**
	 * Add order source column for HPOS.
	 *
	 * @since x.x.x
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_hpos_order_source_column( $columns ) {
		$new_columns = array();
		
		foreach ( $columns as $key => $column ) {
			$new_columns[ $key ] = $column;
			if ( 'order_status' === $key ) {
				$new_columns['order_source'] = __( 'Source', 'woocommerce-square' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Display order source column for HPOS.
	 *
	 * @since x.x.x
	 * @param string   $column Column name.
	 * @param \WC_Order $order Order object.
	 */
	public function display_hpos_order_source_column( $column, $order ) {
		if ( 'order_source' !== $column ) {
			return;
		}

		$this->render_order_source_indicator( $order );
	}

	/**
	 * Render order source indicator.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order Order object.
	 */
	private function render_order_source_indicator( $order ) {
		$is_square_order = $order->get_meta( self::WC_META_ORDERED_VIA_SQUARE );
		$square_order_id = $order->get_meta( self::WC_META_SQUARE_ORDER_ID );
		$sync_status     = $order->get_meta( self::WC_META_SQUARE_SYNC_STATUS );
		
		if ( $is_square_order ) {
			$status_indicator = '';
			if ( $sync_status ) {
				$status_class = 'completed' === $sync_status ? 'success' : ( 'failed' === $sync_status ? 'error' : 'warning' );
				$status_indicator = ' <span class="wc-square-sync-status wc-square-status-' . esc_attr( $status_class ) . '" title="' . esc_attr( ucfirst( $sync_status ) ) . '">●</span>';
			}
			
			echo '<span class="wc-square-order-source square" title="' . esc_attr__( 'Imported from Square', 'woocommerce-square' ) . '">';
			echo '<span class="dashicons dashicons-external"></span> ';
			echo esc_html__( 'Square', 'woocommerce-square' );
			echo $status_indicator;
			echo '</span>';
		} elseif ( $square_order_id ) {
			$status_indicator = '';
			if ( $sync_status ) {
				$status_class = 'completed' === $sync_status ? 'success' : ( 'failed' === $sync_status ? 'error' : 'warning' );
				$status_indicator = ' <span class="wc-square-sync-status wc-square-status-' . esc_attr( $status_class ) . '" title="' . esc_attr( ucfirst( $sync_status ) ) . '">●</span>';
			}
			
			echo '<span class="wc-square-order-source woo-synced" title="' . esc_attr__( 'Synced to Square', 'woocommerce-square' ) . '">';
			echo '<span class="dashicons dashicons-upload"></span> ';
			echo esc_html__( 'WooCommerce', 'woocommerce-square' );
			echo $status_indicator;
			echo '</span>';
		} else {
			echo '<span class="wc-square-order-source woo" title="' . esc_attr__( 'Created in WooCommerce', 'woocommerce-square' ) . '">';
			echo '<span class="dashicons dashicons-cart"></span> ';
			echo esc_html__( 'WooCommerce', 'woocommerce-square' );
			echo '</span>';
		}
	}

	/**
	 * Tag a WooCommerce order as imported from Square.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $square_order_id Square order ID.
	 * @param string    $import_source Import source (webhook, polling, manual).
	 */
	public static function tag_order_as_square_imported( $order, $square_order_id = '', $import_source = 'unknown' ) {
		$order->update_meta_data( self::WC_META_ORDERED_VIA_SQUARE, 'true' );
		
		if ( $square_order_id ) {
			$order->update_meta_data( self::WC_META_SQUARE_ORDER_ID, $square_order_id );
		}
		
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_STATUS, self::SYNC_STATUS_COMPLETED );
		$order->update_meta_data( self::WC_META_SQUARE_IMPORT_SOURCE, $import_source );
		$order->update_meta_data( self::WC_META_SQUARE_IMPORT_DATE, current_time( 'mysql' ) );
		$order->update_meta_data( self::WC_META_SQUARE_VERSION, wc_square()->get_version() );
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_TIMESTAMP, current_time( 'c' ) );
		
		$order->save();
	}

	/**
	 * Tag a WooCommerce order as synced to Square.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $square_order_id Square order ID.
	 */
	public static function tag_order_as_synced_to_square( $order, $square_order_id ) {
		$order->update_meta_data( self::WC_META_SQUARE_ORDER_ID, $square_order_id );
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_STATUS, self::SYNC_STATUS_COMPLETED );
		$order->update_meta_data( self::WC_META_SQUARE_VERSION, wc_square()->get_version() );
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_TIMESTAMP, current_time( 'c' ) );
		
		$order->save();
	}

	/**
	 * Update order sync status.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @param string    $status Sync status.
	 * @param string    $error_message Error message if failed.
	 */
	public static function update_order_sync_status( $order, $status, $error_message = '' ) {
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_STATUS, $status );
		$order->update_meta_data( self::WC_META_SQUARE_SYNC_LAST_ATTEMPT, current_time( 'mysql' ) );
		
		if ( $error_message ) {
			$order->update_meta_data( self::WC_META_SQUARE_SYNC_ERROR, $error_message );
		} else {
			$order->delete_meta_data( self::WC_META_SQUARE_SYNC_ERROR );
		}
		
		$order->save();
	}

	/**
	 * Set Square metadata on order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @param array     $square_metadata Square metadata.
	 */
	public static function set_square_metadata( $order, $square_metadata ) {
		foreach ( $square_metadata as $key => $value ) {
			switch ( $key ) {
				case self::SQUARE_META_WOO_ORDER_ID:
					$order->update_meta_data( self::WC_META_SQUARE_REFERENCE_ID, $value );
					break;
				case self::SQUARE_META_BILLING_COMPANY:
					// Only set if not already present.
					if ( ! $order->get_billing_company() ) {
						$order->set_billing_company( $value );
					}
					break;
				case self::SQUARE_META_BILLING_EMAIL:
					// Only set if not already present.
					if ( ! $order->get_billing_email() ) {
						$order->set_billing_email( $value );
					}
					break;
				case self::SQUARE_META_CUSTOMER_NOTE:
					// Only set if not already present.
					if ( ! $order->get_customer_note() ) {
						$order->set_customer_note( $value );
					}
					break;
				case self::SQUARE_META_PAYMENT_METHOD:
					$order->update_meta_data( '_square_payment_method', $value );
					break;
				default:
					// Store other metadata with square_ prefix.
					$order->update_meta_data( '_square_meta_' . sanitize_key( $key ), $value );
					break;
			}
		}
		
		$order->save();
	}

	/**
	 * Check if order was imported from Square.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public static function is_square_imported_order( $order ) {
		return 'true' === $order->get_meta( self::WC_META_ORDERED_VIA_SQUARE ) && ! empty( $order->get_meta( self::WC_META_SQUARE_ORDER_ID ) );
	}

	/**
	 * Check if order was synced to Square.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return bool
	 */
	public static function is_order_synced_to_square( $order ) {
		return ! empty( $order->get_meta( self::WC_META_SQUARE_ORDER_ID ) );
	}

	/**
	 * Get Square order ID from WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return string|false
	 */
	public static function get_square_order_id( $order ) {
		return $order->get_meta( self::WC_META_SQUARE_ORDER_ID );
	}

	/**
	 * Get order sync status.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return string
	 */
	public static function get_order_sync_status( $order ) {
		return $order->get_meta( self::WC_META_SQUARE_SYNC_STATUS );
	}

	/**
	 * Get all Square metadata from order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return array
	 */
	public static function get_all_square_metadata( $order ) {
		$metadata = array();
		
		$meta_fields = array(
			'square_order_id'        => self::WC_META_SQUARE_ORDER_ID,
			'sync_status'            => self::WC_META_SQUARE_SYNC_STATUS,
			'ordered_via_square'     => self::WC_META_ORDERED_VIA_SQUARE,
			'fulfillment_id'         => self::WC_META_SQUARE_FULFILLMENT_ID,
			'location_id'            => self::WC_META_SQUARE_LOCATION_ID,
			'import_source'          => self::WC_META_SQUARE_IMPORT_SOURCE,
			'import_date'            => self::WC_META_SQUARE_IMPORT_DATE,
			'sync_error'             => self::WC_META_SQUARE_SYNC_ERROR,
			'last_attempt'           => self::WC_META_SQUARE_SYNC_LAST_ATTEMPT,
			'version'                => self::WC_META_SQUARE_VERSION,
			'reference_id'           => self::WC_META_SQUARE_REFERENCE_ID,
			'sync_timestamp'         => self::WC_META_SQUARE_SYNC_TIMESTAMP,
		);
		
		foreach ( $meta_fields as $key => $meta_key ) {
			$value = $order->get_meta( $meta_key );
			if ( $value ) {
				$metadata[ $key ] = $value;
			}
		}
		
		return $metadata;
	}

	/**
	 * Generate Square metadata for WooCommerce order.
	 *
	 * @since x.x.x
	 * @param \WC_Order $order WooCommerce order.
	 * @return array
	 */
	public static function generate_square_metadata_for_wc_order( $order ) {
		$metadata = array(
			self::SQUARE_META_ORDERED_VIA_WOO  => 'true',
			self::SQUARE_META_WOO_ORDER_ID     => (string) $order->get_id(),
			self::SQUARE_META_WOO_ORDER_KEY    => $order->get_order_key(),
			self::SQUARE_META_SYNC_VERSION     => wc_square()->get_version(),
			self::SQUARE_META_SYNC_TIMESTAMP   => current_time( 'c' ),
		);

		// Add billing information that doesn't fit in Square's standard fields.
		if ( $order->get_billing_company() ) {
			$metadata[ self::SQUARE_META_BILLING_COMPANY ] = $order->get_billing_company();
		}

		if ( $order->get_billing_email() ) {
			$metadata[ self::SQUARE_META_BILLING_EMAIL ] = $order->get_billing_email();
		}

		// Add customer note.
		if ( $order->get_customer_note() ) {
			$metadata[ self::SQUARE_META_CUSTOMER_NOTE ] = $order->get_customer_note();
		}

		// Add payment method.
		if ( $order->get_payment_method() ) {
			$metadata[ self::SQUARE_META_PAYMENT_METHOD ] = $order->get_payment_method();
		}

		return $metadata;
	}

	/**
	 * Enqueue admin styles for order source indicators.
	 *
	 * @since x.x.x
	 */
	public function enqueue_admin_styles() {
		if ( ! is_admin() ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
			return;
		}

		wp_add_inline_style( 'wp-admin', '
			.wc-square-order-source {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				font-size: 12px;
				padding: 2px 6px;
				border-radius: 3px;
				background: #f0f0f1;
			}
			.wc-square-order-source.square {
				background: #e7f3ff;
				color: #0073aa;
			}
			.wc-square-order-source.woo {
				background: #f0f6fc;
				color: #2c3338;
			}
			.wc-square-order-source.woo-synced {
				background: #f0fdf4;
				color: #166534;
			}
			.wc-square-order-source .dashicons {
				font-size: 14px;
				width: 14px;
				height: 14px;
			}
			.wc-square-sync-status {
				font-size: 10px;
				margin-left: 4px;
			}
			.wc-square-status-success {
				color: #16a34a;
			}
			.wc-square-status-error {
				color: #dc2626;
			}
			.wc-square-status-warning {
				color: #ea580c;
			}
			.wc-square-order-info-banner {
				background: #e7f3ff;
				border: 1px solid #b3d9ff;
				border-radius: 4px;
				padding: 10px;
				margin: 10px 0;
			}
			.wc-square-info {
				margin: 0;
				color: #0073aa;
				display: flex;
				align-items: center;
				gap: 6px;
			}
			.wc-square-error {
				color: #dc2626;
				font-family: monospace;
				font-size: 11px;
				background: #fef2f2;
				padding: 4px 6px;
				border-radius: 3px;
				display: block;
				margin-top: 4px;
			}
			.wc-square-order-source-info p {
				margin: 8px 0;
			}
			.wc-square-order-source-info code {
				background: #f0f0f1;
				padding: 2px 4px;
				border-radius: 3px;
				font-size: 11px;
			}
		' );
	}
} 