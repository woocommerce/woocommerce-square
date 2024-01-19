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
	class WC_Square_Cash_App_Handler {
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
			this.cashAppButton = '#wc-square-cash-app';
			this.checkoutForm = $( 'form.checkout, #wc-cash-app-payment-form' );
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

			if ( this.settingUp ) {
				return;
			}
			
			this.settingUp = true;
			this.block_ui();
			this.get_payment_request().then(
				( response ) => {
					this.payment_request = JSON.parse( response );
					this.total_amount = this.payment_request.total.amount;
					this.load_cash_app_form();
				},
				( message ) => {
					this.log( '[Square Cash App] Could not build payment request. ' + message, 'error' );
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

			// $( document ).on( 'payment_method_selected', () => {
			// 	if ( ! this.isPayForOrderPage ) {
			// 		return;
			// 	}

			// 	 $( '#payment_method_override' ).remove();
			// } );
		}

		/**
		 * Load the Cash App payment form
		 *
		 * @since x.x.x
		 */
		load_cash_app_form() {
			if ( this.cashAppPay ) {
				this.cashAppPay.destroy();
				this.cashAppLoaded = false;
			}

			if ( this.cashAppLoaded ) {
				return;
			}

			this.cashAppLoaded = true;

			this.log( '[Square] Building Cash App Pay' );
			const { applicationId, locationId } = this.get_form_params();
			this.payments = window.Square.payments( applicationId, locationId );
			this.initializeCashAppPay();
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

			this.cashAppPay = await this.payments.cashAppPay( paymentRequest, {
				redirectURL: window.location.href,
				referenceId: '123' // TODO: Add a reference ID.
			});

			this.cashAppPay.attach( '#wc-square-cash-app', this.buttonStyles);

			this.cashAppPay.addEventListener('ontokenization', (event) => {
				this.handleCashAppPaymentResponse( event );
			});

			/**
			 * Display the button after successful initialize of Cash App Pay.
			 */
			if ( this.cashAppPay ) {
				$( this.cashAppButton ).show();
			}
		}

		async handleCashAppPaymentResponse( event ) {
			this.blockedForm = this.blockForms( this.checkoutForm );

			const { tokenResult, error } = event.detail;
			if ( error ) {
				this.render_errors( [error.message] );
			} else if ( tokenResult.status === 'OK' ) {
				const nonce = tokenResult.token;
				if ( ! nonce ) {
					return this.render_errors( this.args.general_error );
				}
				$( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val( nonce );
				$( '#payment_method_override' ).remove();
				$( '#payment' ).after( `<input id="payment_method_override" type="hidden" name="payment_method" value="${this.args.gateway_id}" />` );

				// Submit the form.
				const checkoutForm = $( 'form.checkout' );
				checkoutForm.submit();
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
		 * Block the Apple Pay and Google Pay buttons from being clicked which processing certain actions
		 *
		 * @since x.x.x
		 */
		block_ui() {
			$( this.buttons ).block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
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
	}

	window.WC_Square_Cash_App_Handler = WC_Square_Cash_App_Handler;
} );
