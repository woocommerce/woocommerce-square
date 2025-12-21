<?php
/**
 * WooCommerce Square
 *
 * This source file is subject to the GNU General Public License v3.0
 * that is bundled with this package in the file license.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@woocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade WooCommerce Square to newer
 * versions in the future. If you wish to customize WooCommerce Square for your
 * needs please refer to https://docs.woocommerce.com/document/woocommerce-square/
 *
 * @author    WooCommerce
 * @copyright Copyright: (c) 2019, Automattic, Inc.
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0 or later
 */

namespace WooCommerce\Square\Admin;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square;

/**
 * The settings page class.
 *
 * @see \WooCommerce\Square\Settings handles settings registration
 *
 * @since 2.0.0
 */
class Settings_Page extends \WC_Settings_Page {


	/** @var Square\Settings settings handler instance */
	protected $settings_handler;


	/**
	 * Constructs the settings page.
	 *
	 * @since 2.0.0
	 *
	 * @param Square\Settings $settings_handler a settings handler instance, for displaying and saving the settings
	 */
	public function __construct( Square\Settings $settings_handler ) {

		$this->id               = Square\Plugin::PLUGIN_ID;
		$this->label            = __( 'Square', 'woocommerce-square' );
		$this->settings_handler = $settings_handler;

		parent::__construct();
	}


	/**
	 * Outputs the settings page.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function output() {
		global $current_section;

		if ( 'update' === $current_section ) {
			$this->output_update_section();
		} else {
			$this->output_general_section();
		}
	}


	/**
	 * Outputs the general settings section.
	 *
	 * @since 2.0.0
	 */
	private function output_general_section() {

		$this->settings_handler->admin_options();
		self::output_import_products_modal_template();
	}


	/**
	 * Outputs the "Update" settings section.
	 *
	 * @since 2.0.0
	 */
	private function output_update_section() {
		global $hide_save_button;

		// removes the save/update button from the screen
		$hide_save_button = true;

		Sync_Page::output();
	}


	/**
	 * Saves the settings.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 */
	public function save() {

		$this->settings_handler->process_admin_options();
	}


	/**
	 * Gets the settings.
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_settings() {

		return (array) apply_filters( 'woocommerce_get_settings_square', $this->settings_handler->get_form_fields() );
	}


	/**
	 * Gets the settings sections.
	 *
	 * @internal
	 *
	 * @since 2.0.0
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			''       => __( 'Settings', 'woocommerce-square' ), // this key is intentionally blank
			'update' => __( 'Update', 'woocommerce-square' ),
		);

		/**
		 * Filters the WooCommerce Square settings sections.
		 *
		 * @since 2.0.0
		 *
		 * @param array $sections settings sections
		 */
		return (array) apply_filters( 'woocommerce_get_sections_square', $sections );
	}


	/**
	 * Outputs a backbone modal template for importing products from Square.
	 *
	 * @since 2.0.0
	 */
	private static function output_import_products_modal_template() {

		?>
		<script type="text/template" id="tmpl-wc-square-import-products">
			<div class="wc-backbone-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1><?php esc_html_e( 'Import Products From Square', 'woocommerce-square' ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close modal window', 'woocommerce-square' ); ?></span>
							</button>
						</header>
						<article>
							<p><?php esc_html_e( 'You are about to import all new products, variations and categories from Square. This will create a new product in WooCommerce for every product retrieved from Square. If you have products in the trash from the previous imports, these will be ignored in the import.', 'woocommerce-square' ); ?></p>
							<hr>
							<h4><?php esc_html_e( 'Do you wish to import existing product updates from Square?', 'woocommerce-square' ); ?></h4>
							<?php /* translators: Placeholders: %1$s - <a> tag linking to WooCommerce Square docs, %2%s - closing </a> tag */ ?>
							<p><?php printf( esc_html__( 'Doing so will update existing WooCommerce products with the latest information from Square. %1$sView Documentation%2$s.', 'woocommerce-square' ), '<a href="https://docs.woocommerce.com/document/woocommerce-square/#section-4">', '</a>' ); ?></p>
							<label for="wc-square-import-product-updates"><input type="checkbox" id="wc-square-import-product-updates" /><?php esc_html_e( 'Update existing products during import.', 'woocommerce-square' ); ?></label>
						</article>
						<footer>
							<div class="inner">
								<button id="btn-close" class="button button-large"><?php esc_html_e( 'Cancel', 'woocommerce-square' ); ?></button>
								<button id="btn-ok" class="button button-large button-primary"><?php esc_html_e( 'Import Products', 'woocommerce-square' ); ?></button>
							</div>
						</footer>
					</section>
				</div>
			</div>
			<div class="wc-backbone-modal-backdrop modal-close"></div>
		</script>
		<?php
	}
}
