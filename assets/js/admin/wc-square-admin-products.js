/* global wc_square_admin_products */

/**
 * WooCommerce Square admin general scripts for the settings page and update tab.
 *
 * @since 2.0.0
 */
jQuery( document ).ready( ( $ ) => {
	const typenow = window.typenow || '';
	const pagenow = window.pagenow || '';
	const __ = wp.i18n.__

	// bail if not on product admin pages.
	if ( 'product' !== typenow ) {
		return;
	}

	// bail if product sync is disabled.
	if ( ! wc_square_admin_products.is_product_sync_enabled ) {
		return;
	} else if ( 'product_page_product_importer' === pagenow ) {
		const importNotice = '<div class="error">' +
			'<p>' +
			__( 'Product syncing with square has been enabled. ' +
			'If you are trying to update product inventory, you should do it in Square. ' +
			'Your existing inventory data in WooCommerce will be overwritten with data from Square products.', 'woocommerce-square' ) +
			'</p>' +
			'</div>';
		$('.wc-progress-steps').after( importNotice );
	}

	// products quick edit screen.
	if ( 'edit-product' === pagenow ) {
		// when clicking the quick edit button fetch the default Synced with Square checkbox
		$( '#the-list' ).on( 'click', '.editinline', ( e ) => {
			const $row = $( e.target ).closest( 'tr' );
			const postID = $row.find( 'th.check-column input' ).val();
			const data = {
				action: 'wc_square_get_quick_edit_product_details',
				security: wc_square_admin_products.get_quick_edit_product_details_nonce,
				product_id: $row.find( 'th.check-column input' ).val(),
			};

			$.post( wc_square_admin_products.ajax_url, data, ( response ) => {
				const $editRow = $( 'tr#edit-' + postID );
				const $squareSynced = $editRow.find( 'select.square-synced' );
				const $errors = $editRow.find( '.wc-square-sync-with-square-errors' );

				if ( ! response.success && response.data ) {
					// if the product has variations without an SKU we show an inline error message and bail.
					if ( 'missing_variation_sku' === response.data ) {
						$squareSynced.prop( 'checked', false );
						$squareSynced.prop( 'disabled', true );
						$errors.find( '.missing_variation_sku' ).show();
						return;
					}
				}

				const $sku = $editRow.find( 'input[name=_sku]' );
				const $stockStatus = $editRow.find( 'select[name=_stock_status]' );
				const $stockQty = $editRow.find( 'input[name=_stock]' );
				const $manageStockLabel = $editRow.find( '.manage_stock_field .manage_stock' );
				const $manageStockInput = $editRow.find( 'input[name=_manage_stock]' );
				const $manageStockDesc = '<span class="description"><a href="' + wc_square_admin_products.settings_url + '">' + wc_square_admin_products.i18n.synced_with_square + '</a></span>';
				const edit_url = response.data.edit_url;
				const i18n = response.data.i18n;
				const is_variable = response.data.is_variable;

				$squareSynced.val( response.data.is_synced_with_square );

				// if the SKU changes, enabled or disable Synced with Square checkbox accordingly
				$sku.on( 'change keyup keypress', ( e ) => {
					if ( '' === $( e.target ).val() && ! is_variable ) {
						$squareSynced.val( 'no' ).trigger( 'change' );
						$squareSynced.prop( 'disabled', true );
						$errors.find( '.missing_sku' ).show();
					} else {
						$squareSynced.prop( 'disabled', false );
						$squareSynced.trigger( 'change' );
						return $errors.find( '.missing_sku' ).hide();
					}
				} ).trigger( 'change' );

				// if Synced with Square is enabled, we might as well disable stock management (without verbose explanations as in the product page).
				$squareSynced.on( 'change', ( e ) => {
					// Only handle stock fields if inventory sync is enabled.
					if ( ! wc_square_admin_products.is_inventory_sync_enabled ) {
						return;
					}

					if ( 'no' === $( e.target ).val() ) {
						$manageStockInput.off();
						$manageStockInput.add( $stockQty ).css( {
							opacity: 1,
						} );

						$manageStockLabel.find( '.description' ).remove();

						// Stock input manipulation will differ depending on whether product is variable or simple.
						if ( is_variable ) {
							if ( $manageStockInput.is( ':checked' ) ) {
								$( '.stock_qty_field' ).show();
								$( '.backorder_field' ).show();
							} else {
								$( '.stock_status_field' ).show();
							}
						} else {
							$stockQty.prop( 'readonly', false );
							$stockStatus.prop( 'readonly', false );
						}
					} else {
						$manageStockInput.on( 'click', () => {
							return false;
						} );

						$manageStockInput.add( $stockQty ).css( {
							opacity: '0.5',
						} );

						$manageStockLabel.append( $manageStockDesc );

						if ( wc_square_admin_products.is_woocommerce_sor && edit_url && i18n ) {
							$manageStockLabel.append( '<p class="description"><a href="' + edit_url + '">' + i18n + '</a></p>' );
						}

						if ( is_variable ) {
							$( '.stock_status_field' ).hide();
							$( '.stock_qty_field' ).hide();
							$( '.backorder_field' ).hide();
						} else {
							$stockQty.prop( 'readonly', true );
							$stockStatus.prop( 'readonly', true );
						}
					}
				} ).trigger( 'change' );
			} );
		} );
	}

	// individual product edit screen.
	if ( 'product' === pagenow ) {
		const syncCheckboxID = '#_' + wc_square_admin_products.synced_with_square_taxonomy;

		/**
		 * Checks whether the product is variable.
		 *
		 * @since 2.0.0
		 */
		const isVariable = () => {
			return wc_square_admin_products.variable_product_types.includes( $( '#product-type' ).val() );
		};

		/**
		 * Checks whether the product has a SKU.
		 *
		 * @since 2.0.0
		 */
		const hasSKU = () => {
			return '' !== $( '#_sku' ).val().trim();
		};

		/**
		 * Checks whether the product variations all have SKUs.
		 *
		 * @since 2.2.3
		 *
		 * @param {Array} skus
		 */
		const hasVariableSKUs = ( skus ) => {
			if ( ! skus.length ) {
				return false;
			}

			const valid = skus.filter( ( sku ) => '' !== $( sku ).val().trim() );

			return valid.length === skus.length;
		};

		/**
		 * Checks whether the given skus are unique.
		 *
		 * @since 2.2.3
		 *
		 * @param {Array} skus
		 */
		const hasUniqueSKUs = ( skus ) => {
			const skuValues = skus.map( ( sku ) => $( sku ).val() );

			return skuValues.every( ( sku ) => skuValues.indexOf( sku ) === skuValues.lastIndexOf( sku ) );
		};

		/**
		 * Checks whether the product has more than one variation attribute.
		 *
		 * @since 2.0.0
		 */
		const hasMultipleAttributes = () => {
			const $variation_attributes = $( '.woocommerce_attribute_data input[name^="attribute_variation"]:checked' );

			return isVariable() && $variation_attributes && $variation_attributes.length > 1;
		};

		/**
		 * Displays the given error and disables the sync checkbox.
		 * Accepted errors are 'missing_sku', 'missing_variation_sku', and 'multiple_attributes'.
		 *
		 * @since 2.2.3
		 *
		 * @param {string} error
		 */
		const showError = ( error ) => {
			$( '.wc-square-sync-with-square-error.' + error ).show();
			$( syncCheckboxID ).prop( 'disabled', true );
			$( syncCheckboxID ).prop( 'checked', false );
		};

		/**
		 * Hides the given error and maybe enables the sync checkbox.
		 * Accepted errors are 'missing_sku', 'missing_variation_sku', and 'multiple_attributes'.
		 *
		 * @since 2.2.3
		 *
		 * @param {string} error
		 * @param {boolean} enable Whether to enable the sync checkbox.
		 */
		const hideError = ( error, enable = true ) => {
			$( '.wc-square-sync-with-square-error.' + error ).hide();

			if ( enable ) {
				$( syncCheckboxID ).prop( 'disabled', false );
			}
		};

		/**
		 * Handle SKU.
		 *
		 * Disables the Sync with Square checkbox and toggles an inline notice when no SKU is set on a product.
		 *
		 * @since 2.0.
		 *
		 * @param {string} syncCheckboxID
		 */
		const handleSKU = ( syncCheckboxID ) => {
			if ( isVariable() ) {
				$( '#_sku' ).off( 'change keypress keyup' );
				hideError( 'missing_sku', ! hasMultipleAttributes() );

				const skus = $( 'input[id^="variable_sku"]' );
				skus.on( 'change keypress keyup', () => {
					if ( ! hasVariableSKUs( $.makeArray( skus ) ) || ! hasUniqueSKUs( $.makeArray( skus ) ) ) {
						showError( 'missing_variation_sku' );
					} else {
						hideError( 'missing_variation_sku', ! hasMultipleAttributes() );
					}
					$( syncCheckboxID ).triggerHandler( 'change' );
				} ).triggerHandler( 'change' );
			} else {
				$( 'input[id^="variable_sku"]' ).off( 'change keypress keyup' );
				hideError( 'missing_variation_sku', ! hasMultipleAttributes() );

				$( '#_sku' ).on( 'change keypress keyup', ( e ) => {
					if ( '' === $( e.target ).val().trim() ) {
						showError( 'missing_sku' );
					} else {
						hideError( 'missing_sku', ! hasMultipleAttributes() );
					}
					$( syncCheckboxID ).trigger( 'change' );
				} ).trigger( 'change' );
			}
		};

		/**
		 * Disables the Sync with Square checkbox and toggles an inline notice when more than one attribute is set on the product.
		 *
		 * @since 2.0.0
		 *
		 * @param {string} syncCheckboxID
		 */
		const handleAttributes = ( syncCheckboxID ) => {
			$( '#variable_product_options' ).on( 'reload', () => {
				if ( hasSKU() ) {
					$( syncCheckboxID ).prop( 'disabled', false )
				}

				$( syncCheckboxID ).trigger( 'change' );
			} ).trigger( 'reload' );
		};

		/**
		 * Triggers an update to the sync checkbox, checking for relevant errors.
		 *
		 * @since 2.2.3
		 */
		const triggerUpdate = () => {
			handleSKU( syncCheckboxID );
			$( syncCheckboxID ).trigger( 'change' );

			// handleSKU misses cases where product is variable with no variations.
			if ( isVariable() && ! $( 'input[id^="variable_sku"]' ).length ) {
				showError( 'missing_variation_sku' );
			}
		};

		/**
		 * Show/hides the Sync with Square meta fields depending on the product type.
		 *
		 * @since 3.8.3
		 * @param {string} productType The product type selected.
		 */
		const toggleSyncProductMeta = ( productType ) => {
			const syncProductMeta = $( '.wc-square-sync-with-square' );

			if ( wc_square_admin_products.supported_products_for_sync.includes( productType ) ) {
				syncProductMeta.show();
			} else {
				syncProductMeta.hide();
			}
		};

		// fire once on page load
		handleAttributes( syncCheckboxID );

		/**
		 * Handle stock management.
		 *
		 * If product is managed by Square, handle stock fields according to chosen SoR.
		 */
		const $stockFields = $( '.stock_fields' );
		const $stockInput = $stockFields.find( '#_stock' );
		const $stockStatus = $( '.stock_status_field' );
		const $manageField = $( '._manage_stock_field' );
		const $manageInput = $manageField.find( '#_manage_stock' );
		const $manageDesc = $manageField.find( '.description' );
		// keep note of the original manage stock checkbox description, if we need to restore it later
		const manageDescOriginal = $manageDesc.text();
		// keep track of the original manage stock checkbox status, if we need to restore it later
		const manageStockOriginal = $( '#_manage_stock' ).is( ':checked' );

		$( syncCheckboxID ).on( 'change', ( e ) => {
			// only handle stock fields if inventory sync is enabled.
			if ( ! wc_square_admin_products.is_inventory_sync_enabled ) {
				return;
			}

			const variableProduct = wc_square_admin_products.variable_product_types.includes( $( '#product-type' ).val() );

			let useSquare;

			if ( $( e.target ).is( ':checked' ) && $( '#_square_item_variation_id' ).length > 0 ) {
				useSquare = true;
					let syncInventory = '';
					if (!$manageInput.is(':checked') && !variableProduct) {
						syncInventory =
							' (<a href="#" class="sync-stock-from-square" data-product-id="' +
							$('#post_ID').val() +
							'">' +
							wc_square_admin_products.i18n.sync_inventory +
							'</a>)<div class="sync-stock-spinner spinner" style="float:none;"></div>';
					}

					$manageDesc.html(
						'<a href="' +
							wc_square_admin_products.settings_url +
							'">' +
							wc_square_admin_products.i18n.synced_with_square +
							'</a>' +
							syncInventory
					);

					$manageInput.on('click', () => {
						return false;
					});
					$manageInput.css({ opacity: '0.5' });

				$stockInput.prop( 'readonly', true );

				// WooCommerce SoR - note: for variable products, the stock can be fetched for individual variations.
				if ( wc_square_admin_products.is_woocommerce_sor && ! variableProduct ) {
					// add inline note with a toggle to fetch stock from Square manually via AJAX (sanity check to avoid appending multiple times).
					if ( $( 'p._stock_field span.description' ).length === 0 ) {
						$stockInput.after(
							'<span class="description" style="display:block;clear:both;"><a href="#" id="fetch-stock-with-square">' + wc_square_admin_products.i18n.fetch_stock_with_square + '</a><div class="spinner" style="float:none;"></div></span>'
						);
					}
					$( '#fetch-stock-with-square' ).on( 'click', ( e ) => {
						e.preventDefault();
						const $spinner = $( 'p._stock_field span.description .spinner' );
						const data = {
							action: 'wc_square_fetch_product_stock_with_square',
							security: wc_square_admin_products.fetch_product_stock_with_square_nonce,
							product_id: $( '#post_ID' ).val(),
						};

						$spinner.css( 'visibility', 'visible' );

						$.post( wc_square_admin_products.ajax_url, data, ( response ) => {
							if ( response && response.success ) {
								const quantity = response.data.quantity;
								const manageStock = response.data.manage_stock;

								if ( ! manageStock ) {
									$manageInput.prop( 'checked', false ).trigger( 'change' );
									$( '#_stock_status' ).val( 'instock' );
									$manageDesc.html(
										'<a href="' + wc_square_admin_products.settings_url + '">' + wc_square_admin_products.i18n.inventory_tracking_disabled + '</a>'
									);
								}
								$stockInput.val( quantity );
								$stockFields.find( 'input[name=_original_stock]' ).val( quantity );
								$stockInput.prop( 'readonly', false );
								$( 'p._stock_field span.description' ).remove();
							} else {
								if ( response.data ) {
									$( '.inventory-fetch-error' ).remove();
									$spinner.after( '<span class="inventory-fetch-error" style="display:inline-block;color:red;">' + response.data + '</span>' );
								}

								$spinner.css( 'visibility', 'hidden' );
							}
						} );
					} );

				// Square SoR.
				} else if ( wc_square_admin_products.is_square_sor ) {
						// add inline note explaining stock is managed by Square (sanity check to avoid appending multiple times)
						if ($('p._stock_field span.description').length === 0) {
							$stockInput.after(
								'<span class="description" style="display:block;clear:both;">' +
									wc_square_admin_products.i18n
										.managed_by_square +
									' (<a href="#" class="sync-stock-from-square" data-product-id="' +
									$('#post_ID').val() +
									'">' +
									wc_square_admin_products.i18n
										.sync_stock_from_square +
									'</a>)<div class="sync-stock-spinner spinner" style="float:none;"></div></span>'
							);
						}
				}
			} else {
				useSquare = false;

				// remove any inline note to WooCommerce core stock fields that may have been added when Synced with Square is enabled.
				$( 'p._stock_field span.description' ).remove();
				$stockInput.prop( 'readonly', false );
				$manageDesc.html( manageDescOriginal );
				$manageInput.off( 'click' );
				$manageInput.css( { opacity: 1 } );
				$manageInput.prop( 'checked', manageStockOriginal );

				if ( ! variableProduct ) {
					if ( manageStockOriginal ) {
						$stockFields.show();
						$stockStatus.hide();
					} else {
						$stockStatus.show();
						$stockFields.hide();
					}
				}
			}

			// handle variations data separately (HTML differs from parent UI!).
			$( '.woocommerce_variation' ).each( ( index, e ) => {
				// fetch relevant variables for each variation.
				const variationID = $( e ).find( 'h3 input.variable_post_id' ).val();
				const $variationManageInput = $( e ).find( '.variable_manage_stock' );
				const $variationManageField = $variationManageInput.parent();
				const $variationStockInput = $( e ).find( '.wc_input_stock' );
				const $variationStockField = $variationStockInput.parent();

				// Square manages variations stock
				if ( useSquare ) {
					// disable stock management inputs
					$variationStockInput.prop( 'readonly', true );
					$variationManageInput.on('click', () => {
						return false;
					});
					$variationManageInput.css( { opacity: '0.5' } );

					// add a note that the variation stock is managed by square, but check if it wasn't added already to avoid duplicates.
					if ( 0 === $variationManageField.find( '.description' ).length ) {
							let syncInventory = '';
							if (!$variationManageInput.is(':checked')) {
								syncInventory =
									' - <a href="#" class="sync-stock-from-square" data-product-id="' +
									variationID +
									'">' +
									wc_square_admin_products.i18n
										.sync_inventory +
									'</a><div class="sync-stock-spinner spinner" style="float:none;"></div>';
							}
							$variationManageInput.after(
								'(<span class="description">' +
									wc_square_admin_products.i18n
										.managed_by_square +
									'</span>)' +
									syncInventory
							);
					}

					if ( wc_square_admin_products.is_woocommerce_sor ) {
						const fetchVariationStockActionID = 'fetch-stock-with-square-' + variationID;

						// add inline note with a toggle to fetch stock from Square manually via AJAX (sanity check to avoid appending multiple times)
						if ( 0 === $variationStockField.find( 'span.description' ).length ) {
							$variationStockInput.after(
								'<span class="description" style="display:block;clear:both;"><a href="#" id="' + fetchVariationStockActionID + '">' + wc_square_admin_products.i18n.fetch_stock_with_square + '</a><div class="spinner" style="float:none;"></div></span>'
							);
						}

						// listen for requests to update stock with Square for the individual variation.
						$( '#' + fetchVariationStockActionID ).on( 'click', ( e ) => {
							e.preventDefault();
							const $spinner = $( e.target ).next( '.spinner' );
							const data = {
								action: 'wc_square_fetch_product_stock_with_square',
								security: wc_square_admin_products.fetch_product_stock_with_square_nonce,
								product_id: variationID,
							};

							$spinner.css( 'visibility', 'visible' );

							$.post( wc_square_admin_products.ajax_url, data, ( response ) => {
								if ( response && response.success ) {
									const quantity = response.data.quantity;
									const manageStock = response.data.manage_stock;

									if ( ! manageStock ) {
										$variationManageInput.prop( 'checked', false ).trigger( 'change' );
									}

									$variationStockInput.val( quantity );
									$variationStockField.parent().find( 'input[name^="variable_original_stock"]' ).val( quantity );
									$variationStockInput.prop( 'readonly', false );
									$variationStockField.find( '.description' ).remove();
								} else {
									if ( response.data ) {
										$( '.inventory-fetch-error' ).remove();
										$spinner.after( '<span class="inventory-fetch-error" style="display:inline-block;color:red;">' + response.data + '</span>' );
									}

									$spinner.css( 'visibility', 'hidden' );
								}
							} );
						} );
					} else if (wc_square_admin_products.is_square_sor) {
							if (
								$variationStockField.find('span.description')
									.length === 0
							) {
								$variationStockInput.after(
									'<span class="description" style="display:block;clear:both;"><a href="#" class="sync-stock-from-square" data-product-id="' +
										variationID +
										'">' +
										wc_square_admin_products.i18n
											.sync_stock_from_square +
										'</a><div class="sync-stock-spinner spinner" style="float:none;"></span>'
								);
							}
					}
				} else {
					// restore WooCommerce stock when user chooses to disable Sync with Square checkbox.
					$variationStockInput.prop( 'readonly', false );
					$variationManageInput.off( 'click' );
					$variationManageInput.css( { opacity: 1 } );
					$variationManageInput.next( '.description' ).remove();
				}
			} );
		// initial page load handling.
		} ).trigger( 'change' );

		// trigger an update if the product type changes.
		$( '#product-type' ).on( 'change', ( e ) => {
			if ( 'complete' === document.readyState ) {
				triggerUpdate();
			}
			toggleSyncProductMeta( $( e.target ).val() );
		} ).trigger( 'change' );

		// Sync stock from the Square.
		$('#woocommerce-product-data').on(
			'click',
			'.sync-stock-from-square',
			(event) => {
				event.preventDefault();
				const productId = $(event.target).data('product-id');
				const $spinner = $('.sync-stock-spinner.spinner');
				const data = {
					action: 'wc_square_fetch_product_stock_with_square',
					security:
						wc_square_admin_products.fetch_product_stock_with_square_nonce,
					product_id: productId,
				};

				$spinner.css('visibility', 'visible');

				$.post(wc_square_admin_products.ajax_url, data, () => {
					$spinner.css('visibility', 'hidden');
					window.location.reload();
				});
			}
		);

		$( '#product-type, #_square_gift_card' ).on( 'change', function() {
			const productType = $( '#product-type' ).val();
			const squareGiftCardCheckbox = $( '#_square_gift_card' );
			const isGiftCard = squareGiftCardCheckbox.prop( 'checked' );
			let displayToggleFields = [
				$( '.wc-square-sync-with-square' ),
				$( '.inventory_sold_individually' ),
				$( 'label[for="_virtual"]' ),
				$( 'label[for="_downloadable"]' ),
				$( '._tax_status_field' ).closest( '.options_group' ),
			];

			if ( 'variable' === productType ) {
				displayToggleFields = [
					...displayToggleFields,
					$( '.variable_is_virtual' ).closest( '.tips' ),
					$( '.variable_is_downloadable' ).closest( '.tips' ),
				];
			}

			if ( isGiftCard ) {
				if ( ! $( '#_virtual' ).prop( 'checked' ) ) {
					$( '#_virtual' ).trigger( 'click' ).prop( 'checked', true );
				}

				$( '.variable_is_virtual' ).each( ( index, virtualVariationEl ) => {
					if ( ! $( virtualVariationEl ).prop( 'checked' ) ) {
						$( virtualVariationEl ).trigger( 'click' ).prop( 'checked', true );
					}
				} );
				displayToggleFields.forEach( object => object.hide() );
			} else {
				displayToggleFields.forEach( object => object.show() );

				if ( 'variable' === productType ) {
					$( 'label[for="_virtual"]' ).hide();
					$( 'label[for="_downloadable"]' ).hide();
				}
			}
		} ).trigger( 'change' );

		let observer = null;

		observeVariations();

		// trigger an update for variable products when variations are loaded, added, or removed.
		$( '#woocommerce-product-data' ).on( 'woocommerce_variations_loaded woocommerce_variations_added woocommerce_variations_removed', () => {
			observeVariations();
			triggerUpdate();
		} );

		/**
		 * Hides unnecessary meta fields when Variatble product is set as Gift Card.
		 * @returns void
		 */
		function observeVariations() {
			let variationsContainer = document.querySelector( '.woocommerce_variations' );

			if ( observer || ! variationsContainer ) {
				return;
			}

			observer = new MutationObserver( () => {
				$( '#_square_gift_card' ).trigger( 'change' );
			} );

			if ( observer ) {
				// Start observing the woocommerce variations list.
				observer.observe(
					variationsContainer,
					{
						attributes: false,
						childList: true,
						subtree: false
					}
				);
			}
		}
	}
} );
