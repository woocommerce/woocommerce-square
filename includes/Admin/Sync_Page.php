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

use WooCommerce\Square\Handlers\Product;
use WooCommerce\Square\Sync\Records;

/**
 * Handles HTML output for the "Update" section of the Settings page with sync status handling and UI.
 *
 * @since 2.0.0
 */
class Sync_Page {


	/**
	 * Outputs the page HTML.
	 *
	 * @since 2.0.0
	 */
	public static function output() {

		?>
		<div id="wc-square-sync-page">

			<h2><?php esc_html_e( 'Update', 'woocommerce-square' ); ?></h2>
			<?php self::output_system_record_of_data_info(); ?>
			<?php self::output_sync_status(); ?>

			<h2><?php esc_html_e( 'Sync records', 'woocommerce-square' ); ?></h2>
			<?php self::output_sync_records(); ?>
			<?php self::output_sync_with_square_modal_template(); ?>

		</div>
		<?php
	}


	/**
	 * Outputs notice-like tabular HTML with information on the current Sync setting handling.
	 *
	 * @since 2.0.0
	 */
	private static function output_system_record_of_data_info() {

		?>
		<table class="wc_square_table sor widefat" cellspacing="0">
			<tbody>
				<?php if ( wc_square()->get_settings_handler()->is_system_of_record_square() ) : ?>

					<tr>
						<td>
							<?php
							printf(
								/* translators: Placeholders: %1$s, %3$s - opening <strong> HTML tag, %2$s, $4%s - closing </strong> HTML tag */
								esc_html__( '%1$sSquare%2$s is the system of record set in the sync settings. The following data from Square will overwrite WooCommerce data for synced products: %3$sname, price, description, category, inventory%4$s.', 'woocommerce-square' ),
								'<strong>',
								'</strong>',
								'<strong>',
								'</strong>'
							);
							?>
						</td>
					</tr>
					<tr>
						<td>
							<?php
							printf(
								/* translators: Placeholders: %1$s - opening <strong> HTML tag, %2$s closing </strong> HTML tag */
								esc_html__( '%1$sProduct images%2$s will be imported from Square if no featured image is set in WooCommerce.', 'woocommerce-square' ),
								'<strong>',
								'</strong>'
							);
							?>
						</td>
					</tr>

				<?php elseif ( wc_square()->get_settings_handler()->is_system_of_record_woocommerce() ) : ?>

					<tr>
						<td>
							<?php
							printf(
								/* translators: Placeholders: %1$s, %3$s - opening <strong> HTML tag, %2$s, %4$s - closing </strong> HTML tag */
								esc_html__( '%1$sWooCommerce%2$s is the system of record set in the sync settings. The following data from WooCommerce will overwrite Square data for synced products: %3$sname, price, inventory, category, image%4$s.', 'woocommerce-square' ),
								'<strong>',
								'</strong>',
								'<strong>',
								'</strong>'
							);
							?>
						</td>
					</tr>

				<?php else : ?>

					<tr>
						<td>
							<?php
							printf(
								/* translators: Placeholders: %1$s - opening <strong> HTML tag, %2$s closing </strong> HTML tag*/
								esc_html__( '%1$sSync setting not chosen.%2$s Products will not be synced between Square and WooCommerce.', 'woocommerce-square' ),
								'<strong>',
								'</strong>'
							);
							?>
						</td>
					</tr>

				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}


