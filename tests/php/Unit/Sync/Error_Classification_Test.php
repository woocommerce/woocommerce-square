<?php
/**
 * Tests for the sync error classification used to decide whether a single object is skipped
 * or the whole job fails.
 *
 * @package WooCommerce\Square
 */

namespace WooCommerce\Square\Tests\Unit\Sync;

use WP_UnitTestCase;
use WooCommerce\Square\Sync\Manual_Synchronization;

/**
 * classify_sync_error() decides whether one product is skipped or the entire sync job fails,
 * so a wrong answer either strands a good product or hides a broken connection behind dozens
 * of misleading per product alerts.
 *
 * Messages here use the exact format API::do_post_parse_response_validation() produces:
 * "[CODE] detail", joined with " | " when a response carries several errors.
 */
class Error_Classification_Test extends WP_UnitTestCase {

	/** @var Manual_Synchronization */
	private $job;

	public function setUp(): void {
		parent::setUp();
		$this->job = new Manual_Synchronization( new \stdClass() );
	}

	/**
	 * Calls a protected method on the job under test.
	 *
	 * @param string $method method name
	 * @param mixed  ...$args arguments
	 * @return mixed
	 */
	private function call( $method, ...$args ) {
		$reflection = new \ReflectionMethod( Manual_Synchronization::class, $method );
		$reflection->setAccessible( true );
		return $reflection->invokeArgs( $this->job, $args );
	}

	public function tearDown(): void {
		remove_all_filters( 'wc_square_isolatable_error_codes' );
		parent::tearDown();
	}

	/**
	 * @dataProvider provide_messages
	 */
	public function test_classify_sync_error( $message, $expected, $why ) {
		$this->assertSame( $expected, $this->call( 'classify_sync_error', new \Exception( $message ) ), $why );
	}

	public function provide_messages() {
		return array(
			// Throttling keeps the existing job level retry and backoff.
			array( '[RATE_LIMITED] Too many requests', 'rate_limited', 'rate limiting must keep the existing retry path' ),
			array( '[RATE_LIMITED] a | [BAD_REQUEST] b', 'rate_limited', 'rate limiting wins over a data error in the same response' ),

			// Object level data problems: the same request will always fail, so skip the object.
			array( '[BAD_REQUEST] Item name is too long', 'isolatable', 'a data error is the object own fault' ),
			array( '[VALUE_TOO_LONG] Value at `item_data.name`', 'isolatable', 'a data error is the object own fault' ),
			// Isolatable at the classifier level, but do NOT read this row as "an item option
			// collision is fixed by skipping the product". This exact message is the job restart
			// case: API::create_options_and_values() sets woocommerce_square_refresh_sync_cycle
			// and clears the cached options data before re-throwing, so run() replays the cycle
			// with fresh options and the product syncs. The staging catch in
			// upsert_catalog_objects() snapshots that option and rethrows when it changed, which
			// takes effect before isolation ever applies. Believing isolation handles this is
			// what produced the original regression: the product was recorded as skipped and
			// stranded even though the replay would have synced it. Same two gate structure as
			// NOT_FOUND below. The classifier cannot tell the two apart, and it does not need to.
			array( '[INVALID_VALUE] An existing Item Option has name "Size"', 'isolatable', 'a data error is the object own fault at the classifier level' ),
			// NOT_FOUND is deliberately isolatable even though Square also returns it when the
			// configured location does not belong to the account, which no amount of skipping
			// fixes. push_inventory_changes_isolated() gates that second case separately, by
			// refusing to drop anything unless Square named an object present in the chunk. Do
			// not remove NOT_FOUND from the allow list to solve the location case; that would
			// stop stale catalog mappings from being isolated at all.
			array( '[NOT_FOUND] Object ABC not found', 'isolatable', 'a stale mapping is the object own fault' ),
			array( '[CONFLICT] version mismatch', 'isolatable', 'a version conflict is the object own fault' ),
			array( '[BAD_REQUEST] a | [VALUE_TOO_LONG] b', 'isolatable', 'every code is a data error, so the object can be skipped' ),

			// Connection level problems. Skipping cannot fix these and would bury the real cause
			// under one misleading alert per product.
			array( '[UNAUTHORIZED] This request could not be authorized.', 'fatal', 'auth failure must fail the job' ),
			array( '[ACCESS_TOKEN_EXPIRED] The access token has expired.', 'fatal', 'auth failure must fail the job' ),
			array( '[ACCESS_TOKEN_REVOKED] Token revoked', 'fatal', 'auth failure must fail the job' ),
			array( '[FORBIDDEN] Not authorized to perform this action.', 'fatal', 'a permission problem is not product data' ),
			array( '[INSUFFICIENT_SCOPES] Missing ITEMS_WRITE', 'fatal', 'a missing scope is not product data' ),

			// Transient problems. The write may already have been applied, so re-sending the
			// objects individually under fresh temporary IDs would duplicate them in Square.
			array( '[INTERNAL_SERVER_ERROR] Something went wrong', 'fatal', 'a server error may already have been applied' ),
			array( '[SERVICE_UNAVAILABLE] Try again', 'fatal', 'a server error may already have been applied' ),
			array( 'cURL error 28: Operation timed out after 30001 milliseconds', 'fatal', 'a timeout may already have been applied' ),

			// Anything unrecognised must fail closed, never be assumed to be bad product data.
			array( '', 'fatal', 'an empty message must not be assumed to be a data error' ),
			array( 'Response data is invalid', 'fatal', 'a plugin level error must not be assumed to be a data error' ),
			array( '[INVALID_REQUEST_ERROR] Bad body', 'fatal', 'a code that is not on the allow list must fail closed' ),
			array( '[BAD_REQUEST] a | [INTERNAL_SERVER_ERROR] b', 'fatal', 'a mixed response may still have been partly applied' ),
		);
	}

