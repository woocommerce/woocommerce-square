<?php
/**
 * Plugin double whose API handler is a scripted fake.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync\Fixtures;

use WooCommerce\Square\Plugin;

/**
 * The sync calls wc_square()->get_api() for every request, and get_api() builds a fresh API
 * client each time, so the client cannot be replaced on the real instance. This double is a copy
 * of the real plugin instance with only get_api() overridden; install() swaps it in as the
 * singleton and restore() puts the real instance back.
 */
class Scripted_Api_Plugin extends Plugin {

	/** @var object the fake API returned by get_api() */
	public $scripted_api;

	/** @var Plugin|null the real instance while the double is installed */
	private static $real_instance;

	/**
	 * @param string|null $access_token unused.
	 * @param bool|null   $is_sandbox   unused.
	 * @return object
	 */
	public function get_api( $access_token = null, $is_sandbox = null ) {
		return $this->scripted_api;
	}

	/**
	 * Installs a copy of the real plugin instance that answers get_api() with the given fake.
	 *
	 * @param object $api the fake API.
	 * @return self
	 */
	public static function install( $api ) {
		$real   = Plugin::instance();
		$double = ( new \ReflectionClass( self::class ) )->newInstanceWithoutConstructor();

		for ( $class = new \ReflectionClass( $real ); $class; $class = $class->getParentClass() ) {
			foreach ( $class->getProperties() as $property ) {
				if ( $property->isStatic() || $property->getDeclaringClass()->getName() !== $class->getName() ) {
					continue;
				}

				$property->setAccessible( true );

				if ( $property->isInitialized( $real ) ) {
					$property->setValue( $double, $property->getValue( $real ) );
				}
			}
		}

		$double->scripted_api = $api;
		self::$real_instance  = $real;
		self::set_instance( $double );

		return $double;
	}

	/**
	 * Puts the real plugin instance back.
	 */
	public static function restore() {
		if ( self::$real_instance ) {
			self::set_instance( self::$real_instance );
			self::$real_instance = null;
		}
	}

	/**
	 * @param Plugin $instance the instance wc_square() should return.
	 */
	private static function set_instance( $instance ) {
		$property = new \ReflectionProperty( Plugin::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $instance );
	}
}
