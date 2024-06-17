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

use WooCommerce;

defined( 'ABSPATH' ) or exit;

/**
 * Plugin lifecycle handler.
 *
 * Registers and displays milestone notice prompts and eventually the plugin
 * install, upgrade, activation, and deactivation routines.
 *
 * @since 3.0.0
 */
class Lifecycle {


	/** @var array the version numbers that have an upgrade routine */
	protected $upgrade_versions = array();

	/** @var string minimum milestone version */
	private $milestone_version;

	/** @var Plugin plugin instance */
	private $plugin;


	/**
	 * Constructs the class.
	 *
	 * @since 3.0.0
	 *
	 * @param Plugin $plugin plugin instance
	 */
	public function __construct( WooCommerce\Square\Framework\Plugin $plugin ) {

		$this->plugin = $plugin;

		$this->add_hooks();
	}


	/**
	 * Adds the action & filter hooks.
	 *
	 * @since 3.0.0
	 */
	protected function add_hooks() {

		// handle activation
		add_action( 'admin_init', array( $this, 'handle_activation' ) );

		// handle deactivation
		add_action( 'deactivate_' . $this->get_plugin()->get_plugin_file(), array( $this, 'handle_deactivation' ) );

		if ( is_admin() && ! wp_doing_ajax() ) {

			// initialize the plugin lifecycle
			add_action( 'wp_loaded', array( $this, 'init' ) );

			// add the admin notices
			add_action( 'init', array( $this, 'add_admin_notices' ) );
		}

		// catch any milestones triggered by action
		add_action( 'wc_square_milestone_reached', array( $this, 'trigger_milestone' ), 10, 3 );
	}


	/**
	 * Initializes the plugin lifecycle.
	 *
	 * @since 3.0.0
	 */
	public function init() {

		// potentially handle a new activation
		$this->handle_activation();

		$installed_version = $this->get_installed_version();
		$plugin_version    = $this->get_plugin()->get_version();

		// installed version lower than plugin version?
		if ( version_compare( $installed_version, $plugin_version, '<' ) ) {

			if ( ! $installed_version ) {

				$this->install();

				// store the upgrade event regardless if there was a routine for it
				$this->store_event( 'install' );

				/**
				 * Fires after the plugin has been installed.
				 *
				 * @since 3.0.0
				 */
				do_action( 'wc_square_installed' );

			} else {

				$this->upgrade( $installed_version );

				// store the upgrade event regardless if there was a routine for it
				$this->add_upgrade_event( $installed_version );

				// if the plugin never had any previous milestones, consider them all reached so their notices aren't displayed
				if ( ! $this->get_milestone_version() ) {
					$this->set_milestone_version( $plugin_version );
				}

				/**
				 * Fires after the plugin has been updated.
				 *
				 * @since 3.0.0
				 *
				 * @param string $installed_version previously installed version
				 */
				do_action( 'wc_square_updated', $installed_version );
			}

			// new version number
			$this->set_installed_version( $plugin_version );
		}
	}

	/**
	 * Performs plugin installation.
	 *
	 * @since 2.0.0
	 */
	protected function install() {
		/**
		 * Fires upon plugin installed.
		 *
		 * @since 2.0.0
		 *
		 * @param string $version plugin version (available from v2.0.0)
		 */
		do_action( 'wc_square_installed', WooCommerce\Square\Plugin::VERSION );
	}

	/**
	 * Triggers plugin activation.
	 *
	 * We don't use register_activation_hook() as that can't be called inside
	 * the 'plugins_loaded' action. Instead, we rely on setting to track the
	 * plugin's activation status.
	 *
	 * @internal
	 *
	 * @link https://developer.wordpress.org/reference/functions/register_activation_hook/#comment-2100
	 *
	 * @since 3.0.0
	 */
	public function handle_activation() {

		if ( ! get_option( 'wc_square_is_active', false ) ) {

			/**
			 * Fires when the plugin is activated.
			 *
			 * @since 3.0.0
			 */
			do_action( 'wc_square_activated' );

			update_option( 'wc_square_is_active', 'yes' );
		}
	}


	/**
	 * Triggers plugin deactivation.
	 *
	 * @internal
	 *
	 * @since 3.0.0
	 */
	public function handle_deactivation() {

		/**
		 * Fires when the plugin is deactivated.
		 *
		 * @since 3.0.0
		 */
		do_action( 'wc_square_deactivated' );

		delete_option( 'wc_square_is_active' );
	}

