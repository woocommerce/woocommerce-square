<?php
/**
 * Location transient prefetch compatibility tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit;

use Square\ApiHelper;
use Square\Models\Address;
use Square\Models\BusinessHours;
use Square\Models\Location;
use WooCommerce\Square\API;
use WooCommerce\Square\Framework\Plugin_Dependencies;
use WooCommerce\Square\Plugin;
use WooCommerce\Square\Settings;
use WP_UnitTestCase;

class Settings_Prefetch_Test extends WP_UnitTestCase {

	private $key = 'wc_square_locations_prefetch_test';
	private $cache_mode;

	public function setUp(): void {
		parent::setUp();
		$this->cache_mode = wp_using_ext_object_cache();
		wp_using_ext_object_cache( true );
	}

	public function tearDown(): void {
		delete_transient( $this->key );
		delete_transient( $this->key . '_json' );
		wp_using_ext_object_cache( $this->cache_mode );
		parent::tearDown();
	}

	private function location() {
		$location = new Location();
		$location->setId( 'L1' );
		$location->setName( 'Shop' );
		$address = new Address();
		$address->setAddressLine1( 'Example street' );
		$location->setAddress( $address );
		$location->setBusinessHours( new BusinessHours() );
		return $location;
	}

	private function settings( $locations, $calls ) {
		$api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->onlyMethods( array( 'get_locations' ) )->getMock();
		$api->expects( $this->exactly( $calls ) )->method( 'get_locations' )->willReturn( $locations );
		$dependencies = $this->getMockBuilder( Plugin_Dependencies::class )->disableOriginalConstructor()->onlyMethods( array( 'meets_php_dependencies' ) )->getMock();
		$dependencies->method( 'meets_php_dependencies' )->willReturn( true );
		$plugin = $this->getMockBuilder( Plugin::class )->disableOriginalConstructor()->onlyMethods( array( 'get_version', 'get_api', 'get_dependency_handler' ) )->getMock();
		$plugin->method( 'get_version' )->willReturn( 'prefetch_test' );
		$plugin->method( 'get_api' )->willReturn( $api );
		$plugin->method( 'get_dependency_handler' )->willReturn( $dependencies );
		$settings = $this->getMockBuilder( Settings::class )->disableOriginalConstructor()->onlyMethods( array( 'get_plugin', 'is_admin_settings_screen', 'get_access_token', 'is_sandbox', 'get_location_id' ) )->getMock();
		$settings->method( 'get_plugin' )->willReturn( $plugin );
		$settings->method( 'is_admin_settings_screen' )->willReturn( false );
		$settings->method( 'get_location_id' )->willReturn( 'L1' );
		return $settings;
	}

	public function test_persistent_cache_hydrates_complete_models_without_an_api_call() {
		$location = $this->location();
		$this->settings( array( $location ), 1 )->get_locations();
		$json = get_transient( $this->key . '_json' );
		$this->assertIsString( $json );
		$this->assertSame( ApiHelper::serialize( array( $location ) ), $json );
		$this->assertFalse( wp_cache_get( $this->key, 'transient', false, $found ) );
		$this->assertTrue( $found );
		$warm = $this->settings( array(), 0 )->get_locations();
		$this->assertInstanceOf( Location::class, $warm[0] );
		$this->assertInstanceOf( Address::class, $warm[0]->getAddress() );
		$this->assertInstanceOf( BusinessHours::class, $warm[0]->getBusinessHours() );
		$this->assertSame( $json, ApiHelper::serialize( $warm ) );
	}

	public function test_legacy_invalidation_and_rollback_reject_stale_json() {
		$old = $this->location();
		$this->settings( array( $old ), 1 )->get_locations();
		delete_transient( $this->key );
		$fresh = $this->location();
		$fresh->setName( 'New shop' );
		$this->assertSame( 'New shop', $this->settings( array( $fresh ), 1 )->get_locations()[0]->getName() );
		set_transient( $this->key, array( $old ), HOUR_IN_SECONDS );
		$this->assertSame( 'New shop', $this->settings( array( $fresh ), 1 )->get_locations()[0]->getName() );
	}

	public function test_empty_and_corrupt_payloads() {
		$this->assertSame( array(), $this->settings( array(), 1 )->get_locations() );
		$this->assertSame( '[]', get_transient( $this->key . '_json' ) );
		$this->assertSame( array(), $this->settings( array(), 0 )->get_locations() );
		foreach ( array( '{broken', '[null]', '[{"name":{}}]' ) as $json ) {
			set_transient( $this->key . '_json', $json, HOUR_IN_SECONDS );
			$this->assertInstanceOf( Location::class, $this->settings( array( $this->location() ), 1 )->get_locations()[0] );
		}
	}

	public function test_force_refresh_keeps_models_and_replaces_json() {
		$this->settings( array(), 1 )->get_locations();
		$this->assertCount( 1, $this->settings( array( $this->location() ), 1 )->get_locations( true ) );
		$this->assertCount( 1, $this->settings( array(), 0 )->get_locations() );
	}

	public function test_database_transient_format_remains_readable_by_legacy_code() {
		wp_using_ext_object_cache( false );
		$location = $this->location();
		$this->settings( array( $location ), 1 )->get_locations();
		$this->assertInstanceOf( Location::class, get_transient( $this->key )[0] );
		$this->assertSame( 'Shop', $this->settings( array(), 0 )->get_locations()[0]->getName() );
		$this->assertFalse( get_transient( $this->key . '_json' ) );
		delete_transient( $this->key );
	}
}
