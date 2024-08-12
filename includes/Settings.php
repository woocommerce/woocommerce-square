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

use Exception;
use WooCommerce\Square\Framework\Square_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * The settings API class.
 *
 * This handles registering, getting, and storing the general plugin options.
 *
 * Note that this is separate from the gateway settings.
 *
 * @since 2.0.0
 */
class Settings extends \WC_Settings_API {


	/**
	 * Square Sync setting.
	 *
	 * @var string square Sync setting indicator
	 */
	const SYSTEM_OF_RECORD_SQUARE = 'square';

	/**
	 * Woocommerce Sync setting.
	 *
	 * @var string square Sync setting indicator
	 */
	const SYSTEM_OF_RECORD_WOOCOMMERCE = 'woocommerce';

	/**
	 * Disabled Sync setting.
	 *
	 * @var string Sync setting indicator for disabled sync
	 */
	const SYSTEM_OF_RECORD_DISABLED = 'disabled';

	/** Debug mode log to file */
	const DEBUG_MODE_LOG = 'log';

	/** Debug mode display on checkout */
	const DEBUG_MODE_CHECKOUT = 'checkout';

	/** Debug mode log to file and display on checkout */
	const DEBUG_MODE_BOTH = 'both';

	/** Debug mode disabled */
	const DEBUG_MODE_OFF = 'off';

	/**
	 * Refresh token
	 *
	 * @var string un-encrypted refresh token
	 */
	protected $refresh_token;

	/**
	 * Access token
	 *
	 * @var string un-encrypted access token
	 */
	protected $access_token;

	/**
	 * Square business locations
	 *
	 * @var array business locations returned by the API
	 */
	protected $locations;

	/**
	 * Square plugin instance
	 *
	 * @var Plugin plugin instance
	 */
	protected $plugin;


	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 *
	 * @param Plugin $plugin plugin instance.
	 */
	public function __construct( Plugin $plugin ) {

		$this->plugin    = $plugin;
		$this->plugin_id = 'wc_';
		$this->id        = $plugin->get_id();

		add_action( 'init', array( $this, 'init' ) );

		// remove some of our custom fields that shouldn't be saved.
		add_action(
			'woocommerce_settings_api_sanitized_fields_' . $this->id,
			function ( $fields ) {

				unset( $fields['general'], $fields['connect'], $fields['import_products'] );

				if ( $this->is_sandbox() ) {
					$this->update_access_token( $fields['sandbox_token'] );
					$this->access_token  = false; // Remove encrypted token.
					$this->refresh_token = false; // Remove encrypted token.
				}

				// Update the sync interval if it is changed.
				$this->maybe_change_sync_interval( $fields );

				$this->init_form_fields(); // Reload form fields after saving token.

				return $fields;
			}
		);

		add_action( 'admin_notices', array( $this, 'show_auth_keys_changed_notice' ) );

		add_action( 'admin_notices', array( $this, 'show_visit_wizard_notice' ) );

		add_action( 'wp_ajax_wc_square_settings_get_locations', array( $this, 'get_locations_ajax_callback' ) );

		add_action( 'admin_init', array( $this, 'square_onboarding_redirect' ) );

		add_action( 'admin_menu', array( $this, 'register_pages' ) );

		add_action( 'woocommerce_settings_square', array( $this, 'render_square_settings_container' ) );

		add_action( 'woocommerce_settings_checkout', array( $this, 'render_payments_settings_container' ) );

		// Register REST API controllers.
		new \WooCommerce\Square\Admin\Rest\WC_REST_Square_Settings_Controller();
		new \WooCommerce\Square\Admin\Rest\WC_REST_Square_Credit_Card_Payment_Settings_Controller();
		new \WooCommerce\Square\Admin\Rest\WC_REST_Square_Cash_App_Settings_Controller();
		new \WooCommerce\Square\Admin\Rest\WC_REST_Square_Gift_Cards_Settings_Controller();
	}

	/**
	 * Redirect users to the templates screen on plugin activation.
	 *
	 * @since 4.7.0
	 */
	public function square_onboarding_redirect() {
		if ( ! $this->get_plugin()->get_dependency_handler()->meets_php_dependencies() ) {
			return;
		}

		if ( ! get_option( 'wc_square_show_wizard_on_activation' ) ) {
			add_option( 'wc_square_show_wizard_on_activation', true, '', 'no' );
			wp_safe_redirect( admin_url( 'admin.php?page=woocommerce-square-onboarding' ) );
			exit;
		}
	}