	/**
	 * Helper method to install default settings for a plugin.
	 *
	 * @since 3.0.0
	 *
	 * @param array $settings settings in format required by WC_Admin_Settings
	 */
	public function install_default_settings( array $settings ) {

		foreach ( $settings as $setting ) {

			if ( isset( $setting['id'], $setting['default'] ) ) {
				update_option( $setting['id'], $setting['default'] );
			}
		}
	}

	/**
	 * Performs any upgrade tasks based on the provided installed version.
	 *
	 * @since 3.0.0
	 *
	 * @param string $installed_version installed version
	 */
	protected function upgrade( $installed_version ) {

		foreach ( $this->upgrade_versions as $upgrade_version ) {

			$upgrade_method = 'upgrade_to_' . str_replace( array( '.', '-' ), '_', $upgrade_version );

			if ( version_compare( $installed_version, $upgrade_version, '<' ) && is_callable( array( $this, $upgrade_method ) ) ) {

				$this->get_plugin()->log( sprintf( "Starting upgrade to v%s", $upgrade_version ) );

				$this->$upgrade_method( $installed_version );

				$this->get_plugin()->log( sprintf( "Upgrade to v%s complete.", $upgrade_version ) );
			}
		}
	}


	/**
	 * Adds any lifecycle admin notices.
	 *
	 * @since 3.0.0
	 */
	public function add_admin_notices() {

		// display any milestone notices
		foreach ( $this->get_milestone_messages() as $id => $message ) {

			// bail if this notice was already dismissed
			if ( ! $this->get_plugin()->get_admin_notice_handler()->should_display_notice( $id ) ) {
				continue;
			}

			/**
			 * Filters a milestone notice message.
			 *
			 * @since 3.0.0
			 *
			 * @param string $message message text to be used for the milestone notice
			 * @param string $id milestone ID
			 */
			$message = apply_filters( 'wc_square_milestone_message', $this->generate_milestone_notice_message( $message ), $id );

			if ( $message ) {

				$this->get_plugin()->get_admin_notice_handler()->add_admin_notice( $message, $id, array(
					'always_show_on_settings' => false,
				) );

				// only display one notice at a time
				break;
			}
		}
	}


	/** Milestone Methods *****************************************************/


	/**
	 * Triggers a milestone.
	 *
	 * This will only be triggered if the install's "milestone version" is lower
	 * than $since. Plugins can specify $since as the version at which a
	 * milestone's feature was added. This prevents existing installs from
	 * triggering notices for milestones that have long passed, like a payment
	 * gateway's first successful payment. Omitting $since will assume the
	 * milestone has always existed and should only trigger for fresh installs.
	 *
	 * @since 3.0.0
	 *
	 * @param string $id milestone ID
	 * @param string $message message to display to the user
	 * @param string $since the version since this milestone has existed in the plugin
	 * @return bool
	 */
	public function trigger_milestone( $id, $message, $since = '1.0.0' ) {

		// if the plugin was had milestones before this milestone was added, don't trigger it
		if ( version_compare( $this->get_milestone_version(), $since, '>' ) ) {
			return false;
		}

		return $this->register_milestone_message( $id, $message );
	}


	/**
	 * Generates a milestone notice message.
	 *
	 * @since 3.0.0
	 *
	 * @param string $custom_message custom text that notes what milestone was completed.
	 * @return string
	 */
	protected function generate_milestone_notice_message( $custom_message ) {

		$message = '';

		if ( $this->get_plugin()->get_reviews_url() ) {

			// to be prepended at random to each milestone notice
			$exclamations = array(
				__( 'Awesome', 'woocommerce-square' ),
				__( 'Fantastic', 'woocommerce-square' ),
				__( 'Cowabunga', 'woocommerce-square' ),
				__( 'Congratulations', 'woocommerce-square' ),
				__( 'Hot dog', 'woocommerce-square' ),
			);

			$message = $exclamations[ array_rand( $exclamations ) ] . ', ' . esc_html( $custom_message ) . ' ';

			$message .= sprintf(
				/* translators: Placeholders: %1$s - plugin name, %2$s - <a> tag, %3$s - </a> tag, %4$s - <a> tag, %5$s - </a> tag */
				__( 'Are you having a great experience with %1$s so far? Please consider %2$sleaving a review%3$s! If things aren\'t going quite as expected, we\'re happy to help -- please %4$sreach out to our support team%5$s.', 'woocommerce-square' ),
				'<strong>' . esc_html( $this->get_plugin()->get_plugin_name() ) . '</strong>',
				'<a href="' . esc_url( $this->get_plugin()->get_reviews_url() ) . '">', '</a>',
				'<a href="' . esc_url( $this->get_plugin()->get_support_url() ) . '">', '</a>'
			);
		}

		return $message;
	}


