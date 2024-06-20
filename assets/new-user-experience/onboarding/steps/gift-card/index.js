/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
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

	if (!giftCardsGatewaySettingsLoaded) {
		return null;
	}

	return (
		<>
			<Section>
				<SectionTitle title={
					parse(
						sprintf(
							__( 'Gift Cards %s', 'woocommerce-square' ),
							'settings' === origin ? `<small className="wc-admin-breadcrumb"><a href="${wcSquareSettings.adminUrl}admin.php?page=wc-settings&amp;tab=checkout" ariaLabel="Return to payments">⤴</a></small>` : ''
						)
					)
				} />
				<SectionDescription>
					{__(
						'You can receive payments with Square Gift Cards and sell Square Gift Cards by enabling the Gift Cards option here.',
						'woocommerce-square'
					)}
				</SectionDescription>

				<div className='woo-square-wizard__fields'>
					{ 'settings' !== origin &&
						<InputWrapper
							label={ __( 'Enable Square Gift Cards', 'woocommerce-square' ) }
							variant="boxed"
						>
							<ToggleControl
								className="gift-card-gateway-toggle-field"
								data-testid="gift-card-gateway-toggle-field"
								checked={ 'yes' === enabled }
								onChange={ ( enabled ) => setGiftCardData( { enabled: enabled ? 'yes' : 'no' } ) }
							/>
						</InputWrapper>
					}

					{ 'settings' === origin &&
						<InputWrapper
							label={ __( 'Enable / Disable', 'woocommerce-square' ) }
							>
							<SquareCheckboxControl
								className="gift-card-gateway-toggle-field"
								data-testid="gift-card-gateway-toggle-field"
								label={ __( 'Enable this payment method.', 'woocommerce-square' ) }
								checked={ 'yes' === enabled }
								onChange={ ( enabled ) => setGiftCardData( { enabled: enabled ? 'yes' : 'no' } ) }
							/>
						</InputWrapper>
					}
				</div>
			</Section>
		</>
	);
};
