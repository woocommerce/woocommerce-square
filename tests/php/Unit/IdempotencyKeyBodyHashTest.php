<?php
/**
 * Tests for WooCommerce\Square\Plugin::get_idempotency_key_body_hash().
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit;

use WP_UnitTestCase;

class IdempotencyKeyBodyHashTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'wc_square' ) || ! wc_square() instanceof \WooCommerce\Square\Plugin ) {
			$this->markTestSkipped( 'wc_square() unavailable - plugin did not bootstrap in this environment.' );
		}
	}

	/**
	 * A key built for a catalog upsert embeds the body hash as the first 32
	 * characters of the key input; the parser must return exactly that hash.
	 */
	public function test_parses_body_hash_from_upsert_key() {
		$body_hash = md5( 'request-body' );
		$key       = wc_square()->get_idempotency_key( $body_hash . time() . '_upsert_products' );

		$this->assertSame( $body_hash, wc_square()->get_idempotency_key_body_hash( $key ) );
	}

	/**
	 * Keys stored by pre-fix plugin versions used the same input shape
	 * (md5 concatenated with time() and the context suffix), so an in-flight
	 * job upgraded mid-sync still parses correctly.
	 */
	public function test_parses_body_hash_from_legacy_format_key() {
		$body_hash = md5( 'legacy-body' );
		$legacy    = sha1( get_option( 'siteurl' ) . $body_hash . '1784600000_upsert_products' ) . ':' . $body_hash . '1784600000_upsert_products';

		$this->assertSame( $body_hash, wc_square()->get_idempotency_key_body_hash( $legacy ) );
	}

	/**
	 * When the wc_square_idempotency_key filter strips the input suffix the key
	 * has no colon and no parseable hash; the parser must return null so the
	 * caller falls back to generating a fresh key.
	 */
	public function test_returns_null_without_input_suffix() {
		$key = wc_square()->get_idempotency_key( md5( 'body' ) . '_upsert_products', false );

		$this->assertNull( wc_square()->get_idempotency_key_body_hash( $key ) );
	}

	public function test_returns_null_for_short_input() {
		$this->assertNull( wc_square()->get_idempotency_key_body_hash( sha1( 'x' ) . ':tooshort' ) );
	}

	public function test_returns_null_for_non_hex_input() {
		$this->assertNull( wc_square()->get_idempotency_key_body_hash( sha1( 'x' ) . ':not-a-hex-hash-zzzzzzzzzzzzzzzzzzzz_upsert_products' ) );
	}

	public function test_returns_null_for_non_string() {
		$this->assertNull( wc_square()->get_idempotency_key_body_hash( null ) );
		$this->assertNull( wc_square()->get_idempotency_key_body_hash( 123 ) );
	}

	/**
	 * SQUARE-15: a retry with an unchanged request body must resubmit with the
	 * SAME idempotency key.
	 */
	public function test_reusable_key_reuses_previous_key_when_body_unchanged() {
		$body_hash  = md5( serialize( array( 'item' => '#category_12' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$stored_key = wc_square()->get_idempotency_key( $body_hash . time() . '_upsert_products' );

		$this->assertSame( $stored_key, wc_square()->get_reusable_idempotency_key( $stored_key, $body_hash, '_upsert_products' ) );
	}

	/**
	 * SQUARE-272: a retry whose body changed (e.g. #category_* placeholders
	 * resolved to real Square IDs) must get a fresh key, never the stored one.
	 */
	public function test_reusable_key_generates_fresh_key_when_body_changed() {
		$original_hash = md5( serialize( array( 'item' => '#category_12' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$changed_hash  = md5( serialize( array( 'item' => 'REAL_SQUARE_ID' ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		$stored_key    = wc_square()->get_idempotency_key( $original_hash . time() . '_upsert_products' );

		$new_key = wc_square()->get_reusable_idempotency_key( $stored_key, $changed_hash, '_upsert_products' );

		$this->assertNotSame( $stored_key, $new_key );
		$this->assertSame( $changed_hash, wc_square()->get_idempotency_key_body_hash( $new_key ) );
	}

	public function test_reusable_key_generates_fresh_key_without_previous_key() {
		$body_hash = md5( 'body' );

		$key = wc_square()->get_reusable_idempotency_key( null, $body_hash, '_upsert_products' );

		$this->assertSame( $body_hash, wc_square()->get_idempotency_key_body_hash( $key ) );
	}

	/**
	 * A previous key without a parseable hash (e.g. the input suffix was removed
	 * by the wc_square_idempotency_key filter) must never be reused.
	 */
	public function test_reusable_key_generates_fresh_key_for_unparseable_previous_key() {
		$body_hash    = md5( 'body' );
		$unparseable  = sha1( 'filtered-key-without-suffix' );
		$resolved_key = wc_square()->get_reusable_idempotency_key( $unparseable, $body_hash, '_upsert_products' );

		$this->assertNotSame( $unparseable, $resolved_key );
		$this->assertSame( $body_hash, wc_square()->get_idempotency_key_body_hash( $resolved_key ) );
	}
}
