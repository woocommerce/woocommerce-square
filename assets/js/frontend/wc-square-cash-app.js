/* global wc_add_to_cart_variation_params */

/**
 * Square Cash App Pay Handler class.
 *
 * @since x.x.x
 */
jQuery( document ).ready( ( $ ) => {
	/**
	 * Square Cash App Pay Handler class.
	 *
	 * @since x.x.x
	 */
	class WC_Square_Cash_App_Pay_Handler {
		/**
		 * Setup handler
		 *
		 * @param {Array} args
		 * @since x.x.x
		 */
		constructor( args ) {
			if ( false === args.payment_request ) {
				return;
			}

			this.args = args;
			this.payment_request = args.payment_request;
			this.total_amount = args.payment_request.total.amount;
			this.isPayForOrderPage = args.is_pay_for_order_page;
			this.orderId = args.order_id;
			this.id_dasherized = args.gateway_id_dasherized;
			this.buttonStyles  = args.button_styles;
			this.referenceId = this.reference_id;
			this.cashAppButton = '#wc-square-cash-app';
			this.checkoutForm = $( 'form.checkout, #wc-square-cash-app-pay-cash_app_pay-form' );
			this.settingUp = false;

			if ( $( this.cashAppButton ).length === 0 ) {
				return;
			}

			this.build_cash_app();
			this.attach_page_events();
		}

		/**
		 * Fetch a new payment request object and reload the Square Payments
		 *
		 * @since x.x.x
		 */
		build_cash_app() {
			// if we are already setting up, bail.
			if ( this.settingUp ) {
				return;
			}

			this.settingUp = true;
			this.block_ui();
			return this.get_payment_request().then(
				( response ) => {
					const oldPaymentRequest = JSON.stringify( this.payment_request );
					this.payment_request = JSON.parse( response );
					this.total_amount = this.payment_request.total.amount;

					// If we have a nonce, loaded button and no updated payment request, bail.
					if (
						this.has_payment_nonce() &&
						$( '#wc-square-cash-app #cash_app_pay_v1_element' ).length &&
						JSON.stringify( this.payment_request ) === oldPaymentRequest
					) {
						this.settingUp = false;
						this.unblock_ui();
						return;
					}
					$( this.cashAppButton ).hide();
					// Clear the nonce.
					$( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val( '' );
					return this.load_cash_app_form();
				},
				( message ) => {
					this.log( '[Square Cash App Pay] Could not build payment request. ' + message, 'error' );
					$( this.cashAppButton ).hide();
					this.settingUp = false;
				}
			);
		}

		/**
		 * Add page event listeners
		 *
		 * @since x.x.x
		 */
		attach_page_events() {
			$( document.body ).on( 'updated_checkout', () => this.build_cash_app() );
			$( document.body ).on( 'click', 'input[name="payment_method"]', () => this.toggle_order_button() );
			// WC 3.4.3+ trigger
			$( document.body ).on( 'payment_method_selected', () => this.toggle_order_button() );
		}

		/**
		 * Load the Cash App payment form
		 *
		 * @since x.x.x
		 */
		async load_cash_app_form() {
			this.log( '[Square Cash App Pay] Building Cash App Pay' );
			const { applicationId, locationId } = this.get_form_params();
			this.payments = window.Square.payments( applicationId, locationId );
			await this.initializeCashAppPay();
			this.unblock_ui();
			this.settingUp = false;
		}

		/**
		 * Initializes the Cash App Pay payment methods.
		 *
		 * @returns void
		 */
		async initializeCashAppPay() {
			if ( ! this.payments ) {
				return;
			}

			/**
			 * Create a payment request.
			 */
			const paymentRequest = this.payments.paymentRequest( this.create_payment_request() );

			// Destroy the existing Cash App Pay instance.
			if ( this.cashAppPay ) {
				await this.cashAppPay.destroy();
			}

			this.cashAppPay = await this.payments.cashAppPay( paymentRequest, {
				redirectURL: window.location.href,
				referenceId: this.referenceId,
			});

			await this.cashAppPay.attach( '#wc-square-cash-app', this.buttonStyles );
			
			this.cashAppPay.addEventListener('ontokenization', (event) => this.handleCashAppPaymentResponse( event ) );

			// Toggle the place order button.
			this.toggle_order_button();

			/**
			 * Display the button after successful initialize of Cash App Pay.
			 */
			if ( this.cashAppPay ) {
				$( this.cashAppButton ).show();
			}
		}

		/**
		 * Handles the Cash App payment response.
		 * 
		 * @param {Object} event The event object.
		 * @returns void
		 */
		handleCashAppPaymentResponse( event ) {
			this.blockedForm = this.blockForms( this.checkoutForm );

			const { tokenResult, error } = event.detail;
			if ( error ) {
				this.render_errors( [error.message] );
			} else if ( tokenResult.status === 'OK' ) {
				const nonce = tokenResult.token;
				if ( ! nonce ) {
					this.render_errors( this.args.general_error );
				} else {
					$( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val( nonce );
	
					// Submit the form.
					if ( ! $( 'input#payment_method_square_cash_app_pay' ).is( ':checked' ) ) {
						$('input#payment_method_square_cash_app_pay').attr('checked', true);
						$( 'input#payment_method_square_cash_app_pay' ).trigger('click');
					}
	
					this.toggle_order_button();
					if ( this.isPayForOrderPage ) {
						$( 'form#order_review' ).trigger('submit');
					} else {
						$( 'form.checkout' ).trigger('submit');
					}
				}
			}

			// unblock UI
			if ( this.blockedForm ) {
				this.blockedForm.unblock();
			}
		}

		/**
		 * Blocks a form when a payment is under process.
		 *
		 * @param {Object} jQueryFormEl The form jQuery object.
		 * @returns {Object} Returns the input jQuery object.
		 */
		blockForms( jQueryFormEl ) {
			jQueryFormEl.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );

			return jQueryFormEl;
		}

		/**
		 * Gets the Square payment form params.
		 *
		 * @since x.x.x
		 */
		get_form_params() {
			const params = {
				applicationId: this.args.application_id,
				locationId: this.args.location_id,
			};

			return params;
		}

		/**
		 * Sets the a payment request object for the Square Payment Form
		 *
		 * @since x.x.x
		 */
		create_payment_request() {
			return this.payment_request;
		}

		/*
		 * Get the payment request on a product page
		 *
		 * @since x.x.x
		 */
		get_payment_request() {
			return new Promise( ( resolve, reject ) => {
				const data = {
					security: this.args.payment_request_nonce,
					is_pay_for_order_page: this.isPayForOrderPage,
					order_id: this.orderId,
				};

				// retrieve a payment request object.
				$.post( this.get_ajax_url( 'get_payment_request' ), data, ( response ) => {
					if ( response.success ) {
						return resolve( response.data );
					}

					return reject( response.data );
				} );
			} );
		}

		/*
		 * Helper function to return the ajax URL for the given request/action
		 *
		 * @since x.x.x
		 */
		get_ajax_url( request ) {
			return this.args.ajax_url.replace( '%%endpoint%%', 'square_cash_app_' + request );
		}

		/*
		 * Renders errors given the error message HTML
		 *
		 * @since x.x.x
		 */
		render_errors_html( errors_html ) {
			// hide and remove any previous errors.
			$( '.woocommerce-error, .woocommerce-message' ).remove();

			const element = $( 'form[name="checkout"]' );

			// add errors
			element.before( errors_html );

			// unblock UI
			if ( this.blockedForm ) {
				this.blockedForm.unblock();
			}

			// scroll to top
			$( 'html, body' ).animate( {
				scrollTop: element.offset().top - 100,
			}, 1000 );
		}

		/*
		 * Renders errors
		 *
		 * @since x.x.x
		 */
		render_errors( errors ) {
			const error_message_html = '<ul class="woocommerce-error"><li>' + errors.join( '</li><li>' ) + '</li></ul>';
			this.render_errors_html( error_message_html );
		}

		/*
		 * Block the payment buttons being clicked which processing certain actions
		 *
		 * @since x.x.x
		 */
		block_ui() {
			$( '.woocommerce-checkout-payment, #payment' ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
		}

		/*
		 * Unblocks the payment buttons
		 *
		 * @since x.x.x
		 */
		unblock_ui() {
			$( '.woocommerce-checkout-payment, #payment' ).unblock();
		}

		/*
		 * Logs messages to the console when logging is turned on in the settings
		 *
		 * @since x.x.x
		 */
		log( message, type = 'notice' ) {
			// if logging is disabled, bail.
			if ( ! this.args.logging_enabled ) {
				return;
			}

			if ( type === 'error' ) {
				return console.error( message );
			}

			return console.log( message );
		}

		/*
		 * Returns the payment nonce
		 *
		 * @since x.x.x
		 */
		has_payment_nonce() {
			return $( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val();
		}

		/*
		 * Returns the selected payment gateway id
		 *
		 * @since x.x.x
		 */
		get_selected_gateway_id() {
			return $( 'form.checkout, form#order_review' ).find( 'input[name=payment_method]:checked' ).val();
		}

		/*
		 * Toggles the order button
		 *
		 * @since x.x.x
		 */
		toggle_order_button() {
			if ( this.get_selected_gateway_id() === this.args.gateway_id && ! this.has_payment_nonce() ) {
				$( '#place_order' ).hide();
			} else {
				$( '#place_order' ).show();
			}
		}
	}

	window.WC_Square_Cash_App_Pay_Handler = WC_Square_Cash_App_Pay_Handler;
} );