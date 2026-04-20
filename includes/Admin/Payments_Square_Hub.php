<?php
/**
 * WooCommerce Square
 *
 * Square settings hub under WooCommerce > Settings > Payments.
 */

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the consolidated Square settings section on the Payments settings screen.
 *
 * @since x.x.x
 */
final class Payments_Square_Hub {

	/** Inner tab: General. */
	public const TAB_GENERAL = 'general';

	/** Inner tab: Payment Methods. */
	public const TAB_PAYMENT_METHODS = 'payment-methods';

	/** Inner tab: Payments & Transactions. */
	public const TAB_PAYMENTS_TRANSACTIONS = 'payments-transactions';

	/** Inner tab: Synchronize Square. */
	public const TAB_SYNCHRONIZE = 'synchronize';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init() {
	}

}
