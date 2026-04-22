/**
 * Payment Methods tab — all four payment method cards.
 *
 * Components are imported from payment-method-field-types.js, which also
 * registers them as wcReactSettings field transformers for future WC versions.
 */
import { __ } from '@wordpress/i18n';
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

	if ( ! allLoaded ) {
		return <Loader />;
	}

	const handleSaveAll = async () => {
		await Promise.all( [
			savePaymentGatewaySettings(),
			saveCashAppSettings(),
			saveGiftCardsSettings(),
		] );
	};

	// Wire hub header Save button.
	const hubBtn = document.getElementById( HUB_SAVE_BUTTON_ID );
	if ( hubBtn && ! hubBtn.dataset.wired ) {
		hubBtn.dataset.wired = '1';
		hubBtn.disabled = false;
		hubBtn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			handleSaveAll();
		} );
	}

	return (
		<div className="wc-square-payment-methods">
			{/* Credit Card */}
			<PaymentMethodCard
				value={ paymentGatewaySettings }
				label={ __( 'Credit Card', 'woocommerce-square' ) }
				onChange={ setCreditCardData }
			>
				<DigitalWalletsCard
					settings={ paymentGatewaySettings }
					setDigitalWalletData={ setDigitalWalletData }
				/>
			</PaymentMethodCard>

			{/* Cash App Pay */}
			<PaymentMethodCard
				value={ cashAppGatewaySettings }
				label={ __( 'Cash App Pay', 'woocommerce-square' ) }
				onChange={ setCashAppData }
			>
				<CashAppCustomizePanel
					settings={ cashAppGatewaySettings }
					onChange={ setCashAppData }
				/>
			</PaymentMethodCard>

			{/* Gift Cards */}
			<PaymentMethodCard
				value={ giftCardsGatewaySettings }
				label={ __( 'Gift Cards', 'woocommerce-square' ) }
				onChange={ setGiftCardData }
			/>
		</div>
	);
};
