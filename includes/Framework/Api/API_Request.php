<?php
/**
 * WooCommerce Plugin Framework
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
 * Modified by WooCommerce on 01 December 2021.
 */

namespace WooCommerce\Square\Framework\Api;

defined( 'ABSPATH' ) or exit;

interface API_Request {
	/**
	 * Returns the method for this request: one of HEAD, GET, PUT, PATCH, POST, DELETE
	 *
	 * @since 3.0.0
	 */
	public function get_method();


	/**
	 * Returns the request path
	 *
	 * @since 3.0.0
	 */
	public function get_path();


	/**
	 * Gets the request query params.
	 *
	 * @since 3.0.0
	 */
	public function get_params();


	/**
	 * Gets the request data.
	 *
	 * @since 3.0.0
	 */
	public function get_data();


	/**
	 * Returns the string representation of this request
	 *
	 * @since 3.0.0
	 * @return string the request
	 */
	public function to_string();


	/**
	 * Returns the string representation of this request with any and all
	 * sensitive elements masked or removed
	 *
	 * @since 3.0.0
	 * @return string the request, safe for logging/displaying
	 */
	public function to_string_safe();
}