	/**
	 * Outputs tabular HTML with information on sync status and a button to trigger a manual sync.
	 *
	 * @since 2.0.0
	 */
	private static function output_sync_status() {

		$is_connected      = wc_square()->get_settings_handler()->is_connected();
		$sync_in_progress  = $is_connected ? wc_square()->get_sync_handler()->is_sync_in_progress() : false;
		$synced_products   = wc_square()->get_settings_handler()->is_product_sync_enabled() ? Product::get_products_synced_with_square() : array();
		$synced_count      = count( $synced_products );
		$is_product_import = false;

		if ( $sync_in_progress ) {

			$current_job       = wc_square()->get_sync_handler()->get_job_in_progress();
			$is_product_import = isset( $current_job->action ) && 'product_import' === $current_job->action;
		}

		if ( ! $is_connected ) {
			$disabled_reason = esc_html__( 'Please connect to Square to enable product sync.', 'woocommerce-square' );
		} elseif ( ! wc_square()->get_settings_handler()->get_location_id() ) {
			$disabled_reason = esc_html__( 'Please set the business location to enable product sync.', 'woocommerce-square' );
		} elseif ( 0 === $synced_count ) {
			$disabled_reason = esc_html__( 'There are currently no products marked to be synced with Square.', 'woocommerce-square' );
		} elseif ( $sync_in_progress ) {
			$disabled_reason = esc_html__( 'A sync is currently in progress. Please try again later.', 'woocommerce-square' );
		}

		if ( ! empty( $disabled_reason ) ) {
			/* translators: Placeholder: %s - reason text */
			$disabled_reason = sprintf( esc_html__( 'Product sync between WooCommerce and Square is currently unavailable. %s', 'woocommerce-square' ), $disabled_reason );
		}

		?>
		<table class="wc_square_table sync widefat" cellspacing="0">
			<thead>
				<tr>
					<th class="synced-products"><?php esc_html_e( 'Synced products', 'woocommerce-square' ); ?></th>
					<th class="latest-sync"><?php esc_html_e( 'Latest sync', 'woocommerce-square' ); ?></th>
					<th class="next-sync"><?php esc_html_e( 'Next sync', 'woocommerce-square' ); ?></th>
					<th class="actions"><?php esc_html_e( 'Actions', 'woocommerce-square' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td class="synced-products">
						<a href="<?php echo esc_url( admin_url( 'edit.php?s&post_status=all&post_type=product&product_type=synced-with-square&stock_status&paged=1' ) ); ?>">
							<?php
							printf(
								/* translators: Placeholder: %d number of products synced with Square */
								esc_html( _n( '%d product', '%d products', $synced_count, 'woocommerce-square' ) ),
								esc_html( $synced_count )
							);
							?>
						</a>
						<input
							type="hidden"
							id="wc-square-sync-products-count"
							value="<?php echo esc_attr( $synced_count ); ?>"
						/>
					</td>

					<?php $date_time_format = wc_date_format() . ' ' . wc_time_format(); ?>

					<td class="latest-sync">
						<p class="sync-result">
							<?php if ( $sync_in_progress ) : ?>
								<?php if ( $is_product_import ) : ?>
									<em><?php esc_html_e( 'Importing now&hellip;', 'woocommerce-square' ); ?></em>
								<?php else : ?>
									<em><?php esc_html_e( 'Syncing now&hellip;', 'woocommerce-square' ); ?></em>
								<?php endif; ?>
							<?php else : ?>
								<?php $last_synced_at = wc_square()->get_sync_handler()->get_last_synced_at(); ?>
								<?php if ( $last_synced_at ) : ?>
									<?php
									$last_synced_date = new \DateTime();
									$last_synced_date->setTimestamp( $last_synced_at );
									$last_synced_date->setTimezone( new \DateTimeZone( wc_timezone_string() ) );
									echo esc_html( $last_synced_date->format( $date_time_format ) );
									?>
								<?php else : ?>
									<em><?php esc_html_e( 'Not synced yet.', 'woocommerce-square' ); ?></em>
								<?php endif; ?>
							<?php endif; ?>
						</p>
					</td>
					<td class="next-sync">
						<p>
							<?php if ( $sync_in_progress ) : ?>
								&mdash;
							<?php else : ?>
								<?php $next_sync_at = wc_square()->get_sync_handler()->get_next_sync_at(); ?>
								<?php if ( $next_sync_at ) : ?>
									<?php
									$next_sync_date = new \DateTime();
									$next_sync_date->setTimestamp( $next_sync_at );
									$next_sync_date->setTimezone( new \DateTimeZone( wc_timezone_string() ) );

									echo esc_html( $next_sync_date->format( $date_time_format ) );
									?>
								<?php else : ?>
									&mdash;
								<?php endif; ?>
							<?php endif; ?>
						</p>
					</td>
					<td class="actions">
						<button
							id="wc-square-sync"
							class="button button-large"
							<?php echo ! empty( $disabled_reason ) ? sprintf( 'disabled="disabled" title="%s"', esc_attr( $disabled_reason ) ) : ''; ?>
						><?php esc_html_e( 'Sync now', 'woocommerce-square' ); ?></span></button>
						<div id="wc-square-sync-progress-spinner" class="spinner" style="float:none; <?php echo $sync_in_progress ? 'visibility:visible;' : ''; ?>"></div>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}


	/**
	 * Outputs tabular HTML with sync record logs and UI.
	 *
	 * @since 2.0.0
	 */
	private static function output_sync_records() {

		$records = Records::get_records();
		?>

		<button
			id="wc-square_clear-sync-records"
			class="button button-large"
			<?php
			if ( empty( $records ) ) {
				echo 'disabled="disabled"';
			}
			?>
		><?php echo esc_html_x( 'Clear history', 'Delete all records', 'woocommerce-square' ); ?></button>

		<table class="wc_square_table records widefat" cellspacing="0">
			<thead>
				<tr>
					<th class="date-time"><?php echo esc_html_x( 'Time', 'Date - Time', 'woocommerce-square' ); ?></th>
					<th class="type"><?php esc_html_e( 'Status', 'woocommerce-square' ); ?></th>
					<th class="message"><?php esc_html_e( 'Message', 'woocommerce-square' ); ?></th>
					<th class="actions"><?php esc_html_e( 'Actions', 'woocommerce-square' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $records ) ) : ?>

					<?php foreach ( $records as $record ) : ?>

						<tr id="record-<?php echo esc_attr( $record->get_id() ); ?>">
							<td class="date-time"><?php echo esc_html( $record->get_local_date() ); ?></td>
							<td class="type"><mark class="<?php echo esc_attr( sanitize_html_class( $record->get_type() ) ); ?>"><span><?php echo esc_html( $record->get_label() ); ?></span></mark></td>
							<td class="message"><?php echo wp_kses_post( $record->get_message() ); ?></td>
							<td class="actions">
								<?php foreach ( $record->get_actions() as $action ) : ?>
									<button
										class="button action <?php echo sanitize_html_class( $action->name ); ?> tips"
										data-id="<?php echo esc_attr( $record->get_id() ); ?>"
										data-action="<?php echo esc_attr( $action->name ); ?>"
										data-tip="<?php echo esc_attr( $action->label ); ?>"
									><?php echo wp_kses_post( $action->icon ); ?></button>
								<?php endforeach; ?>
							</td>
						</tr>

					<?php endforeach; ?>

				<?php else : ?>

					<tr>
						<td colspan="4">
							<em><?php esc_html_e( 'No records found.', 'woocommerce-square' ); ?></em>
						</td>
					</tr>

				<?php endif; ?>
			</tbody>
			<tfoot>
				<tr>
					<th class="date-time"><?php echo esc_html_x( 'Time', 'Date - Time', 'woocommerce-square' ); ?></th>
					<th class="type"><?php esc_html_e( 'Status', 'woocommerce-square' ); ?></th>
					<th class="message"><?php esc_html_e( 'Message', 'woocommerce-square' ); ?></th>
					<th class="actions"><?php esc_html_e( 'Actions', 'woocommerce-square' ); ?></th>
				</tr>
			</tfoot>
		</table>
		<?php
	}


