<?php
/**
 * Square Order Webhook Handler.
 *
 * Handles Square webhook events for order synchronization.
 *
 * @package WooCommerce\Square\API\Webhooks
 * @since x.x.x
 */

namespace WooCommerce\Square\API\Webhooks;

defined( 'ABSPATH' ) || exit;

/**
 * Order Webhook Handler Class.
 *
 * @since x.x.x
 */
class Order_Webhook {

	/**
	 * Webhook events this handler supports.
	 *
	 * @since x.x.x
	 * @var array
	 */
	const SUPPORTED_EVENTS = array(
		'order.created',
		'order.updated',
		'order.fulfillment.updated',
	);

	/**
	 * Webhook endpoint path.
	 *
	 * @since x.x.x
	 * @var string
	 */
	const WEBHOOK_ENDPOINT = 'wc-square/webhook/orders';

	/**
	 * Initialize the webhook handler.
	 *
	 * @since x.x.x
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_webhook_endpoint' ) );
		add_action( 'woocommerce_square_webhook_order_created', array( $this, 'handle_order_created' ), 10, 2 );
		add_action( 'woocommerce_square_webhook_order_updated', array( $this, 'handle_order_updated' ), 10, 2 );
		add_action( 'woocommerce_square_webhook_order_fulfillment_updated', array( $this, 'handle_order_fulfillment_updated' ), 10, 2 );
		
		// Register webhook import action.
		add_action( 'woocommerce_square_import_order', array( $this, 'process_order_import' ), 10, 2 );
	}

	/**
	 * Register the webhook endpoint.
	 *
	 * @since x.x.x
	 */
	public function register_webhook_endpoint() {
		add_rewrite_rule(
			'^' . self::WEBHOOK_ENDPOINT . '/?$',
			'index.php?wc-square-webhook=orders',
			'top'
		);

		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_webhook_request' ) );
	}

