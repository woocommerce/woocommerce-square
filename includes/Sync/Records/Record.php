<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Sync\Records;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Sync\Records;

/**
 * The sync record object.
 *
 * @since 2.0.0
 */
class Record {


	/** @var string unique identifier */
	private $id = '';

	/** @var string date in UTC */
	private $date = '';

	/** @var string the record status */
	private $type = '';

	/** @var string message (optional, may contain HTML) */
	private $message = '';

	/** @var int associated product ID (optional) */
	private $product_id = 0;

	/** @var bool whether the associated product was hidden when the record was created */
	private $product_hidden = false;


	/**
	 * Sync record constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param array|int $data raw data or related product ID
	 */
	public function __construct( $data ) {

		foreach ( $this->parse_data( $data ) as $key => $value ) {
			$this->$key = $value;
		}
	}


	/**
	 * Parses data from the store into the object properties
	 *
	 * @since 2.0.0
	 *
	 * @param int|array $data associative array or product ID
	 * @return array
	 */
	private function parse_data( $data ) {

		if ( is_numeric( $data ) ) {
			$data = array(
				'type'       => 'alert',
				'product_id' => absint( $data ),
			);
		}

		$date = date( 'Y-m-d H:i:s', current_time( 'timestamp', true ) );
		$data = wp_parse_args(
			(array) $data,
			array(
				'type'           => $this->get_default_type(),
				'date'           => $date,
				'message'        => '',
				'product_id'     => 0,
				'product_hidden' => false,
			)
		);

		if ( empty( $data['id'] ) ) {
			$data['id'] = uniqid( 'wc_square_sync_record_', false );
		}

		if ( ! strtotime( $data['date'] ) ) {
			$data['date'] = $date;
		}

		if ( ! is_string( $data['message'] ) ) {
			$data['message'] = '';
		}

		if ( ! is_numeric( $data['product_id'] ) ) {
			$data['product_id'] = $data['product_id'] instanceof \WC_Product ? $data['product_id']->get_id() : 0;
		}

		if ( 0 === $data['product_id'] ) {
			$data['product_hidden'] = false;
		}

		return $data;
	}


	/**
	 * Gets the record's raw data.
	 *
	 * @since 2.0.0
	 *
	 * @return array associative array
	 */
	public function get_data() {

		return array(
			'id'             => (string) $this->id,
			'type'           => (string) $this->type,
			'date'           => (string) $this->date,
			'message'        => (string) $this->message,
			'product_id'     => (int) $this->product_id,
			'product_hidden' => (bool) $this->product_hidden,
		);
	}


	/**
	 * Gets the record's ID.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_id() {

		return (string) $this->id;
	}


	/**
	 * Sets an ID for the record.
	 *
	 * @since 2.0.0
	 *
	 * @param null|string $id
	 * @return string set ID
	 */
	public function set_id( $id = null ) {

		if ( is_string( $id ) ) {
			$id = trim( $id );
		} else {
			$id = null;
		}

		if ( empty( $id ) ) {
			$id = uniqid( 'wc_square_sync_record_', false );
		}

		return (string) $this->id = $id;
	}


	/**
	 * Gets a record's default status.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_default_type() {

		return 'info';
	}


	/**
	 * Gets a record's possible statuses.
	 *
	 * @since 2.0.0
	 *
	 * @return array associative array of types and labels
	 */
	private function get_valid_types() {

		return array(
			'info'     => __( 'Info', 'woocommerce-square' ),
			'notice'   => __( 'Notice', 'woocommerce-square' ),
			'alert'    => __( 'Alert', 'woocommerce-square' ),
			'resolved' => __( 'Resolved', 'woocommerce-square' ),
		);
	}


	/**
	 * Gets the record's label.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_label() {

		$type  = $this->get_type();
		$types = $this->get_valid_types();

		return isset( $types[ $type ] ) ? $types[ $type ] : $types[ $this->get_default_type() ];
	}


	/**
	 * Gets the record status.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_type() {

		return array_key_exists( $this->type, $this->get_valid_types() ) ? $this->type : $this->get_default_type();
	}


	/**
	 * Set's the record status.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type a known type
	 * @return string set type
	 */
	public function set_type( $type ) {

		if ( array_key_exists( $type, $this->get_valid_types() ) ) {
			$this->type = $type;
		} else {
			$this->type = $this->get_default_type();
		}

		return $this->type;
	}


	/**
	 * Checks if the record is of a given type.
	 *
	 * @since 2.0.0
	 *
	 * @param array|string $type one or more types to check
	 * @return bool
	 */
	public function is_type( $type ) {

		return is_array( $type ) ? in_array( $this->type, $type, true ) : $this->type === $type;
	}


	/**
	 * Checks whether the record is resolved.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_resolved() {

		return $this->is_type( 'resolved' );
	}


	/**
	 * Resolves the record.
	 *
	 * @since 2.0.0
	 */
	public function resolve() {

		$this->set_type( 'resolved' );
	}


	/**
	 * Gets the record's timestamp, in UTC.
	 *
	 * @since 2.0.0
	 *
	if (
	 * @return int
	 */
	public function get_timestamp() {

		return (int) strtotime( $this->date );
	}


	/**
	 * Gets the record's date, in UTC.
	 *
	 * @since 2.0.0
	 *
	 * @param string $format defaults to MySQL format
	 * @return string
	 */
	public function get_date( $format = 'Y-m-d H:i:s' ) {

		return date( (string) $format, $this->get_timestamp() );
	}


