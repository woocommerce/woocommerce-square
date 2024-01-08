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

namespace WooCommerce\Square;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Handlers\Product;

/**
 * The plugin lifecycle handler.
 *
 * @since 2.0.0
 *
 * @method Plugin get_plugin()
 */
class Lifecycle extends \WooCommerce\Square\Framework\Lifecycle {

	/**
	 * Lifecycle constructor.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin main instance.
	 */
	public function __construct( Plugin $plugin ) {

		parent::__construct( $plugin );

		// plugin upgrade path: maps automatically each semver to upgrade_to_x_y_z() protected method.
		$this->upgrade_versions = array(
			'2.0.0',
			'2.0.4',
			'2.1.5',
			'2.2.0',
			'2.3.0',
			'3.0.2',
			'3.2.0',
			'3.7.1',
			'3.8.3',
		);
	}


	/**
	 * Performs plugin installation.
	 *
	 * @since 2.0.0
	 */
	protected function install() {

		// create the db table for the customer index.
		Gateway\Customer_Helper::create_table();

		/**
		 * Fires upon plugin installed.
		 *
		 * @since 2.0.0
		 *
		 * @param string $version plugin version (available from v2.0.0)
		 */
		do_action( 'wc_square_installed', Plugin::VERSION );
	}


	/**
	 * Performs upgrade tasks.
	 *
	 * @since 2.0.0
	 *
	 * @param string $installed_version semver.
	 */
	protected function upgrade( $installed_version ) {

		parent::upgrade( $installed_version );

		/**
		 * Fires upon plugin upgraded (legacy hook).
		 *
		 * @since 1.0.0
		 *
		 * @param string $version version updating to (available from v2.0.0)
		 * @param string $version version updating from (available from v2.0.0)
		 */
		do_action( 'wc_square_updated', Plugin::VERSION, $installed_version );
	}


	/**
	 * Upgrades to version 2.0.0
	 *
	 * @since 2.0.0
	 */
	protected function upgrade_to_2_0_0() {

		// create the db table for the customer index.
		Gateway\Customer_Helper::create_table();

		wc_set_time_limit( 300 );

		// migrate all the things!
		$syncing_products = $this->migrate_plugin_settings();

		$this->migrate_gateway_settings();
		$this->migrate_orders();

		// only set the products "sync" status if v2 is now configured to sync products.
		if ( $syncing_products ) {

			$this->migrate_products();

			// assume a last sync occurred before upgrading.
			$this->get_plugin()->get_sync_handler()->set_last_synced_at();
			$this->get_plugin()->get_sync_handler()->set_inventory_last_synced_at();
		}

		$this->migrate_customers();

		// mark upgrade complete.
		update_option( 'wc_square_updated_to_2_0_0', true );
	}


	/**
	 * Upgrades to version 2.0.4.
	 *
	 * @since 2.0.4
	 */
	protected function upgrade_to_2_0_4() {

		$v1_settings = get_option( 'woocommerce_squareconnect_settings', array() );
		$v2_settings = get_option( 'wc_square_settings', array() );

		$v2_settings = $this->get_migrated_system_of_record( $v1_settings, $v2_settings );

		update_option( 'wc_square_settings', $v2_settings );
	}

	/**
	 * Upgrades to version 2.1.5
	 *
	 * 2.1.5 updated the woocommerce_square_customers database schema.
	 *
	 * @see https://github.com/woocommerce/woocommerce-square/issues/359
	 * @since 2.1.5
	 */
	protected function upgrade_to_2_1_5() {
		Gateway\Customer_Helper::create_table();
	}

	/**
	 * Adds the missing Square customer table.
	 *
	 * @see https://github.com/woocommerce/woocommerce-square/issues/825
	 * @since 3.2.0
	 */
	protected function upgrade_to_3_2_0() {
		Gateway\Customer_Helper::create_table();
	}

	/**
	 * Deletes all transient data related to payment tokens.
	 *
	 * @see https://github.com/woocommerce/woocommerce-square/issues/1050
	 * @since 3.8.3
	 */
	protected function upgrade_to_3_8_3() {
		wc_square()->get_gateway()->get_payment_tokens_handler()->clear_all_transients();
	}

