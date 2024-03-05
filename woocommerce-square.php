<?php
/**
 * Plugin Name: WooCommerce Square
 * Version: 4.5.1
 * Plugin URI: https://woocommerce.com/products/square/
 * Requires at least: 6.3
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * Description: Adds ability to sync inventory between WooCommerce and Square POS. In addition, you can also make purchases through the Square payment gateway.
 * Author: WooCommerce
 * Author URI: https://www.woocommerce.com/
 * Text Domain: woocommerce-square
 * Domain Path: /i18n/languages/
 *
 * License: GPL-3.0-or-later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * @author    WooCommerce
 * @copyright Copyright (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * WC requires at least: 8.4
 * WC tested up to: 8.6
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WC_SQUARE_PLUGIN_VERSION' ) ) {
	define( 'WC_SQUARE_PLUGIN_VERSION', '4.5.1' ); // WRCS: DEFINED_VERSION.
}

if ( ! defined( 'WC_SQUARE_PLUGIN_URL' ) ) {
	define( 'WC_SQUARE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'WC_SQUARE_PLUGIN_PATH' ) ) {
	define( 'WC_SQUARE_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

/**
 * The plugin loader class.
 *
 * @since 2.0.0
 */
class WooCommerce_Square_Loader {


	/** minimum PHP version required by this plugin */
	const MINIMUM_PHP_VERSION = '7.4.0';

	/** minimum WordPress version required by this plugin */
	const MINIMUM_WP_VERSION = '6.3';

	/** minimum WooCommerce version required by this plugin */
	const MINIMUM_WC_VERSION = '8.4';

	/**
	 * SkyVerge plugin framework version used by this plugin
	 * Constant is left as it is for legacy purposes.
	 **/
	const FRAMEWORK_VERSION = '5.4.0';

	/** the plugin name, for displaying notices */
	const PLUGIN_NAME = 'Square for WooCommerce';


	/** @var WooCommerce_Square_Loader single instance of this class */
	private static $instance;

	/** @var array the admin notices to add */
	private $notices = array();


	/**
	 * Constructs the class.
	 *
	 * @since 2.0.0
	 */
	protected function __construct() {
		add_action( 'admin_init', array( $this, 'add_plugin_notices' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );

		// if the environment check fails, don't initialize the plugin.
		if ( $this->is_environment_compatible() ) {
			add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
			add_action( 'woocommerce_blocks_payment_method_type_registration', array( $this, 'register_payment_method_block_integrations' ), 5, 1 );
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		}
	}


	/**
	 * Cloning instances is forbidden due to singleton pattern.
	 *
	 * @since 2.0.0
	 */
	public function __clone() {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot clone instances of %s.', get_class( $this ) ), '2.0.0' );
	}


	/**
	 * Unserializing instances is forbidden due to singleton pattern.
	 *
	 * @since 2.0.0
	 */
	public function __wakeup() {

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		_doing_it_wrong( __FUNCTION__, sprintf( 'You cannot unserialize instances of %s.', get_class( $this ) ), '2.0.0' );
	}


	/**
	 * Initializes the plugin.
	 *
	 * @since 2.0.0
	 */
	public function init_plugin() {

		if ( ! $this->plugins_compatible() ) {
			return;
		}

		$this->load_framework();

		// autoload plugin and vendor files
		$loader = require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';

		// register plugin namespace with autoloader
		$loader->addPsr4( 'WooCommerce\\Square\\', __DIR__ . '/includes' );

		require_once plugin_dir_path( __FILE__ ) . 'includes/Functions.php';

		// fire it up!
		wc_square();
	}


	/**
	 * Loads the base framework classes.
	 *
	 * @since 2.0.0
	 */
	protected function load_framework() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/Framework/Plugin.php';
		require_once plugin_dir_path( __FILE__ ) . 'includes/Framework/PaymentGateway/Payment_Gateway_Plugin.php';
	}


	/**
	 * Gets the framework version in namespace form.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_framework_version_namespace() {

		return 'v' . str_replace( '.', '_', $this->get_framework_version() );
	}


	/**
	 * Gets the framework version used by this plugin.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	protected function get_framework_version() {

		return self::FRAMEWORK_VERSION;
	}

	/**
	 * Adds notices for out-of-date WordPress and/or WooCommerce versions.
	 *
	 * @since 2.0.0
	 */
	public function add_plugin_notices() {

		if ( ! $this->is_wp_compatible() ) {

			$this->add_admin_notice(
				'update_wordpress',
				'error',
				sprintf(
					'%s requires WordPress version %s or higher. Please %supdate WordPress &raquo;%s',
					'<strong>' . self::PLUGIN_NAME .
					'</strong>',
					self::MINIMUM_WP_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>'
				)
			);
		}

		if ( ! $this->is_wc_compatible() ) {

			$this->add_admin_notice(
				'update_woocommerce',
				'error',
				sprintf(
					'%1$s requires WooCommerce version %2$s or higher. Please %3$supdate WooCommerce%4$s to the latest version, or %5$sdownload the minimum required version &raquo;%6$s',
					'<strong>' . self::PLUGIN_NAME . '</strong>',
					self::MINIMUM_WC_VERSION,
					'<a href="' . esc_url( admin_url( 'update-core.php' ) ) . '">',
					'</a>',
					'<a href="' . esc_url( 'https://downloads.wordpress.org/plugin/woocommerce.' . self::MINIMUM_WC_VERSION . '.zip' ) . '">',
					'</a>'
				)
			);
		}
	}


