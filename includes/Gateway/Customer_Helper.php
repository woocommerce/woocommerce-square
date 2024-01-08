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
 */

namespace WooCommerce\Square\Gateway;

defined( 'ABSPATH' ) || exit;

class Customer_Helper {


	/**
	 * Adds customers to the local index.
	 *
	 * @since 2.0.0
	 *
	 * @param \Square\Models\Customer[] $customers Square API customers
	 */
	public static function add_customers( array $customers ) {
		global $wpdb;

		$placeholders = array();
		$values       = array();

		$query = "INSERT INTO {$wpdb->prefix}woocommerce_square_customers (square_id, email_address) VALUES ";

		foreach ( $customers as $customer ) {

			// skip any bad data
			if ( ! $customer instanceof \Square\Models\Customer ) {
				continue;
			}

			$placeholders[] = '(%s, %s)';

			$values[] = wc_clean( $customer->getId() );
			$values[] = wc_clean( $customer->getEmailAddress() );
		}

		$query .= implode( ', ', $placeholders );

		// update the Square ID value when duplicate email addresses are present
		$query .= " ON DUPLICATE KEY UPDATE email_address = VALUES(email_address)"; //phpcs:ignore Squiz.Strings.DoubleQuoteUsage.NotRequired

		$wpdb->query( $wpdb->prepare( $query, $values ) ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}


	/**
	 * Adds a customer to the index.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square customer ID
	 * @param string $email_address customer email address
	 * @param int $user_id WordPress user ID
	 */
	public static function add_customer( $square_id, $email_address, $user_id = 0 ) {
		global $wpdb;

		if ( is_email( $email_address ) ) {

			$params = array(
				'square_id'     => wc_clean( $square_id ),
				'email_address' => wc_clean( $email_address ),
			);

			if ( $user_id && is_numeric( $user_id ) ) {
				$params['user_id'] = (int) $user_id;
			}

			$wpdb->insert(
				"{$wpdb->prefix}woocommerce_square_customers",
				$params
			);
		}
	}


	/**
	 * Gets a Square customer ID from an email address.
	 *
	 * @param string $email_address customer email address
	 * @return string|null
	 */
	public static function get_square_id( $email_address ) {
		global $wpdb;

		$square_id = null;

		if ( is_email( $email_address ) ) {

			$square_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT square_id FROM {$wpdb->prefix}woocommerce_square_customers WHERE email_address = %s",
					$email_address
				)
			);
		}

		return $square_id;
	}


	public static function get_customers_by_email( $email_address ) {
		global $wpdb;

		$square_ids = array();

		if ( is_email( $email_address ) ) {

			$square_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT square_id FROM {$wpdb->prefix}woocommerce_square_customers WHERE email_address = %s",
					$email_address
				)
			);
		}

		return $square_ids;
	}


	/**
	 * Determines if a customer exists in the index.
	 *
	 * @since 2.0.0
	 *
	 * @param string $square_id Square customer ID
	 * @return bool
	 */
	public static function is_customer_indexed( $square_id ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}woocommerce_square_customers WHERE square_id = %s",
				$square_id
			)
		);

		return (bool) $result;
	}


	/**
	 * Creates the db table for the customer index.
	 *
	 * @since 2.0.0
	 */
	public static function create_table() {
		global $wpdb;

		$wpdb->hide_errors();

		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$schema = $wpdb->prepare( "CREATE TABLE {$wpdb->prefix}woocommerce_square_customers (`square_id` varchar(191) NOT NULL, `email_address` varchar(200) NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL, PRIMARY KEY (`square_id`) ) %1s", $collate ); //phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $schema );
	}


}
