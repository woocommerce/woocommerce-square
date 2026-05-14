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
	$_tmp        = getenv( 'TMPDIR' ) ? rtrim( getenv( 'TMPDIR' ), '/' ) : '/tmp';
	$_tests_dir  = $_tmp . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php — have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load WooCommerce and the plugin under test before the WP test
 * harness boots them. Required so the plugin's namespace + REST controllers
 * are registered.
 */
function _manually_load_plugin() {
	// Load WooCommerce first if available.
	$wc_path = dirname( __DIR__, 2 ) . '/../woocommerce/woocommerce.php';
	if ( file_exists( $wc_path ) ) {
		require_once $wc_path;
	}

	// Load the Square plugin.
	require_once dirname( __DIR__, 2 ) . '/woocommerce-square.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
