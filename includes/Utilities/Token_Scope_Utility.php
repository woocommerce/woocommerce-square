<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Utilities;

defined( 'ABSPATH' ) || exit;

/**
 * Utility for Square OAuth token introspection (retrieve token status).
 * Used to check merchant scopes before calling scope-gated APIs (e.g. discount codes).
 *
 * @see https://developer.squareup.com/reference/square/o-auth-api/retrieve-token-status
 * @since 5.3.0
 */
class Token_Scope_Utility {

	/**
	 * OAuth scopes required for Square discount code APIs. Both must be present on the token.
	 *
	 * @var string[]
	 */
	const DISCOUNT_CODES_SCOPES_REQUIRED = array( 'DISCOUNT_CODES_READ', 'DISCOUNT_CODES_WRITE' );

	/**
	 * Transient key prefix for cached scopes. Suffix is environment (production or sandbox).
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'wc_square_token_scopes_';

	/**
	 * Cache duration for token scopes in seconds (24 hours).
	 *
	 * @var int
	 */
	const CACHE_DURATION = HOUR_IN_SECONDS * 24;

	/**
	 * Retrieve token status from Square OAuth API and return the list of scopes.
	 * Results are cached per environment to avoid repeated API calls.
	 *
	 * @since 5.3.0
	 *
	 * @param string|null $access_token Access token (optional; uses plugin settings if null).
	 * @param bool|null   $is_sandbox   Whether sandbox (optional; uses plugin settings if null).
	 * @return array|WP_Error List of scope strings, or WP_Error on failure.
	 */
	public static function get_token_scopes( $access_token = null, $is_sandbox = null ) {
		if ( null === $access_token || null === $is_sandbox ) {
			if ( ! function_exists( 'wc_square' ) ) {
				return new \WP_Error( 'no_plugin', __( 'Square plugin not available.', 'woocommerce-square' ) );
			}
			$settings     = wc_square()->get_settings_handler();
			$access_token = $access_token ?? $settings->get_access_token();
			$is_sandbox   = $is_sandbox ?? $settings->is_sandbox();
		}

		if ( empty( $access_token ) ) {
			return new \WP_Error( 'no_token', __( 'No access token available.', 'woocommerce-square' ) );
		}

		$env       = $is_sandbox ? 'sandbox' : 'production';
		$transient = self::TRANSIENT_PREFIX . $env;
		$cached    = get_transient( $transient );

		if ( is_array( $cached ) && isset( $cached['scopes'] ) ) {
			return $cached['scopes'];
		}

		// Direct HTTP call: the Square PHP SDK does not expose the OAuth2 token status endpoint (RetrieveTokenStatus).
		$base = $is_sandbox ? 'https://connect.squareupsandbox.com' : 'https://connect.squareup.com';
		$url  = $base . '/oauth2/token/status';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization'  => 'Bearer ' . $access_token,
					'Content-Type'   => 'application/json',
					'Square-Version' => '2026-01-22',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 200 !== $code ) {
			$message = isset( $data['errors'][0]['detail'] ) ? $data['errors'][0]['detail'] : wp_remote_retrieve_response_message( $response );
			return new \WP_Error( 'token_status_error', $message, array( 'status' => $code ) );
		}

		$scopes = isset( $data['scopes'] ) && is_array( $data['scopes'] ) ? $data['scopes'] : array();
		set_transient( $transient, array( 'scopes' => $scopes ), self::CACHE_DURATION );

		return $scopes;
	}

	/**
	 * Whether the current merchant's token has both scopes required for discount code APIs.
	 * Both DISCOUNT_CODES_READ and DISCOUNT_CODES_WRITE must be present.
	 *
	 * @since 5.3.0
	 *
	 * @param string|null $access_token Access token (optional).
	 * @param bool|null   $is_sandbox   Whether sandbox (optional).
	 * @return bool True if token has both DISCOUNT_CODES_READ and DISCOUNT_CODES_WRITE, false otherwise.
	 */
	public static function merchant_has_discount_codes_scope( $access_token = null, $is_sandbox = null ) {
		$scopes = self::get_token_scopes( $access_token, $is_sandbox );

		if ( is_wp_error( $scopes ) || ! is_array( $scopes ) ) {
			return false;
		}

		$scopes_list = array_values( $scopes );
		foreach ( self::DISCOUNT_CODES_SCOPES_REQUIRED as $required ) {
			if ( ! in_array( $required, $scopes_list, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear cached token scopes for an environment (e.g. after token update or disconnect).
	 *
	 * @since 5.3.0
	 *
	 * @param string|null $environment 'production', 'sandbox', or null to clear both.
	 */
	public static function clear_scope_cache( $environment = null ) {
		if ( null === $environment ) {
			delete_transient( self::TRANSIENT_PREFIX . 'production' );
			delete_transient( self::TRANSIENT_PREFIX . 'sandbox' );
			return;
		}
		delete_transient( self::TRANSIENT_PREFIX . $environment );
	}
}
