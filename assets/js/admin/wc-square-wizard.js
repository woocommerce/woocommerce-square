/* global wc_square_admin_products */

/**
 * WooCommerce Square admin general scripts for the settings page and update tab.
 *
 * @since 2.0.0
 */
jQuery( document ).ready( ( $ ) => {


	alert('hey 356');

	const typenow = window.typenow || '';
	const pagenow = window.pagenow || '';
	const __ = wp.i18n.__

	// bail if not on product admin pages.
	if ( 'product' !== typenow ) {
		return;
	}

		
} );
