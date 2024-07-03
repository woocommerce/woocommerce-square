<?php
/**
 * WooCommerce Plugin Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 15 December 2021.
 */

namespace WooCommerce\Square\Framework;

defined( 'ABSPATH' ) || exit;

/**
 * # WooCommerce Plugin Framework
 *
 * This framework class provides a base level of configurable and overrideable
 * functionality and features suitable for the implementation of a WooCommerce
 * plugin.  This class handles all the "non-feature" support tasks such
 * as verifying dependencies are met, loading the text domain, etc.
 */
abstract class Plugin {

	/** @var object single instance of plugin */
	protected static $instance;

	/** @var string plugin id */
	private $id;

	/** @var string version number */
	private $version;

	/** @var string plugin path without trailing slash */
	private $plugin_path;

	/** @var string plugin uri */
	private $plugin_url;

	/** @var \WC_Logger instance */
	private $logger;

	/** @var  Admin_Message_Handler instance */
	private $message_handler;

	/** @var string the plugin text domain */
	private $text_domain;

	/** @var Plugin_Dependencies dependency handler instance */
	private $dependency_handler;

	/** @var Plugin\Lifecycle lifecycle handler instance */
	protected $lifecycle_handler;

	/** @var REST_API REST API handler instance */
	protected $rest_api_handler;

	/** @var Admin\Setup_Wizard handler instance */
	protected $setup_wizard_handler;

	/** @var Admin_Notice_Handler the admin notice handler class */
	private $admin_notice_handler;


	/**
	 * Initialize the plugin.
	 *
	 * Child plugin classes may add their own optional arguments.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id plugin id
	 * @param string $version plugin version number
	 * @param array $args {
	 *     optional plugin arguments
	 *
	 *     @type string $text_domain the plugin textdomain, used to set up translations
	 *     @type array  $dependencies {
	 *         PHP extension, function, and settings dependencies
	 *
	 *         @type array $php_extensions PHP extension dependencies
	 *         @type array $php_functions  PHP function dependencies
	 *         @type array $php_settings   PHP settings dependencies
	 *     }
	 * }
	 */
	public function __construct( $id, $version, $args = array() ) {

		// required params
		$this->id      = $id;
		$this->version = $version;

		$args = wp_parse_args(
			$args,
			array(
				'text_domain'  => '',
				'dependencies' => array(),
			)
		);

		$this->text_domain = $args['text_domain'];

		// includes that are required to be available at all times
		$this->includes();

		// initialize the dependencies manager
		$this->init_dependencies( $args['dependencies'] );

		// build the admin message handler instance
		$this->init_admin_message_handler();

		// build the admin notice handler instance
		$this->init_admin_notice_handler();

		// build the lifecycle handler instance
		$this->init_lifecycle_handler();

		// add the action & filter hooks
		$this->add_hooks();
	}


	/** Init methods **********************************************************/


	/**
	 * Initializes the plugin dependency handler.
	 *
	 * @since 3.0.0
	 *
	 * @param array $dependencies {
	 *     PHP extension, function, and settings dependencies
	 *
	 *     @type array $php_extensions PHP extension dependencies
	 *     @type array $php_functions  PHP function dependencies
	 *     @type array $php_settings   PHP settings dependencies
	 * }
	 */
	protected function init_dependencies( $dependencies ) {

		$this->dependency_handler = new Plugin_Dependencies( $this, $dependencies );
	}


	/**
	 * Builds the admin message handler instance.
	 *
	 * Plugins can override this with their own handler.
	 *
	 * @since 3.0.0
	 */
	protected function init_admin_message_handler() {

		$this->message_handler = new Admin_Message_Handler( 'square' );
	}


	/**
	 * Builds the admin notice handler instance.
	 *
	 * Plugins can override this with their own handler.
	 *
	 * @since 3.0.0
	 */
	protected function init_admin_notice_handler() {

		$this->admin_notice_handler = new Admin_Notice_Handler( $this );
	}

	/**
	 * Builds the lifecycle handler instance.
	 *
	 * Plugins can override this with their own handler to perform install and
	 * upgrade routines.
	 *
	 * @since 3.0.0
	 */
	protected function init_lifecycle_handler() {

		$this->lifecycle_handler = new Lifecycle( $this );
	}

