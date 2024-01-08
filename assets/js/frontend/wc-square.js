/**
 * WooCommerce Square Payment Form handler.
 *
 * @since 2.0.0
 */
jQuery( document ).ready( ( $ ) => {
	/**
	 * Square Credit Card Payment Form Handler class.
	 *
	 * @since 2.0.0
	 */
	class WC_Square_Payment_Form_Handler {
		/**
		 * Setup handler.
		 *
		 * @since 2.3.2-1
		 *
		 * @param {Object} args
		 */
		constructor( args ) {
			this.id = args.id;
			this.id_dasherized = args.id_dasherized;
			this.csc_required = args.csc_required;
			this.enabled_card_types = args.enabled_card_types;
			this.square_card_types = args.square_card_types;
			this.ajax_log_nonce = args.ajax_log_nonce;
			this.ajax_url = args.ajax_url;
			this.application_id = args.application_id;
			this.currency_code = args.currency_code;
			this.general_error = args.general_error;
			this.input_styles = args.input_styles;
			this.is_add_payment_method_page = args.is_add_payment_method_page;
			this.is_checkout_registration_enabled = args.is_checkout_registration_enabled;
			this.is_user_logged_in = args.is_user_logged_in;
			this.location_id = args.location_id;
			this.logging_enabled = args.logging_enabled;
			this.ajax_wc_checkout_validate_nonce = args.ajax_wc_checkout_validate_nonce;
			this.is_manual_order_payment = args.is_manual_order_payment;
			this.current_postal_code_value = '';
			this.payment_token_nonce = args.payment_token_nonce;
			this.payment_token_status = true;
			this.billing_details_message_wrapper = $( '#square-pay-for-order-billing-details-wrapper' );
			this.orderId = args.order_id;
			this.ajax_get_order_amount_nonce = args.ajax_get_order_amount_nonce;

			if ( $( 'form.checkout' ).length ) {
				this.form = $( 'form.checkout' );
				this.handle_checkout_page();
			} else if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
				this.handle_pay_page();
			} else if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
				this.handle_add_payment_method_page();
			} else {
				this.log( 'No payment form found!' );
				return;
			}

			// localized error messages.
			this.params = window.sv_wc_payment_gateway_payment_form_params;

			// unblock the UI and clear any payment nonces when a server-side error occurs.
			$( document.body ).on( 'checkout_error', () => {
				$( 'input[name=wc-square-credit-card-payment-nonce]' ).val( '' );
				$( 'input[name=wc-square-credit-card-buyer-verification-token]' ).val( '' );
			} );

			/**
			 * When payment method is updated, recalculate form size.
			 */
			$( document.body ).on( 'change', `#payment_method_${ this.id }`, () => {
				if ( this.payment_form ) {
					this.log( 'Recalculating payment form size' );
					this.payment_form.recalculateSize();
				}
			} );

			$( 'input[name="payment_method"]' ).on( 'change', ( e ) => {
				if ( ! this.billing_details_message_wrapper.length ) {
					return;
				}

				if ( 'square_credit_card' === $( e.target ).val() && $( e.target ).prop( 'checked' ) ) {
					this.billing_details_message_wrapper.slideDown();
				} else {
					this.billing_details_message_wrapper.slideUp();
				}

				$( document.body ).trigger( 'country_to_state_changed' );
			} ).trigger( 'change' );
		}

		/**
		 * Public: Handle required actions on the checkout page.
		 */
		handle_checkout_page() {
			// updated payment fields jQuery object on each checkout update (prevents stale data).
			$( document.body ).on( 'updated_checkout', () => this.set_payment_fields() );

			// handle saved payment methods note on the checkout page.
			// this is bound to `updated_checkout` so it fires even when other parts of the checkout are changed.
			$( document.body ).on( 'updated_checkout', () => this.handle_saved_payment_methods() );

			// validate payment data before order is submitted.
			this.form.on( `checkout_place_order_${ this.id }`, () => this.validate_payment_data() );
		}

		/**
		 * Public: Handle associated actions for saved payment methods.
		 */
		handle_saved_payment_methods() {
			// make available inside change events.
			const id_dasherized = this.id_dasherized;
			const form_handler = this;
			const $new_payment_method_selection = $( `div.js-wc-${ id_dasherized }-new-payment-method-form` );

			// show/hide the saved payment methods when a saved payment method is de-selected/selected.
			$( `input.js-wc-${ this.id_dasherized }-payment-token` ).on( 'change', () => {
				const tokenized_payment_method_selected = $( `input.js-wc-${ id_dasherized }-payment-token:checked` ).val();

				if ( tokenized_payment_method_selected ) {
					// using an existing tokenized payment method, hide the 'new method' fields.
					$new_payment_method_selection.slideUp( 200 );
				} else {
					// use new payment method, display the 'new method' fields.
					$new_payment_method_selection.slideDown( 200 );
				}
			} ).trigger( 'change' );

			// display the 'save payment method' option for guest checkouts if the 'create account' option is checked
			// but only hide the input if there is a 'create account' checkbox (some themes just display the password).
			$( 'input#createaccount' ).on( 'change', ( e ) => {
				if ( $( e.target ).is( ':checked' ) ) {
					form_handler.show_save_payment_checkbox( id_dasherized );
				} else {
					form_handler.hide_save_payment_checkbox( id_dasherized );
				}
			} );

			if ( ! $( 'input#createaccount' ).is( ':checked' ) ) {
				$( 'input#createaccount' ).trigger( 'change' );
			}

			// hide the 'save payment method' when account creation is not enabled and customer is not logged in.
			if ( ! this.is_user_logged_in && ! this.is_checkout_registration_enabled ) {
				this.hide_save_payment_checkbox( id_dasherized );
			}
		}

		/**
		 * Public: Handle required actions on the Order > Pay page.
		 */
		handle_pay_page() {
			this.set_payment_fields();

			// handle saved payment methods.
			this.handle_saved_payment_methods();

			const self = this;

			// validate payment data before order is submitted.
			// but only when one of our payment gateways is selected.
			this.form.on( 'submit', function() {
				if ( $( '#order_review input[name=payment_method]:checked' ).val() === self.id ) {
					return self.validate_payment_data();
				}
			} );
		}

		/**
		 * Public: Handle required actions on the Add Payment Method page.
		 */
		handle_add_payment_method_page() {
			this.set_payment_fields();

			const self = this;

			// validate payment data before order is submitted.
			// but only when one of our payment gateways is selected.
			this.form.on( 'submit', function() {
				if ( $( '#add_payment_method input[name=payment_method]:checked' ).val() === self.id ) {
					return self.validate_payment_data();
				}
			} );
		}

		/**
		 * Sets up the Square payment fields.
		 *
		 * @since 2.0.0
		 */
		set_payment_fields() {
			if ( this.payment_form ) {
				// Don't re-initialize the payment form if it's already been initialized and exist in DOM.
				if (
					$(
						'#wc-square-credit-card-container .sq-card-iframe-container'
					).children().length
				) {
					this.payment_form.configure( {
						postalCode: $( '#billing_postcode' ).val()
					} );
					return;
				}

				// Destroy the payment form and re-initialize it.
				this.log( 'Destroying payment form' );
				this.payment_form.destroy().then( () => {
					this.log( 'Re-building payment form' );
					this.initializeCard( this.payments );
				} );
				return;
			}

			this.log( 'Building payment form' );

			const { applicationId, locationId } = this.get_form_params();
			this.payments = window.Square.payments( applicationId, locationId ); // eslint-disable-line no-undef
			this.initializeCard( this.payments );
		}

		/**
		 * Initialises the Credit Card field with defaults (if any)
		 *
		 * @param {object} payments The Square payment object.
		 */
		initializeCard( payments ) {
			let defaultPostalCode = $( '#billing_postcode' ).val();
			defaultPostalCode = defaultPostalCode || '';

			payments.card( {
				postalCode: defaultPostalCode, // Default postal code value.
			} ).then( ( card ) => {
				if ( ! document.getElementById( 'wc-square-credit-card-container' ) ) {
					return;
				}

				card.attach( '#wc-square-credit-card-container' );
				this.payment_form = card;
				this.log( 'Payment form loaded' );
			} );
		}

		/**
		 * Gets the Square payment form params.
		 *
		 * @since 2.0.0
		 *
		 * @return {Object} Form params.
		 */
		get_form_params() {
			return {
				applicationId: this.application_id,
				locationId: this.location_id,
			};
		}

		/**
		 * Used to request a card nonce and submit the form.
		 *
		 * @since 2.0.0
		 */
		validate_payment_data() {
			if ( ! this.payment_token_status ) {
				this.payment_token_status = true;
				return true;
			}

			if ( this.form.is( '.processing' ) ) {
				// bail when already processing.
				return false;
			}

			// let through if nonce is already present - nonce is only present on non-tokenized payments.
			if ( this.has_nonce() ) {
				this.log( 'Payment nonce present, placing order' );
				return true;
			}

			const tokenized_card_id = this.get_tokenized_payment_method_id();

			if ( tokenized_card_id ) {
				if ( this.has_verification_token() ) {
					this.log( 'Tokenized payment verification token present, placing order' );
					return true;
				}

				this.log( 'Requesting verification token for tokenized payment' );

				this.block_ui();

				fetch( `${ wc_checkout_params.ajax_url }?action=wc_square_credit_card_get_token_by_id&token_id=${ tokenized_card_id }&nonce=${ this.payment_token_nonce }` )
				.then( ( response ) => {
					if ( response.ok ) {
						return response.json()
					} else {
						throw new Error( 'Error in fetching payment token by ID.' );
					}
				} )
				.then( ( { success, data: token } ) => {
					if ( success ) {
						this.log( 'Requesting verification token for tokenized payment' );
	
						this.block_ui();
	
						this.get_verification_details().then((verificationDetails) => {
							return this.payments
								.verifyBuyer(token, verificationDetails)
								.then((verificationResult) => {
									this.handle_verify_buyer_response(
										false,
										verificationResult
									);
								});
						});
					} else {
						this.payment_token_status = false;
						this.form.trigger( 'submit' );
						this.log( token );
					}
				} );

				return false;
			}

			this.log( 'Requesting payment nonce' );
			this.block_ui();
			this.handleSubmission();
			return false;
		}

		/**
		 * Generates Square payment token and submits form.
		 */
		handleSubmission() {
			const tokenPromise = this.payment_form.tokenize();
			tokenPromise.then( tokenResult => {
				const { token, details, status } = tokenResult;

				if ( status === 'OK' ) {
					this.handle_card_nonce_response( token, details );
				} else {
					if ( tokenResult.errors ) {
						this.handle_errors( tokenResult.errors )
					}
				}
			} );
		}

		/**
		 * Gets the selected tokenized payment method ID, if there is one.
		 *
		 * @since 2.1.0
		 *
		 * @return {string} Tokenized payment method ID.
		 */
		get_tokenized_payment_method_id() {
			return $( `.payment_method_${ this.id }` ).find( '.js-wc-square-credit-card-payment-token:checked' ).val();
		}

		/**
		 * Handles the Square payment form card nonce response.
		 *
		 * @since 2.1.0
		 *
		 * @param {Object} errors Validation errors, if any.
		 * @param {string} nonce Payment nonce.
		 * @param {Object} details Non-confidential info about the card used.
		 */
		handle_card_nonce_response( nonce, details ) {
			const { card: cardData, billing } = details;

			// no errors, but also no payment data.
			if ( ! nonce ) {
				const message = 'Nonce is missing from the Square response';

				this.log( message, 'error' );
				this.log_data( message, 'response' );
				return this.handle_errors();
			}

			// if we made it this far, we have payment data.
			this.log( 'Card data received' );
			this.log( cardData );
			this.log_data( cardData, 'response' );

			if ( cardData.last4 ) {
				$( `input[name=wc-${ this.id_dasherized }-last-four]` ).val( cardData.last4 );
			}

			if ( cardData.expMonth ) {
				$( `input[name=wc-${ this.id_dasherized }-exp-month]` ).val( cardData.expMonth );
			}

			if ( cardData.expYear ) {
				$( `input[name=wc-${ this.id_dasherized }-exp-year]` ).val( cardData.expYear );
			}

			if ( billing?.postalCode ) {
				$( `input[name=wc-${ this.id_dasherized }-payment-postcode]` ).val( billing.postalCode );
			}

			if ( cardData.brand ) {
				$( `input[name=wc-${ this.id_dasherized }-card-type]` ).val( cardData.brand );
			}

			// payment nonce data.
			$( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val( nonce );

			// if 3ds is enabled, we need to verify the buyer and record the verification token before continuing.
			this.log( 'Verifying buyer' );

			this.get_verification_details().then((verificationDetails) => {
				return this.payments
					.verifyBuyer(nonce, verificationDetails)
					.then((verificationResult) => {
						this.handle_verify_buyer_response(
							false,
							verificationResult
						);
				});
			});
		}

		/**
		 * Handles the response from a call to verifyBuyer()
		 *
		 * @since 2.1.0
		 *
		 * @param {Object} errors Verification errors, if any.
		 * @param {Object} verification_result Results of verification.
		 */
		handle_verify_buyer_response( errors, verification_result ) {
			if ( errors ) {
				$( errors ).each( ( index, error ) => {
					if ( ! error.field ) {
						error.field = 'none';
					}
				} );

				return this.handle_errors( errors );
			}

			// no errors, but also no verification token.
			if ( ! verification_result || ! verification_result.token ) {
				const message = 'Verification token is missing from the Square response';

				this.log( message, 'error' );
				this.log_data( message, 'response' );

				return this.handle_errors();
			}

			this.log( 'Verification result received' );
			this.log( verification_result );

			$( `input[name=wc-${ this.id_dasherized }-buyer-verification-token]` ).val( verification_result.token );

			this.form.trigger( 'submit' );
		}

		/**
		 * Gets a verification details object to be used in verifyBuyer()
		 *
		 * @since 2.1.0
		 *
		 * @return {Promise<Object>} Verification details object.
		 */
		get_verification_details() {
			const verification_details = {
				billingContact: {
					familyName: $( '#billing_last_name' ).val() || '',
					givenName: $( '#billing_first_name' ).val() || '',
					email: $( '#billing_email' ).val() || '',
					country: $( '#billing_country' ).val() || '',
					region: $( '#billing_state' ).val() || '',
					city: $( '#billing_city' ).val() || '',
					postalCode: $( '#billing_postcode' ).val() || '',
					phone: $( '#billing_phone' ).val() || '',
					addressLines: [ $( '#billing_address_1' ).val() || '', $( '#billing_address_2' ).val() || '' ],
				},
				intent: this.get_intent(),
			};

			if ( 'CHARGE' === verification_details.intent ) {
				verification_details.currencyCode = this.currency_code;
				return this.get_amount().then((amount) => {
					verification_details.amount = amount;
					this.log(verification_details);
					return verification_details;
				});
			}

			return new Promise((resolve) => {
				this.log(verification_details);
				resolve(verification_details);
			});
		}

		/**
		 * Gets the intent of this processing - either 'CHARGE' or 'STORE'
		 *
		 * The gateway stores cards before processing a payment, so this checks whether the customer checked "save method"
		 * at checkout, and isn't otherwise using a saved method already.
		 *
		 * @since 2.1.0
		 *
		 * @return {string} {'CHARGE'|'STORE'}
		 */
		get_intent() {
			const $save_method_input = $( '#wc-square-credit-card-tokenize-payment-method' );

			let save_payment_method;

			if ( $save_method_input.is( 'input:checkbox' ) ) {
				save_payment_method = $save_method_input.is( ':checked' );
			} else {
				save_payment_method = 'true' === $save_method_input.val();
			}

			if ( ! this.get_tokenized_payment_method_id() && save_payment_method ) {
				return 'STORE';
			}

			return 'CHARGE';
		}

		/**
		 * Gets the amount of this payment.
		 *
		 * @since 2.1.0
		 *
		 * @return {Promise<string>} Payment amount.
		 */
		get_amount() {
			return new Promise((resolve, reject) => {
				const data = {
					action: 'wc_' + this.id + '_get_order_amount',
					security: this.ajax_get_order_amount_nonce,
					order_id: this.orderId,
					is_pay_order: this.is_manual_order_payment,
				};

				$.ajax({
					url: this.ajax_url,
					method: 'post',
					cache: false,
					data,
					complete: (response) => {
						const result = response.responseJSON;
						if (result && result.success) {
							return resolve(result.data);
						}

						return reject(result);
					},
				});
			});
		}

		/**
		 * Handle error data.
		 *
		 * @since 2.0.0
		 *
		 * @param {Object|null} errors
		 */
		handle_errors( errors = null ) {
			this.log( 'Error getting payment data', 'error' );

			// clear any previous nonces
			$( 'input[name=wc-square-credit-card-payment-nonce]' ).val( '' );
			$( 'input[name=wc-square-credit-card-buyer-verification-token]' ).val( '' );

			const messages = [];

			if ( errors ) {
				const field_order = [ 'none', 'cardNumber', 'expirationDate', 'cvv', 'postalCode' ];

				if ( errors.length >= 1 ) {
					// sort based on the field order without the brackets around a.field and b.field.
					// the precedence is different and gives different results.
					errors.sort( ( a, b ) => {
						return field_order.indexOf( a.field ) - field_order.indexOf( b.field );
					} );
				}

				$( errors ).each( ( index, error ) => {
					// only display the errors that can be helped by the customer.
					if ( 'UNSUPPORTED_CARD_BRAND' === error.type || 'VALIDATION_ERROR' === error.type ) {
						return messages.push( error.message );
					}

					// otherwise, log more serious errors to the debug log.
					return this.log_data( errors, 'response' );
				} );
			}

			// if no specific messages are set, display a general error.
			if ( messages.length === 0 ) {
				messages.push( this.general_error );
			}

			// Conditionally process error rendering.
			if ( ! this.is_add_payment_method_page && ! this.is_manual_order_payment ) {
				this.render_checkout_errors( messages );
			} else {
				this.render_errors( messages );
			}

			this.unblock_ui();
		}

		/**
		 * Public: Render any new errors and bring them into the viewport.
		 *
		 * @param {Array} errors
		 */
		render_errors( errors ) {
			// hide and remove any previous errors.
			$( '.woocommerce-error, .woocommerce-message' ).remove();

			// add errors.
			this.form.prepend( '<ul class="woocommerce-error"><li>' + errors.join( '</li><li>' ) + '</li></ul>' );

			// unblock UI
			this.form.removeClass( 'processing' ).unblock();
			this.form.find( '.input-text, select' ).trigger( 'blur' );

			// scroll to top
			$( 'html, body' ).animate( {
				scrollTop: this.form.offset().top - 100,
			}, 1000 );
		}

		/**
		 * Blocks the payment form UI.
		 *
		 * @since 3.0.0
		 */
		block_ui() {
			this.form.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
		}

		/**
		 * Unblocks the payment form UI.
		 *
		 * @since 3.0.0
		 */
		unblock_ui() {
			return this.form.unblock();
		}

		/**
		 * Hides save payment method checkbox.
		 *
		 * @since 2.1.2
		 *
		 * @param {string} id_dasherized
		 */
		hide_save_payment_checkbox( id_dasherized ) {
			const $parent_row = $( `input.js-wc-${ id_dasherized }-tokenize-payment-method` ).closest( 'p.form-row' );

			$parent_row.hide();
			$parent_row.next().hide();
		}

		/**
		 * Shows save payment method checkbox.
		 *
		 * @since 2.1.2
		 *
		 * @param {string} id_dasherized
		 */
		show_save_payment_checkbox( id_dasherized ) {
			const $parent_row = $( `input.js-wc-${ id_dasherized }-tokenize-payment-method` ).closest( 'p.form-row' );

			$parent_row.slideDown();
			$parent_row.next().show();
		}

		/**
		 * Determines if a nonce is present in the hidden input.
		 *
		 * @since 2.0.0
		 *
		 * @return {boolean} True if nonce is present, otherwise false.
		 */
		has_nonce() {
			return $( `input[name=wc-${ this.id_dasherized }-payment-nonce]` ).val();
		}

		/**
		 * Determines if a verification token is present in the hidden input.
		 *
		 * @since 2.1.0
		 *
		 * @return {boolean} True if verification token is present, otherwise false.
		 */
		has_verification_token() {
			return $( `input[name=wc-${ this.id_dasherized }-buyer-verification-token]` ).val();
		}

		/**
		 * Logs data to the debug log via AJAX.
		 *
		 * @since 2.0.0
		 *
		 * @param {Object} data Request data.
		 * @param {string} type Data type.
		 */
		log_data( data, type ) {
			// if logging is disabled, bail.
			if ( ! this.logging_enabled ) {
				return;
			}

			const ajax_data = {
				action: 'wc_' + this.id + '_log_js_data',
				security: this.ajax_log_nonce,
				type,
				data,
			};

			$.ajax( {
				url: this.ajax_url,
				data: ajax_data,
			} );
		}

		/**
		 * Logs any messages or errors to the console.
		 *
		 * @since 2.0.0
		 *
		 * @param {string} message
		 * @param {string} type Data type.
		 */
		log( message, type = 'notice' ) {
			// if logging is disabled, bail.
			if ( ! this.logging_enabled ) {
				return;
			}

			if ( 'error' === type ) {
				console.error( 'Square Error: ' + message );
			} else {
				console.log( 'Square: ' + message );
			}
		}

		/**
		 * AJAX validate WooCommerce form data.
		 *
		 * Triggered only if errors are present on Square payment form.
		 *
		 * @since 2.2
		 *
		 * @param {Array} square_errors Square validation errors.
		 */
		render_checkout_errors( square_errors ) {
			const wc_object = window.wc_cart_fragments_params || window.wc_cart_params || window.wc_checkout_params;
			const ajax_url = wc_object.wc_ajax_url.toString().replace( '%%endpoint%%', this.id + '_checkout_handler' );
			const square_handler = this;

			const form_data = this.form.serializeArray();

			// Add action field to data for nonce verification.
			form_data.push( {
				name: 'wc_' + this.id + '_checkout_validate_nonce',
				value: this.ajax_wc_checkout_validate_nonce,
			} );

			return $.ajax( {
				url: ajax_url,
				method: 'post',
				cache: false,
				data: form_data,
				complete: ( response ) => {
					const result = response.responseJSON;

					// If validation is not triggered and WooCommerce returns failure.
					// Temporary workaround to fix problems when user email is invalid.
					if ( result.hasOwnProperty( 'result' ) && 'failure' === result.result ) {
						$( result.messages ).map( ( message ) => {
							const errors = [];

							$( message ).children( 'li' ).each( () => {
								errors.push( $( this ).text().trim() );
							} );

							return square_errors.unshift( ...errors );
						} );

					// If validation is complete and WooCommerce returns validaiton errors.
					} else if ( result.hasOwnProperty( 'success' ) && ! result.success ) {
						square_errors.unshift( ...result.data.messages );
					}

					square_handler.render_errors( square_errors );
				},
			} );
		}
	}

	window.WC_Square_Payment_Form_Handler = WC_Square_Payment_Form_Handler;
} );
