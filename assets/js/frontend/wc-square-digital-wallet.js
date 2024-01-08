/* global wc_add_to_cart_variation_params */

/**
 * Square Credit Card Digital Wallet Handler class.
 *
 * @since 2.3
 */
jQuery( document ).ready( ( $ ) => {
	/**
	 * Square Credit Card Digital Wallet Handler class.
	 *
	 * @since 2.3
	 */
	class WC_Square_Digital_Wallet_Handler {
		/**
		 * Setup handler
		 *
		 * @param {Array} args
		 * @since 2.3
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
			this.cartForm = '.cart';
			this.wallet = '#wc-square-digital-wallet';
			this.buttons = '.wc-square-wallet-buttons';
			this.isGooglePayHidden = this.args.hide_button_options.includes( 'google' );
			this.isApplePayHidden = this.args.hide_button_options.includes( 'apple' );
			this.productPageForm = $( '.product form' );
			this.cartTotalsForms = $( '.cart_totals, .woocommerce-cart-form' );
			this.checkoutForm = $( 'form.checkout, #wc-square-digital-wallet' );

			if ( $( this.wallet ).length === 0 ) {
				return;
			}

			$( this.wallet ).hide();
			$( this.buttons ).hide();

			this.build_digital_wallet();
			this.attach_page_events();
			this.buildDigitalWalletDebounced = this.debounce( this.build_digital_wallet, 500 );
		}

		/**
		 * Fetch a new payment request object and reload the Square Payments
		 *
		 * @since 2.3
		 */
		build_digital_wallet() {
			this.block_ui();
			this.get_payment_request().then(
				( response ) => {
					this.payment_request = JSON.parse( response );
					this.total_amount = this.payment_request.total.amount;
					this.load_square_form();
				},
				( message ) => {
					this.log( '[Square] Could not build payment request. ' + message, 'error' );
					$( this.wallet ).hide();
				}
			);
		}

		/**
		 * Add page event listeners
		 *
		 * @since 2.3
		 */
		attach_page_events() {
			if ( this.args.context === 'product' ) {
				const addToCartButton = $( '.single_add_to_cart_button' );

				$( '#apple-pay-button, #wc-square-google-pay' ).on( 'click', ( e ) => {
					if ( addToCartButton.is( '.disabled' ) || ! this.isProductAddonsFormsValid() ) {
						e.stopImmediatePropagation();

						if ( addToCartButton.is( '.wc-variation-is-unavailable' ) ) {
							window.alert( wc_add_to_cart_variation_params.i18n_unavailable_text );
						} else if ( addToCartButton.is( '.wc-variation-selection-needed' ) ) {
							window.alert( wc_add_to_cart_variation_params.i18n_make_a_selection_text );
						}
						return;
					}

					this.add_to_cart();
				} );

				$( document.body ).on( 'woocommerce_variation_has_changed', () => this.build_digital_wallet() );

				$( '.quantity' ).on( 'input', '.qty', () => this.buildDigitalWalletDebounced() );
			}

			if ( this.args.context === 'cart' ) {
				$( document.body ).on( 'updated_cart_totals', () => this.build_digital_wallet() );
			}

			if ( this.args.context === 'checkout' ) {
				$( document.body ).on( 'updated_checkout', () => this.build_digital_wallet() );
			}

			$( document ).on( 'payment_method_selected', () => {
				if ( ! this.isPayForOrderPage ) {
					return;
				}

				$( '#payment_method_override' ).remove();
			} );
		}

		/**
		 * Load the digital wallet payment form
		 *
		 * @since 2.3
		 */
		load_square_form() {
			if ( this.googlePay ) {
				this.googlePay.destroy();
				this.squareFormLoaded = false;
			}

			if ( this.applePay ) {
				this.applePay.destroy();
				this.squareFormLoaded = false;
			}

			if ( this.squareFormLoaded ) {
				return;
			}

			this.squareFormLoaded = true;

			this.log( '[Square] Building digital wallet payment form' );
			const { applicationId, locationId } = this.get_form_params();
			this.payments = window.Square.payments( applicationId, locationId );
			this.initializeDigitalWalletPaymentMethods();
		}

		/**
		 * Initializes the Google Pay and Apple Pay payment methods.
		 *
		 * @returns void
		 */
		async initializeDigitalWalletPaymentMethods() {
			if ( ! this.payments ) {
				return;
			}

			/**
			 * Create a payment request.
			 */
			const paymentRequest = this.payments.paymentRequest( this.create_payment_request() );

			/**
			 * Register shipping event handlers for Google Pay and Apple Pay.
			 */
			this.registerDigitalWalletShippingEventHandlers( paymentRequest );

			/**
			 * Conditionally display Google Pay button based on settings.
			 */
			if ( ! this.isGooglePayHidden ) {
				this.googlePay = await this.payments.googlePay( paymentRequest );

				/**
				 * Create a Google Pay button in the target element.
				 */
				this.googlePay.attach( '#wc-square-google-pay', {
					buttonSizeMode: 'fill',
					buttonType: 'long',
					buttonColor: this.args.google_pay_color,
				} );

				/**
				 * Click event handler for when the Google Pay button is clicked.
				 */
				$( '#wc-square-google-pay' ).on( 'click', async ( e ) => this.handleGooglePayPaymentMethodSubmission( e, this.googlePay ) );
				$( '#wc-square-google-pay' ).show();
			}

			/**
			 * Conditionally display Apple Pay button based on settings.
			 */
			if ( ! this.isApplePayHidden ) {

				try {
					this.applePay = await this.payments.applePay( paymentRequest );

					/**
					 * Apple Pay doesn't need to be attached.
					 * https://developer.squareup.com/docs/web-payments/apple-pay#:~:text=Note%3A%20You%20do%20not%20need%20to%20%60attach%60%20applePay.
					 */

					/*
					 * Click event handler for when the Apple Pay button is clicked.
					 */
					$( '#apple-pay-button' ).on( 'click', async ( e ) => this.handleApplePayPaymentMethodSubmission( e, this.applePay ) );
					$( '#apple-pay-button' ).show();
				} catch ( e ) {
					console.log( e.message );
				}
			}

			/**
			 * Display the wallet after successful initialize of Google Pay or Apple Pay.
			 */
			if ( this.googlePay || this.applePay ) {
				$( this.buttons ).unblock();
				$( this.wallet ).show();
			}
		}

		/**
		 *
		 * @param {object} e Event object
		 * @param {object} googlePay The Google Pay Button object
		 * @returns
		 */
		async handleGooglePayPaymentMethodSubmission( e, googlePay = null ) {
			e.preventDefault();

			if ( ! googlePay ) {
				return;
			}

			this.blockedForm = null;

			switch ( this.args.context ) {
				case 'product':
					this.blockedForm = this.blockForms( this.productPageForm );
					break;

				case 'cart':
					this.blockedForm = this.blockForms( this.cartTotalsForms );
					break;

				case 'checkout':
					this.blockedForm = this.blockForms( this.checkoutForm );
					break;

				default:
					break;
			}

			/**
			 * This method presents the Google Pay payment sheet. When the buyer completes
			 * their interaction with Google Pay, the returned promise resolves with a
			 * tokenResult object. The returned token and buyer details can be used to
			 * complete the payment on your server.
			 */
			const result = await googlePay.tokenize();

			if ( result.status === 'OK' ) {
				const cardData = { ...result.details.card, digital_wallet_type: result.details.method };
				const billingAndShippingData = {
					billingContact: result.details.billing,
				};

				if ( result.details.shipping ) {
					billingAndShippingData.shippingContact = result.details.shipping.contact;
					billingAndShippingData.shippingOption = result.details.shipping.option;
				}

				this.handle_card_nonce_response( false, result.token, cardData, billingAndShippingData );
			} else {
				if ( this.blockedForm ) {
					this.blockedForm.unblock();
				}
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
		 *
		 * @param {object} e Event object
		 * @param {object} applePay The Apple Pay Button object
		 * @returns
		 */
		async handleApplePayPaymentMethodSubmission( e, applePay = null ) {
			e.preventDefault();

			if ( ! applePay ) {
				return;
			}

			/**
			 * This method presents the Apple Pay payment sheet. When the buyer completes
			 * their interaction with Apple Pay, the returned promise resolves with a
			 * tokenResult object. The returned token and buyer details can be used to
			 * complete the payment on your server.
			 */
			const result = await applePay.tokenize();

			if ( result.status === 'OK' ) {
				const cardData = { ...result.details.card, digital_wallet_type: result.details.method };
				const billingAndShippingData = {
					billingContact: result.details.billing,
				};

				if ( result.details.shipping ) {
					billingAndShippingData.shippingContact = result.details.shipping.contact;
					billingAndShippingData.shippingOption = result.details.shipping.option;
				}

				this.handle_card_nonce_response( false, result.token, cardData, billingAndShippingData );
			}
		}

		/**
		 * Registers evet handlers for the followinng shipping events:
		 * - shippingoptionchanged
		 * - shippingoptionchanged
		 *
		 * @param {object} paymentRequest The Square Payment Request object.
		 */
		registerDigitalWalletShippingEventHandlers( paymentRequest ) {
			paymentRequest.addEventListener( 'shippingoptionchanged', ( option ) => this.handle_shipping_option_changed( option ) );
			paymentRequest.addEventListener( 'shippingcontactchanged', ( shippingContact ) => this.handle_shipping_address_changed( shippingContact ) );
		}

		/**
		 * Gets the Square payment form params.
		 *
		 * @since 2.3
		 */
		get_form_params() {
			const params = {
				applicationId: this.args.application_id,
				locationId: this.args.location_id,
				autobuild: false,
				applePay: {
					elementId: 'apple-pay-button',
				},
				googlePay: {
					elementId: 'wc-square-google-pay',
				}
			};

			// Fix console errors for Google Pay when there are no shipping options set. See note in Square documentation under shippingOptions: https://developer.squareup.com/docs/api/paymentform#paymentrequestfields.
			if ( this.payment_request.requestShippingAddress === false ) {
				delete params.callbacks.shippingOptionChanged;
			}

			// Remove support for Google Pay and/or Apple Pay if chosen in settings.
			if ( this.args.hide_button_options.includes( 'google' ) ) {
				delete params.googlePay;
			}

			if ( this.args.hide_button_options.includes( 'apple' ) ) {
				delete params.applePay;
			}

			return params;
		}

		/**
		 * Sets the a payment request object for the Square Payment Form
		 *
		 * @since 2.3
		 */
		create_payment_request() {
			return this.payment_request;
		}

		/**
		 * Check which methods are supported and show/hide the correct buttons on frontend
		 * Reference: https://developer.squareup.com/docs/api/paymentform#methodssupported
		 *
		 * @param {Object} methods
		 * @param {string} unsupportedReason
		 *
		 * @since 2.3
		 */
		methods_supported( methods, unsupportedReason ) {
			if ( methods.applePay === true || methods.googlePay === true ) {
				if ( methods.applePay === true ) {
					$( '#apple-pay-button' ).show();
				}

				if ( methods.googlePay === true ) {
					$( '#wc-square-google-pay' ).show();
				}

				$( this.wallet ).show();
			} else {
				this.log( unsupportedReason );
			}
		}

		/*
		 * Get the payment request on a product page
		 *
		 * @since 2.3
		 */
		get_payment_request() {
			return new Promise( ( resolve, reject ) => {
				const data = {
					context: this.args.context,
					security: this.args.payment_request_nonce,
					is_pay_for_order_page: this.isPayForOrderPage,
					order_id: this.orderId,
				};

				if ( this.args.context === 'product' ) {
					const product_data = this.get_product_data();
					$.extend( data, product_data );
				}
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
		 * Handle all shipping address recalculations in the Apple/Google Pay window
		 *
		 * Reference: https://developer.squareup.com/docs/api/paymentform#shippingcontactchanged
		 *
		 * @since 2.3
		 */
		async handle_shipping_address_changed( shippingContact ) {
			const data = {
				context: this.args.context,
				shipping_contact: shippingContact,
				security: this.args.recalculate_totals_nonce,
				is_pay_for_order_page: this.isPayForOrderPage,
				order_id: this.orderId,
			};

			// send ajax request get_shipping_options.
			const response = await this.recalculate_totals( data );
			return response;
		}

		/*
		 * Handle all shipping method changes in the Apple/Google Pay window
		 *
		 * Reference: https://developer.squareup.com/docs/api/paymentform#shippingoptionchanged
		 *
		 * @since 2.3
		 */
		async handle_shipping_option_changed( shippingOption ) {
			const data = {
				context: this.args.context,
				shipping_option: shippingOption.id,
				security: this.args.recalculate_totals_nonce,
				is_pay_for_order_page: this.isPayForOrderPage,
				order_id: this.orderId,
			};

			const response = await this.recalculate_totals( data );
			return response;
		}

		/*
		 * Handle the payment response.
		 *
		 * On success, set the checkout billing/shipping data and submit the checkout.
		 *
		 * @since 2.3
		 */
		handle_card_nonce_response( errors = false, nonce = false, cardData = {}, billingAndShippingData = {} ) {
			if ( errors ) {
				return this.render_errors( errors );
			}

			if ( ! nonce ) {
				return this.render_errors( this.args.general_error );
			}

			this.block_ui();

			const orderReviewForm = $( '#order_review' )

			const { billingContact = {}, shippingContact = {}, shippingOption = null } = billingAndShippingData;

			const data = {
				action: '',
				_wpnonce: this.args.process_checkout_nonce,
				billing_first_name: billingContact.givenName ? billingContact.givenName : '',
				billing_last_name: billingContact.familyName ? billingContact.familyName : '',
				billing_company: $( '#billing_company' ).val(),
				billing_email: shippingContact.email ? shippingContact.email : ( billingContact.email ? billingContact.email : '' ),
				billing_phone: shippingContact.phone ? shippingContact.phone : '',
				billing_country: billingContact.countryCode ? billingContact.countryCode.toUpperCase() : '',
				billing_address_1: billingContact.addressLines && billingContact.addressLines[ 0 ] ? billingContact.addressLines[ 0 ] : '',
				billing_address_2: billingContact.addressLines && billingContact.addressLines[ 1 ] ? billingContact.addressLines[ 1 ] : '',
				billing_city: billingContact.city ? billingContact.city : '',
				billing_state: billingContact.state ? billingContact.state : '',
				billing_postcode: billingContact.postalCode ? billingContact.postalCode : '',
				shipping_first_name: shippingContact.givenName ? shippingContact.givenName : '',
				shipping_last_name: shippingContact.familyName ? shippingContact.familyName : '',
				shipping_company: $( '#shipping_company' ).val(),
				shipping_country: shippingContact.countryCode ? shippingContact.countryCode.toUpperCase() : '',
				shipping_address_1: shippingContact.addressLines && shippingContact.addressLines[ 0 ] ? shippingContact.addressLines[ 0 ] : '',
				shipping_address_2: shippingContact.addressLines && shippingContact.addressLines[ 1 ] ? shippingContact.addressLines[ 1 ] : '',
				shipping_city: shippingContact.city ? shippingContact.city : '',
				shipping_state: shippingContact.state ? shippingContact.state : '',
				shipping_postcode: shippingContact.postalCode ? shippingContact.postalCode : '',
				shipping_method: [ ! shippingOption ? null : shippingOption.id ],
				order_comments: '',
				payment_method: 'square_credit_card',
				ship_to_different_address: 1,
				terms: 1,
				'wc-square-credit-card-payment-nonce': nonce,
				'wc-square-credit-card-last-four': cardData.last4 ? cardData.last4 : null,
				'wc-square-credit-card-exp-month': cardData.expMonth ? cardData.expMonth : null,
				'wc-square-credit-card-exp-year': cardData.expYear ? cardData.expYear : null,
				'wc-square-credit-card-payment-postcode': cardData.billing.postalCode ? cardData.billing.postalCode : null,
				'wc-square-digital-wallet-type': cardData.digital_wallet_type,
				is_pay_for_order_page: this.isPayForOrderPage,
			};

			if ( this.isPayForOrderPage ) {
				if ( cardData.last4 ) {
					$( `input[name=wc-${ this.id_dasherized }-last-four]` ).val( cardData.last4 );
				}

				if ( cardData.expMonth ) {
					$( `input[name=wc-${ this.id_dasherized }-exp-month]` ).val( cardData.expMonth );
				}

				if ( cardData.expYear ) {
					$( `input[name=wc-${ this.id_dasherized }-exp-year]` ).val( cardData.expYear );
				}

				if ( cardData?.billing?.postalCode ) {
					$( `input[name=wc-${ this.id_dasherized }-payment-postcode]` ).val( cardData.billing.postalCode );
				}

				if ( cardData.brand ) {
					$( `input[name=wc-${ this.id_dasherized }-card-type]` ).val( cardData.brand );
				}

				$( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val( nonce );
				$( '#payment_method_override' ).remove();
				$( '#payment' ).after( `<input id="payment_method_override" type="hidden" name="payment_method" value="square_credit_card" />` );
			}

			// handle slightly different mapping for Google Pay (Google returns full name as a single string).
			if ( cardData.digital_wallet_type === 'GOOGLE_PAY' ) {
				if ( billingContact.givenName ) {
					data.billing_first_name = billingContact.givenName.split( ' ' ).slice( 0, 1 ).join( ' ' );
					data.billing_last_name = billingContact.givenName.split( ' ' ).slice( 1 ).join( ' ' );
				}

				if ( shippingContact.givenName ) {
					data.shipping_last_name = shippingContact.givenName.split( ' ' ).slice( 0, 1 ).join( ' ' );
					data.shipping_last_name = shippingContact.givenName.split( ' ' ).slice( 1 ).join( ' ' );
				}
			}

			// if the billing_phone was not found on shippingContact, use the value on billingContact if that exists.
			if ( ! data.billing_phone && billingContact.phone ) {
				data.billing_phone = billingContact.phone;
			}

			// if SCA is enabled, verify the buyer and add verification token to data.
			this.log( '3DS verification enabled. Verifying buyer' );

			var self = this;

			/* Solution for https://github.com/woocommerce/woocommerce-square/issues/908
			* This logic submits the custom fields added by other plugins to the checkout form.
			*/
			const checkoutForm = $( 'form.checkout' );
			const checkoutSerialisedData = checkoutForm.find( ':input:not(:hidden)' ).serializeArray();

			checkoutSerialisedData.forEach( function ( item ) {
				if ( ! ( item.name in data ) ) {
					data[ item.name ] = item.value;
				}
			} );

			// We remove fields that are not needed for digital wallet checkout.
			delete data['wc-square-credit-card-payment-token'];
			// end of solution for issue #908.

			try {
				this.payments.verifyBuyer(
					nonce,
					self.get_verification_details( billingContact, shippingContact ),
				).then( ( verificationResult ) =>  {
					if ( verificationResult.token ) {
						// SCA verification complete. Do checkout.
						if ( this.isPayForOrderPage && orderReviewForm.length ) {
							$( `input[name=wc-${ this.id_dasherized }-buyer-verification-token]` ).val( verificationResult.token );
							orderReviewForm.trigger( 'submit' );
							return;
						}

						self.log( '3DS verification successful' );
						data['wc-square-credit-card-buyer-verification-token'] = verificationResult.token;
						self.do_checkout( data );
					}
				} );
			} catch( err ) {
				self.log( '3DS verification failed' );
				self.log(err);
				self.render_errors( [err.message] );
			}
		}

		/**
		 * Do Digital Wallet Checkout
		 *
		 * @since 2.4.2
		 *
		 * @param {Object} args
		 */
		do_checkout( data ) {
			// AJAX process checkout.
			this.process_digital_wallet_checkout( data ).then(
				( response ) => {
					window.location = response.redirect;
				},
				( response ) => {
					this.log( response, 'error' );
					this.render_errors_html( response.messages );
				}
			);
		}

		/**
		 * Gets a verification details object to be used in verifyBuyer()
		 *
		 * @since 2.4.2
		 *
		 * @param {Object} billingContact
		 * @param {Object} shippingContact
		 *
		 * @return {Object} Verification details object.
		 */
		get_verification_details( billingContact, shippingContact ) {
			const verification_details = {
				intent: 'CHARGE',
				amount: this.total_amount,
				currencyCode: this.payment_request.currencyCode,
				billingContact: {
					familyName: billingContact.familyName ? billingContact.familyName : '',
					givenName: billingContact.givenName ? billingContact.givenName : '',
					email: shippingContact.email ? shippingContact.email : '',
					countryCode: billingContact.countryCode ? billingContact.countryCode.toUpperCase() : '',
					state: billingContact.state ? billingContact.state : '',
					city: billingContact.city ? billingContact.city : '',
					postalCode: billingContact.postalCode ? billingContact.postalCode : '',
					phone: shippingContact.phone ? shippingContact.phone : '',
					addressLines: billingContact.addressLines ? billingContact.addressLines : '',
				},
			}

			this.log( verification_details );

			return verification_details;
		}

		/*
		 * Recalculate totals
		 *
		 * @since 2.3
		 */
		async recalculate_totals( data ) {
			return new Promise( ( resolve, reject ) => {
				return $.post( this.get_ajax_url( 'recalculate_totals' ), data, ( response ) => {
					if ( response.success ) {
						this.total_amount = response.data.total.amount;
						return resolve( response.data );
					}
					return reject( response.data );
				} );
			} );
		}

		/*
		 * Get the product data for building the payment request on the product page
		 *
		 * @since 2.3
		 */
		get_product_data() {
			let product_id = $( '.single_add_to_cart_button' ).val();

			const attributes = {};

			// Check if product is a variable product.
			if ( $( '.single_variation_wrap' ).length ) {
				product_id = $( '.single_variation_wrap' ).find( 'input[name="product_id"]' ).val();
				if ( $( '.variations_form' ).length ) {
					$( '.variations_form' ).find( '.variations select' ).each( ( index, select ) => {
						const attribute_name = $( select ).data( 'attribute_name' ) || $( select ).attr( 'name' );
						const value = $( select ).val() || '';
						return attributes[ attribute_name ] = value;
					} );
				}
			}

			return {
				product_id,
				quantity: $( '.quantity .qty' ).val(),
				attributes,
			};
		}

		/**
		 * Adds a form validation check for the Product add-ons plugin.
		 *
		 * @link https://github.com/woocommerce/woocommerce-square/issues/970
		 * @returns {boolean}
		 */
		isProductAddonsFormsValid() {
			const cartForm = $( 'form.cart' );

			if ( ! cartForm.length ) {
				return true;
			}

			// trigger HTML5 form validation.
			const isValid = cartForm.get(0).reportValidity();

			// WC_PAO is Woo Product Addon's global var.
			if ( ! window.WC_PAO ) {
				return isValid;
			}

			// Return if a product doesn't have addons added to it.
			if ( ! WC_PAO.Form( cartForm ).$addons ) {
				return isValid;
			}

			return WC_PAO.Form( cartForm ).validation.validate( true ) && isValid;
		}

		/*
		 * Add the product to the cart
		 *
		 * @since 2.3
		 */
		add_to_cart() {
			const data = {
				security: this.args.add_to_cart_nonce,
			};
			const product_data = this.get_product_data();
			const cartForm     = $( 'form.cart' );

			if ( ! cartForm.length ) {
				return;
			}

			$.extend( data, product_data );

			const formData = new FormData( cartForm.get(0) );
			
			for (const [key, value] of formData.entries()) {
				if ( key.endsWith( '[]' ) ) {
					if ( Array.isArray( data[ key ] ) ) {
						data[ key ].push( value );
					} else {
						data[ key ] = [ value ];
					}
				} else {
					data[ key ] = value;
				}
			}

			// retrieve a payment request object.
			$.post( this.get_ajax_url( 'add_to_cart' ), data, ( response ) => {
				if ( response.error ) {
					return window.alert( response.data );
				}

				const data = JSON.parse( response.data );
				this.payment_request = data.payment_request;
				this.args.payment_request_nonce = data.payment_request_nonce;
				this.args.add_to_cart_nonce = data.add_to_cart_nonce;
				this.args.recalculate_totals_nonce = data.recalculate_totals_nonce;
				this.args.process_checkout_nonce = data.process_checkout_nonce;
			} );
		}

		/*
		 * Process the digital wallet checkout
		 *
		 * @since 2.3
		 */
		process_digital_wallet_checkout( data ) {
			return new Promise( ( resolve, reject ) => {
				$.post( this.get_ajax_url( 'process_checkout' ), data, ( response ) => {
					if ( response.result === 'success' ) {
						return resolve( response );
					}

					return reject( response );
				} );
			} );
		}

		/*
		 * Helper function to return the ajax URL for the given request/action
		 *
		 * @since 2.3
		 */
		get_ajax_url( request ) {
			return this.args.ajax_url.replace( '%%endpoint%%', 'square_digital_wallet_' + request );
		}

		/*
		 * Renders errors given the error message HTML
		 *
		 * @since 2.3
		 */
		render_errors_html( errors_html ) {
			// hide and remove any previous errors.
			$( '.woocommerce-error, .woocommerce-message' ).remove();

			let element = '';

			switch ( this.args.context ) {
				case 'product':
					element = $( '.product' );
					break;

				case 'cart':
					element = $( '.shop_table.cart' ).closest( 'form' );
					break;

				case 'checkout':
					element = $( 'form[name="checkout"]' );
					break;

				default:
					break;
			}
			// add errors
			element.before( errors_html );

			// unblock UI
			this.blockedForm.unblock();

			// scroll to top
			$( 'html, body' ).animate( {
				scrollTop: element.offset().top - 100,
			}, 1000 );
		}

		/*
		 * Renders errors
		 *
		 * @since 2.3
		 */
		render_errors( errors ) {
			const error_message_html = '<ul class="woocommerce-error"><li>' + errors.join( '</li><li>' ) + '</li></ul>';
			this.render_errors_html( error_message_html );
		}

		/*
		 * Block the Apple Pay and Google Pay buttons from being clicked which processing certain actions
		 *
		 * @since 2.3
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
		 * @since 2.3
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

		/**
		 * Returns a debounced function.
		 *
		 * @param {function} func The function that needs to be debounced.
		 * @param {*} wait The debounce innterval.
		 * @param {*} immediate If the function should fire on the leading edge.
		 * @returns {function}
		 */
		debounce( func, wait, immediate ) {
			let timeout;

			return function() {
				let context = this, args = arguments;
				let later = function() {
					timeout = null;
					if ( ! immediate ) func.apply( context, args );
				};
				let callNow = immediate && !timeout;
				clearTimeout( timeout );
				timeout = setTimeout( later, wait );
				if ( callNow ) func.apply( context, args );
			};
		};
	}

	window.WC_Square_Digital_Wallet_Handler = WC_Square_Digital_Wallet_Handler;
} );
