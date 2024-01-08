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

namespace WooCommerce\Square\Framework\PaymentGateway\Integrations;
use WooCommerce\Square\Framework\PaymentGateway\Payment_Gateway;

defined( 'ABSPATH' ) or exit;

/**
 * Abstract Integration
 *
 * @since 3.0.0
 */
abstract class Payment_Gateway_Integration {


	/** @var Payment_Gateway direct gateway instance */
	protected $gateway;


	/**
	 * Bootstraps the class.
	 *
	 * @since 3.0.0
	 *
	 * @param Payment_Gateway $gateway direct gateway instance
	 */
	public function __construct( Payment_Gateway $gateway ) {

		$this->gateway = $gateway;
	}


	/**
	 * Return the gateway for the integration
	 *
	 * @since 3.0.0
	 * @return Payment_Gateway
	 */
	public function get_gateway() {

		return $this->gateway;
	}
}
