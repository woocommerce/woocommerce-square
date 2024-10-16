import '../../css/frontend/wc-square-payment-gateway-my-payment-methods.scss';

/*
 Square Payment Gateway My Payment Methods
 */

( function () {
	const bind = function ( fn, me ) {
		return function () {
			return fn.apply( me, arguments );
		};
	};

	jQuery( document ).ready( function ( $ ) {
		'use strict';
		return ( window.Square_Payment_Methods_Handler = ( function () {
			function Square_Payment_Methods_Handler( args ) {
				this.cancel_edit = bind( this.cancel_edit, this );
				this.save_method = bind( this.save_method, this );
				this.edit_method = bind( this.edit_method, this );
				this.id = args.id;
				this.slug = args.slug;
				this.i18n = args.i18n;
				this.ajax_url = args.ajax_url;
				this.ajax_nonce = args.ajax_nonce;
				if ( ! args.has_core_tokens ) {
					$( '.wc-' + this.slug + '-my-payment-methods' )
						.prev(
							'.woocommerce-Message.woocommerce-Message--info'
						)
						.hide();
				}
				$(
					'.wc-' + this.slug + '-payment-method-actions .button.tip'
				).tipTip();
				$( '.wc-' + this.slug + '-my-payment-methods' ).on(
					'click',
					'.wc-' +
						this.slug +
						'-payment-method-actions .edit-payment-method',
					( function ( _this ) {
						return function ( event ) {
							return _this.edit_method( event );
						};
					} )( this )
				);
				$( '.wc-' + this.slug + '-my-payment-methods' ).on(
					'click',
					'.wc-' +
						this.slug +
						'-payment-method-actions .save-payment-method',
					( function ( _this ) {
						return function ( event ) {
							return _this.save_method( event );
						};
					} )( this )
				);
				$( '.wc-' + this.slug + '-my-payment-methods' ).on(
					'click',
					'.wc-' +
						this.slug +
						'-payment-method-actions .cancel-edit-payment-method',
					( function ( _this ) {
						return function ( event ) {
							return _this.cancel_edit( event );
						};
					} )( this )
				);
				$( '.wc-' + this.slug + '-my-payment-methods' ).on(
					'click',
					'.wc-' +
						this.slug +
						'-payment-method-actions .delete-payment-method',
					( function ( _this ) {
						return function ( event ) {
							if (
								$( event.currentTarget ).hasClass(
									'disabled'
								) ||
								! confirm( _this.i18n.delete_ays )
							) {
								return event.preventDefault();
							}
						};
					} )( this )
				);
				$( '.button[href*="add-payment-method"]' ).click( function (
					event
				) {
					if ( $( this ).hasClass( 'disabled' ) ) {
						return event.preventDefault();
					}
				} );
			}

			Square_Payment_Methods_Handler.prototype.edit_method = function (
				event
			) {
				let button, row;
				event.preventDefault();
				button = $( event.currentTarget );
				row = button.parents( 'tr' );
				row.find( '.view' ).hide();
				row.find( '.edit' ).show();
				row.addClass( 'editing' );
				button
					.text( this.i18n.cancel_button )
					.removeClass( 'edit-payment-method' )
					.addClass( 'cancel-edit-payment-method' )
					.removeClass( 'button' );
				button.siblings( '.save-payment-method' ).show();
				button.siblings( '.delete-payment-method' ).hide();
				return this.enable_editing_ui();
			};

			Square_Payment_Methods_Handler.prototype.save_method = function (
				event
			) {
				let button, data, row;
				event.preventDefault();
				button = $( event.currentTarget );
				row = button.parents( 'tr' );
				this.block_ui();
				row.next( '.error' ).remove();
				data = {
					action: 'wc_' + this.id + '_save_payment_method',
					nonce: this.ajax_nonce,
					token_id: row.data( 'token-id' ),
					data: row.find( 'input[name]' ).serialize(),
				};
				return $.post( this.ajax_url, data )
					.done(
						( function ( _this ) {
							return function ( response ) {
								if ( ! response.success ) {
									return _this.display_error(
										row,
										response.data
									);
								}
								if ( response.data.is_default ) {
									row.siblings()
										.find(
											'.wc-' +
												_this.slug +
												'-payment-method-default .view'
										)
										.empty()
										.siblings( '.edit' )
										.find( 'input' )
										.prop( 'checked', false );
								}
								if ( response.data.html != null ) {
									row.replaceWith( response.data.html );
								}
								if ( response.data.nonce != null ) {
									_this.ajax_nonce = response.data.nonce;
								}
								return _this.disable_editing_ui();
							};
						} )( this )
					)
					.fail(
						( function ( _this ) {
							return function ( jqXHR, textStatus, error ) {
								return _this.display_error( row, error );
							};
						} )( this )
					)
					.always(
						( function ( _this ) {
							return function () {
								return _this.unblock_ui();
							};
						} )( this )
					);
			};

			Square_Payment_Methods_Handler.prototype.cancel_edit = function (
				event
			) {
				let button, row;
				event.preventDefault();
				button = $( event.currentTarget );
				row = button.parents( 'tr' );
				row.find( '.view' ).show();
				row.find( '.edit' ).hide();
				row.removeClass( 'editing' );
				button
					.removeClass( 'cancel-edit-payment-method' )
					.addClass( 'edit-payment-method' )
					.text( this.i18n.edit_button )
					.addClass( 'button' );
				button.siblings( '.save-payment-method' ).hide();
				button.siblings( '.delete-payment-method' ).show();
				return this.disable_editing_ui();
			};

			Square_Payment_Methods_Handler.prototype.enable_editing_ui = function () {
				$( '.wc-' + this.slug + '-my-payment-methods' ).addClass(
					'editing'
				);
				return $( '.button[href*="add-payment-method"]' ).addClass(
					'disabled'
				);
			};

			Square_Payment_Methods_Handler.prototype.disable_editing_ui = function () {
				$( '.wc-' + this.slug + '-my-payment-methods' ).removeClass(
					'editing'
				);
				return $( '.button[href*="add-payment-method"]' ).removeClass(
					'disabled'
				);
			};

			Square_Payment_Methods_Handler.prototype.block_ui = function () {
				return $( '.wc-' + this.slug + '-my-payment-methods' )
					.parent( 'div' )
					.block( {
						message: null,
						overlayCSS: {
							background: '#fff',
							opacity: 0.6,
						},
					} );
			};

			Square_Payment_Methods_Handler.prototype.unblock_ui = function () {
				return $( '.wc-' + this.slug + '-my-payment-methods' )
					.parent( 'div' )
					.unblock();
			};

			Square_Payment_Methods_Handler.prototype.display_error = function (
				row,
				error,
				message
			) {
				let columns;
				if ( message == null ) {
					message = '';
				}
				console.error( error );
				if ( ! message ) {
					message = this.i18n.save_error;
				}
				columns = $(
					'.wc-' + this.slug + '-my-payment-methods thead tr th'
				).size();
				return $(
					'<tr class="error"><td colspan="' +
						columns +
						'">' +
						message +
						'</td></tr>'
				)
					.insertAfter( row )
					.find( 'td' )
					.delay( 8000 )
					.slideUp( 200 );
			};

			return Square_Payment_Methods_Handler;
		} )() );
	} );
}.call( this ) );

//# sourceMappingURL=sv-wc-payment-gateway-my-payment-methods.min.js.map
