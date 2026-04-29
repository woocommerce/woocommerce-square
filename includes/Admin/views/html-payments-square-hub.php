<?php
/**
 * Template: Square settings hub shell (inner tabs + panel).
 *
 * @package WooCommerce\Square\Admin
 * @var string $current_tab          Active inner tab slug.
 * @var array  $tabs                 Tab ID => label map.
 * @var string $payments_screen_url  URL for the parent Payments screen.
 * @var string $parent_tab_label     Label for the parent Payments tab (from WC filter).
 * @var string $section_label        Label for the Square section (from WC filter).
 */

defined( 'ABSPATH' ) || exit;

use WooCommerce\Square\Admin\Payments_Square_Hub;
?>
<div class="wc-square-settings-hub">

	<div class="wc-square-settings-hub__header">
		<p class="wc-square-settings-hub__breadcrumb">
			<a href="<?php echo esc_url( $payments_screen_url ); ?>"><?php echo esc_html( $parent_tab_label ); ?></a>
			<span class="wc-square-settings-hub__sep">/</span>
			<span class="wc-square-settings-hub__current"><?php echo esc_html( $section_label ); ?></span>
		</p>
		<?php if ( Payments_Square_Hub::TAB_GENERAL === $current_tab ) : ?>
			<button type="button" class="button button-primary wc-square-settings-hub__save" id="wc-square-settings-hub__save-general">
				<?php esc_html_e( 'Save', 'woocommerce-square' ); ?>
			</button>
		<?php elseif ( Payments_Square_Hub::TAB_PAYMENT_METHODS === $current_tab ) : ?>
			<button type="button" class="button button-primary wc-square-settings-hub__save" id="wc-square-settings-hub__save-payment-methods" disabled>
				<?php esc_html_e( 'Save', 'woocommerce-square' ); ?>
			</button>
		<?php elseif ( Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS === $current_tab ) : ?>
			<button type="button" class="button button-primary wc-square-settings-hub__save" id="wc-square-settings-hub__save-payments-transactions" disabled>
				<?php esc_html_e( 'Save', 'woocommerce-square' ); ?>
			</button>
		<?php else : ?>
			<button type="button" class="button button-primary wc-square-settings-hub__save" disabled aria-disabled="true" title="<?php esc_attr_e( 'Saving will be available when settings are added to this screen.', 'woocommerce-square' ); ?>">
				<?php esc_html_e( 'Save', 'woocommerce-square' ); ?>
			</button>
		<?php endif; ?>
	</div>

	<nav class="wc-square-settings-hub__tablist" aria-label="<?php esc_attr_e( 'Square settings sections', 'woocommerce-square' ); ?>">
		<?php foreach ( $tabs as $tab_id => $label ) : ?>
			<?php
			$url     = Payments_Square_Hub::get_hub_url( $tab_id );
			$classes = 'wc-square-settings-hub__tab' . ( $current_tab === $tab_id ? ' is-active' : '' );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"<?php echo $current_tab === $tab_id ? ' aria-current="page"' : ''; ?>>
				<?php echo esc_html( $label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php
	$panel_classes = 'wc-square-settings-hub__panel';
	if ( Payments_Square_Hub::TAB_GENERAL === $current_tab ) {
		$panel_classes .= ' wc-square-settings-hub__panel--general';
	} elseif ( Payments_Square_Hub::TAB_PAYMENT_METHODS === $current_tab ) {
		$panel_classes .= ' wc-square-settings-hub__panel--payment-methods';
	} elseif ( Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS === $current_tab ) {
		$panel_classes .= ' wc-square-settings-hub__panel--payments-transactions';
	}
	?>
	<div class="<?php echo esc_attr( $panel_classes ); ?>">
		<?php if ( Payments_Square_Hub::TAB_GENERAL === $current_tab ) : ?>
			<div id="woocommerce-square-settings__container-general"></div>
		<?php elseif ( Payments_Square_Hub::TAB_PAYMENT_METHODS === $current_tab ) : ?>
			<div id="woocommerce-square-settings__container-payment-methods"></div>
		<?php elseif ( Payments_Square_Hub::TAB_PAYMENTS_TRANSACTIONS === $current_tab ) : ?>
			<div id="woocommerce-square-settings__container-payments-transactions"></div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Settings content for this tab will be added in a future release.', 'woocommerce-square' ); ?></p>
		<?php endif; ?>
	</div>

</div>