	/**
	 * Gets the record's date, in the local timezone.
	 *
	 * @since 2.0.0
	 *
	 * @param null|string $format optional PHP date format (defaults to the site date/time format)
	 * @return string
	 */
	public function get_local_date( $format = null ) {

		if ( ! is_string( $format ) ) {
			$format = wc_date_format() . ' ' . wc_time_format();
		}

		try {

			$date      = new \DateTime( date( (string) $format, $this->get_timestamp() ), new \DateTimeZone( 'UTC' ) );
			$timezone  = new \DateTimeZone( wc_timezone_string() );
			$offset    = $timezone->getOffset( $date );
			$timestamp = $date->getTimestamp() + $offset;

		} catch ( \Exception $e ) {

			$timestamp = $this->get_timestamp();
		}

		return date( (string) $format, $timestamp );
	}


	/**
	 * Gets the record's message.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_message() {

		$message = trim( $this->message );

		if ( '' === $message && ( $product = $this->get_product() ) ) {

			if ( 'variation' === $product->get_type() ) {
				$message = sprintf(
					/* translators: Placeholder: %s - product name */
					esc_html__( '%s variation not found in Square.', 'woocommerce-square' ),
					'<a href="' . esc_url( get_edit_post_link( $product->get_parent_id() ) ) . '">' . $product->get_formatted_name() . '</a>'
				);
			} else {
				$message = sprintf(
					/* translators: Placeholder: %s - product name */
					esc_html__( '%s not found in Square.', 'woocommerce-square' ),
					'<a href="' . esc_url( get_edit_post_link( $product->get_id() ) ) . '">' . $product->get_formatted_name() . '</a>'
				);
			}
		}

		return $message;
	}


	/**
	 * Sets the record's message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message may contain HTML
	 * @return bool success
	 */
	public function set_message( $message ) {

		if ( is_string( $message ) ) {
			$this->message = trim( $message );
		} else {
			$this->message = '';
		}

		return ! empty( $this->message );
	}


	/**
	 * Removes the record's message.
	 *
	 * @since 2.0.0
	 */
	public function remove_message() {

		$this->message = '';
	}


	/**
	 * Sets the record's product.
	 *
	 * @since 2.0.0
	 *
	 * @param int|\WC_Product $product
	 * @return bool success
	 */
	public function set_product( $product ) {

		if ( $product instanceof \WC_Product ) {
			$product_id = $product->get_id();
		} else {
			$product_id = $product;
		}

		if ( is_numeric( $product_id ) ) {
			$this->product_id = $product_id;
		} else {
			$this->product_id = 0;
		}

		return $this->product_id > 0;
	}


	/**
	 * Removes a related product from the record.
	 *
	 * @since 2.0.0
	 */
	public function remove_product() {

		$this->product_id = 0;
	}


	/**
	 * Checks whether there is a valid product associated with the record.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function has_product() {

		return $this->get_product() instanceof \WC_Product;
	}


	/**
	 * Gets the product associated with the record.
	 *
	 * @since 2.0.0
	 *
	 * @return null|\WC_Product
	 */
	public function get_product() {

		$product = wc_get_product( $this->get_product_id() );

		return $product instanceof \WC_Product ? $product : null;
	}


	/**
	 * Gets the product ID associated with the record.
	 *
	 * @since 2.0.0
	 *
	 * @return int
	 */
	public function get_product_id() {

		return absint( $this->product_id );
	}


	/**
	 * Sets a flag whether when adding the record the product was contextually hidden from catalog.
	 *
	 * Note: this is for historical record purposes and may not correspond to the effective product visibility, nor does not affect the product visibility when called.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $was_hidden whether product was hidden when creating the record
	 * @return bool success
	 */
	public function set_product_hidden( $was_hidden = true ) {

		$set = false;

		if ( is_bool( $was_hidden ) ) {
			$this->product_hidden = $was_hidden;
			$set                  = true;
		}

		return $set;
	}


	/**
	 * Checks whether a flag was set for hiding the product from catalog when the record was created.
	 *
	 * This may not reflect the actual product visibility status, it is only for historical purposes.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function was_product_hidden() {

		return true === $this->product_hidden;
	}


	/**
	 * Gets the record's actions.
	 *
	 * @since 2.0.0
	 *
	 * @return \stdClass[] associative array of action names and action properties as objects
	 */
	public function get_actions() {

		$actions = array();

		if ( ! $this->is_resolved() ) {

			$actions['delete'] = (object) array(
				'name'  => 'delete',
				'label' => __( 'Delete', 'woocommerce-square' ),
				'icon'  => '<span class="dashicons dashicons-trash"></span>',
			);

			if ( ! $this->is_type( 'info' ) ) {

				$actions['resolve'] = (object) array(
					'name'  => 'resolve',
					'label' => __( 'Ignore', 'woocommerce-square' ),
					'icon'  => '<span class="dashicons dashicons-hidden"></span>',
				);
			}

			if ( $this->has_product() ) {

				$actions['unsync'] = (object) array(
					'name'  => 'unsync',
					'label' => __( 'Unlink', 'woocommerce-square' ),
					'icon'  => '<span class="dashicons dashicons-editor-unlink"></span>',
				);
			}
		}

		/**
		 * Filters the sync record action.
		 *
		 * @since 2.0.0
		 *
		 * @param \stdClass[] array of action names and objects
		 * @param Record instance of the current record object
		 */
		return (array) apply_filters( 'wc_square_sync_record_actions', $actions, $this );
	}


	/**
	 * Saves the record to storage.
	 *
	 * @since 2.0.0
	 *
	 * @return bool success
	 */
	public function save() {

		return Records::set_record( $this );
	}


	/**
	 * Deletes the record from storage.
	 *
	 * @since 2.0.0
	 *
	 * @return bool success
	 */
	public function destroy() {

		return Records::delete_record( $this->get_id() );
	}


}