	/**
	 * Registers square page(s).
	 *
	 * @since 4.7.0
	 */
	public function register_pages() {
		if ( ! $this->get_plugin()->get_dependency_handler()->meets_php_dependencies() ) {
			return;
		}

		$current_page = isset( $_GET['page'] ) ? wp_unslash( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		if ( ! get_option( 'wc_square_connected_page_visited' ) || 'woocommerce-square-onboarding' === $current_page ) {
			add_submenu_page( 'woocommerce', __( 'Square Onboarding', 'woocommerce-square' ), __( 'Square Onboarding', 'woocommerce-square' ), 'manage_woocommerce', 'woocommerce-square-onboarding', array( $this, 'render_onboarding_page' ) ); // phpcs:ignore WordPress.WP.Capabilities.Unknown
		}
	}

	/**
	 * Output the Setup Wizard page(s).
	 */
	public function render_onboarding_page() {
		printf(
			'<div class="wrap" id="woocommerce-square-onboarding"></div>'
		);
	}

	/**
	 * Show a notice to visit the wizard on plugin activation.
	 *
	 * @since 4.7.0
	 */
	public function show_visit_wizard_notice() {
		if ( ! wc_square()->get_dependency_handler()->meets_php_dependencies() ) {
			return;
		}

		if ( get_option( 'wc_square_connected_page_visited' ) ) {
			return;
		}

		wc_square()->get_admin_notice_handler()->add_admin_notice(
			sprintf(
				/* translators: %1$s - <a> tag, %2$s - </a> tag */
				esc_html__(
					'Welcome to Square for WooCommerce! Get started by visiting the %1$sOnboarding Wizard%2$s.',
					'woocommerce-square'
				),
				'<a href="' . esc_url( admin_url( 'admin.php?page=woocommerce-square-onboarding' ) ) . '">',
				'</a>'
			),
			'wc-square-welcome',
			array(
				'dismissible'  => false,
				'notice_class' => 'notice-info',
			)
		);
	}

	/**
	 * Redirect users to the onboarding wizard screen on plugin activation.
	 *
	 * @since 4.7.0
	 */
	public function render_square_settings_container() {
		$section = isset( $_GET['section'] ) && ! empty( $_GET['section'] ) ? wp_unslash( $_GET['section'] ) : 'general'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended

		printf(
			'<div id="woocommerce-square-settings__container-' . esc_html( $section ) . '"></div>',
		);
	}

	/**
	 * Redirect users to the onboarding wizard screen on plugin activation.
	 *
	 * @since 4.7.0
	 */
	public function render_payments_settings_container() {
		$tab     = isset( $_GET['tab'] ) ? wp_unslash( $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended
		$section = isset( $_GET['section'] ) ? wp_unslash( $_GET['section'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Recommended

		if ( 'checkout' !== $tab ) {
			return;
		}

		if ( ! ( 'square_credit_card' === $section || 'square_cash_app_pay' === $section || 'gift_cards_pay' === $section ) ) {
			return;
		}

		printf(
			'<div id="woocommerce-square-payment-gateway-settings__container--' . esc_html( $section ) . '"></div>',
		);
	}

	/**
	 * Show warning to reconnect if the `SQUARE_ENCRYPTION_KEY` and `SQUARE_ENCRYPTION_SALT` constants
	 * are newly added.
	 *
	 * @since 4.2.0
	 */
	public function show_auth_keys_changed_notice() {
		$is_keys_updated = get_option( 'wc_square_auth_key_updated', false );
		$show_message    = ( $this->is_custom_square_auth_keys_set() && empty( $is_keys_updated ) )
							|| ( ! $this->is_custom_square_auth_keys_set() && $is_keys_updated );

		if ( $show_message ) {
			wc_square()->get_admin_notice_handler()->add_admin_notice(
				esc_html__( 'Square was disconnected because authentication keys were changed. Please connect again.', 'woocommerce-square' ),
				'wc-square-disconnected-keys-changed',
				array(
					'dismissible'  => false,
					'notice_class' => 'notice-warning',
				)
			);

			delete_option( 'wc_square_access_tokens' );
		}

		if ( ! $this->is_custom_square_auth_keys_set() && $is_keys_updated ) {
			delete_option( 'wc_square_auth_key_updated' );
		}
	}

	/**
	 * Returns true if `SQUARE_ENCRYPTION_KEY` and `SQUARE_ENCRYPTION_SALT` constants are both set.
	 *
	 * @since 4.2.0
	 *
	 * @return boolean
	 */
	public function is_custom_square_auth_keys_set() {
		return defined( 'SQUARE_ENCRYPTION_KEY' ) && defined( 'SQUARE_ENCRYPTION_SALT' );
	}

	/**
	 * Initializes form fields and settings.
	 *
	 * @since 3.5.1
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();
	}


	/**
	 * Initializes the form fields.
	 *
	 * @since 2.0.0
	 */
	public function init_form_fields() {
		$this->form_fields = array();
	}


	/**
	 * Gets the form fields.
	 *
	 * Overridden to populate the Location settings options on display.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_form_fields() {

		$fields = parent::get_form_fields();

		// Confirm our local enable sandbox setting matches what is sent from the front end
		// to account for changes from sandbox to production incorrectly fetching sandbox locations.
		if ( $this->settings && isset( $_POST['wc_square_environment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing

			$environment = 'yes' === $this->settings['enable_sandbox'] ? 'sandbox' : 'production';

			if ( $environment !== $_POST['wc_square_environment'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				return $fields;
			}
		}

		$location_id_field_key = '';
		// Get the location_id field.
		foreach ( $fields as $key => $value ) {
			if ( strpos( $key, 'location_id' ) ) {
				$location_id_field_key = $key;
				break;
			}
		}

		if ( did_action( 'wc_square_initialized' ) && $this->is_admin_settings_screen() && ! empty( $location_id_field_key ) ) {

			$locations = array(
				'' => __( 'Please choose a location', 'woocommerce-square' ),
			);

			if ( ! empty( $this->get_locations() ) ) {
				foreach ( $this->get_locations() as $location ) {
					if ( 'ACTIVE' === $location->getStatus() && in_array( 'CREDIT_CARD_PROCESSING', (array) $location->getCapabilities(), true ) ) {
						$locations[ $location->getId() ] = $location->getName();
					}
				}
			}

			$fields[ $location_id_field_key ]['options'] = $locations;
		}

		return $fields;
	}


	/**
	 * Generates the HTML for import products button.
	 *
	 * @param string $id form id.
	 * @param array  $field form fields.
	 */
	public function generate_import_products_html( $id, $field ) {

		$is_location_set = (bool) $this->get_location_id();
		$is_sor_set      = (bool) $this->get_system_of_record_name();
		$display         = $is_location_set && $is_sor_set ? '' : 'display: none';

		ob_start();
		?>
		<tr valign="top" style="<?php echo esc_attr( $display ); ?>">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo wp_kses_post( $field['title'] ); ?> <?php echo $this->get_tooltip_html( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<a id="wc_square_import_products" href='#' class='button js-import-square-products <?php echo ( ! $this->get_location_id() ? 'disabled' : '' ); ?>'>
					<?php echo esc_html__( 'Import all products from Square', 'woocommerce-square' ); ?>
				</a>
				<p class="description wc_square_save_changes_message" style="display: none;"><?php esc_html_e( 'You have made changes to the settings. Please save the changes to enable the button.', 'woocommerce-square' ); ?></p>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}


	/**
	 * Generates the Connection field HTML.
	 *
	 * @since 2.0.0
	 *
	 * @param string $id field ID.
	 * @param array  $field field data.
	 * @return string
	 */
	public function generate_connect_html( $id, $field ) {

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo wp_kses_post( $field['title'] ); ?> <?php echo $this->get_tooltip_html( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<?php
				if ( $this->get_access_token() ) {
					echo $this->get_plugin()->get_connection_handler()->get_disconnect_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo $this->get_plugin()->get_connection_handler()->get_connect_button_html( $this->is_sandbox() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}


	/**
	 * Updates the stored refresh token.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token refresh token.
	 */
	public function update_refresh_token( $token ) {

		$refresh_tokens = $this->get_refresh_tokens();
		$environment    = $this->get_environment();

		if ( ! empty( $token ) ) {

			$this->refresh_token = $token;

			if ( Utilities\Encryption_Utility::is_encryption_supported() ) {

				$encryption = new Utilities\Encryption_Utility();

				try {

					$token = $encryption->encrypt_data( $token );

				} catch ( \Exception $exception ) {

					// log the event, but don't halt the process.
					$this->get_plugin()->log( 'Could not encrypt refresh token. ' . $exception->getMessage() );
				}
			}

			$refresh_tokens[ $environment ] = $token;
		}

		update_option( 'wc_square_refresh_tokens', $refresh_tokens );
	}


	/**
	 * Updates the stored access token.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token access token.
	 */
	public function update_access_token( $token ) {

		$access_tokens = $this->get_access_tokens();
		$environment   = $this->get_environment();

		if ( ! empty( $token ) ) {

			$this->access_token = $token;

			if ( Utilities\Encryption_Utility::is_encryption_supported() ) {

				$encryption = new Utilities\Encryption_Utility();

				try {

					$token = $encryption->encrypt_data( $token );

				} catch ( \Exception $exception ) {

					// log the event, but don't halt the process.
					$this->get_plugin()->log( 'Could not encrypt access token. ' . $exception->getMessage() );
				}
			}

			$access_tokens[ $environment ] = $token;
		} elseif ( isset( $access_tokens[ $environment ] ) ) {

			unset( $access_tokens[ $environment ] );
		}

		update_option( 'wc_square_access_tokens', $access_tokens );
	}


	/**
	 * Clears any stored refresh tokens.
	 *
	 * @since 2.0.0
	 */
	public function clear_refresh_tokens() {
		delete_option( 'wc_square_refresh_tokens' );
	}


	/**
	 * Clears any stored access tokens.
	 *
	 * @since 2.0.0
	 */
	public function clear_access_tokens() {

		delete_option( 'wc_square_access_tokens' );
	}


	/**
	 * Clears the location ID from the settings.
	 *
	 * This is helpful on disconnect / revoke so that previously set location IDs don't stick around and cause confusion.
	 *
	 * @since 2.0.0
	 */
	public function clear_location_id() {

		$settings = get_option( $this->get_option_key(), array() );

		$settings[ $this->get_environment() . '_location_id' ] = '';

		update_option( $this->get_option_key(), $settings );
	}


	/** Conditional methods *******************************************************************************************/


	/**
	 * Determines if WooCommerce is configured to be the Sync setting.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_system_of_record_woocommerce() {

		return self::SYSTEM_OF_RECORD_WOOCOMMERCE === $this->get_system_of_record();
	}


	/**
	 * Determines if Square is configured to be the Sync setting.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_system_of_record_square() {

		return self::SYSTEM_OF_RECORD_SQUARE === $this->get_system_of_record();
	}


	/**
	 * Determines if there is no Sync setting.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_system_of_record_disabled() {

		$sor = $this->get_system_of_record();

		return empty( $sor ) || self::SYSTEM_OF_RECORD_DISABLED === $sor;
	}


	/**
	 * Determines if inventory sync is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_inventory_sync_enabled() {

		/**
		 * Filters the inventory sync setting.
		 *
		 * @since 2.0.0
		 */
		return (bool) apply_filters( 'wc_square_inventory_sync_enabled', 'yes' === get_option( 'woocommerce_manage_stock' ) && $this->is_product_sync_enabled() && 'yes' === $this->get_option( 'enable_inventory_sync' ) );
	}

	/**
	 * Determines if image overriding is enabled.
	 *
	 * @since 3.9.0
	 *
	 * @return bool
	 */
	public function is_override_product_images_enabled() {
		/**
		 * Filter to enable/disable overriding product images.
		 *
		 * @since 3.9.0
		 *
		 * @param boolean 'should_override' Boolean flag to toggle overriding image feature.
		 */
		return (bool) apply_filters( 'wc_square_override_product_images_enabled', 'yes' === $this->get_option( 'override_product_images' ) );
	}


	/**
	 * Determines if product sync is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_product_sync_enabled() {

		return ! $this->is_system_of_record_disabled();
	}


	/**
	 * Determines whether to hide products that don't exist in square from the catalog.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function hide_missing_square_products() {

		return 'yes' === $this->get_option( 'hide_missing_products' );
	}

	/**
	 * Returns sync interval in seconds.
	 * Returns 1 hr = 3600 seconds as default.
	 *
	 * @since 3.5.1
	 *
	 * @return int
	 */
	public function get_sync_interval() {
		$sync_interval = $this->get_option( 'sync_interval', '' );
		$sync_interval = empty( $sync_interval ) ? HOUR_IN_SECONDS : $sync_interval * HOUR_IN_SECONDS;

		/**
		 * Filters the frequency with which products should be synced.
		 *
		 * @since 2.0.0
		 *
		 * @param int $interval sync interval in seconds (defaults to one hour)
		 */
		return (int) max( MINUTE_IN_SECONDS, (int) apply_filters( 'wc_square_sync_interval', $sync_interval ) );
	}


	/**
	 * Determines if the plugin settings are fully configured.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_configured() {

		return $this->get_location_id() && $this->get_system_of_record();
	}


	/**
	 * Determines if the plugin is connected to Square.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_connected() {

		return (bool) $this->get_access_token();
	}


	/**
	 * Determines if configured in the sandbox environment.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_sandbox() {

		return 'sandbox' === $this->get_environment();
	}


	/**
	 * Determines if debug logging is enabled.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_debug_enabled() {

		return 'yes' === $this->get_option( 'debug_logging_enabled' );
	}


	/** Getter methods ************************************************************************************************/


	/**
	 * Gets the configured location.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_location_id() {
		$location_id = $this->get_option( $this->get_environment() . '_location_id' );

		if ( empty( $location_id ) ) {
			$square_db_version = get_option( $this->get_plugin()->get_plugin_version_name() );

			// if the Square DB version is still pre-2.2.0, fetch the location ID using the previous option name
			if ( ! empty( $square_db_version ) && version_compare( $square_db_version, '2.2.0', '<' ) ) {
				$location_id = $this->get_option( 'location_id' );
			}
		}

		return $location_id;
	}


	/**
	 * Gets the available locations.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $force whether to force a refetch of the locations.
	 *
	 * @return \Square\Models\Location[]
	 */
	public function get_locations( $force = false ) {
		if ( is_array( $this->locations ) ) {
			return $this->locations;
		}

		$locations_transient_key = 'wc_square_locations_' . $this->get_plugin()->get_version();

		$section = isset( $_GET['section'] ) ? sanitize_text_field( wp_unslash( $_GET['section'] ) ) : false;  // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! ( $this->is_admin_settings_screen() || ( $this->is_admin_settings_screen() && 'update' !== $section ) || $force ) ) {
			$this->locations = get_transient( $locations_transient_key );
		}

		if ( ! is_array( $this->locations ) && did_action( 'wc_square_initialized' ) ) {

			$this->locations = array();

			if ( ! $this->get_plugin()->get_dependency_handler()->meets_php_dependencies() ) {
				return $this->locations;
			}

			try {

				// cache the locations returned so they can be used elsewhere.
				$this->locations = $this->get_plugin()->get_api( $this->get_access_token(), $this->is_sandbox() )->get_locations();
				set_transient( $locations_transient_key, $this->locations, HOUR_IN_SECONDS );

				// check the returned IDs against what's currently configured.
				$stored_location_id = $this->get_location_id();
				$found              = ! $stored_location_id;

				foreach ( $this->locations as $location ) {

					if ( $stored_location_id && $location->getId() === $stored_location_id ) {
						$found = true;
						break;
					}
				}

				// if the currently set location ID is not present in the connected account's available locations, clear it locally.
				if ( ! $found ) {
					$this->clear_location_id();
				}
			} catch ( \Exception $exception ) {
				$this->get_plugin()->log( 'Could not retrieve business locations.' );
			}
		}

		return $this->locations;
	}

	/**
	 * Ajax callback for locations.
	 *
	 * @since 4.7.0
	 */
	public function get_locations_ajax_callback() {
		check_ajax_referer( 'wc_square_settings', 'security' );

		$locations = $this->get_locations( true );

		wp_send_json_success( $locations );
	}

	/**
	 * Gets the configured Sync setting.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_system_of_record() {

		return $this->get_option( 'system_of_record' );
	}


	/**
	 * Gets the configured Sync setting name.
	 *
	 * @since 2.0.0
	 *
	 * @return string or empty string if no Sync setting is configured
	 */
	public function get_system_of_record_name() {

		switch ( $this->get_system_of_record() ) {

			case 'square':
				$sor = __( 'Square', 'woocommerce-square' );
				break;
			case 'woocommerce':
				$sor = __( 'WooCommerce', 'woocommerce-square' );
				break;
			default:
				$sor = '';
				break;
		}

		return $sor;
	}

	/**
	 * Gets the refresh token.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_refresh_token() {

		if ( empty( $this->refresh_token ) ) {

			$tokens = $this->get_refresh_tokens();
			$token  = null;

			if ( ! empty( $tokens[ $this->get_environment() ] ) ) {
				$token = $tokens[ $this->get_environment() ];
			}

			if ( $token && Utilities\Encryption_Utility::is_encryption_supported() ) {

				$encryption = new Utilities\Encryption_Utility();

				try {

					$token = $encryption->decrypt_data( $token );

				} catch ( \Exception $exception ) {

					// log the event, but don't halt the process.
					$this->get_plugin()->log( 'Could not decrypt refresh token. ' . $exception->getMessage() );
				}
			}

			$this->refresh_token = $token;
		}

		/**
		 * Filters the configured refresh token.
		 *
		 * @since 2.0.0
		 *
		 * @param string $refresh_token
		 */
		return apply_filters( 'wc_square_refresh_token', $this->refresh_token );
	}

	/**
	 * Gets the access token.
	 *
	 * @since 2.0.0
	 *
	 * @return string|null
	 */
	public function get_access_token() {

		if ( empty( $this->access_token ) || $this->is_admin_settings_screen() ) {

			$tokens = $this->get_access_tokens();
			$token  = null;

			if ( ! empty( $tokens[ $this->get_environment() ] ) ) {
				$token = $tokens[ $this->get_environment() ];
			}

			if ( $token && Utilities\Encryption_Utility::is_encryption_supported() ) {

				$encryption = new Utilities\Encryption_Utility();

				try {

					$token = $encryption->decrypt_data( $token );

				} catch ( \Exception $exception ) {

					// log the event, but don't halt the process.
					$this->get_plugin()->log( 'Could not decrypt access token. ' . $exception->getMessage() );
				}
			}

			$this->access_token = $token;
		}

		/**
		 * Filters the configured access token.
		 *
		 * @since 2.0.0
		 *
		 * @param string $access_token access token
		 */
		return apply_filters( 'wc_square_access_token', $this->access_token );
	}


	/**
	 * Gets the stored access tokens.
	 *
	 * Each environment may have its own token.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_access_tokens() {
		return (array) get_option( 'wc_square_access_tokens', array() );
	}


	/**
	 * Gets the stored refresh tokens.
	 *
	 * Each environment may have its own token.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_refresh_tokens() {
		return (array) get_option( 'wc_square_refresh_tokens', array() );
	}

	/**
	 * Gets setting enabled sandbox.
	 *
	 * @since 2.1.2
	 *
	 * @return string
	 */
	public function get_enable_sandbox() {
		return $this->get_option( 'enable_sandbox' );
	}

	/**
	 * Tells is if the setting for enabling sandbox is checked.
	 *
	 * @since 2.1.2
	 *
	 * @return boolean
	 */
	public function is_sandbox_setting_enabled() {
		return 'yes' === $this->get_enable_sandbox();
	}


	/**
	 * Gets the configured environment.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_environment() {
		$sanboxed = ( defined( 'WC_SQUARE_SANDBOX' ) && WC_SQUARE_SANDBOX ) || $this->is_sandbox_setting_enabled();
		return $sanboxed ? 'sandbox' : 'production';
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 2.0.0
	 *
	 * @return Plugin
	 */
	public function get_plugin() {

		return $this->plugin;
	}

	/**
	 * Determines if the current request is for the Square admin settings screen.
	 *
	 * @since 2.1.5
	 * @return bool True if the current request is for the Square admin settings, otherwise false.
	 */
	public function is_admin_settings_screen() {
		return isset( $_GET['page'], $_GET['tab'] ) && 'wc-settings' === $_GET['page'] && Plugin::PLUGIN_ID === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Update the sync interval if it has changed.
	 *
	 * @param array $settings
	 * @return void
	 */
	public function maybe_change_sync_interval( $settings ) {
		// Bail if we have a filter in place to manage the sync interval.
		if ( has_filter( 'wc_square_sync_interval' ) ) {
			return;
		}

		$old_settings = get_option( $this->get_option_key(), array() );
		// Bail if we don't have a sync interval.
		if ( empty( $old_settings['sync_interval'] ) || empty( $settings['sync_interval'] ) ) {
			return;
		}

		// If the sync interval has changed, schedule a new sync.
		if ( $old_settings['sync_interval'] !== $settings['sync_interval'] ) {
			$this->plugin->get_sync_handler()->schedule_sync( true );
		}
	}
}