	/**
	 * Merchant supplied names are echoed verbatim inside error details, so a product literally
	 * named "[UNAUTHORIZED] Widget" must not turn a data error into a job failure. Codes are
	 * only read from the start of each " | " separated segment.
	 */
	public function test_bracketed_text_inside_a_detail_is_not_read_as_a_code() {
		$this->assertSame(
			'isolatable',
			$this->call( 'classify_sync_error', new \Exception( '[BAD_REQUEST] Product "[UNAUTHORIZED] Widget" is invalid' ) )
		);
	}

	public function test_isolatable_codes_are_filterable() {
		$this->assertSame( 'fatal', $this->call( 'classify_sync_error', new \Exception( '[SOME_NEW_CODE] nope' ) ) );

		add_filter(
			'wc_square_isolatable_error_codes',
			static function ( $codes ) {
				$codes[] = 'SOME_NEW_CODE';
				return $codes;
			}
		);

		$this->assertSame( 'isolatable', $this->call( 'classify_sync_error', new \Exception( '[SOME_NEW_CODE] nope' ) ) );
	}

	/**
	 * @dataProvider provide_code_presence
	 */
	public function test_has_square_error_code( $message, $expected ) {
		$this->assertSame( $expected, $this->call( 'has_square_error_code', new \Exception( $message ) ) );
	}

	public function provide_code_presence() {
		return array(
			array( '[BAD_REQUEST] nope', true ),
			array( '[RATE_LIMITED] a | [BAD_REQUEST] b', true ),
			array( 'Invalid product', false ),
			array( 'Type of $catalog_object must be an ITEM', false ),
			array( '', false ),
			// A bracketed word mid detail is not a code.
			array( '[BAD_REQUEST] Product "[UNAUTHORIZED] Widget" is invalid', true ),
			array( 'Product "[UNAUTHORIZED] Widget" is invalid', false ),
		);
	}
}
