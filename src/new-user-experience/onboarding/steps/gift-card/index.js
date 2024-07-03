/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import parse from 'html-react-parser';

/**
 * Internal dependencies.
 */
import {
	Section,
	SectionTitle,
	SectionDescription,
	InputWrapper,
	SquareCheckboxControl,
} from '../../../components';
import { usePaymentGatewaySettings } from '../../hooks';

export const GiftCardSetup = ( { origin = '' } ) => {
	const {
		giftCardsGatewaySettingsLoaded,
		giftCardsGatewaySettings,
		setGiftCardData,
	} = usePaymentGatewaySettings();

	const { enabled } = giftCardsGatewaySettings;

	if ( ! giftCardsGatewaySettingsLoaded ) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle
					title={ parse(
						sprintf(
							/* translators: %s: Gift Cards */
							__( 'Gift Cards %s', 'woocommerce' ),
							origin === 'settings'
								? `<small className="wc-admin-breadcrumb"><a href="${ wcSquareSettings.adminUrl }admin.php?page=wc-settings&amp;tab=checkout" ariaLabel="Return to payments">⤴</a></small>` // eslint-disable-line no-undef
								: ''
						)
					) }
				/>
				<SectionDescription>
					{ __(
						'You can receive payments with Square Gift Cards and sell Square Gift Cards by enabling the Gift Cards option here.',
						'woocommerce'
					) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					{ origin !== 'settings' && (
						<InputWrapper
							label={ __(
								'Enable Square Gift Cards',
								'woocommerce'
							) }
							variant="boxed"
						>
							<ToggleControl
								className="gift-card-gateway-toggle-field"
								data-testid="gift-card-gateway-toggle-field"
								checked={ enabled === 'yes' }
								onChange={ ( value ) =>
									setGiftCardData( {
										enabled: value ? 'yes' : 'no',
									} )
								}
							/>
						</InputWrapper>
					) }

					{ origin === 'settings' && (
						<InputWrapper
							label={ __( 'Enable / Disable', 'woocommerce' ) }
						>
							<SquareCheckboxControl
								className="gift-card-gateway-toggle-field"
								data-testid="gift-card-gateway-toggle-field"
								label={ __(
									'Enable this payment method.',
									'woocommerce'
								) }
								checked={ enabled === 'yes' }
								onChange={ ( value ) =>
									setGiftCardData( {
										enabled: value ? 'yes' : 'no',
									} )
								}
							/>
						</InputWrapper>
					) }
				</div>
			</Section>
		</>
	);
};