	/**
	 * Upgrades to version 3.7.1
	 *
	 * This upgrade disables gift cards, and shows a notice to inform the merchant.
	 * Gift Cards are in beta status and not recommended for production.
	 *
	 * @see https://github.com/woocommerce/woocommerce-square/issues/1003
	 * @see https://github.com/woocommerce/woocommerce-square/issues/1009
	 * @since 3.7.1.
	 */
	protected function upgrade_to_3_7_1() {
		// Early return if Gift Cards are already disabled.
		$gateway_settings = get_option( 'woocommerce_square_credit_card_settings', array() );
		if ( ! isset( $gateway_settings['enable_gift_cards'] ) || 'no' === $gateway_settings['enable_gift_cards'] ) {
			return;
		}

		// Force-disable Gift Cards (only if store has it enabled).
		$gateway_settings['enable_gift_cards'] = 'no';
		update_option( 'woocommerce_square_credit_card_settings', $gateway_settings );

		// Store an option to inform the merchant that Gift Cards has been force-disabled.
		// Using version number in the option name so it's easy to remove in the future.
		update_option( 'woocommerce_square_3_7_1_gift_cards_force_disable_notice', 'yes' );
	}

	/**
	 * Generates a milestone notice message.
	 *
	 * @since 2.1.7
	 *
	 * @param string $custom_message Custom text that notes what milestone was completed.
	 * @return string
	 */
	protected function generate_milestone_notice_message( $custom_message ) {

		$message = '';

		if ( $this->get_plugin()->get_reviews_url() ) {

			// to be prepended at random to each milestone notice.
			$exclamations = array(
				__( 'Awesome', 'woocommerce-square' ),
				__( 'Congratulations', 'woocommerce-square' ),
				__( 'Great', 'woocommerce-square' ),
				__( 'Fantastic', 'woocommerce-square' ),
			);

			$message = $exclamations[ array_rand( $exclamations ) ] . ', ' . esc_html( $custom_message ) . ' ';

			$message .= sprintf(
			/* translators: Placeholders: %1$s - plugin name, %2$s - <a> tag, %3$s - </a> tag, %4$s - <a> tag, %5$s - </a> tag */
				__( 'Are you having a great experience with %1$s so far? Please consider %2$sleaving a review%3$s! If things aren\'t going quite as expected, we\'re happy to help -- please %4$sreach out to our support team%5$s.', 'woocommerce-square' ),
				'<strong>' . esc_html( $this->get_plugin()->get_plugin_name() ) . '</strong>',
				'<a href="' . esc_url( $this->get_plugin()->get_reviews_url() ) . '">',
				'</a>',
				'<a href="' . esc_url( $this->get_plugin()->get_support_url() ) . '">',
				'</a>'
			);
		}

		return $message;
	}

	/**
	 * Upgrades to version 2.2.0.
	 *
	 * @since 2.2.0
	 */
	protected function upgrade_to_2_2_0() {

		$v1_settings = get_option( 'wc_square_settings', array() );

		$v2_settings = $this->set_environment_location_id( $v1_settings );

		update_option( 'wc_square_settings', $v2_settings );
	}

	/**
	 * Upgrades to version 2.3.0.
	 *
	 * @since 2.3.0
	 */
	protected function upgrade_to_2_3_0() {
		// Set `enable_digital_wallets` default to no for existing stores
		$gateway_settings = get_option( 'woocommerce_square_credit_card_settings', array() );

		if ( ! isset( $gateway_settings['enable_digital_wallets'] ) ) {
			$gateway_settings['enable_digital_wallets'] = 'no';
		}

		update_option( 'woocommerce_square_credit_card_settings', $gateway_settings );
	}

	/**
	 * Deletes the transient that holds locations data.
	 *
	 * @see https://github.com/woocommerce/woocommerce-square/issues/786#issuecomment-1121388650
	 *
	 * @since 3.0.2
	 */
	protected function upgrade_to_3_0_2() {
		delete_transient( 'wc_square_locations' );
	}