	/**
	 * Adds the action & filter hooks.
	 *
	 * @since 3.0.0
	 */
	private function add_hooks() {

		// initialize the plugin
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ), 15 );

		// initialize the plugin admin
		add_action( 'admin_init', array( $this, 'init_admin' ), 0 );

		// hook for translations seperately to ensure they're loaded
		add_action( 'init', array( $this, 'load_translations' ) );

		add_action( 'admin_footer', array( $this, 'add_delayed_admin_notices' ) );

		// add a 'Configure' link to the plugin action links
		add_filter( 'plugin_action_links_' . plugin_basename( $this->get_plugin_file() ), array( $this, 'plugin_action_links' ) );

		// automatically log HTTP requests from Base
		$this->add_api_request_logging();

		// add any PHP incompatibilities to the system status report
		add_filter( 'woocommerce_system_status_environment_rows', array( $this, 'add_system_status_php_information' ) );
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 3.0.0
	 */
	public function __clone() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot clone instances of %s.', 'woocommerce-square' ), esc_html( $this->get_plugin_name() ) ), '3.1.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 3.0.0
	 */
	public function __wakeup() {
		/* translators: Placeholders: %s - plugin name */
		_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( 'You cannot unserialize instances of %s.', 'woocommerce-square' ), esc_html( $this->get_plugin_name() ) ), '3.1.0' );
	}


	/**
	 * Load plugin & framework text domains.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	public function load_translations() {

		$this->load_framework_textdomain();

		// if this plugin passes along its text domain, load its translation files
		if ( $this->text_domain ) {
			$this->load_plugin_textdomain();
		}
	}


	/**
	 * Loads the framework textdomain.
	 *
	 * @since 3.0.0
	 */
	protected function load_framework_textdomain() {
		$this->load_textdomain( 'woocommerce-square', dirname( plugin_basename( $this->get_framework_file() ) ) );
	}


	/**
	 * Loads the plugin textdomain.
	 *
	 * @since 3.0.0
	 */
	protected function load_plugin_textdomain() {
		$this->load_textdomain( $this->text_domain, dirname( plugin_basename( $this->get_plugin_file() ) ) );
	}


	/**
	 * Loads the plugin textdomain.
	 *
	 * @since 3.0.0
	 * @param string $textdomain the plugin textdomain
	 * @param string $path the i18n path
	 */
	protected function load_textdomain( $textdomain, $path ) {

		// user's locale if in the admin for WP 4.7+, or the site locale otherwise
		$locale = is_admin() && is_callable( 'get_user_locale' ) ? get_user_locale() : get_locale();

		/**
		 * @see https://developer.wordpress.org/reference/hooks/plugin_locale/ plugin_locale
		 * @since 3.0.0
		*/
		$locale = apply_filters( 'plugin_locale', $locale, $textdomain );

		load_textdomain( $textdomain, WP_LANG_DIR . '/' . $textdomain . '/' . $textdomain . '-' . $locale . '.mo' );

		load_plugin_textdomain( $textdomain, false, untrailingslashit( $path ) . '/i18n/languages' );
	}

	/**
	 * Include any critical files which must be available as early as possible,
	 *
	 * @since 3.0.0
	 */
	private function includes() {

		$framework_path = $this->get_framework_path();

		// addresses
		require_once $framework_path . '/Addresses/Address.php';
		require_once $framework_path . '/Addresses/Customer_Address.php';

		// common utility methods
		require_once $framework_path . '/Square_Helper.php';

		// backwards compatibility for older WC versions
		require_once $framework_path . '/Plugin_Compatibility.php';
		require_once $framework_path . '/Compatibility/Data_Compatibility.php';
		require_once $framework_path . '/Compatibility/Order_Compatibility.php';

		// generic API base
		require_once $framework_path . '/Api/Base.php';
		require_once $framework_path . '/Api/API_Request.php';
		require_once $framework_path . '/Api/API_Response.php';

		// JSON API base
		require_once $framework_path . '/Api/API_JSON_Request.php';
		require_once $framework_path . '/Api/API_JSON_Response.php';

		// Handlers
		require_once $framework_path . '/Plugin_Dependencies.php';
		require_once $framework_path . '/Admin_Message_Handler.php';
		require_once $framework_path . '/Admin_Notice_Handler.php';
		require_once $framework_path . '/Lifecycle.php';
	}

	/**
	 * Returns true if on the admin plugin settings page, if any
	 *
	 * @since 3.0.0
	 * @return boolean true if on the admin plugin settings page
	 */
	public function is_plugin_settings() {
		// optional method, not all plugins *have* a settings page
		return false;
	}

	/**
	 * Return the plugin action links.  This will only be called if the plugin
	 * is active.
	 *
	 * @since 3.0.0
	 * @param array $actions associative array of action names to anchor tags
	 * @return array associative array of plugin action links
	 */
	public function plugin_action_links( $actions ) {

		$custom_actions = array();

		// settings url(s)
		if ( $this->get_square_onboarding_link() && wc_square()->get_dependency_handler()->meets_php_dependencies() ) {
			$custom_actions['setup-wizard'] = $this->get_square_onboarding_link();
		}

		// documentation url if any
		if ( $this->get_documentation_url() ) {
			/* translators: Docs as in Documentation */
			$custom_actions['docs'] = sprintf( '<a href="%s" target="_blank">%s</a>', $this->get_documentation_url(), esc_html__( 'Docs', 'woocommerce-square' ) );
		}

		// support url if any
		if ( $this->get_support_url() ) {
			$custom_actions['support'] = sprintf( '<a href="%s">%s</a>', $this->get_support_url(), esc_html_x( 'Support', 'noun', 'woocommerce-square' ) );
		}

		// review url if any
		if ( $this->get_reviews_url() ) {
			$custom_actions['review'] = sprintf( '<a href="%s">%s</a>', $this->get_reviews_url(), esc_html_x( 'Review', 'verb', 'woocommerce-square' ) );
		}

		// add the links to the front of the actions list
		return array_merge( $custom_actions, $actions );
	}

	/**
	 * Automatically log API requests/responses when using Base
	 *
	 * @since 3.0.0
	 * @see Base::broadcast_request()
	 */
	public function add_api_request_logging() {

		if ( ! has_action( 'wc_square_api_request_performed' ) ) {
			add_action( 'wc_square_api_request_performed', array( $this, 'log_api_request' ), 10, 2 );
		}
	}


	/**
	 * Log API requests/responses
	 *
	 * @since 3.0.0
	 * @param array $request request data, see Base::broadcast_request() for format
	 * @param array $response response data
	 * @param string|null $log_id log to write data to
	 */
	public function log_api_request( $request, $response, $log_id = null ) {

		$this->log( sprintf( "Request\n %s", $this->get_api_log_message( $request ) ), $log_id );

		if ( ! empty( $response ) ) {
			$this->log( sprintf( "Response\n %s", $this->get_api_log_message( $response ) ), $log_id );
		}
	}


	/**
	 * Transform the API request/response data into a string suitable for logging
	 *
	 * @since 3.0.0
	 * @param array $data
	 * @return string
	 */
	public function get_api_log_message( $data ) {

		$messages = array();

		$messages[] = isset( $data['uri'] ) && $data['uri'] ? 'Request' : 'Response';

		foreach ( (array) $data as $key => $value ) {
			// print_r here is necessary to dump request / response data for logging purposes.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$messages[] = trim( sprintf( '%s: %s', $key, is_array( $value ) || ( is_object( $value ) && 'stdClass' === get_class( $value ) ) ? print_r( (array) $value, true ) : $value ) );
		}

		return implode( "\n", $messages ) . "\n";
	}


	/**
	 * Adds any PHP incompatibilities to the system status report.
	 *
	 * @since 3.0.0
	 *
	 * @param array $rows WooCommerce system status rows
	 * @return array
	 */
	public function add_system_status_php_information( $rows ) {

		foreach ( $this->get_dependency_handler()->get_incompatible_php_settings() as $setting => $values ) {

			if ( isset( $values['type'] ) && 'min' === $values['type'] ) {

				// if this setting already has a higher minimum from another plugin, skip it
				if ( isset( $rows[ $setting ]['expected'] ) && $values['expected'] < $rows[ $setting ]['expected'] ) {
					continue;
				}

				/* translators: Placeholders: %1$s = Current PHP setting value, %2$s = Minimum required PHP setting value */
				$note = __( '%1$s - A minimum of %2$s is required.', 'woocommerce-square' );

			} else {

				// if this requirement is already listed, skip it
				if ( isset( $rows[ $setting ] ) ) {
					continue;
				}

				/* translators: Placeholders: %1$s = Current PHP setting value set as, %2$s = Minimum required PHP setting value */
				$note = __( 'Set as %1$s - %2$s is required.', 'woocommerce-square' );
			}

			$note = sprintf( $note, esc_html( $values['actual'] ), esc_html( $values['expected'] ) );

			$rows[ $setting ] = array(
				'name'     => $setting,
				'note'     => $note,
				'success'  => false,
				'expected' => $values['expected'], // WC doesn't use this, but it's useful for us
			);
		}

		return $rows;
	}


	/**
	 * Saves errors or messages to WooCommerce Log (woocommerce/logs/plugin-id-xxx.txt)
	 *
	 * @since 3.0.0
	 * @param string $message error or message to save to log
	 * @param string $log_id optional log id to segment the files by, defaults to plugin id
	 */
	public function log( $message, $log_id = null ) {

		if ( is_null( $log_id ) ) {
			$log_id = 'square';
		}

		if ( ! is_object( $this->logger ) ) {
			$this->logger = new \WC_Logger();
		}

		$this->logger->add( $log_id, $message );
	}

	/**
	 * Gets the main plugin file.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_plugin_file() {

		$slug = dirname( plugin_basename( $this->get_file() ) );

		return trailingslashit( $slug ) . $slug . '.php';
	}


	/**
	 * The implementation for this abstract method should simply be:
	 *
	 * return __FILE__;
	 *
	 * @since 3.0.0
	 * @return string the full path and filename of the plugin file
	 */
	abstract protected function get_file();


	/**
	 * Returns the plugin id
	 *
	 * @since 3.0.0
	 * @return string plugin id
	 */
	public function get_id() {
		return $this->id;
	}


	/**
	 * Returns the plugin id with dashes in place of underscores, and
	 * appropriate for use in frontend element names, classes and ids
	 *
	 * @since 3.0.0
	 * @return string plugin id with dashes in place of underscores
	 */
	public function get_id_dasherized() {
		return str_replace( '_', '-', 'square' );
	}


	/**
	 * Returns the plugin full name including "WooCommerce", ie
	 * "WooCommerce X".  This method is defined abstract for localization purposes
	 *
	 * @since 3.0.0
	 * @return string plugin name
	 */
	abstract public function get_plugin_name();

	/**
	 * Gets the dependency handler.
	 *
	 * @since 3.0.0
	 *
	 * @return Plugin_Dependencies
	 */
	public function get_dependency_handler() {

		return $this->dependency_handler;
	}


	/**
	 * Gets the lifecycle handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Plugin\Lifecycle
	 */
	public function get_lifecycle_handler() {

		return $this->lifecycle_handler;
	}

	/**
	 * Gets the admin message handler.
	 *
	 * @since 3.0.0
	 *
	 * @return Admin_Message_Handler
	 */
	public function get_message_handler() {

		return $this->message_handler;
	}


	/**
	 * Gets the admin notice handler instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Admin_Notice_Handler
	 */
	public function get_admin_notice_handler() {

		return $this->admin_notice_handler;
	}


	/**
	 * Returns the plugin version name.  Defaults to wc_{plugin id}_version
	 *
	 * @since 3.0.0
	 * @return string the plugin version name
	 */
	public function get_plugin_version_name() {

		return 'wc_square_version';
	}


	/**
	 * Returns the current version of the plugin
	 *
	 * @since 3.0.0
	 * @return string plugin version
	 */
	public function get_version() {
		return $this->version;
	}


	/**
	 * Returns the "Configure" plugin action link to go directly to the plugin
	 * settings page (if any)
	 *
	 * @since 3.0.0
	 * @see Plugin::get_settings_url()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports credit cards)
	 * @return string plugin configure link
	 */
	public function get_settings_link( $plugin_id = null ) {

		$settings_url = $this->get_settings_url( $plugin_id );

		if ( $settings_url ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Configure', 'woocommerce-square' ) );
		}

		// no settings
		return '';
	}

	/**
	 * Returns the "Configure" plugin action link to go directly to the plugin
	 * settings page (if any)
	 *
	 * @since 4.7.0
	 * @see Plugin::get_settings_url()
	 * @param string $step optional step identifier.
	 *
	 * @return string plugin configure link
	 */
	public function get_square_onboarding_link( $step = '' ) {

		$square_onboarding_url = $this->get_square_onboarding_url( $step );

		if ( $square_onboarding_url ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $square_onboarding_url ), esc_html__( 'Setup Wizard', 'woocommerce-square' ) );
		}

		// no settings
		return '';
	}


	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 4.7.0
	 * @see Plugin::get_settings_link()
	 * @return string plugin settings URL
	 */
	public function get_square_onboarding_url() {

		// stub method
		return '';
	}

	/**
	 * Gets the plugin configuration URL
	 *
	 * @since 3.0.0
	 * @see Plugin::get_settings_link()
	 * @param string $plugin_id optional plugin identifier.  Note that this can be a
	 *        sub-identifier for plugins with multiple parallel settings pages
	 *        (ie a gateway that supports credit cards)
	 * @return string plugin settings URL
	 */
	public function get_settings_url( $plugin_id = null ) {

		// stub method
		return '';
	}

	/**
	 * Returns the admin configuration url for the admin general configuration page
	 *
	 * @since 3.0.0
	 * @return string admin configuration url for the admin general configuration page
	 */
	public function get_general_configuration_url() {

		return admin_url( 'admin.php?page=wc-settings&tab=general' );
	}


	/**
	 * Gets the plugin documentation url, used for the 'Docs' plugin action
	 *
	 * @since 3.0.0
	 * @return string documentation URL
	 */
	public function get_documentation_url() {

		return null;
	}


	/**
	 * Gets the support URL, used for the 'Support' plugin action link
	 *
	 * @since 3.0.0
	 * @return string support url
	 */
	public function get_support_url() {

		return null;
	}


	/**
	 * Gets the plugin sales page URL.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_sales_page_url() {

		return '';
	}


	/**
	 * Gets the plugin reviews page URL.
	 *
	 * Used for the 'Reviews' plugin action and review prompts.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_reviews_url() {

		return $this->get_sales_page_url() ? $this->get_sales_page_url() . '#comments' : '';
	}


	/**
	 * Returns the plugin's path without a trailing slash, i.e.
	 * /path/to/wp-content/plugins/plugin-directory
	 *
	 * @since 3.0.0
	 * @return string the plugin path
	 */
	public function get_plugin_path() {

		if ( $this->plugin_path ) {
			return $this->plugin_path;
		}

		return $this->plugin_path = untrailingslashit( plugin_dir_path( $this->get_file() ) );
	}


	/**
	 * Returns the plugin's url without a trailing slash, i.e.
	 *
	 * @since 3.0.0
	 * @return string the plugin URL
	 */
	public function get_plugin_url() {

		if ( $this->plugin_url ) {
			return $this->plugin_url;
		}

		return $this->plugin_url = untrailingslashit( plugins_url( '/', $this->get_file() ) );
	}

	/**
	 * Returns the loaded framework __FILE__
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_framework_file() {

		return __FILE__;
	}


	/**
	 * Returns the loaded framework path, without trailing slash. Ths is the highest
	 * version framework that was loaded by the bootstrap.
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_framework_path() {

		return untrailingslashit( plugin_dir_path( $this->get_framework_file() ) );
	}

	/**
	 * Returns the loaded framework assets URL without a trailing slash
	 *
	 * @since 3.0.0
	 * @return string
	 */
	public function get_framework_assets_url() {

		return untrailingslashit( plugins_url( '/assets', $this->get_framework_file() ) );
	}


	/**
	 * Helper function to determine whether a plugin is active
	 *
	 * @since 3.0.0
	 * @param string $plugin_name plugin name, as the plugin-filename.php
	 * @return boolean true if the named plugin is installed and active
	 */
	public function is_plugin_active( $plugin_name ) {

		$active_plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		$plugin_filenames = array();

		foreach ( $active_plugins as $plugin ) {

			if ( Square_Helper::str_exists( $plugin, '/' ) ) {

				// normal plugin name (plugin-dir/plugin-filename.php)
				list( , $filename ) = explode( '/', $plugin );

			} else {

				// no directory, just plugin file
				$filename = $plugin;
			}

			$plugin_filenames[] = $filename;
		}

		return in_array( $plugin_name, $plugin_filenames, true );
	}
}
