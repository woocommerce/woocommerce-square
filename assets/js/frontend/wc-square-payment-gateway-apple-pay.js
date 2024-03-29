/*
 Square Apple Pay Handler
 */

( function () {
	var bind = function ( fn, me ) {
			return function () {
				return fn.apply( me, arguments );
			};
		},
		extend = function ( child, parent ) {
			for ( const key in parent ) {
				if ( hasProp.call( parent, key ) ) child[ key ] = parent[ key ];
			}
			function ctor() {
				this.constructor = child;
			}
			ctor.prototype = parent.prototype;
			child.prototype = new ctor();
			child.__super__ = parent.prototype;
			return child;
		},
		hasProp = {}.hasOwnProperty;

	jQuery( document ).ready( function ( $ ) {
		'use strict';
		window.SV_WC_Apple_Pay_Handler = ( function () {
			function SV_WC_Apple_Pay_Handler( args ) {
				this.get_payment_request = bind(
					this.get_payment_request,
					this
				);
				this.reset_payment_request = bind(
					this.reset_payment_request,
					this
				);
				this.attach_update_events = bind(
					this.attach_update_events,
					this
				);
				this.on_cancel_payment = bind( this.on_cancel_payment, this );
				this.process_authorization = bind(
					this.process_authorization,
					this
				);
				this.on_payment_authorized = bind(
					this.on_payment_authorized,
					this
				);
				this.on_shipping_method_selected = bind(
					this.on_shipping_method_selected,
					this
				);
				this.on_shipping_contact_selected = bind(
					this.on_shipping_contact_selected,
					this
				);
				this.on_payment_method_selected = bind(
					this.on_payment_method_selected,
					this
				);
				this.validate_merchant = bind( this.validate_merchant, this );
				this.on_validate_merchant = bind(
					this.on_validate_merchant,
					this
				);
				this.params = sv_wc_apple_pay_params;
				this.payment_request = args.payment_request;
				this.buttons = '.sv-wc-apple-pay-button';
				if ( this.is_available() ) {
					if ( this.payment_request ) {
						$( this.buttons ).show();
					}
					this.init();
					this.attach_update_events();
				}
			}

			SV_WC_Apple_Pay_Handler.prototype.is_available = function () {
				if ( ! window.ApplePaySession ) {
					return false;
				}
				return ApplePaySession.canMakePaymentsWithActiveCard(
					this.params.merchant_id
				).then(
					( function ( _this ) {
						return function ( canMakePayments ) {
							return canMakePayments;
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.init = function () {
				return $( document.body ).on(
					'click',
					'.sv-wc-apple-pay-button',
					( function ( _this ) {
						return function ( e ) {
							let error;
							e.preventDefault();
							_this.block_ui();
							try {
								_this.session = new ApplePaySession(
									1,
									_this.payment_request
								);
								_this.session.onvalidatemerchant = function (
									event
								) {
									return _this.on_validate_merchant( event );
								};
								_this.session.onpaymentmethodselected = function (
									event
								) {
									return _this.on_payment_method_selected(
										event
									);
								};
								_this.session.onshippingcontactselected = function (
									event
								) {
									return _this.on_shipping_contact_selected(
										event
									);
								};
								_this.session.onshippingmethodselected = function (
									event
								) {
									return _this.on_shipping_method_selected(
										event
									);
								};
								_this.session.onpaymentauthorized = function (
									event
								) {
									return _this.on_payment_authorized( event );
								};
								_this.session.oncancel = function ( event ) {
									return _this.on_cancel_payment( event );
								};
								return _this.session.begin();
							} catch ( _error ) {
								error = _error;
								return _this.fail_payment( error );
							}
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_validate_merchant = function (
				event
			) {
				return this.validate_merchant( event.validationURL ).then(
					( function ( _this ) {
						return function ( merchant_session ) {
							merchant_session = $.parseJSON( merchant_session );
							return _this.session.completeMerchantValidation(
								merchant_session
							);
						};
					} )( this ),
					( function ( _this ) {
						return function ( response ) {
							_this.session.abort();
							return _this.fail_payment(
								'Merchant could no be validated. ' +
									response.message
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.validate_merchant = function (
				url
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let data;
							data = {
								action: 'sv_wc_apple_pay_validate_merchant',
								nonce: _this.params.validate_nonce,
								merchant_id: _this.params.merchant_id,
								url,
							};
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										return resolve( response.data );
									}
									return reject( response.data );
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_payment_method_selected = function (
				event
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let data;
							data = {
								action: 'sv_wc_apple_pay_recalculate_totals',
								nonce: _this.params.recalculate_totals_nonce,
							};
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										data = response.data;
										return resolve(
											_this.session.completePaymentMethodSelection(
												data.total,
												data.line_items
											)
										);
									}
									console.error(
										'[Apple Pay] Error selecting a shipping contact. ' +
											response.data.message
									);
									return reject(
										_this.session.completePaymentMethodSelection(
											_this.payment_request.total,
											_this.payment_request.lineItems
										)
									);
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_shipping_contact_selected = function (
				event
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let data;
							data = {
								action: 'sv_wc_apple_pay_recalculate_totals',
								nonce: _this.params.recalculate_totals_nonce,
								contact: event.shippingContact,
							};
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										data = response.data;
										return resolve(
											_this.session.completeShippingContactSelection(
												ApplePaySession.STATUS_SUCCESS,
												data.shipping_methods,
												data.total,
												data.line_items
											)
										);
									}
									console.error(
										'[Apple Pay] Error selecting a shipping contact. ' +
											response.data.message
									);
									return reject(
										_this.session.completeShippingContactSelection(
											ApplePaySession.STATUS_FAILURE,
											[],
											_this.payment_request.total,
											_this.payment_request.lineItems
										)
									);
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_shipping_method_selected = function (
				event
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let data;
							data = {
								action: 'sv_wc_apple_pay_recalculate_totals',
								nonce: _this.params.recalculate_totals_nonce,
								method: event.shippingMethod.identifier,
							};
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										data = response.data;
										return resolve(
											_this.session.completeShippingMethodSelection(
												ApplePaySession.STATUS_SUCCESS,
												data.total,
												data.line_items
											)
										);
									}
									console.error(
										'[Apple Pay] Error selecting a shipping method. ' +
											response.data.message
									);
									return reject(
										_this.session.completeShippingMethodSelection(
											ApplePaySession.STATUS_FAILURE,
											_this.payment_request.total,
											_this.payment_request.lineItems
										)
									);
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_payment_authorized = function (
				event
			) {
				return this.process_authorization( event.payment ).then(
					( function ( _this ) {
						return function ( response ) {
							_this.set_payment_status( true );
							return _this.complete_purchase( response );
						};
					} )( this ),
					( function ( _this ) {
						return function ( response ) {
							_this.set_payment_status( false );
							return _this.fail_payment(
								'Payment could no be processed. ' +
									response.message
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.process_authorization = function (
				payment
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let data;
							data = {
								action: 'sv_wc_apple_pay_process_payment',
								nonce: _this.params.process_nonce,
								type: _this.type,
								payment: JSON.stringify( payment ),
							};
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										return resolve( response.data );
									}
									return reject( response.data );
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.on_cancel_payment = function (
				event
			) {
				return this.unblock_ui();
			};

			SV_WC_Apple_Pay_Handler.prototype.complete_purchase = function (
				response
			) {
				return ( window.location = response.redirect );
			};

			SV_WC_Apple_Pay_Handler.prototype.fail_payment = function (
				error
			) {
				console.error( '[Apple Pay] ' + error );
				this.unblock_ui();
				return this.render_errors( [ this.params.generic_error ] );
			};

			SV_WC_Apple_Pay_Handler.prototype.set_payment_status = function (
				success
			) {
				let status;
				if ( success ) {
					status = ApplePaySession.STATUS_SUCCESS;
				} else {
					status = ApplePaySession.STATUS_FAILURE;
				}
				return this.session.completePayment( status );
			};

			SV_WC_Apple_Pay_Handler.prototype.attach_update_events = function () {};

			SV_WC_Apple_Pay_Handler.prototype.reset_payment_request = function (
				data
			) {
				if ( data == null ) {
					data = {};
				}
				this.block_ui();
				return this.get_payment_request( data ).then(
					( function ( _this ) {
						return function ( response ) {
							$( _this.buttons ).show();
							_this.payment_request = $.parseJSON( response );
							return _this.unblock_ui();
						};
					} )( this ),
					( function ( _this ) {
						return function ( response ) {
							console.error(
								'[Apple Pay] Could not build payment request. ' +
									response.message
							);
							$( _this.buttons ).hide();
							return _this.unblock_ui();
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.get_payment_request = function (
				data
			) {
				return new Promise(
					( function ( _this ) {
						return function ( resolve, reject ) {
							let base_data;
							base_data = {
								action: 'sv_wc_apple_pay_get_payment_request',
								type: _this.type,
							};
							$.extend( data, base_data );
							return $.post(
								_this.params.ajax_url,
								data,
								function ( response ) {
									if ( response.success ) {
										return resolve( response.data );
									}
									return reject( response.data );
								}
							);
						};
					} )( this )
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.render_errors = function (
				errors
			) {
				$( '.woocommerce-error, .woocommerce-message' ).remove();
				this.ui_element.prepend(
					'<ul class="woocommerce-error"><li>' +
						errors.join( '</li><li>' ) +
						'</li></ul>'
				);
				this.ui_element.removeClass( 'processing' ).unblock();
				return $( 'html, body' ).animate(
					{
						scrollTop: this.ui_element.offset().top - 100,
					},
					1000
				);
			};

			SV_WC_Apple_Pay_Handler.prototype.block_ui = function () {
				return this.ui_element.block( {
					message: null,
					overlayCSS: {
						background: '#fff',
						opacity: 0.6,
					},
				} );
			};

			SV_WC_Apple_Pay_Handler.prototype.unblock_ui = function () {
				return this.ui_element.unblock();
			};

			return SV_WC_Apple_Pay_Handler;
		} )();
		window.Square_Apple_Pay_Cart_Handler = ( function ( superClass ) {
			extend( Square_Apple_Pay_Cart_Handler, superClass );

			function Square_Apple_Pay_Cart_Handler( args ) {
				this.attach_update_events = bind(
					this.attach_update_events,
					this
				);
				this.type = 'cart';
				this.ui_element = $( 'form.woocommerce-cart-form' ).parents(
					'div.woocommerce'
				);
				Square_Apple_Pay_Cart_Handler.__super__.constructor.call(
					this,
					args
				);
			}

			Square_Apple_Pay_Cart_Handler.prototype.attach_update_events = function () {
				return $( document.body ).on(
					'updated_cart_totals',
					( function ( _this ) {
						return function () {
							return _this.reset_payment_request();
						};
					} )( this )
				);
			};

			return Square_Apple_Pay_Cart_Handler;
		} )( SV_WC_Apple_Pay_Handler );
		window.Square_Apple_Pay_Checkout_Handler = ( function ( superClass ) {
			extend( Square_Apple_Pay_Checkout_Handler, superClass );

			function Square_Apple_Pay_Checkout_Handler( args ) {
				this.attach_update_events = bind(
					this.attach_update_events,
					this
				);
				this.type = 'checkout';
				this.ui_element = $( 'form.woocommerce-checkout' );
				Square_Apple_Pay_Checkout_Handler.__super__.constructor.call(
					this,
					args
				);
				this.buttons = '.sv-wc-apply-pay-checkout';
			}

			Square_Apple_Pay_Checkout_Handler.prototype.attach_update_events = function () {
				return $( document.body ).on(
					'updated_checkout',
					( function ( _this ) {
						return function () {
							return _this.reset_payment_request();
						};
					} )( this )
				);
			};

			return Square_Apple_Pay_Checkout_Handler;
		} )( SV_WC_Apple_Pay_Handler );
		return ( window.Square_Apple_Pay_Product_Handler = ( function (
			superClass
		) {
			extend( Square_Apple_Pay_Product_Handler, superClass );

			function Square_Apple_Pay_Product_Handler( args ) {
				this.type = 'product';
				this.ui_element = $( 'form.cart' );
				Square_Apple_Pay_Product_Handler.__super__.constructor.call(
					this,
					args
				);
			}

			return Square_Apple_Pay_Product_Handler;
		} )( SV_WC_Apple_Pay_Handler ) );
	} );
}.call( this ) );

//# sourceMappingURL=sv-wc-payment-gateway-apple-pay.min.js.map