	/**
	 * Migrates plugin settings from v1 to v2.
	 *
	 * @see Lifecycle::upgrade_to_2_0_0()
	 *
	 * @since 2.0.0
	 *
	 * @return bool whether a Sync setting was enabled from migration
	 */
	private function migrate_plugin_settings() {

		$this->get_plugin()->log( 'Migrating plugin settings...' );

		// get legacy and new default settings.
		$new_settings    = get_option( 'wc_square_settings', array() );
		$legacy_settings = get_option( 'woocommerce_squareconnect_settings', array() );
		$email_settings  = get_option( 'woocommerce_wc_square_sync_completed_settings', array() );

		// bail if they already have v2 settings present.
		if ( ! empty( $new_settings ) ) {
			return;
		}

		// handle access token first.
		$legacy_access_token = get_option( 'woocommerce_square_merchant_access_token' );
		if ( $legacy_access_token ) {

			// the access token was previously stored unencrypted.
			if ( ! empty( $legacy_access_token ) && Utilities\Encryption_Utility::is_encryption_supported() ) {

				$encryption = new Utilities\Encryption_Utility();

				try {
					$legacy_access_token = $encryption->encrypt_data( $legacy_access_token );
				} catch ( \Exception $exception ) {
					// log the event, but don't halt the process.
					$this->get_plugin()->log( 'Could not encrypt access token during upgrade. ' . $exception->getMessage() );
				}
			}

			// previously only 'production' environment was assumed.
			$access_tokens               = get_option( 'wc_square_access_tokens', array() );
			$access_tokens['production'] = is_string( $legacy_access_token ) ? $legacy_access_token : '';

			update_option( 'wc_square_access_tokens', $access_tokens );
		}

		// migrate store location.
		if ( ! empty( $legacy_settings['location'] ) ) {
			$new_settings['location_id'] = $legacy_settings['location'];
		}

		// toggle debug logging.
		$new_settings['debug_logging_enabled'] = isset( $legacy_settings['logging'] ) && in_array( $legacy_settings['logging'], array( 'yes', 'no' ), true ) ? $legacy_settings['logging'] : 'no';

		// set the SOR and inventory sync values.
		$new_settings = $this->get_migrated_system_of_record( $legacy_settings, $new_settings );

		// migrate email notification settings: if there's a recipient, we enable email and pass recipient(s) to email setting.
		if ( isset( $legacy_settings['sync_email'] ) && is_string( $legacy_settings['sync_email'] ) && '' !== trim( $legacy_settings['sync_email'] ) ) {
			$email_settings['enabled']   = 'yes';
			$email_settings['recipient'] = $legacy_settings['sync_email'];
		} else {
			$email_settings['enabled']   = 'no';
			$email_settings['recipient'] = '';
		}

		// save email settings.
		update_option( 'woocommerce_wc_square_sync_completed_settings', $email_settings );
		// save plugin settings.
		update_option( 'wc_square_settings', $new_settings );

		$this->get_plugin()->log( 'Plugin settings migration complete.' );

		return isset( $new_settings['system_of_record'] ) && Settings::SYSTEM_OF_RECORD_DISABLED !== $new_settings['system_of_record'];
	}


	/**
	 * Migrates gateway settings from v1 to v2.
	 *
	 * @see Lifecycle::upgrade_to_2_0_0()
	 *
	 * @since 2.0.0
	 */
	private function migrate_gateway_settings() {

		$this->get_plugin()->log( 'Migrating gateway settings...' );

		$legacy_settings = get_option( 'woocommerce_square_settings', array() );
		$new_settings    = get_option( 'woocommerce_square_credit_card_settings', array() );

		// bail if they already have v2 settings present.
		if ( ! empty( $new_settings ) ) {
			return;
		}

		if ( isset( $legacy_settings['enabled'] ) ) {
			$new_settings['enabled'] = 'yes' === $legacy_settings['enabled'] ? 'yes' : 'no';
		}

		if ( isset( $legacy_settings['title'] ) && is_string( $legacy_settings['title'] ) ) {
			$new_settings['title'] = $legacy_settings['title'];
		}

		if ( isset( $legacy_settings['description'] ) && is_string( $legacy_settings['description'] ) ) {
			$new_settings['description'] = $legacy_settings['description'];
		}

		// note: the following is not an error, the setting on v1 intends "delayed" capture, hence authorization only, if set.
		if ( isset( $legacy_settings['capture'] ) ) {
			$new_settings['transaction_type'] = 'yes' === $legacy_settings['capture'] ? Gateway::TRANSACTION_TYPE_AUTHORIZATION : Gateway::TRANSACTION_TYPE_CHARGE;
		}

		// not quite the same, since tokenization is a new thing, but we could presume the intention to let customers save their payment details.
		if ( isset( $legacy_settings['create_customer'] ) ) {
			$new_settings['tokenization'] = 'yes' === $legacy_settings['create_customer'] ? 'yes' : 'no';
		}

		if ( isset( $legacy_settings['logging'] ) ) {
			$new_settings['debug_mode'] = 'yes' === $legacy_settings['logging'] ? 'log' : 'off';
		}

		// there was no card types setting in v1.
		$new_settings['card_types'] = array(
			'VISA',
			'MC',
			'AMEX',
			'JCB',
			// purposefully omit dinersclub & discover.
		);

		// save migrated settings.
		update_option( 'woocommerce_square_credit_card_settings', $new_settings );

		$this->get_plugin()->log( 'Gateway settings migration complete.' );
	}


