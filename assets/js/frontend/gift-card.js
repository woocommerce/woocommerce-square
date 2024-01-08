/**
 * Square Gift Card Digital Wallet Handler class.
 *
 * @since 3.7.0
 */
jQuery( document ).ready( ( $ ) => {
	/**
	 * Square Gift Card class.
	 *
	 * @since 3.7.0
	 */
	class WC_Square_Gift_Card_Handler {
		/**
		 * Setup handler
		 *
		 * @param {Array} args
		 * @since 3.7.0
		 */
		constructor( args ) {
			this.applicationId        = args.applicationId;
			this.locationId           = args.locationId;
			this.giftCard             = null;
			this.applyGiftCardNonce   = args.applyGiftCardNonce;
			this.removeGiftCardText   = args.removeGiftCardText;
			this.applyDiffGiftCard    = args.applyDiffGiftCard;
			this.splitPaymentQuestion = args.splitPaymentQuestion;
			this.splitPaymentMessage  = args.splitPaymentMessage;
			this.isValidPage          = args.isValidPage;
			this.reviewOrderTable     = $( '.woocommerce-checkout-review-order-table' );
			this.payments             = window.Square ? window.Square.payments( this.applicationId, this.locationId ) : false;

			// For the Single product page.
			this.handleSingProductPage();

			// For the Checkout page.
			$( document.body ).on( 'updated_checkout', this.init.bind( this ) );
		}

		/**
		 * Initialises all methods required for the Gift Card feature to work.
		 *
		 * @since 3.7.0
		 */
		async init( e, data ) {
			if ( ! ( this.payments ) ) {
				return;
			}

			this.renderGiftCardHtml( data.fragments );
			this.initVariables();

			this.block_ui( this.giftCardAppWrapper );

			$( document.body ).on( 'update_checkout', () => {
				if ( this.hiddenFieldsEl ) {
					this.hiddenFieldsEl.find( 'input[name="payment_method"]' ).remove();
				}
				// Adds a spinner to the Gift Card form elements until it is loaded.
				this.block_ui( this.giftCardAppWrapper );
			} );


			try {
				// Initialise Square Gift Card.
				this.giftCard = await this.initializeGiftCard( this.payments );
			} catch ( e ) {
				console.error( 'Initializing Gift Card failed', e );
			}

			// Remove the spinner overlay.
			this.giftCardAppWrapper.unblock();

			// Event handler that runs when a Gift Card is applied.
			this.giftCardBtnEl.on( 'click', async () => {
				await this.applyGiftCardHandler( this.giftCard );
			} );

			// Event handler that runs when a customer clicks the split total link.
			this.splitPaymentEl.on( 'click', this.onSplitTotal.bind( this ) );

			if ( this.giftCard ) {
				this.giftCard.addEventListener( 'errorClassAdded', () => {
					this.toggleGiftCardApplyButton( true );
				} );
				this.giftCard.addEventListener( 'errorClassRemoved', () => {
					this.toggleGiftCardApplyButton( false );
				} );
			}

			// Event handler that runs when a Gift Card is removed.
			this.removeGiftCardEl.on( 'click', this.onRemoveGiftCard.bind( this ) );
		}

		/**
		 * Enables/disables input field.
		 *
		 * @param {object} formWrapper jQuery object form Wrapper
		 * @param {boolean} status Status indicating if the input fields are enabled/disabled
		 */
		toggleDisabledAttribute( formWrapper = null, status = false ) {
			if ( ! formWrapper ) {
				return;
			}

			formWrapper
				.find( 'input, textarea' )
				.each( ( index, element ) => {
					$( element ).attr( 'disabled', status )
				} );
		}

		/**
		 * Handles gift card buying/loading UI on the single products page.
		 */
		handleSingProductPage() {
			// Gift card product buying options.
			$( 'input[name="square-gift-card-buying-option"]' ).on( 'click', ( e ) => {
				const newWrapper = $( `[data-square-gift-card-activity="new"]` );
				const loadWrapper = $( `[data-square-gift-card-activity="load"]` );

				if ( 'new' === $( e.target ).val() ) {
					newWrapper.show();
					loadWrapper.hide();
					this.toggleDisabledAttribute( newWrapper );
					this.toggleDisabledAttribute( loadWrapper, true );
				} else {
					loadWrapper.show();
					newWrapper.hide();
					this.toggleDisabledAttribute( loadWrapper );
					this.toggleDisabledAttribute( newWrapper, true );
				}

			} );
		}

		/**
		 * Initialises variables.
		 */
		initVariables() {
			this.giftCardWrapperEl  = $( '#square-gift-card-fields-input' );
			this.giftCardAppWrapper = $( '#square-gift-card-wrapper' );
			this.giftCardBtnEl      = $( '#square-gift-card-apply-btn' );
			this.removeGiftCardEl   = $( '#square-gift-card-remove' );
			this.splitPaymentEl     = $( '#square-split-payment' );
		}

		/**
		 * Disables the Gift Card Apply button if the Card number is invalid.
		 * Enables otherwise.
		 *
		 * @param {boolean} isDisabled State of the Gift Card apply button.
		 */
		toggleGiftCardApplyButton( isDisabled = false ) {
			this.giftCardBtnEl.prop( 'disabled', isDisabled );
		}

		/**
		 * Initialises Square Gift card.
		 *
		 * @since 3.7.0
		 * @param {object} payments Square payments object.
		 */
		async initializeGiftCard( payments = null ) {
			if ( ! payments ) {
				return;
			}

			const giftCard = await payments.giftCard();
			await giftCard.attach( this.giftCardWrapperEl[0] );

			return giftCard;
		}

		/**
		 * Renders the HTML input fields for Gift Card.
		 * @param {*} fragments HTML fragments for Gift Card fields.
		 */
		renderGiftCardHtml( fragments ) {
			$( '#square-gift-card-wrapper' ).remove();
			$( '#square-gift-card-split-details' ).remove();
			$( '.woocommerce-checkout-review-order-table' ).after( fragments['.woocommerce-square-gift-card-html'] );

			this.hiddenFieldsEl = $( '#square-gift-card-hidden-fields' );

			if ( this.hiddenFieldsEl && fragments['has-balance'] ) {
				this.hiddenFieldsEl.append( '<input name="payment_method" type="hidden" value="square_credit_card" />' );
			} else {
				this.hiddenFieldsEl.find( 'input[name="payment_method"]' ).remove();
			}
		}

		/**
		 * Handles submission when the `Apply` button is clicked.
		 *
		 * @since 3.7.0
		 * @param {object} giftCard Gift Card object.
		 */
		async applyGiftCardHandler( giftCard ) {
			this.block_ui( this.giftCardAppWrapper );

			let token;

			try {
				token = await this.tokenize( giftCard );
			} catch {
				giftCard.setError( 'giftCardNumber' );
				this.giftCardAppWrapper.unblock();
			}

			if ( ! token ) {
				return;
			}

			await this.checkGiftCardBalance( token );

			$( document.body ).trigger( 'update_checkout' );
		}

		/**
		 * Tokenizes Gift Card.
		 *
		 * @since 3.7.0
		 * @param {object} giftCard Gift Card object.
		 * @returns string|boolean
		 */
		async tokenize( giftCard ) {
			const tokenResult = await giftCard.tokenize();

			if ( tokenResult.status === 'OK' ) {
				return tokenResult.token;
			} else {
				return false;
			}
		}

		/**
		 * Checks Gift Card's balance.
		 *
		 * @since 3.7.0
		 * @param {null|string} token Payment token.
		 * @returns string
		 */
		async checkGiftCardBalance( token = null ) {
			const formData = new FormData();

			formData.append( 'token', token );
			formData.append( 'action', 'wc_square_check_gift_card_balance' );
			formData.append( 'security', this.applyGiftCardNonce );

			const response = await fetch(
				woocommerce_params.ajax_url,
				{
					method: 'POST',
					body: formData,
				}
			)

			if ( 200 !== response.status ) {
				return;
			}

			return response.json();
		}

		/**
		 * Runs when the user clicks the button to remove the Gift Card.
		 *
		 * @since 3.7.0
		 * @param {Object} event Event object.
		 */
		async onRemoveGiftCard( event ) {
			event.preventDefault();

			if ( this.giftCard ) {
				const giftCardDestroyed = await this.giftCard.destroy();

				if ( giftCardDestroyed ) {
					this.giftCard = await this.initializeGiftCard( this.payments );
				}
			}

			const formData = new FormData();

			formData.append( 'action', 'wc_square_gift_card_remove' );
			formData.append( 'security', this.applyGiftCardNonce );

			let response = await fetch(
				woocommerce_params.ajax_url,
				{
					method: 'POST',
					body: formData,
				}
			);

			if ( 200 !== response.status ) {
				return;
			}

			response = await response.json();

			if ( response.success ) {
				$( document.body ).trigger( 'update_checkout' );
			}
		}

		/**
		 * Runs when the user clicks the button to split the total with a Square Gift Card.
		 *
		 * @since 3.9.0
		 * @param {Object} event Event object.
		 */
		async onSplitTotal( event ) {
			event.preventDefault();

			const formData = new FormData();

			formData.append( 'action', 'wc_square_split_payments' );
			formData.append( 'security', this.applyGiftCardNonce );

			let response = await fetch(
				woocommerce_params.ajax_url,
				{
					method: 'POST',
					body: formData,
				}
			);

			if ( 200 !== response.status ) {
				return;
			}

			response = await response.json();

			if ( response.success ) {
				$( document.body ).trigger( 'update_checkout' );
			}
		}

		/**
		 * An overlay to block Gift Card form elements when an async
		 * operation is processing.
		 *
		 * @since 3.7.0
		 * @param {object} element jQuery object.
		 * @returns void
		 */
		block_ui( element = null ) {
			if ( ! element ) {
				return;
			}

			element.block( {
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6,
				},
			} );
		}
	}

	window.WC_Square_Gift_Card_Handler = WC_Square_Gift_Card_Handler;
} );