	/**
	 * Registers a milestone message to be displayed in the admin.
	 *
	 * @since 3.0.0
	 * @see Lifecycle::generate_milestone_notice_message()
	 *
	 * @param string $id milestone ID
	 * @param string $message message to display to the user
	 * @return bool whether the message was successfully registered
	 */
	public function register_milestone_message( $id, $message ) {

		$milestone_messages = $this->get_milestone_messages();
		$dismissed_notices  = array_keys( $this->get_plugin()->get_admin_notice_handler()->get_dismissed_notices() );

		// get the total number of dismissed milestone messages
		$dismissed_milestone_messages = array_intersect( array_keys( $milestone_messages ), $dismissed_notices );

		// if the user has dismissed more than three milestone messages already, don't add any more
		if ( count( $dismissed_milestone_messages ) > 3 ) {
			return false;
		}

		$milestone_messages[ $id ] = $message;

		return update_option( 'wc_square_milestone_messages', $milestone_messages );
	}


	/** Event history methods *****************************************************************************************/


	/**
	 * Adds an upgrade lifecycle event.
	 *
	 * @since 3.0.0
	 *
	 * @param string $from_version version upgrading from
	 * @param array $data extra data to add
	 * @return false|int
	 */
	public function add_upgrade_event( $from_version, array $data = array() ) {

		$data = array_merge( array(
			'from_version' => $from_version,
		), $data );

		return $this->store_event( 'upgrade', $data );
	}

	/**
	 * Stores a lifecycle event.
	 *
	 * This can be used to log installs, upgrades, etc...
	 *
	 * Uses a direct database query to avoid cache issues.
	 *
	 * @since 3.0.0
	 *
	 * @param string $name lifecycle event name
	 * @param array $data any extra data to store
	 * @return false|int
	 */
	public function store_event( $name, array $data = array() ) {
		global $wpdb;

		$history = $this->get_event_history();

		$event = array(
			'name'    => wc_clean( $name ),
			'time'    => (int) current_time( 'timestamp' ),
			'version' => wc_clean( $this->get_plugin()->get_version() ),
		);

		if ( ! empty( $data ) ) {
			$event['data'] = wc_clean( $data );
		}

		array_unshift( $history, $event );

		// limit to the last 30 events
		$history = array_slice( $history, 0, 29 );

		return $wpdb->replace(
			$wpdb->options,
			array(
				'option_name'  => $this->get_event_history_option_name(),
				'option_value' => wp_json_encode( $history ),
				'autoload'     => 'no',
			),
			array(
				'%s',
				'%s',
			)
		);
	}


	/**
	 * Gets the lifecycle event history.
	 *
	 * The last 30 events are stored, with the latest first.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	public function get_event_history() {
		global $wpdb;

		$history = array();

		$results = $wpdb->get_var( $wpdb->prepare( "
			SELECT option_value
			FROM {$wpdb->options}
			WHERE option_name = %s
		", $this->get_event_history_option_name() ) );

		if ( $results ) {
			$history = json_decode( $results, true );
		}

		return is_array( $history ) ? $history : array();
	}


	/**
	 * Gets the event history option name.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_event_history_option_name() {

		return 'wc_square_lifecycle_events';
	}


	/** Utility Methods *******************************************************/


	/**
	 * Gets the registered milestone messages.
	 *
	 * @since 3.0.0
	 *
	 * @return array
	 */
	protected function get_milestone_messages() {

		return get_option( 'wc_square_milestone_messages', array() );
	}


	/**
	 * Sets the milestone version.
	 *
	 * @since 3.0.0
	 *
	 * @param string $version plugin version
	 * @return bool
	 */
	public function set_milestone_version( $version ) {

		$this->milestone_version = $version;

		return update_option( 'wc_square_milestone_version', $version );
	}


	/**
	 * Gets the milestone version.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	public function get_milestone_version() {

		if ( ! $this->milestone_version ) {
			$this->milestone_version = get_option( 'wc_square_milestone_version', '' );
		}

		return $this->milestone_version;
	}


	/**
	 * Gets the currently installed plugin version.
	 *
	 * @since 3.0.0
	 *
	 * @return string
	 */
	protected function get_installed_version() {

		return get_option( $this->get_plugin()->get_plugin_version_name() );
	}


	/**
	 * Sets the installed plugin version.
	 *
	 * @since 3.0.0
	 *
	 * @param string $version version to set
	 */
	protected function set_installed_version( $version ) {

		update_option( $this->get_plugin()->get_plugin_version_name(), $version );
	}


	/**
	 * Gets the plugin instance.
	 *
	 * @since 3.0.0
	 *
	 * @return Plugin
	 */
	protected function get_plugin() {

		return $this->plugin;
	}
}
