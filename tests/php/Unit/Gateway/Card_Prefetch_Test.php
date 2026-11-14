<?php
/**
 * Saved-card transient prefetch compatibility tests.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Gateway;

use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Square_Credit_Card_Payment_Token;
use WooCommerce\Square\Gateway;
use WooCommerce\Square\Gateway\Card_Handler;
use WooCommerce\Square\Plugin;
use WP_UnitTestCase;

class Card_Prefetch_Test extends WP_UnitTestCase {

	private $user_id;
	private $handler;
	private $key;
	private $cache_mode;

	public function setUp(): void {
		parent::setUp();
		$this->cache_mode = wp_using_ext_object_cache();
		wp_using_ext_object_cache( true );
		$this->user_id = self::factory()->user->create();
		$gateway       = $this->getMockBuilder( Gateway::class )->disableOriginalConstructor()->onlyMethods( array( 'get_environment', 'get_id', 'get_customer_id', 'get_api' ) )->getMock();
		$gateway->method( 'get_environment' )->willReturn( 'sandbox' );
		$gateway->method( 'get_id' )->willReturn( Plugin::GATEWAY_ID );
		$gateway->method( 'get_customer_id' )->willReturn( false );
		$gateway->expects( $this->never() )->method( 'get_api' );
		$this->handler = new Card_Handler( $gateway );
		$this->key     = 'wc_square_tokens_' . md5( Plugin::GATEWAY_ID . '_' . $this->user_id . '_sandbox' );
	}

	public function tearDown(): void {
		delete_transient( $this->key );
		delete_transient( $this->key . '_ids' );
		remove_action( 'woocommerce_payment_token_deleted', array( $this->handler, 'remove_token' ), 25 );
		remove_action( 'wc_payment_gateway_square_credit_card_payment_method_added', array( $this->handler, 'clear_cache_for_new_token' ) );
		remove_filter( 'woocommerce_get_customer_payment_tokens_limit', array( $this->handler, 'increase_query_limit_for_payment_methods' ) );
		wp_using_ext_object_cache( $this->cache_mode );
		parent::tearDown();
	}

	private function token() {
		$token = new Square_Credit_Card_Payment_Token();
		$token->set_token( 'ccof:synthetic' );
		$token->set_gateway_id( Plugin::GATEWAY_ID );
		$token->set_user_id( $this->user_id );
		$token->set_card_type( 'visa' );
		$token->set_last4( '1111' );
		$token->set_expiry_month( '12' );
		$token->set_expiry_year( '2030' );
		$token->set_environment( 'sandbox' );
		$token->set_billing_hash( 'synthetic-hash' );
		$token->add_meta_data( 'custom', array( 'enabled' => true ) );
		$token->save();
		return $token;
	}

	public function test_persistent_cache_holds_ids_and_restores_token_properties_and_meta() {
		$token = $this->token();
		$cold  = $this->handler->get_tokens( $this->user_id );
		$this->assertSame( array( $token->get_id() => $token->get_id() ), get_transient( $this->key . '_ids' ) );
		$this->assertFalse( wp_cache_get( $this->key, 'transient', false, $found ) );
		$this->assertTrue( $found );
		$warm = $this->handler->get_tokens( $this->user_id );
		$this->assertInstanceOf( Square_Credit_Card_Payment_Token::class, $warm[ $token->get_id() ] );
		$this->assertSame( wp_json_encode( $cold[ $token->get_id() ]->get_data() ), wp_json_encode( $warm[ $token->get_id() ]->get_data() ) );
		$this->assertSame( array( 'enabled' => true ), $warm[ $token->get_id() ]->get_meta( 'custom' ) );
	}

	public function test_legacy_clear_and_rollback_do_not_reuse_stale_ids() {
		$token = $this->token();
		$this->handler->get_tokens( $this->user_id );
		$second = $this->token();
		$this->handler->clear_cache_for_new_token( $second->get_id(), $this->user_id );
		$this->assertCount( 2, $this->handler->get_tokens( $this->user_id ) );
		set_transient( $this->key, array( $token->get_id() => $token ), HOUR_IN_SECONDS );
		$this->assertCount( 2, $this->handler->get_tokens( $this->user_id ) );
	}

	public function test_empty_and_missing_token_cache_entries() {
		$this->assertSame( array(), $this->handler->get_tokens( $this->user_id ) );
		$this->assertSame( array(), get_transient( $this->key . '_ids' ) );
		$this->assertSame( array(), $this->handler->get_tokens( $this->user_id ) );
		set_transient( $this->key . '_ids', array( 999999 => 999999 ), HOUR_IN_SECONDS );
		$this->assertSame( array(), $this->handler->get_tokens( $this->user_id ) );
	}

	public function test_filtered_id_payload_cannot_load_another_users_token_or_an_object() {
		$token = $this->token();
		$token->set_user_id( self::factory()->user->create() );
		$token->save();
		set_transient( $this->key, false, HOUR_IN_SECONDS );
		set_transient( $this->key . '_ids', array( $token->get_id(), new \stdClass(), -1, 'invalid' ), HOUR_IN_SECONDS );
		$this->assertSame( array(), $this->handler->get_tokens( $this->user_id ) );
	}

	public function test_token_key_filter_can_disable_all_transient_writes() {
		$disable = static function () {
			return false;
		};
		$writes  = 0;
		$record  = static function () use ( &$writes ) {
			++$writes;
		};
		add_filter( 'wc_payment_gateway_square_credit_card_payment_tokens_transient_key', $disable );
		add_action( 'set_transient', $record );
		try {
			$this->assertSame( array(), $this->handler->get_tokens( $this->user_id ) );
			$this->assertSame( 0, $writes );
			$this->assertFalse( get_transient( $this->key . '_ids' ) );
		} finally {
			remove_action( 'set_transient', $record );
			remove_filter( 'wc_payment_gateway_square_credit_card_payment_tokens_transient_key', $disable );
		}
	}

	public function test_database_transients_keep_legacy_token_objects() {
		wp_using_ext_object_cache( false );
		$token = $this->token();
		$this->handler->get_tokens( $this->user_id );
		$this->assertInstanceOf( Square_Credit_Card_Payment_Token::class, get_transient( $this->key )[ $token->get_id() ] );
		$this->assertCount( 1, $this->handler->get_tokens( $this->user_id ) );
		$this->assertFalse( get_transient( $this->key . '_ids' ) );
		delete_transient( $this->key );
	}
}
