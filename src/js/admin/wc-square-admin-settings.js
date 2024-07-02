/* global wc_square_admin_settings */

/**
 * WooCommerce Square scripts for admin product pages.
 *
 * @since 2.0.0
 */
jQuery( document ).ready( ( $ ) => {
	const pagenow = window.pagenow || '';

	// bail if not on the admin settings page.
	if ( 'woocommerce_page_wc-settings' !== pagenow ) {
		return;
	}

	// Function to get form fields as array and sort by name
	window.serialize_form = ( form ) => {
		let form_data = $( form ).serializeArray();
		// Exclude `_wp_http_referer` & `_wpnonce` fields
		form_data = $.grep( form_data, ( e ) => {
			return e.name !== '_wp_http_referer' && e.name !== '_wpnonce';
		} );
		form_data.sort( ( a, b ) => ( a.name > b.name ) ? 1 : -1 );
		return form_data;
	}

	// Get URL Params of admin page for conditional checks
	var is_square_settings_page = false;
	var url_params              = new window.URLSearchParams( window.location.search );
	var new_square_settings;
	var current_square_settings;
	let is_sor_set;
	let location_id = $( '#wc_square_location_id, #wc_square_sandbox_location_id' ).val();
	let show_changes_message = $( '.wc_square_save_changes_message' );

	// Get serialized array of current form values to check for changes
	if (
		'square' === url_params.get( 'tab' )
		&& (
			! url_params.get( 'section' )
			|| '' !== url_params.get( 'section' )
		)
	) {
		is_square_settings_page = true;
		current_square_settings = serialize_form( $( '#mainform' ) );
	}

	$( document ).on( 'change', function( e, ignore ) {
		if ( ignore ) {
			return;
		}

		const $import_products     = $( '#wc_square_import_products' );
		const $import_products_row = $import_products.closest( 'tr' );

		if ( is_sor_set && location_id && location_id.length ) {
			$import_products_row.show();
		} else {
			$import_products_row.hide();
		}
	} );

	$( '#mainform :input' ).on( 'change', function( e, ignore ) {

		if ( ignore ) {
			return;
		}

		const $import_products = $( '#wc_square_import_products' );

		// Get serialized array of new form values to check for changes
		new_square_settings = serialize_form( '#mainform' );

		// Compare changes by converting to String. Much easier since we've sorted the fields by name to avoid order mismatch
		if ( JSON.stringify( current_square_settings ) !== JSON.stringify( new_square_settings ) ) {
			if ( e.target.name === 'wc_square_sandbox_location_id' || e.target.name === 'wc_square_location_id' ) {
				return;
			}

			show_changes_message.show();
			$import_products.addClass( 'disabled' );
		} else {
			show_changes_message.hide();
			$import_products.removeClass( 'disabled' );
		}
	} );

	$( '#wc_square_sandbox_location_id, #wc_square_location_id' ).on( 'change', function() {
		location_id = $( this ).val();
	} );

	if ( ! wc_square_admin_settings.is_sandbox ) {
		// Hide sandbox settings if is_sandbox is set.
		$( '#wc_square_sandbox_settings' ).hide();
		$( '#wc_square_sandbox_settings' ).next().hide();
		$( '.wc_square_sandbox_settings' ).closest( 'tr' ).hide();
	}

	$( '#wc_square_system_of_record' ).on( 'change', ( e ) => {
		const sync_setting = $( e.target ).val();
		const $inventory_sync = $( '#wc_square_enable_inventory_sync' );
		const $inventory_sync_row = $inventory_sync.closest( 'tr' );

		const $import_products     = $( '#wc_square_import_products' );
		const $import_products_row = $import_products.closest( 'tr' );

		const $override_images     = $( '#wc_square_override_product_images' );
		const $override_images_row = $override_images.closest( 'tr' );
		const $sync_interval     = $( '#wc_square_sync_interval' );
		const $sync_interval_row = $sync_interval.closest( 'tr' );

		// toggle the "Sync inventory" setting depending on the SOR.
		if ( 'square' === sync_setting || 'woocommerce' === sync_setting ) {
			is_sor_set = true;
			$inventory_sync.next( 'span' ).html( wc_square_admin_settings.i18n.sync_inventory_label[ sync_setting ] );
			$inventory_sync_row.find( '.description' ).html( wc_square_admin_settings.i18n.sync_inventory_description[ sync_setting ] );
			$inventory_sync_row.show();
			$import_products_row.show();
			$sync_interval_row.show();
		} else {
			is_sor_set = false;
			$inventory_sync.prop( 'checked', false );
			$inventory_sync_row.hide();
			$import_products_row.hide();
			$override_images_row.hide();
			$sync_interval_row.hide();
		}

		// toggle the "Hide missing products" setting depending on the SOR.
		if ( 'square' === sync_setting ) {
			$( '#wc_square_hide_missing_products' ).closest( 'tr' ).show();
			$override_images_row.show();
		} else {
			$( '#wc_square_hide_missing_products' ).closest( 'tr' ).hide();
			$override_images_row.hide();
		}
	} ).trigger( 'change', [ true ] );

	$( '.js-import-square-products' ).on( 'click', function( e ) {
		e.preventDefault();

		// If button is disabled, return without action
		if ( $( this ).hasClass( 'disabled' ) ) {
			return;
		}

		new $.WCBackboneModal.View( {
			target: 'wc-square-import-products',
		} );

		$( '#btn-close' ).on( 'click', ( e ) => {
			e.preventDefault();

			$( 'button.modal-close' ).trigger( 'click' );
		} );
	} );

	// initiate a manual sync.
	$( '#wc-square-sync' ).on( 'click', ( e ) => {
		e.preventDefault();

		// open a modal dialog.
		new $.WCBackboneModal.View( {
			target: 'wc-square-sync',
		} );

		// enable cancel sync button.
		$( '#btn-close' ).on( 'click', ( e ) => {
			e.preventDefault();

			$( 'button.modal-close' ).trigger( 'click' );
		} );
	} );

	// Listen for wc_backbone_modal_response event handler.
	$( document.body ).on( 'wc_backbone_modal_response', ( e, target ) => {
		let data;

		switch ( target ) {
			case 'wc-square-import-products':
				// Add Block overlay since the modal exits immediately
				// after wc_backbone_modal_response is triggered.
				$( '#wpbody' ).block( {
					message: null,
					overlayCSS: {
						opacity: '0.2',
					},
					onBlock: function onBlock() {
						$( '.blockUI.blockOverlay' ).css(
							{
								position: 'fixed',
							}
						);
					},
				} );

				const update_during_import = $( '#wc-square-import-product-updates' ).prop( 'checked' );
				data = {
					action: 'wc_square_import_products_from_square',
					security: wc_square_admin_settings.import_products_from_square,
					update_during_import,
				};

				$.post( wc_square_admin_settings.ajax_url, data, ( response ) => {
					const message = response.data ? response.data : null;

					if ( message ) {
						alert( message );
					}

					location.href = 'admin.php?page=wc-settings&tab=square&section=update';
				} );
				break;

			case 'wc-square-sync':
				$( 'table.sync' ).block( {
					message: null,
					overlayCSS: {
						opacity: '0.2',
					},
				} );

				$( 'table.records' ).block( {
					message: null,
					overlayCSS: {
						opacity: '0.2',
					},
				} );

				$( '#wc-square_clear-sync-records' ).prop( 'disabled', true );

				data = {
					action: 'wc_square_sync_products_with_square',
					security: wc_square_admin_settings.sync_products_with_square,
				};

				$.post( wc_square_admin_settings.ajax_url, data, ( response ) => {
					if ( response && response.success ) {
						location.reload();
					} else {
						$( '#wc-square_clear-sync-records' ).prop( 'disabled', false );
						$( 'table.sync' ).unblock();
						$( 'table.records' ).unblock();
					}
				} );
				break;
		}
	} );

	// Clear sync records history.
	const noRecordsFoundRow = '<tr><td colspan="4"><em>' + wc_square_admin_settings.i18n.no_records_found + '</em></td></tr>';
	$( '#wc-square_clear-sync-records' ).on( 'click', ( e ) => {
		e.preventDefault();

		$( 'table.records' ).block( {
			message: null,
			overlayCSS: {
				opacity: '0.2',
			},
		} );

		const data = {
			action: 'wc_square_handle_sync_records',
			id: 'all',
			handle: 'delete',
			security: wc_square_admin_settings.handle_sync_with_square_records,
		};

		$.post( wc_square_admin_settings.ajax_url, data, ( response ) => {
			if ( response && response.success ) {
				$( 'table.records tbody' ).html( noRecordsFoundRow );
				$( '#wc-square_clear-sync-records' ).prop( 'disabled', true );
			} else {
				if ( response.data ) {
					alert( response.data );
				}
				console.log( response );
			}
			$( 'table.records' ).unblock();
		} );
	} );

	// Individual sync records actions.
	$( '.records .actions button.action' ).on( 'click', ( e ) => {
		e.preventDefault();

		$( 'table.records' ).block( {
			message: null,
			overlayCSS: {
				opacity: '0.2',
			},
		} );
		const recordId = $( e.currentTarget ).data( 'id' );
		const action = $( e.currentTarget ).data( 'action' );
		const data = {
			action: 'wc_square_handle_sync_records',
			id: recordId,
			handle: action,
			security: wc_square_admin_settings.handle_sync_with_square_records,
		};

		$.post( wc_square_admin_settings.ajax_url, data, ( response ) => {
			if ( response && response.success ) {
				const rowId = '#record-' + recordId;

				if ( 'delete' === action ) {
					$( rowId ).remove();

					if ( ! $( 'table.records tbody tr' ).length ) {
						$( 'table.records tbody' ).html( noRecordsFoundRow );
						$( '#wc-square_clear-sync-records' ).prop( 'disabled', true );
					}
				} else if ( 'resolve' === action || 'unsync' === action ) {
					$( rowId + ' .type' ).html( '<mark class="resolved"><span>' + wc_square_admin_settings.i18n.resolved + '</span></mark>' );
					$( rowId + ' .actions' ).html( '&mdash;' );
				}
			} else {
				if ( response && response.data ) {
					alert( response.data );
				}

				console.log( {
					record: recordId,
					action,
					response,
				} );
			}
			$( 'table.records' ).unblock();
		} );
	} );

	// Add explicit square environment to post data to deal with swapping between production and sandbox in the back end.
	$( 'form' ).on( 'submit', ( e ) => {
		const environment = $( '#wc_square_enable_sandbox' ).is( ':checked' ) ? 'sandbox' : 'production';

		$( e.target ).append(
			$( '<input>',
				{
					type: 'hidden',
					name: 'wc_square_environment',
					value: environment,
				}
			)
		);
	} );

	/**
	 * Returns a job sync status.
	 *
	 * @since 2.0.0
	 *
	 * @param {string} job_id
	 */
	const getSyncStatus = ( job_id ) => {
		let $progress = $( 'span.progress' );

		if ( ! $progress || 0 === $progress.length ) {
			$( 'p.sync-result' ).append( ' <span class="progress" style="display:block"></span>' );
			$progress = $( 'span.progress' );
		}

		const data = {
			action: 'wc_square_get_sync_with_square_status',
			security: wc_square_admin_settings.get_sync_with_square_status_nonce,
			job_id,
		};

		$.post( wc_square_admin_settings.ajax_url, data, ( response ) => {
			if ( response && response.data ) {
				if ( response.success && response.data.id ) {
					// start the progress spinner.
					$( 'table.sync .spinner' ).css( 'visibility', 'visible' );
					// disable interacting with records as more could be added during a sync process.
					$( '#wc-square_clear-sync-records' ).prop( 'disabled', true );
					$( 'table.records .actions button' ).prop( 'disabled', true );
					// continue if the job is in progression.
					if ( 'completed' !== response.data.status && 'failed' !== response.data.status ) {
						let progress = ' ';
						// update progress info in table cell.
						if ( 'product_import' === response.data.action ) {
							progress += wc_square_admin_settings.i18n.skipped + ': ' + parseInt( response.data.skipped_products_count, 10 ) + '<br/>';
							progress += wc_square_admin_settings.i18n.updated + ': ' + parseInt( response.data.updated_products_count, 10 ) + '<br/>';
							progress += wc_square_admin_settings.i18n.imported + ': ' + parseInt( response.data.imported_products_count, 10 );
						} else if ( response.data.percentage ) {
							progress += parseInt( response.data.percentage, 10 ) + '%';
						}

						$progress.html( progress );

						// recursion update loop until we're 'completed' (add a long timeout to avoid missing callback return output).
						setTimeout( () => {
							location.reload();
						}, 30 * 1000 );
					} else {
						// reload page, display updated sync dates and any sync records messages.
						location.reload(); // unlikely job processing exception.
					}
				} else {
					$( '#wc-square_clear-sync-records' ).prop( 'disabled', false );
					$( 'table.records .actions button' ).prop( 'disabled', false );
					$( 'table.sync .spinner' ).css( 'visibility', 'hidden' );
					console.log( response );
				}
			}
		} );
	};

	// run once on page load.
	if ( wc_square_admin_settings.existing_sync_job_id ) {
		getSyncStatus( wc_square_admin_settings.existing_sync_job_id );
	}

	// Show/hide Digital Wallet Settings on Square gateway settings page.
	$( '#woocommerce_square_credit_card_enable_digital_wallets' ).on( 'change', () => {
		const wallet_settings = $( '.wc-square-digital-wallet-options' );

		if ( $( '#woocommerce_square_credit_card_enable_digital_wallets' ).is( ':checked' ) ) {
			wallet_settings.closest( 'tr' ).show();
		} else {
			wallet_settings.closest( 'tr' ).hide();
		}
	} ).trigger( 'change', [ false ] );
} );
