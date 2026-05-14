<?php
/**
 * Runtime probe for the abilities harness. Loaded via `wp eval-file`.
 *
 * Designed to be safe on WC < 10.9. Never introspects Domain classes
 * directly — the lazy-autoload property documented in
 * skills/woocommerce:abilities-api-implement/references/wc-1009-dependency.md
 * keeps those Domain files unparsed on older WC; touching them via
 * method_exists/class_exists would force autoload and fatal on the
 * missing AbilityDefinition interface.
 */

$loader_class    = '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesLoader';
$registrar_class = '\\WooCommerce\\Square\\Internal\\Abilities\\Abilities_Registrar';
$abstract_class  = '\\WooCommerce\\Square\\Internal\\Abilities\\Domain\\AbstractSquareAbility';

echo 'WP version: ' . get_bloginfo( 'version' ) . PHP_EOL;
echo 'WC version: ' . ( defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown' ) . PHP_EOL;
echo 'AbilitiesLoader (WC 10.9+ required): ' . ( class_exists( $loader_class ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'Square Abilities_Registrar autoloads: ' . ( class_exists( $registrar_class ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'AbstractSquareAbility autoloads (abstract; safe to autoload, no interface import): ' . ( class_exists( $abstract_class ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'wp_register_ability function: ' . ( function_exists( 'wp_register_ability' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'wp_get_ability function: ' . ( function_exists( 'wp_get_ability' ) ? 'yes' : 'no' ) . PHP_EOL;
echo 'Feature flag default value: ' . ( apply_filters( 'woocommerce_square_abilities_enabled', false ) ? 'true' : 'false' ) . PHP_EOL;
echo 'Loader filter wired (expect no with feature flag default off): '
	. ( has_filter( 'woocommerce_ability_definition_classes', array( $registrar_class, 'append_classes' ) ) ? 'yes' : 'no' )
	. PHP_EOL;

echo PHP_EOL . '--- forcing the feature flag on ---' . PHP_EOL;
add_filter( 'woocommerce_square_abilities_enabled', '__return_true' );
$registrar_class::reset_initialized_for_testing();
$registrar_class::init();

$loader_wired_after = has_filter( 'woocommerce_ability_definition_classes', array( $registrar_class, 'append_classes' ) );
echo 'After flag-on init(): loader filter wired (expect no on WC<10.9, yes on WC>=10.9): '
	. ( $loader_wired_after ? 'yes' : 'no' )
	. PHP_EOL;

if ( ! class_exists( $loader_class ) ) {
	echo '  -> CONFIRMED silent-bail path. Registrar correctly short-circuits before' . PHP_EOL;
	echo '     adding the filter when AbilitiesLoader is absent (WC<10.9).' . PHP_EOL;
}

echo PHP_EOL . '--- can_manage_woocommerce_square() roundtrip ---' . PHP_EOL;
wp_set_current_user( 0 );
echo '  anon user: ' . ( $registrar_class::can_manage_woocommerce_square() ? 'true (BAD)' : 'false (OK)' ) . PHP_EOL;

$subscriber = get_user_by( 'login', 'subscriber-probe' );
if ( ! $subscriber ) {
	$sub_id     = wp_insert_user(
		array(
			'user_login' => 'subscriber-probe',
			'user_email' => 'subscriber-probe@example.test',
			'user_pass'  => wp_generate_password( 16, true ),
			'role'       => 'subscriber',
		)
	);
	$subscriber = get_user_by( 'id', $sub_id );
}
if ( $subscriber ) {
	wp_set_current_user( $subscriber->ID );
	echo '  subscriber: ' . ( $registrar_class::can_manage_woocommerce_square() ? 'true (BAD)' : 'false (OK)' ) . PHP_EOL;
}

$admin = get_user_by( 'login', 'admin' );
if ( $admin ) {
	wp_set_current_user( $admin->ID );
	echo '  admin: ' . ( $registrar_class::can_manage_woocommerce_square() ? 'true (OK)' : 'false (BAD)' ) . PHP_EOL;
}

echo PHP_EOL . '--- append_classes() — Domain class strings only (no autoload) ---' . PHP_EOL;
$classes = $registrar_class::append_classes( array() );
echo '  count: ' . count( $classes ) . PHP_EOL;
foreach ( $classes as $cls ) {
	echo '   - ' . $cls . PHP_EOL;
}

echo PHP_EOL . '--- wp_get_ability check (expect null on WC<10.9, since registrar bails) ---' . PHP_EOL;
if ( function_exists( 'wp_get_ability' ) ) {
	$probe_names = array(
		'woocommerce-square/get-sync-status',
		'woocommerce-square/get-sync-records',
		'woocommerce-square/get-connection-status',
		'woocommerce-square/get-locations',
		'woocommerce-square/get-product-sync-state',
		'woocommerce-square/get-credit-card-payment-settings',
		'woocommerce-square/get-cash-app-payment-settings',
	);
	foreach ( $probe_names as $name ) {
		$obj = wp_get_ability( $name );
		echo '  ' . $name . ': ' . ( $obj ? 'registered' : 'null' ) . PHP_EOL;
	}
} else {
	echo '  wp_get_ability not available on this WP version.' . PHP_EOL;
}

echo PHP_EOL . 'DONE.' . PHP_EOL;