	/**
	 * Add query vars for webhook endpoint.
	 *
	 * @since x.x.x
	 * @param array $vars Query variables.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'wc-square-webhook';
		return $vars;
	}

	/**
	 * Handle incoming webhook requests.
	 *
	 * @since x.x.x
	 */
	public function handle_webhook_request() {
		$webhook_received_url = $_SERVER['REQUEST_URI'] ?? '';
		$webhook_type         = get_query_var( 'wc-square-webhook' );

		// If the webhook type is not orders or the received URL does not contain the webhook endpoint, return.
		if ( 'orders' !== $webhook_type || strpos( $webhook_received_url, self::WEBHOOK_ENDPOINT ) === false ) {
			return;
		}

		// Set proper headers.
		header( 'Content-Type: application/json' );
		status_header( 200 );

		try {
			// Log webhook reception.
			wc_square()->log( '=== SQUARE WEBHOOK RECEIVED ===', 'webhook' );
			wc_square()->log( 'Request Method: ' . $_SERVER['REQUEST_METHOD'], 'webhook' );
			wc_square()->log( 'User Agent: ' . ( $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown' ), 'webhook' );

			// Verify request method.
			if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				$this->send_error_response( 405, 'Method not allowed' );
				return;
			}

			// Get raw request body.
			$raw_body = file_get_contents( 'php://input' );
			if ( empty( $raw_body ) ) {
				wc_square()->log( 'Empty request body received', 'webhook' );
				$this->send_error_response( 400, 'Empty request body' );
				return;
			}

			// Verify webhook signature.
			if ( ! $this->verify_webhook_signature( $raw_body ) ) {
				wc_square()->log( 'Webhook signature verification failed', 'webhook' );
				$this->send_error_response( 401, 'Invalid webhook signature' );
				return;
			}

			// Parse JSON payload.
			$payload = json_decode( $raw_body, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				wc_square()->log( 'JSON parse error: ' . json_last_error_msg(), 'webhook' );
				$this->send_error_response( 400, 'Invalid JSON payload' );
				return;
			}

			wc_square()->log( 'Parsed Payload: ' . wp_json_encode( $payload, JSON_PRETTY_PRINT ), 'webhook' );

			// Process webhook based on event type.
			$this->process_webhook_event( $payload );

			// Send success response.
			$this->send_success_response();

		} catch ( \Exception $e ) {
			wc_square()->log( 'Webhook processing error: ' . $e->getMessage(), 'webhook' );
			wc_square()->log( 'Stack trace: ' . $e->getTraceAsString(), 'webhook' );
			$this->send_error_response( 500, 'Internal server error' );
		}

		exit;
	}

	/**
	 * Process webhook event based on type.
	 *
	 * @since x.x.x
	 * @param array $payload Webhook payload.
	 */
	private function process_webhook_event( $payload ) {
		$event_type = $payload['type'] ?? '';
		
		if ( ! in_array( $event_type, self::SUPPORTED_EVENTS, true ) ) {
			wc_square()->log( 'Unsupported event type: ' . $event_type, 'webhook' );
			$this->send_error_response( 400, 'Unsupported event type' );
			return;
		}

		// Extract event data.
		$event_data = $payload['data'] ?? array();
		
		// Trigger appropriate action based on event type.
		$action_name = 'woocommerce_square_webhook_' . str_replace( '.', '_', $event_type );
		
		wc_square()->log( "Triggering action: {$action_name}", 'webhook' );
		
		/**
		 * Fires when a Square webhook event is received.
		 *
		 * @since x.x.x
		 * @param array $event_data Event-specific data.
		 * @param array $payload Full webhook payload.
		 */
		do_action( $action_name, $event_data, $payload );
	}

	/**
	 * Verify webhook signature from Square.
	 *
	 * Square uses HMAC-SHA1 signature verification with the format:
	 * HMAC-SHA1(<webhook_notification_url><JSON_body_string>, signature_key)
	 * 
	 * @since x.x.x
	 * @param string $body Raw request body.
	 * @return bool
	 */
	private function verify_webhook_signature( $body ) {
		// Square sends signature in HTTP_X_SQUARE_SIGNATURE header (HMAC-SHA1).
		// Also check for the newer HMAC-SHA256 header as fallback.
		$signature = $_SERVER['HTTP_X_SQUARE_SIGNATURE'] ?? $_SERVER['HTTP_X_SQUARE_HMACSHA256_SIGNATURE'] ?? '';
		
		if ( empty( $signature ) ) {
			wc_square()->log( 'Missing X-Square-Signature header', 'webhook' );
			return false;
		}

		// Get webhook signature key and notification URL from settings.
		$webhook_signature_key = $this->get_webhook_signature_key();
		$notification_url      = get_option( 'square_webhook_url', self::get_webhook_url() );

		if ( empty( $webhook_signature_key ) ) {
			wc_square()->log( 'Webhook signature key not configured', 'webhook' );
			return false;
		}

		if ( empty( $notification_url ) ) {
			wc_square()->log( 'Webhook notification URL not configured', 'webhook' );
			return false;
		}

		// Use the utility function to validate signature.
		$is_valid = self::is_valid_square_signature( $signature, $body, $webhook_signature_key, $notification_url );

		if ( $is_valid ) {
			wc_square()->log( 'Webhook signature verified successfully', 'webhook' );
		} else {
			wc_square()->log( 'Webhook signature verification failed', 'webhook' );
			
			// For debugging, compute the expected signature.
			$string_to_sign     = $notification_url . $body;
			$computed_signature = base64_encode( hash_hmac( 'sha1', $string_to_sign, $webhook_signature_key, true ) );
			
			wc_square()->log( 'Expected: ' . $computed_signature, 'webhook' );
			wc_square()->log( 'Received: ' . $signature, 'webhook' );
			wc_square()->log( 'String to sign length: ' . strlen( $string_to_sign ), 'webhook' );
			wc_square()->log( 'Notification URL: ' . $notification_url, 'webhook' );
			wc_square()->log( 'Body length: ' . strlen( $body ), 'webhook' );
		}

		return $is_valid;
	}

	/**
	 * Get webhook signature key from settings.
	 *
	 * @since x.x.x
	 * @return string
	 */
	private function get_webhook_signature_key() {
		return get_option( 'square_webhook_signature_key', '' );
	}

	/**
	 * Validate Square webhook signature.
	 *
	 * This is a utility function that implements Square's official signature verification.
	 * Can be used by other webhook handlers if needed.
	 *
	 * @since x.x.x
	 * @param string $signature     The signature from HTTP_X_SQUARE_SIGNATURE header.
	 * @param string $request_body  The raw request body.
	 * @param string $signature_key The webhook signature key from Square.
	 * @param string $webhook_url   The webhook URL as registered in Square.
	 * @return bool
	 */
	public static function is_valid_square_signature( $signature, $request_body, $signature_key, $webhook_url ) {
		if ( empty( $signature ) || empty( $request_body ) || empty( $signature_key ) || empty( $webhook_url ) ) {
			return false;
		}

		// Build string to sign: webhook URL + request body.
		$string_to_sign = $webhook_url . $request_body;

		// Generate HMAC-SHA1 signature.
		$computed_signature = base64_encode( hash_hmac( 'sha1', $string_to_sign, $signature_key, true ) );

		// Compare signatures using hash_equals to prevent timing attacks.
		return hash_equals( $computed_signature, $signature );
	}

	/**
	 * Handle order.created webhook event.
	 *
	 * @since x.x.x
	 * @param array $order_data Square order data.
	 * @param array $webhook_payload Full webhook payload.
	 */
	public function handle_order_created( $order_data, $webhook_payload ) {
		wc_square()->log( 'Processing order.created webhook for order: ' . ( $order_data['id'] ?? 'Unknown' ), 'webhook' );

		// Skip if order was created by WooCommerce (check metadata).
		if ( $this->is_order_from_woocommerce( $order_data ) ) {
			wc_square()->log( 'Skipping Square order created from WooCommerce: ' . ( $order_data['id'] ?? 'Unknown' ), 'webhook' );
			return;
		}

		// Queue order import job.
		$this->queue_order_import( $order_data['id'] ?? '', 'created' );
	}

	/**
	 * Handle order.updated webhook event.
	 *
	 * @since x.x.x
	 * @param array $order_data Square order data.
	 * @param array $webhook_payload Full webhook payload.
	 */
	public function handle_order_updated( $order_data, $webhook_payload ) {
		wc_square()->log( 'Processing order.updated webhook for order: ' . ( $order_data['id'] ?? 'Unknown' ), 'webhook' );

		// Skip if order was created by WooCommerce.
		if ( $this->is_order_from_woocommerce( $order_data ) ) {
			wc_square()->log( 'Skipping Square order update from WooCommerce: ' . ( $order_data['id'] ?? 'Unknown' ), 'webhook' );
			return;
		}

		// Queue order update job.
		$this->queue_order_import( $order_data['id'] ?? '', 'updated' );
	}

	/**
	 * Handle order.fulfillment.updated webhook event.
	 *
	 * @since x.x.x
	 * @param array $fulfillment_data Square fulfillment data.
	 * @param array $webhook_payload Full webhook payload.
	 */
	public function handle_order_fulfillment_updated( $fulfillment_data, $webhook_payload ) {
		// $order_id = $fulfillment_data['order_id'] ?? ''; // @TODO: confirm this.
		$order_id = $fulfillment_data['id'] ?? '';

		// @TODO: update the logic for fulfillment.

		wc_square()->log( 'Processing order.fulfillment.updated webhook for order: ' . $order_id, 'webhook' );

		if ( empty( $order_id ) ) {
			wc_square()->log( 'No order ID in fulfillment webhook', 'webhook' );
			return;
		}

		// Queue fulfillment update job.
		$this->queue_order_import( $order_id, 'fulfillment_updated' );
	}

	/**
	 * Check if Square order was created by WooCommerce.
	 *
	 * @since x.x.x
	 * @param array $order_data Square order data.
	 * @return bool
	 */
	private function is_order_from_woocommerce( $order_data ) {
		// @TODO: confirm this.
		$metadata = $order_data['metadata'] ?? array();
		return isset( $metadata['orderedViaWoo'] ) && 'true' === $metadata['orderedViaWoo'];
	}

	/**
	 * Queue order import job.
	 *
	 * @since x.x.x
	 * @param string $square_order_id Square order ID.
	 * @param string $event_type Event type (created, updated, fulfillment_updated).
	 */
	private function queue_order_import( $square_order_id, $event_type ) {
		// @TODO: update flow as per $event_type.

		if ( empty( $square_order_id ) ) {
			wc_square()->log( 'Cannot queue order import: empty Square order ID', 'webhook' );
			return;
		}

		wc_square()->log( "Queuing order import job: {$square_order_id} ({$event_type})", 'webhook' );

		// Schedule single event to process order import.
		wp_schedule_single_event(
			time() + 10, // Process after 10 seconds.
			'woocommerce_square_import_order',
			array(
				'square_order_id' => $square_order_id,
				'event_type'      => $event_type,
			)
		);
	}

	/**
	 * Process order import from webhook event.
	 *
	 * @since x.x.x
	 * @param string $square_order_id Square order ID.
	 * @param string $event_type Event type.
	 */
	public function process_order_import( $square_order_id, $event_type ) {
		wc_square()->log( "Processing webhook order import: {$square_order_id} ({$event_type})", 'webhook' );

		// @TODO: Handle the flow as per $event_type.

		// Delegate to the Order_Importer for actual import logic.
		$result = \WooCommerce\Square\Sync\Order_Importer::import_square_order( $square_order_id, 'webhook' );

		if ( $result ) {
			wc_square()->log( "Webhook order import completed: {$square_order_id} -> WC Order #{$result->get_id()}", 'webhook' );
		} else {
			wc_square()->log( "Webhook order import failed: {$square_order_id}", 'webhook' );
		}
	}

	/**
	 * Send success response.
	 *
	 * @since x.x.x
	 */
	private function send_success_response() {
		wc_square()->log( 'Webhook processed successfully', 'webhook' );
		echo wp_json_encode( array( 'status' => 'success' ) );
	}

	/**
	 * Send error response.
	 *
	 * @since x.x.x
	 * @param int    $status_code HTTP status code.
	 * @param string $message Error message.
	 */
	private function send_error_response( $status_code, $message ) {
		status_header( $status_code );
		echo wp_json_encode( array( 'error' => $message ) );
	}

	/**
	 * Get webhook URL for Square.
	 *
	 * @since x.x.x
	 * @return string
	 */
	public static function get_webhook_url() {
		// Use ngrok URL for local development if defined.
		if ( defined( 'NGROK_URL' ) && NGROK_URL ) {
			$base_url = NGROK_URL;
		} else {
			$base_url = home_url();
		}

		$webhook_url = trailingslashit( $base_url ) . self::WEBHOOK_ENDPOINT . '/?wc-square-webhook=orders';
		
		return $webhook_url;
	}

	/**
	 * Register webhook with Square.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function register_with_square() {
		$webhook_url = self::get_webhook_url();
		$api         = wc_square()->get_api();

		// Delete the options before registering again or a new one.
		delete_option( 'square_webhook_subscription_id' );
		delete_option( 'square_webhook_signature_key' );
		delete_option( 'square_webhook_url' );

		try {
			// Access the Square client directly through reflection since it's protected.
			$reflection      = new \ReflectionClass( $api );
			$client_property = $reflection->getProperty( 'client' );
			$client_property->setAccessible( true );
			$square_client = $client_property->getValue( $api );

			// Create the WebhookSubscription object.
			$webhook_subscription = new \Square\Models\WebhookSubscription();
			$webhook_subscription->setName( 'WooCommerce Order Webhook' );
			$webhook_subscription->setEventTypes( self::SUPPORTED_EVENTS );
			$webhook_subscription->setNotificationUrl( $webhook_url );
			$webhook_subscription->setApiVersion( '2023-12-13' );

			// Create the request.
			$request = new \Square\Models\CreateWebhookSubscriptionRequest( $webhook_subscription );

			wc_square()->log( 'Attempting to register webhook with Square', 'webhook' );
			$response = $square_client->getWebhookSubscriptionsApi()->createWebhookSubscription( $request );

			if ( $response->isSuccess() ) {
				$result          = $response->getResult();
				$subscription    = $result->getSubscription();
				$subscription_id = $subscription->getId();
				$signature_key   = $subscription->getSignatureKey();

				// Store webhook subscription details.
				update_option( 'square_webhook_subscription_id', $subscription_id );
				update_option( 'square_webhook_signature_key', $signature_key );
				update_option( 'square_webhook_url', $webhook_url );

				return true;
			} else {
				$errors = $response->getErrors();
				wc_square()->log( 'Failed to register webhook: ' . wp_json_encode( $errors ), 'webhook' );
			}
		} catch ( \Exception $e ) {
			wc_square()->log( 'Failed to register webhook: ' . $e->getMessage(), 'webhook' );
		}

		return false;
	}

	/**
	 * Unregister webhook from Square.
	 *
	 * @since x.x.x
	 * @return bool
	 */
	public static function unregister_from_square() {
		// @TODO: discuss about providing UI for this.
		$subscription_id = get_option( 'square_webhook_subscription_id' );
		
		if ( empty( $subscription_id ) ) {
			wc_square()->log( 'No webhook subscription ID found to unregister', 'webhook' );
			return true; // Consider it successful if there's nothing to unregister.
		}

		$api = wc_square()->get_api();

		try {
			// Access the Square client directly through reflection.
			$reflection      = new \ReflectionClass( $api );
			$client_property = $reflection->getProperty( 'client' );
			$client_property->setAccessible( true );
			$square_client = $client_property->getValue( $api );

			wc_square()->log( 'Attempting to unregister webhook: ' . $subscription_id, 'webhook' );
			$response = $square_client->getWebhookSubscriptionsApi()->deleteWebhookSubscription( $subscription_id );

			if ( $response->isSuccess() ) {
				// Clear stored webhook details.
				update_option( 'square_webhook_subscription_id', '' );
				update_option( 'square_webhook_signature_key', '' );

				wc_square()->log( 'Webhook unregistered successfully: ' . $subscription_id, 'webhook' );
				return true;
			} else {
				$errors = $response->getErrors();
				wc_square()->log( 'Failed to unregister webhook: ' . wp_json_encode( $errors ), 'webhook' );
			}
		} catch ( \Exception $e ) {
			wc_square()->log( 'Failed to unregister webhook: ' . $e->getMessage(), 'webhook' );
		}

		return false;
	}

	/**
	 * Get webhook status information.
	 *
	 * @since x.x.x
	 * @return array
	 */
	public static function get_webhook_status() {
		$subscription_id = get_option( 'square_webhook_subscription_id' );
		$signature_key   = get_option( 'square_webhook_signature_key' );
		$webhook_url     = self::get_webhook_url();

		return array(
			'registered'         => ! empty( $subscription_id ),
			'subscription_id'    => $subscription_id,
			'signature_key'      => ! empty( $signature_key ),
			'square_webhook_url' => $webhook_url,
		);
	}
}