	/**
	 * Migrates order data from v1 to v2.
	 *
	 * @see Lifecycle::upgrade_to_2_0_0()
	 *
	 * @since 2.0.0
	 */
	private function migrate_orders() {
		global $wpdb;

		$this->get_plugin()->log( 'Migrating orders data...' );

		$wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_wc_square_credit_card_charge_captured' ), array( 'meta_key' => '_square_charge_captured' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery

		// move payment ID to new gateway ID meta key value.
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->postmeta,
			array(
				'meta_value' => 'square_credit_card', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			),
			array(
				'meta_key'   => '_payment_method', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => 'square', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$this->get_plugin()->log( 'Orders migration complete.' );
	}


	/**
	 * Migrates product data from v1 to v2.
	 *
	 * @see Lifecycle::upgrade_to_2_0_0()
	 *
	 * @since 2.0.0
	 */
	private function migrate_products() {
		global $wpdb;

		$this->get_plugin()->log( 'Migrating products data...' );

		// the handling in v1 was reversed, so we want products where sync wasn't disabled.
		$legacy_product_ids = get_posts(
			array(
				'nopaging'    => true,
				'post_type'   => 'product',
				'post_status' => 'all',
				'fields'      => 'ids',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

					'relation' => 'OR',
					array(
						'key'   => '_wcsquare_disable_sync',
						'value' => 'no',
					),
					array(
						'key'     => '_wcsquare_disable_sync',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		// in v2 we turn those products as flagged to be sync-enabled instead.
		if ( ! empty( $legacy_product_ids ) ) {

			$failed_products = array();

			// ensure taxonomy is registered at this stage.
			if ( ! taxonomy_exists( Product::SYNCED_WITH_SQUARE_TAXONOMY ) ) {
				Product::init_taxonomies();
			}

			// will not create the term if already exists.
			wp_create_term( 'yes', Product::SYNCED_WITH_SQUARE_TAXONOMY );

			// set Square sync status via taxonomy term.
			foreach ( $legacy_product_ids as $i => $product_id ) {

				$set_term = wp_set_object_terms( $product_id, array( 'yes' ), Product::SYNCED_WITH_SQUARE_TAXONOMY );

				if ( ! is_array( $set_term ) ) {

					unset( $legacy_product_ids[ $i ] );

					$failed_products[] = $product_id;
				}
			}

			// log any errors.
			if ( ! empty( $failed_products ) ) {
				$this->get_plugin()->log( 'Could not update sync with Square status for products with ID: ' . implode( ', ', array_unique( $failed_products ) ) . '.' );
			}
		}

		$this->get_plugin()->log( 'Products migration complete.' );
	}


	/**
	 * Migrates customer data.
	 *
	 * @since 2.0.0
	 */
	private function migrate_customers() {
		global $wpdb;

		$this->get_plugin()->log( 'Migrating customer data.' );

		$rows = (int) $wpdb->update( $wpdb->usermeta, array( 'meta_key' => 'wc_square_customer_id' ), array( 'meta_key' => '_square_customer_id' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery

		$this->get_plugin()->log( sprintf( '%d customers migrated', $rows ) );
	}


	/**
	 * Adds the Sync setting setting to the v2 plugin settings depending on v1 setting values.
	 *
	 * @since 2.0.2
	 *
	 * @param array $v1_settings v1 plugin settings.
	 * @param array $v2_settings v2 plugin settings.
	 * @return array
	 */
	private function get_migrated_system_of_record( $v1_settings, $v2_settings ) {

		$sync_products     = isset( $v1_settings['sync_products'] ) && 'yes' === $v1_settings['sync_products'];
		$sync_inventory    = $sync_products && isset( $v1_settings['sync_inventory'] ) && 'yes' === $v1_settings['sync_inventory'];
		$inventory_polling = isset( $v1_settings['inventory_polling'] ) && 'yes' === $v1_settings['inventory_polling'];

		$v2_settings['system_of_record']      = $sync_products && $inventory_polling ? Settings::SYSTEM_OF_RECORD_SQUARE : Settings::SYSTEM_OF_RECORD_DISABLED;
		$v2_settings['enable_inventory_sync'] = $inventory_polling || $sync_inventory ? 'yes' : 'no';

		return $v2_settings;
	}

	/**
	 * Adds environment specific location_id to, and removes general location_id from v1 setting array.
	 *
	 * @since 2.2.0
	 *
	 * @param array $v1_settings v1 plugin settings.
	 * @return array
	 */
	private function set_environment_location_id( $v1_settings ) {

		$environment = isset( $v1_settings['enable_sandbox'] ) && 'yes' === $v1_settings['enable_sandbox'] ? 'sandbox' : 'production';

		if ( ! isset( $v1_settings[ $environment . '_location_id' ] ) ) {
			$v1_location_id                               = isset( $v1_settings['location_id'] ) ? $v1_settings['location_id'] : '';
			$v1_settings[ $environment . '_location_id' ] = $v1_location_id;
		}

		return $v1_settings;
	}
}