	/**
	 * Determines if the required plugins are compatible.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function plugins_compatible() {

		return $this->is_wp_compatible() && $this->is_wc_compatible();
	}


	/**
	 * Determines if the WordPress compatible.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_wp_compatible() {

		if ( ! self::MINIMUM_WP_VERSION ) {
			return true;
		}

		return version_compare( get_bloginfo( 'version' ), self::MINIMUM_WP_VERSION, '>=' );
	}


	/**
	 * Determines if the WooCommerce compatible.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	protected function is_wc_compatible() {

		if ( ! self::MINIMUM_WC_VERSION ) {
			return true;
		}

		return defined( 'WC_VERSION' ) && version_compare( WC_VERSION, self::MINIMUM_WC_VERSION, '>=' );
	}


	/**
	 * Deactivates the plugin.
	 *
	 * @since 2.0.0
	 */
	protected function deactivate_plugin() {

		deactivate_plugins( plugin_basename( __FILE__ ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['activate'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			unset( $_GET['activate'] );
		}
	}


	/**
	 * Adds an admin notice to be displayed.
	 *
	 * @since 2.0.0
	 *
	 * @param string $slug the slug for the notice
	 * @param string $class the css class for the notice
	 * @param string $message the notice message
	 */
	public function add_admin_notice( $slug, $class, $message ) {

		$this->notices[ $slug ] = array(
			'class'   => $class,
			'message' => $message,
		);
	}


	/**
	 * Displays any admin notices added with \WooCommerce_Square_Loader::add_admin_notice()
	 *
	 * @since 2.0.0
	 */
	public function admin_notices() {

		foreach ( (array) $this->notices as $notice_key => $notice ) {

			?>
			<div class="<?php echo esc_attr( $notice['class'] ); ?>">
				<p>
					<?php
						echo wp_kses(
							$notice['message'],
							array(
								'a'      => array(
									'href'   => array(),
									'target' => array(),
								),
								'code'   => array(),
								'strong' => array(),
								'br'     => array(),
							)
						);
					?>
				</p>
			</div>
			<?php
		}
	}


	/**
	 * Determines if the server environment is compatible with this plugin.
	 *
	 * Override this method to add checks for more than just the PHP version.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	public function is_environment_compatible() {
		$is_php_valid            = $this->is_php_version_valid();
		$is_opcache_config_valid = $this->is_opcache_save_message_enabled();
		$error_message           = '';

		if ( ! $is_php_valid || ! $is_opcache_config_valid ) {
			$error_message .= sprintf(
				// translators: plugin name
				__( '<strong>All features in %1$s have been disabled</strong> due to unsupported settings:<br>', 'woocommerce-square' ),
				self::PLUGIN_NAME
			);
		}

		if ( ! $is_php_valid ) {
			$error_message .= sprintf(
				// translators: minimum PHP version, current PHP version
				__( '&bull;&nbsp;<strong>Invalid PHP version: </strong>The minimum PHP version required is %1$s. You are running %2$s.<br>', 'woocommerce-square' ),
				self::MINIMUM_PHP_VERSION,
				PHP_VERSION
			);
		}

		if ( ! $is_opcache_config_valid ) {
			$error_message .= sprintf(
				// translators: link to documentation
				__( '&bull;&nbsp;<strong>Invalid OPcache config: </strong><a href="%s" target="_blank">Please ensure the <code>save_comments</code> PHP option is enabled.</a> You may need to contact your hosting provider to change caching options.', 'woocommerce-square' ),
				'https://woocommerce.com/document/woocommerce-square/troubleshooting/#section-3'
			);
		}

		if ( ! empty( $error_message ) ) {
			$this->add_admin_notice(
				'bad_environment',
				'error',
				$error_message
			);
		}

		return $is_php_valid && $is_opcache_config_valid;
	}

	/**
	 * Declares support for HPOS.
	 */
	public function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	/**
	 * Returns true if opcache.save_comments is enabled.
	 *
	 * @since 3.0.2
	 *
	 * @return boolean
	 */
	protected function is_opcache_save_message_enabled() {
		$zend_optimizer_plus = extension_loaded( 'Zend Optimizer+' ) && '0' === ( ini_get( 'zend_optimizerplus.save_comments' ) || '0' === ini_get( 'opcache.save_comments' ) );
		$zend_opcache        = extension_loaded( 'Zend OPcache' ) && '0' === ini_get( 'opcache.save_comments' );

		return ! ( $zend_optimizer_plus || $zend_opcache );
	}

	/**
	 * Returns true if the PHP version of the environment
	 * meets the requirement.
	 *
	 * @since 3.0.2
	 *
	 * @return boolean
	 */
	protected function is_php_version_valid() {
		return version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=' );
	}

	/**
	 * Register the Square Credit Card checkout block integration class
	 *
	 * @since 2.5.0
	 */
	public function register_payment_method_block_integrations( $payment_method_registry ) {
		if ( class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			$payment_method_registry->register( new WooCommerce\Square\Gateway\Blocks_Handler() );
			$payment_method_registry->register( new WooCommerce\Square\Gateway\Cash_App_Pay_Blocks_Handler() );
		}
	}


	/**
	 * Gets the main plugin loader instance.
	 *
	 * Ensures only one instance can be loaded.
	 *
	 * @since 2.0.0
	 *
	 * @return \WooCommerce_Square_Loader
	 */
	public static function instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


}

// fire it up!
WooCommerce_Square_Loader::instance();
