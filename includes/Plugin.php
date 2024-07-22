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

use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway_Plugin;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Square_Credit_Card_Payment_Token;
use WooCommerce\Square\Framework\Square_Helper;
use WooCommerce\Square\Gateway\Cash_App_Pay_Gateway;
use WooCommerce\Square\Gateway\Gift_Card;
use WooCommerce\Square\Handlers\Background_Job;
use WooCommerce\Square\Handlers\Async_Request;
use WooCommerce\Square\Handlers\Email;
use WooCommerce\Square\Handlers\Order;
use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Handlers\Sync;
use WooCommerce\Square\Handlers\Products;

/**
 * The main plugin class.
 *
 * @since 2.0.0
 */
class Plugin extends Payment_Gateway_Plugin {


	/** plugin version number */
	const VERSION = WC_SQUARE_PLUGIN_VERSION;

	/** plugin ID */
	const PLUGIN_ID = 'square';

	/** string gateway ID */
	const GATEWAY_ID = 'square_credit_card';

	/** string Gift Cards gateway ID */
	const GIFT_CARD_PAY_GATEWAY_ID = 'gift_cards_pay';

	/** string Cash App Pay gateway ID */
	const CASH_APP_PAY_GATEWAY_ID = 'square_cash_app_pay';

	/** @var Plugin plugin instance */
	protected static $instance;

	/** @var Settings settings handler instance */
	private $settings_handler;

	/** @var Handlers\Connection connection handler instance  */
	private $connection_handler;

	/** @var Admin admin handler instance */
	private $admin_handler;

	/** @var Sync sync handler instance */
	private $sync_handler;

	/** @var Background_Job background handler instance */
	private $background_job_handler;

	/** @var AJAX handler instance */
	private $ajax_handler;

	/** @var Email emails handler */
	private $email_handler;

	/** @var Order orders handler */
	private $order_handler;

	/** @var Products products handler */
	private $products_handler;

	/** @var Async_Request Asynchronous request handler */
	private $async_request_handler;

	/**
	 * Constructs the plugin.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		parent::__construct(
			self::PLUGIN_ID,
			self::VERSION,
			array(
				'text_domain'  => 'woocommerce-square',
				'gateways'     => array(
					self::GATEWAY_ID               => Gateway::class,
					self::CASH_APP_PAY_GATEWAY_ID  => Cash_App_Pay_Gateway::class,
					self::GIFT_CARD_PAY_GATEWAY_ID => Gift_Card::class,
				),
				'require_ssl'  => true,
				'supports'     => array(
					self::FEATURE_CAPTURE_CHARGE,
					self::FEATURE_CUSTOMER_ID,
					self::FEATURE_MY_PAYMENT_METHODS,
				),
				'dependencies' => array(
					'php_extensions' => array( 'curl', 'json', 'mbstring' ),
				),
			)
		);

		$this->includes();

		/**
		 * Fires upon plugin loaded (legacy hook).
		 *
		 * @since 1.0.0
		 */
		do_action( 'wc_square_loaded' );

		add_action( 'woocommerce_register_taxonomy', array( $this, 'init_taxonomies' ) );
		add_action( 'admin_notices', array( $this, 'add_admin_notices' ) );
		add_filter( 'woocommerce_locate_template', array( $this, 'locate_template' ), 20, 3 );
		add_filter( 'woocommerce_locate_core_template', array( $this, 'locate_template' ), 20, 3 );

