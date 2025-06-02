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

namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Performance logging utility for timing operations and memory usage.
 *
 * @since 4.9.1
 */
class Performance_Logger {

	/** @var array Stores timer start times indexed by key */
	private static $timers = array();

	/**
	 * Starts a timer for performance tracking.
	 *
	 * @since 4.9.1
	 *
	 * @param string $key Unique identifier for this timer
	 * @param \WooCommerce\Square\Plugin $plugin Plugin instance to check debug status
	 */
	public static function start( $key, $plugin ) {
		if ( $plugin->get_settings_handler() && $plugin->get_settings_handler()->is_debug_enabled() ) {
			self::$timers[ $key ] = array(
				'time'   => microtime( true ),
				'memory' => memory_get_usage(),
			);
		}
	}

	/**
	 * Ends a timer and logs the performance data.
	 *
	 * @since 4.9.1
	 *
	 * @param string $key Unique identifier for this timer
	 * @param \WooCommerce\Square\Plugin $plugin Plugin instance for logging
	 * @param boolean $is_error Whether the operation failed
	 */
	public static function end( $key, $plugin, $is_error = false ) {
		if ( ! isset( self::$timers[ $key ] ) ) {
			return;
		}

		$duration     = microtime( true ) - self::$timers[ $key ]['time'];
		$memory_bytes = memory_get_usage() - self::$timers[ $key ]['memory'];

		// Format duration: Show milliseconds if < 1 second, otherwise show seconds
		$time_format = $duration < 1
			? sprintf( '%.0fms', $duration * 1000 )
			: sprintf( '%.3fs', $duration );

		// Format memory: Show MB if > 1MB, otherwise KB
		$memory_format = $memory_bytes > 1048576
			? sprintf( '%.2fMB', $memory_bytes / 1048576 )
			: sprintf( '%.2fKB', $memory_bytes / 1024 );

		$plugin->log(
			sprintf(
				'[Performance] %s %s in %s with %s of memory usage',
				$key,
				$is_error ? 'failed' : 'completed',
				$time_format,
				$memory_format
			)
		);

		unset( self::$timers[ $key ] );
	}

	/**
	 * Gets the current timer value without logging (useful for testing).
	 *
	 * @since 4.9.1
	 *
	 * @param string $key Timer identifier
	 * @return float|null Timer value in seconds or null if timer not found
	 */
	public static function get_timer( $key ) {
		return isset( self::$timers[ $key ] ) ? self::$timers[ $key ] : null;
	}

	/**
	 * Resets all timers (useful for testing).
	 *
	 * @since 4.9.1
	 */
	public static function reset() {
		self::$timers = array();
	}
}
