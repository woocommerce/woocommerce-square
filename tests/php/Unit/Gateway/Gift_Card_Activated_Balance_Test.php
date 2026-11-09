<?php
/**
 * Tests for the authoritative balance recorded after gift-card activation.
 *
 * @package WooCommerce\Square
 */
namespace WooCommerce\Square\Tests\Unit\Gateway;

use PHPUnit\Framework\TestCase;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;
use WooCommerce\Square\Gateway\API;
use WooCommerce\Square\Gateway\API\Responses\Get_Gift_Card;
use WooCommerce\Square\API\Response;

class Gift_Card_Activated_Balance_Test extends TestCase {
	/**
	 * The activated balance must be stored before the recipient-email action.
	 *
	 * @dataProvider balance_provider
	 */
	public function test_activation_records_square_balance_before_notification( $cents, $expected ) {
		$metadata = array(
			'gift_card_line_item_id' => 'gift-line',
			'square_order_id'       => 'square-order',
			'gift_card_balance'     => '90.00',
		);
		$card = new \Square\Models\GiftCard( 'DIGITAL' );
		$card->setId( 'gift-id' );
		$created = new \Square\Models\CreateGiftCardResponse();
		$created->setGiftCard( $card );
		$create_response = $this->getMockBuilder( Get_Gift_Card::class )->disableOriginalConstructor()->onlyMethods( array( 'get_data', 'get_id' ) )->getMock();
		$create_response->method( 'get_data' )->willReturn( $created );
		$create_response->method( 'get_id' )->willReturn( 'gift-id' );

		$activity = new \Square\Models\GiftCardActivity( 'ACTIVATE', 'LOCATION' );
		$activity->setGiftCardGan( 'test-gan' );
		if ( null !== $cents ) {
			$money = new \Square\Models\Money();
			$money->setAmount( $cents );
			$money->setCurrency( 'USD' );
			$activity->setGiftCardBalanceMoney( $money );
		}
		$activated = new \Square\Models\CreateGiftCardActivityResponse();
		$activated->setGiftCardActivity( $activity );
		$activate_response = $this->getMockBuilder( Response::class )->disableOriginalConstructor()->onlyMethods( array( 'get_data' ) )->getMock();
		$activate_response->method( 'get_data' )->willReturn( $activated );
		$api = $this->getMockBuilder( API::class )->disableOriginalConstructor()->onlyMethods( array( 'create_gift_card', 'activate_gift_card' ) )->getMock();
		$api->expects( $this->once() )->method( 'create_gift_card' )->with( 'gift-line' )->willReturn( $create_response );
		$api->expects( $this->once() )->method( 'activate_gift_card' )->with( 'gift-id', 'square-order', 'gift-line' )->willReturn( $activate_response );
		$gateway = $this->getMockBuilder( Payment_Gateway::class )->disableOriginalConstructor()->onlyMethods( array( 'get_api', 'get_order_meta', 'update_order_meta' ) )->getMockForAbstractClass();
		$gateway->method( 'get_api' )->willReturn( $api );
		$gateway->method( 'get_order_meta' )->willReturnCallback( function ( $order, $key ) use ( &$metadata ) { return $metadata[ $key ] ?? ''; } );
		$gateway->method( 'update_order_meta' )->willReturnCallback( function ( $order, $key, $value ) use ( &$metadata ) { $metadata[ $key ] = $value; } );
		$order = $this->getMockBuilder( \WC_Order::class )->disableOriginalConstructor()->onlyMethods( array( 'add_order_note' ) )->getMock();
		$order->expects( $this->once() )->method( 'add_order_note' );
		$hook = $GLOBALS['wp_filter']['wc_square_gift_card_activated'] ?? null;
		remove_all_actions( 'wc_square_gift_card_activated' );
		$notified = false;
		add_action( 'wc_square_gift_card_activated', function () use ( &$metadata, $expected, &$notified ) {
			$this->assertSame( $expected, $metadata['gift_card_balance'] );
			$notified = true;
		} );
		try {
			$this->assertTrue( $gateway->create_gift_card( $order ) );
			$this->assertTrue( $notified );
			$this->assertSame( $expected, $metadata['gift_card_balance'] );
		} finally {
			remove_all_actions( 'wc_square_gift_card_activated' );
			if ( null !== $hook ) {
				$GLOBALS['wp_filter']['wc_square_gift_card_activated'] = $hook;
			}
		}
	}

	public function balance_provider() {
		return array(
			'discounted $100 card' => array( 10000, '100.00' ),
			'fractional amount'    => array( 7725, '77.25' ),
			'zero balance'         => array( 0, '0.00' ),
			'absent balance'       => array( null, '90.00' ),
		);
	}
}