		add_action( 'action_scheduler_init', array( $this, 'schedule_token_migration_job' ) );
		add_action( 'wc_square_init_payment_token_migration_v2', array( $this, 'register_payment_tokens_migration_scheduler' ) );
		add_action( 'wc_square_init_payment_token_migration', '__return_false' );
	}

	/**
	 * Includes required classes.
	 *
	 * @since 2.0.0
	 */
	private function includes() {

		$this->connection_handler = new Handlers\Connection( $this );

		$this->sync_handler = new Sync( $this );

		// background export must be loaded all the time, because otherwise background jobs simply won't work
		require_once $this->get_framework_path() . '/Utilities/WP_Background_Job_Handler.php';

		$this->background_job_handler = new Background_Job();

		$this->ajax_handler = new AJAX();

		$this->email_handler = new Email();

		$this->order_handler = new Order();

		if ( class_exists( '\WooCommerce\Square\Handlers\Async_Request' ) ) {
			$this->async_request_handler = new Async_Request();
		}
	}


	/**
	 * Adds API request logging.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function add_api_request_logging() {

		if ( ! has_action( 'wc_square_api_request_performed' ) ) {
			add_action( 'wc_square_api_request_performed', array( $this, 'log_api_request' ), 10, 2 );
		}
	}


	/**
	 * Logs an API request & response.
	 *
	 * @since 2.0.0
	 *
	 * @param array $request request data
	 * @param array $response response data
	 * @param string|null $log_id log ID
	 */
	public function log_api_request( $request, $response, $log_id = null ) {

		if ( $this->get_settings_handler() && $this->get_settings_handler()->is_debug_enabled() ) {
			parent::log_api_request( $request, $response, $log_id );
		}
	}


	/**
	 * If debug logging is enabled, saves errors or messages to Square Log
	 *
	 * @since 2.2.4
	 * @param string $message error or message to save to log
	 * @param string $log_id optional log id to segment the files by, defaults to plugin id
	 */
	public function log( $message, $log_id = null ) {

		if ( $this->get_settings_handler() && $this->get_settings_handler()->is_debug_enabled() ) {
			parent::log( $message, $log_id );
		}
	}


	/**
	 * Initializes the lifecycle handler.
	 *
	 * @since 2.0.0
	 */
	public function init_lifecycle_handler() {

		$this->lifecycle_handler = new Lifecycle( $this );
	}


	/**
	 * Registers custom taxonomies.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function init_taxonomies() {

		Product::init_taxonomies();
	}


	/**
	 * Initializes the general plugin functionality.
	 *
	 * @since 2.0.0
	 */
	public function init_plugin() {

		$this->settings_handler = new Settings( $this );
		$this->products_handler = new Products( $this );

		if ( ! $this->admin_handler && is_admin() ) {
			$this->admin_handler = new Admin( $this );
		}

		// WooPayments compatibility.
		$wcpay_compatibility = new WC_Payments_Compatibility();
		$wcpay_compatibility->init();

		/**
		 * @see wc_square_initialized
		 * @since 2.0.0
		*/
		do_action( 'wc_square_initialized' );
	}


	/**
	 * Locates the WooCommerce template files from our templates directory.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @param string $template already found template
	 * @param string $template_name searchable template name
	 * @param string $template_path template path
	 * @return string search result for the template
	 */
	public function locate_template( $template, $template_name, $template_path ) {

		// only keep looking if no custom theme template was found
		// or if a default WooCommerce template was found
		if ( ! $template || Square_Helper::str_starts_with( $template, WC()->plugin_path() ) ) {

			// set the path to our templates directory
			$plugin_path = $this->get_plugin_path() . '/templates/';

			// if a template is found, make it so
			if ( is_readable( $plugin_path . $template_name ) ) {
				$template = $plugin_path . $template_name;
			}
		}

		return $template;
	}


	/** Admin methods *************************************************************************************************/


	/**
	 * Adds admin notices.
	 *
	 * @since 2.0.0
	 */
	public function add_admin_notices() {

		// show any one-off messages
		$this->get_message_handler()->show_messages();

		// display a notice if the auto-refresh failed
		if ( get_option( 'wc_square_refresh_failed', false ) ) {

			$message = sprintf(
				/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
				__( 'Heads up! There may be a problem with your connection to Square. In order to continue accepting payments, please %1$sdisconnect and re-connect your site%2$s.', 'woocommerce-square' ),
				'<a href="' . esc_url( $this->get_settings_url() ) . '">',
				'</a>'
			);

			$this->get_admin_notice_handler()->add_admin_notice(
				$message,
				'refresh-failed',
				array(
					'dismissible'  => false,
					'notice_class' => 'notice-warning',
				)
			);
		}

		if ( $this->get_settings_handler()->is_connected() ) {

			$message = '<strong>' . __( 'You are connected to Square!', 'woocommerce-square' ) . '</strong>';

			// prompt to set a location if not set
			if ( ! $this->get_settings_handler()->get_location_id() ) {

				if ( $this->is_plugin_settings() ) {

					$instruction = __( 'To get started, set your business location.', 'woocommerce-square' );

				} else {

					$instruction = sprintf(
						/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
						__( 'Visit the %1$splugin settings%2$s to set your business location.', 'woocommerce-square' ),
						'<a href="' . esc_url( $this->get_settings_url() ) . '">',
						'</a>'
					);
				}

				$this->get_admin_notice_handler()->add_admin_notice( $message . ' ' . $instruction, 'set-location' );

			} elseif ( ! $this->get_sync_handler()->get_last_synced_at() && $this->get_settings_handler()->is_product_sync_enabled() ) {

				$message = __( 'You are ready to sync products!', 'woocommerce-square' );

				if ( ! empty( Product::get_products_synced_with_square() ) ) {

					$instruction = sprintf(
						/* translators: Placeholders: %1$s - <strong> tag, %2$s - product count, %3$s - </strong> tag, %4$s - <a> tag, %5$s - </a> tag */
						__( '%1$s%2$d products%3$s are marked "sync with Square". %4$sStart a new sync now &raquo;%5$s', 'woocommerce-square' ),
						'<strong>',
						count( Product::get_products_synced_with_square() ),
						'</strong>',
						'<a href="' . esc_url( add_query_arg( 'section', 'update', $this->get_settings_url() ) ) . '">',
						'</a>'
					);

				} else {

					$instruction = sprintf(
						/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - <a> tag, %4$s - </a> tag */
						__( '%1$sNo products%2$s are marked "sync with Square". %3$sUpdate your products to sync data &raquo;%4$s', 'woocommerce-square' ),
						'<strong>',
						'</strong>',
						'<a href="' . esc_url( admin_url( 'edit.php?post_type=product' ) ) . '">',
						'</a>'
					);
				}

				$this->get_admin_notice_handler()->add_admin_notice( $message . ' ' . $instruction, 'set-location' );
			}

			// a notice for when WC stock handling is globally disabled
			if ( 'yes' !== get_option( 'woocommerce_manage_stock' ) && $this->get_settings_handler()->is_inventory_sync_enabled() ) {

				$message = sprintf(
					/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
					__( 'Heads up! Square is configured to sync product inventory, but WooCommerce stock management is disabled. Please %1$senable stock management%2$s to ensure product inventory counts are kept in sync.', 'woocommerce-square' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=products&section=inventory' ) ) . '">',
					'</a>'
				);

				$this->get_admin_notice_handler()->add_admin_notice(
					$message,
					'enable-wc-sync',
					array(
						'notice_class' => 'notice-warning',
					)
				);
			}
		} else {

			$instruction = '';

			if ( wc_square()->get_dependency_handler()->meets_php_dependencies() ) {
				if ( $this->is_plugin_settings() ) {
					$instruction = __( 'To get started, connect with Square.', 'woocommerce-square' );
				} else {
					$instruction = sprintf(
						/* translators: Placeholders: %1$s - <a> tag, %2$s - </a> tag */
						__( 'To get started, %1$sconnect with Square &raquo;%2$s', 'woocommerce-square' ),
						'<a href="' . esc_url( $this->get_settings_url() ) . '">',
						'</a>'
					);
				}
			}

			$message = sprintf(
				/* translators: Placeholders: %1$s - plugin name */
				__( 'Thanks for installing %1$s!', 'woocommerce-square' ),
				esc_html( $this->get_plugin_name() )
			);

			$this->get_admin_notice_handler()->add_admin_notice( $message . ' ' . $instruction, 'connect' );
		}

		// add a notice for out-of-bounds base locations
		$this->add_base_location_admin_notice();

		// add a notice when no refresh token is available
		$this->add_missing_refresh_token_notice();

		// add a tax-inclusive warning to product pages
		$this->add_tax_inclusive_pricing_notice();

		if ( get_option( 'wc_square_updated_to_2_0_0' ) ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - plugin name, %2$ - plugin version number, %3$s - opening <a> HTML link tag, %4$s - closing </a> HTML link tag,  %5$s - opening <a> HTML link tag, %6$s - closing </a> HTML link tag*/
					esc_html__( '%1$s has been updated to version %2$s. In order to continue syncing product inventory, please make sure to disconnect and reconnect with Square from the %3$splugin settings%4$s and re-sync your products. Read more in the %5$supdated documentation%6$s.', 'woocommerce-square' ),
					'<strong>' . esc_html( $this->get_plugin_name() ) . '</strong>',
					$this->get_version(),
					'<a href="' . esc_url( $this->get_settings_url() ) . '">',
					'</a>',
					'<a href="' . esc_url( $this->get_documentation_url() ) . '">',
					'</a>'
				),
				'updated-to-v2',
				array( 'notice_class' => 'notice-warning' )
			);
		}
	}


	/**
	 * Adds a notice for out-of-bounds base locations.
	 *
	 * @since 2.0.0
	 */
	protected function add_base_location_admin_notice() {

		$accepted_countries = array(
			'US',
			'CA',
			'GB',
			'AU',
			'JP',
			'IE',
			'FR',
			'ES',
		);

		$base_location = wc_get_base_location();

		if ( isset( $base_location['country'] ) && ! in_array( $base_location['country'], $accepted_countries, true ) ) {

			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - 2-character country code, %4$s - comma separated list of 2-character country codes */
					__( '%1$sWooCommerce Square:%2$s Your base country is %3$s, but Square can’t accept transactions from merchants outside of %4$s.', 'woocommerce-square' ),
					'<strong>',
					'</strong>',
					esc_html( $base_location['country'] ),
					esc_html( Square_Helper::list_array_items( $accepted_countries ) )
				),
				'wc-square-base-location',
				array(
					'notice_class' => 'notice-error',
				)
			);
		}
	}

	/**
	 * Adds a notice if no refresh token has been cached.
	 *
	 * @since 2.0.5
	 */
	protected function add_missing_refresh_token_notice() {
		if ( $this->get_settings_handler()->is_sandbox() ) {
			return;
		}

		$refresh_token    = '';
		$settings_handler = $this->get_settings_handler();

		if ( method_exists( $settings_handler, 'get_access_token' ) ) {
			$access_token = $settings_handler->get_access_token();
			if ( empty( $access_token ) ) {
				// We are already in a disconnected state, don't show the warning.
				return;
			}
		}

		if ( method_exists( $settings_handler, 'get_refresh_token' ) ) {
			$refresh_token = $settings_handler->get_refresh_token();
		}

		if ( empty( $refresh_token ) ) {
			$this->get_admin_notice_handler()->add_admin_notice(
				sprintf(
					/* translators: Placeholders: %1$s - <strong> tag, %2$s - </strong> tag, %3$s - <a> tag, %4$s - </a> tag */
					__( '%1$sWooCommerce Square:%2$s Automatic refreshing of the connection to Square is inactive. Please disconnect and reconnect to resolve.', 'woocommerce-square' ),
					'<strong>',
					'</strong>'
				),
				'wc-square-missing-refresh-token',
				array(
					'dismissible'  => false,
					'notice_class' => 'notice-error',
				)
			);
		}
	}


	/**
	 * Adds a tax-inclusive admin warning to product pages.
	 *
	 * @since 2.0.0
	 */
	protected function add_tax_inclusive_pricing_notice() {
		global $typenow;

		// only show on product edit pages when configured that prices include tax
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required, not writing any changes.
		if ( 'product' === $typenow && isset( $_GET['action'], $_GET['post'] ) && 'edit' === $_GET['action'] && wc_prices_include_tax() && $this->get_settings_handler()->is_product_sync_enabled() ) {

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required, not writing any changes.
			$product = wc_get_product( (int) $_GET['post'] );

			// only show for products configured as taxable and sync with Square
			if ( $product instanceof \WC_Product && $product->is_taxable() && Product::is_synced_with_square( $product ) ) {

				$this->get_admin_notice_handler()->add_admin_notice(
					sprintf(
						/* translators: Placeholders: %1$s = <strong> tag, %2$s = </strong> tag */
						__( '%1$sWooCommerce Square:%2$s Product prices are entered inclusive of tax, but Square does not support syncing tax-inclusive prices. Please make sure your Square tax rates match your WooCommerce tax rates.', 'woocommerce-square' ),
						'<strong>',
						'</strong>'
					),
					'wc-square-tax-inclusive',
					array(
						'notice_class' => 'notice-warning',
					)
				);
			}
		}
	}


	/**
	 * Adds admin notices for currency issues.
	 *
	 * @since 2.0.0
	 */
	protected function add_currency_admin_notices() {

		parent::add_currency_admin_notices();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce not required, only showing a notice.
		if ( isset( $_GET['page'] ) && 'wc-settings' === $_GET['page'] && $this->get_settings_handler()->is_connected() ) {

			foreach ( $this->get_settings_handler()->get_locations() as $location ) {

				if ( $this->get_settings_handler()->get_location_id() === $location->getId() && get_woocommerce_currency() !== $location->getCurrency() ) {

					$this->get_admin_notice_handler()->add_admin_notice(
						sprintf(
							/* translators: Placeholders: %1$s = store currency, %2$s = configured Square business location currency, %3$s = <a> tag, %4$s = </a> tag, %5$s = <a> tag, %6$s = </a> tag */
							__( 'Heads up! Your store currency is %1$s but your configured Square business location currency is %2$s, so payments cannot be processed. Please %3$schoose a different business location%4$s or change your %5$sshop currency%6$s.', 'woocommerce-square' ),
							'<strong>' . esc_html( get_woocommerce_currency() ) . '</strong>',
							'<strong>' . esc_html( $location->getCurrency() ) . '</strong>',
							'<a href="' . esc_url( $this->get_settings_url() ) . '">',
							'</a>',
							'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings' ) ) . '">',
							'</a>'
						),
						'wc-square-currency-mismatch',
						array(
							'notice_class' => 'notice-error',
						)
					);
				}
			}
		}
	}


	/** Helper methods ************************************************************************************************/


	/**
	 * Returns an idempotency key to be used in Square API requests.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key_input
	 * @param bool $append_key_input
	 * @return string
	 */
	public function get_idempotency_key( $key_input = '', $append_key_input = true ) {

		if ( '' === $key_input ) {
			$key_input = uniqid( '', false );
		}

		/**
		 * Filters an idempotency key.
		 *
		 * @since 2.0.0
		 *
		 * @param string $key_input
		 */
		return apply_filters( 'wc_square_idempotency_key', sha1( get_option( 'siteurl' ) . $key_input ) . ( $append_key_input ? ':' . $key_input : '' ) );
	}


	/** Conditional methods *******************************************************************************************/


	/**
	 * Determines if viewing the plugin settings.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_plugin_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce note required, read-only check.
		return parent::is_plugin_settings() || ( isset( $_GET['page'], $_GET['tab'] ) && 'wc-settings' === $_GET['page'] && self::PLUGIN_ID === $_GET['tab'] );
	}

	/**
	 * Determines if viewing the gateway settings.
	 *
	 * @since 2.3.0
	 *
	 * @return bool
	 */
	public function is_gateway_settings() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce note required, read-only check.
		return isset( $_GET['page'], $_GET['tab'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'checkout' === $_GET['tab'] && self::GATEWAY_ID === $_GET['section'];
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the main Square API handler.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $access_token API access token
	 * @return API
	 */
	public function get_api( $access_token = null, $is_sandbox = null ) {

		if ( ! $access_token ) {
			$access_token = $this->get_settings_handler()->get_access_token();
		}

		if ( is_null( $is_sandbox ) ) {
			$is_sandbox = $this->get_settings_handler()->is_sandbox();
		}

		return new API( $access_token, $is_sandbox );
	}


	/**
	 * Gets the connection handler.
	 *
	 * @since 2.0.0
	 *
	 * @return Handlers\Connection
	 */
	public function get_connection_handler() {

		return $this->connection_handler;
	}


	/**
	 * Gets the sync handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Sync
	 */
	public function get_sync_handler() {

		return $this->sync_handler;
	}


	/**
	 * Gets the background sync handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Background_Job
	 */
	public function get_background_job_handler() {

		return $this->background_job_handler;
	}


	/**
	 * Gets the settings handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Settings
	 */
	public function get_settings_handler() {

		return $this->settings_handler;
	}



	/**
	 * Gets the admin handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Admin|null
	 */
	public function get_admin_handler() {

		// throw a notice if calling before admin_init
		Square_Helper::maybe_doing_it_early( 'admin_init', __METHOD__, '2.0.0' );

		return $this->admin_handler;
	}


	/**
	 * Gets the email handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Email
	 */
	public function get_email_handler() {

		return $this->email_handler;
	}


	/**
	 * Gets the order handler instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Order
	 */
	public function get_order_handler() {

		return $this->order_handler;
	}

	/**
	 * Get the products handler instance/
	 *
	 * @since 2.0.8
	 *
	 * @return Products
	 */
	public function get_products_handler() {
		return $this->products_handler;
	}

	/**
	 * Gets the Asynchronous request handler instance.
	 *
	 * @since 4.1.0
	 *
	 * @return Async_Request
	 */
	public function get_async_request_handler() {
		return $this->async_request_handler;
	}

	/**
	 * Gets the plugin name.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_plugin_name() {

		return __( 'WooCommerce Square', 'woocommerce-square' );
	}


	/**
	 * Gets the settings URL.
	 *
	 * @since 2.0.0
	 *
	 * @param null|string $gateway_id gateway ID
	 * @return string
	 */
	public function get_settings_url( $gateway_id = null ) {

		$params = array(
			'page' => 'wc-settings',
			'tab'  => self::PLUGIN_ID,
		);

		// All usage of this return value has been escaped late.
		// nosemgrep audit.php.wp.security.xss.query-arg
		return add_query_arg( $params, admin_url( 'admin.php' ) );
	}

	/**
	 * Gets the Setup Wizard URL.
	 *
	 * @since 4.7.0
	 *
	 * @param string $step Step to go to.
	 *
	 * @return string
	 */
	public function get_square_onboarding_url( $step = '' ) {
		$params = array(
			'page' => 'woocommerce-square-onboarding',
		);

		// Add 'step' if $step is not empty.
		if ( ! empty( $step ) ) {
			$params['step'] = $step;
		}

		// All usage of this return value has been escaped late.
		// nosemgrep audit.php.wp.security.xss.query-arg
		return add_query_arg( $params, admin_url( 'admin.php' ) );
	}


	/**
	 * Gets the sale page URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_sales_page_url() {

		return 'https://woocommerce.com/products/square/';
	}


	/**
	 * Gets the documentation URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_documentation_url() {

		return 'https://docs.woocommerce.com/document/woocommerce-square/';
	}


	/**
	 * Gets the plugin reviews page URL.
	 *
	 * Used for the 'Reviews' plugin action and review prompts.
	 *
	 * @since 2.1.7
	 *
	 * @return string
	 */
	public function get_reviews_url() {

		return $this->get_sales_page_url() ? $this->get_sales_page_url() . '#comments' : '';
	}


	/**
	 * Gets the support URL.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_support_url() {

		return 'https://woocommerce.com/my-account/create-a-ticket/?select=1770503';
	}


	/**
	 * Gets __DIR__.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_file() {

		return __DIR__;
	}


	/**
	 * Gets the singleton instance of the plugin.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Schedules the migration of payment tokens.
	 *
	 * @since 3.8.0
	 */
	public function schedule_token_migration_job() {
		if ( false !== get_option( 'wc_square_payment_token_migration_complete' ) ) {
			return;
		}

		// Remove all OLD scheduled actions to cleanup DB.
		// TODO: Remove this in next release.
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = 'wc_square_init_payment_token_migration'" );

		if ( false === as_has_scheduled_action( 'wc_square_init_payment_token_migration_v2' ) ) {
			as_enqueue_async_action( 'wc_square_init_payment_token_migration_v2', array( 'page' => 1 ) );
		}
	}

	/**
	 * Migrates payment token from user_meta to WC_Payment_Token_CC.
	 *
	 * @param integer $page Pagination number.
	 * @since 3.8.0
	 */
	public function register_payment_tokens_migration_scheduler( $page ) {
		$payment_tokens_handler = wc_square()->get_gateway()->get_payment_tokens_handler();
		$meta_key               = $payment_tokens_handler->get_user_meta_name();

		// Get 5 users in a batch.
		$users = get_users(
			array(
				'fields'     => array( 'ID' ),
				'number'     => 5,
				'paged'      => $page,
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => $meta_key,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		// If users array is empty, then set status in options to indicate migration is complete.
		if ( empty( $users ) ) {
			$payment_tokens_handler->clear_all_transients();
			update_option( 'wc_square_payment_token_migration_complete', true );
			return;
		}

		// Re-run scheduler for the next page of users.
		as_enqueue_async_action( 'wc_square_init_payment_token_migration_v2', array( 'page' => $page + 1 ) );

		foreach ( $users as $user ) {
			$user_payment_tokens = get_user_meta( $user->id, $meta_key, true );

			if ( ! is_array( $user_payment_tokens ) || empty( $user_payment_tokens ) ) {
				continue;
			}

			foreach ( $user_payment_tokens as $token => $user_payment_token_data ) {
				// Check if token already exists in WC_Payment_Token_CC.
				if ( $payment_tokens_handler->user_has_token( $user->id, $token ) ) {
					continue;
				}

				$payment_token = new Square_Credit_Card_Payment_Token();
				$payment_token->set_token( $token );
				$payment_token->set_card_type( $user_payment_token_data['card_type'] );
				$payment_token->set_last4( $user_payment_token_data['last_four'] );
				$payment_token->set_expiry_month( $user_payment_token_data['exp_month'] );
				$payment_token->set_expiry_year( $user_payment_token_data['exp_year'] );
				$payment_token->set_user_id( $user->id );
				$payment_token->set_gateway_id( wc_square()->get_gateway()->get_id() );

				if ( isset( $user_payment_token_data['nickname'] ) ) {
					$payment_token->set_nickname( $user_payment_token_data['nickname'] );
				}

				$payment_token->save();
			}
		}
	}
}