	/**
	 * Outputs a backbone modal template for starting a manual sync process.
	 *
	 * @since 2.0.0
	 */
	private static function output_sync_with_square_modal_template() {

		?>
		<script type="text/template" id="tmpl-wc-square-sync">
			<div class="wc-backbone-modal">
				<div class="wc-backbone-modal-content">
					<section class="wc-backbone-modal-main" role="main">
						<header class="wc-backbone-modal-header">
							<h1><?php esc_html_e( 'Sync products with Square', 'woocommerce-square' ); ?></h1>
							<button class="modal-close modal-close-link dashicons dashicons-no-alt">
								<span class="screen-reader-text"><?php esc_html_e( 'Close modal window', 'woocommerce-square' ); ?></span>
							</button>
						</header>
						<article>
							<?php $square_settings = wc_square()->get_settings_handler(); ?>
							<?php ob_start(); ?>
							<ul>
								<?php if ( $square_settings->is_system_of_record_square() ) : ?>
									<li><?php esc_html_e( 'If a match is found in Square, product data in WooCommerce will be overwritten with Square data.', 'woocommerce-square' ); ?></li>
								<?php elseif ( $square_settings->is_system_of_record_woocommerce() ) : ?>
									<li><?php esc_html_e( 'If a match is found in WooCommerce, product data in Square will be overwritten with WooCommerce data.', 'woocommerce-square' ); ?></li>
								<?php endif; ?>
								<?php if ( $square_settings->is_system_of_record_square() ) : ?>
									<?php if ( $square_settings->hide_missing_square_products() ) : ?>
										<li><?php esc_html_e( 'If a match is not found in Square, the product will be hidden from the catalog in WooCommerce.', 'woocommerce-square' ); ?></li>
									<?php else : ?>
										<li><?php esc_html_e( 'If a match is not found in Square, the product will be skipped in the sync.', 'woocommerce-square' ); ?></li>
									<?php endif; ?>
								<?php else : ?>
									<li><?php esc_html_e( 'If a match is not found, a new product will be created in Square.', 'woocommerce-square' ); ?></li>
								<?php endif; ?>
							</ul>
							<?php $additional_info = ob_get_clean(); ?>
							<?php
							printf(
								/* translators: Placeholders: %1$s - the name of the system of record set in the sync settings (e.g. Square or WooCommerce), %3%s - unordered HTML list of additional information item(s) */
								esc_html__( 'You are about to sync products with Square. %1$s is your system of record set in the sync settings. For all products synced with Square: %2$s', 'woocommerce-square' ),
								esc_html( $square_settings->get_system_of_record_name() ),
								$additional_info // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The contents of $additional_info is already escaped above.
							);
							?>
						</article>
						<footer>
							<div class="inner">
								<button id="btn-close" class="button button-large"><?php esc_html_e( 'Cancel', 'woocommerce-square' ); ?></button>
								<button id="btn-ok" class="button button-large button-primary"><?php esc_html_e( 'Start sync', 'woocommerce-square' ); ?></button>
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
