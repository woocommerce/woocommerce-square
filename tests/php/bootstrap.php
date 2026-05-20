<?php
/**
 * PHPUnit bootstrap for Square for WooCommerce unit tests.
 *
 * Loads the standard WordPress unit-test scaffolding plus the plugin under
 * test. WP_TESTS_DIR can be set via env var; falls back to the path that
 * `bin/install-wp-tests.sh` installs into. Run via:
 *
 *     bash tests/php/bin/install-wp-tests.sh wordpress_test root '' localhost latest
 *     composer test-unit
 *
 * @package WooCommerce\Square
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tmp       = getenv( 'TMPDIR' ) ? rtrim( getenv( 'TMPDIR' ), '/' ) : '/tmp';
	$_tests_dir = $_tmp . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $_tests_dir is env-derived, CLI-only bootstrap context, no HTML target.
	echo "Could not find {$_tests_dir}/includes/functions.php — have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

// Register the plugin's PSR-4 mapping eagerly at bootstrap time so test
// classes can autoload the plugin's namespace without needing the
// plugin's own init_plugin() hook to fire (which depends on a working
// WooCommerce environment). Use spl_autoload_register directly rather
// than reach into Composer's ClassLoader — phpunit-polyfills already
// boots Composer's autoloader earlier in the test bootstrap, so a
// later `require vendor/autoload.php` returns true instead of the
// ClassLoader instance.
$_plugin_root = dirname( __DIR__, 2 );
$_autoload    = $_plugin_root . '/vendor/autoload.php';
if ( file_exists( $_autoload ) ) {
	require_once $_autoload;
}
spl_autoload_register(
	static function ( $class_name ) use ( $_plugin_root ) {
		$prefix = 'WooCommerce\\Square\\';
		if ( strncmp( $class_name, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		$file     = $_plugin_root . '/includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Manually load WooCommerce (if present) and the plugin's main file via
 * the WP test harness. PSR-4 mapping for the plugin's namespace is
 * already registered above; this hook lets the plugin's loader class
 * register and is_environment_compatible() do its thing.
 */
function _manually_load_plugin() {
	$plugin_root = dirname( __DIR__, 2 );

	// Load WooCommerce first if it has been mapped into the test environment.
	$wc_path = $plugin_root . '/../woocommerce/woocommerce.php';
	if ( file_exists( $wc_path ) ) {
		require_once $wc_path;
	}

	// Register the plugin's PSR-4 mapping via Composer's autoloader.
	$autoload = $plugin_root . '/vendor/autoload.php';
	if ( file_exists( $autoload ) ) {
		$loader = require_once $autoload;
		if ( is_object( $loader ) && method_exists( $loader, 'addPsr4' ) ) {
			$loader->addPsr4( 'WooCommerce\\Square\\', $plugin_root . '/includes' );
		}
	}

	// Load the plugin's main file so its loader class registers and hooks
	// can fire. The actual init_plugin() path may short-circuit on
	// is_environment_compatible() — that is OK, the PSR-4 mapping above is
	// what the unit tests rely on.
	if ( file_exists( $plugin_root . '/woocommerce-square.php' ) ) {
		require_once $plugin_root . '/woocommerce-square.php';
	}
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
