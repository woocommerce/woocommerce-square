/**
 * Payment Methods tab — all four payment method cards.
 *
 * Components are imported from payment-method-field-types.js, which also
 * registers them as wcReactSettings field transformers for future WC versions.
 */
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { usePaymentGatewaySettings } from '../onboarding/hooks';
import { Loader } from '../components';
import {
	PaymentMethodCard,
	DigitalWalletsCard,
	CashAppCustomizePanel,
} from './payment-method-field-types';

const HUB_SAVE_BUTTON_ID = 'wc-square-settings-hub__save-payment-methods';

export const PaymentGatewaySettingsApp = () => {
	const {
		paymentGatewaySettingsLoaded,
		cashAppGatewaySettingsLoaded,
		giftCardsGatewaySettingsLoaded,
		paymentGatewaySettings,
		cashAppGatewaySettings,
		giftCardsGatewaySettings,
		savePaymentGatewaySettings,
		saveCashAppSettings,
		saveGiftCardsSettings,
		setCreditCardData,
		setDigitalWalletData,
		setCashAppData,
		setGiftCardData,
	} = usePaymentGatewaySettings( true );

	const allLoaded =
		paymentGatewaySettingsLoaded &&
		cashAppGatewaySettingsLoaded &&
		giftCardsGatewaySettingsLoaded;

	// Ref always holds the latest save functions — avoids stale closure in the button listener.
	const saveAllRef = useRef( null );
	saveAllRef.current = () =>
		Promise.all( [
			savePaymentGatewaySettings(),
			saveCashAppSettings(),
			saveGiftCardsSettings(),
		] );

	useEffect( () => {
		if ( ! allLoaded ) {
			return;
		}
		const hubBtn = document.getElementById( HUB_SAVE_BUTTON_ID );
		if ( ! hubBtn ) {
			return;
		}
		hubBtn.disabled = false;
		const onClick = ( e ) => {
			e.preventDefault();
			saveAllRef.current();
		};
		hubBtn.addEventListener( 'click', onClick );
		return () => {
			hubBtn.removeEventListener( 'click', onClick );
			hubBtn.disabled = true;
		};
	}, [ allLoaded ] );

	if ( ! allLoaded ) {
		return <Loader />;
	}

	return (
		<div className="wc-square-payment-methods">
			<div className="wc-square-payment-methods__header">
				<h2 className="wc-square-payment-methods__title">
					{ __( 'Choose your payment methods', 'woocommerce-square' ) }
				</h2>
				<p className="wc-square-payment-methods__description">
					{ __(
						"Select which payment methods you'd like to offer to your shoppers. You can update these at any time.",
						'woocommerce-square'
					) }
				</p>
			</div>

			<PaymentMethodCard
				value={ paymentGatewaySettings }
				label={ __( 'Credit/debit card', 'woocommerce-square' ) }
				description={ __(
					'Let your customers pay with major credit and debit cards without leaving your store.',
					'woocommerce-square'
				) }
				onChange={ setCreditCardData }
			>
				<DigitalWalletsCard
					settings={ paymentGatewaySettings }
					setDigitalWalletData={ setDigitalWalletData }
				/>
			</PaymentMethodCard>

			<PaymentMethodCard
				value={ cashAppGatewaySettings }
				label={ __( 'Cash App Pay (US only)', 'woocommerce-square' ) }
				description={ __(
					'Enable customers to check out instantly using their Cash App balance or linked payment methods.',
					'woocommerce-square'
				) }
				onChange={ setCashAppData }
			>
				<CashAppCustomizePanel
					settings={ cashAppGatewaySettings }
					onChange={ setCashAppData }
				/>
			</PaymentMethodCard>

			<PaymentMethodCard
				value={ giftCardsGatewaySettings }
				label={ __( 'Square gift cards', 'woocommerce-square' ) }
				description={ __(
					'Accept Square Gift Cards online or in person, giving customers a convenient way to redeem their balance at checkout.',
					'woocommerce-square'
				) }
				onChange={ setGiftCardData }
			/>
		</div>
	);
};
