<?php
/**
 * WooCommerce Payment Gateway Framework
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@skyverge.com so we can send you a copy immediately.
 *
 * @since     3.0.0
 * @author    WooCommerce / SkyVerge
 * @copyright Copyright (c) 2013-2019, SkyVerge, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 *
 * Modified by WooCommerce on 15 December 2021.
 */

namespace WooCommerce\Square\Framework\PaymentGateway\Api;
use WooCommerce\Square\Framework\PaymentGateway\PaymentTokens\Payment_Gateway_Payment_Token;

defined( 'ABSPATH' ) or exit;

/**
 * WooCommerce Direct Payment Gateway API Create Payment Token Response
 */
interface Payment_Gateway_API_Get_Tokenized_Payment_Methods_Response extends Payment_Gateway_API_Response {

	/**
	 * Returns any payment tokens.
	 *
	 * @since 3.0.0
	 *
	 * @return Payment_Gateway_Payment_Token[] array of Payment_Gateway_Payment_Token payment tokens, keyed by the token ID
	 */
	public function get_payment_tokens();
}
