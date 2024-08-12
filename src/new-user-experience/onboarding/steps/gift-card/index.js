/**
 * External dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { ToggleControl, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
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

	const [ defaultPlaceholderUrl, setDefaultPlaceholderUrl ] = useState( wcSquareSettings.gcPlaceholderUrl );

	const { enabled, is_default_placeholder } = giftCardsGatewaySettings;

	if ( ! giftCardsGatewaySettingsLoaded ) {
		return null;
	}

	function openMediaLibrary() {
		const imageUploader = wp.media( {
			title: __( 'Select or Upload an image to use as the Gift card placeholder:' ),
			library : {
				type : 'image'
			},
			button: {
				text: 'Use this image'
			},
			multiple: false
		} ).on( 'select', function() {
			const attachment = imageUploader.state().get( 'selection' ).first().toJSON();

			setGiftCardData( { placeholder_id: attachment.id } );
			setDefaultPlaceholderUrl( attachment.url );
		} );

		imageUploader.open();
	}

	return (
		<>
			<Section>
				<SectionTitle
					title={ parse(
						sprintf(
							/* translators: %s: Gift Cards */
							__( 'Gift Cards %s', 'woocommerce-square' ),
							origin === 'settings'
								? `<small className="wc-admin-breadcrumb"><a href="${ wcSquareSettings.adminUrl }admin.php?page=wc-settings&amp;tab=checkout" ariaLabel="Return to payments">⤴</a></small>` // eslint-disable-line no-undef
								: ''
						)
					) }
				/>
				<SectionDescription>
					{ __(
						'You can receive payments with Square Gift Cards and sell Square Gift Cards by enabling the Gift Cards option here.',
						'woocommerce-square'
					) }
				</SectionDescription>

				<div className="woo-square-wizard__fields">
					{ origin !== 'settings' && (
						<InputWrapper
							label={ __(
								'Enable Square Gift Cards',
								'woocommerce-square'
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
						<>
							<InputWrapper
								label={ __(
									'Enable / Disable',
									'woocommerce-square'
								) }
							>
								<SquareCheckboxControl
									className="gift-card-gateway-toggle-field"
									data-testid="gift-card-gateway-toggle-field"
									label={ __(
										'Enable this payment method.',
										'woocommerce-square'
									) }
									checked={ enabled === 'yes' }
									onChange={ ( value ) =>
										setGiftCardData( {
											enabled: value ? 'yes' : 'no',
										} )
									}
								/>
							</InputWrapper>
							<InputWrapper
								label={ __(
									'Gift card product placeholder image',
									'woocommerce-square'
								) }
							>
								<SquareCheckboxControl
									className="gift-card-gateway-product-placeholder-toggle-field"
									data-testid="gift-card-gateway-product-placeholder-toggle-field"
									label={ __(
										'Enable to use the following image as the default placeholder for gift card products.',
										'woocommerce-square'
									) }
									checked={ is_default_placeholder === 'yes' }
									onChange={ ( value ) =>
										setGiftCardData( {
											is_default_placeholder: value ? 'yes' : 'no',
										} )
									}
								/>
								<img
									style={ { maxWidth: '350px' } }
									src={ defaultPlaceholderUrl }
								/>
								<Button
									variant='link'
									onClick={ openMediaLibrary }
									style={ { width: 'auto' } }
								>
									{ __( 'Replace image', 'woocommerce-square' ) }
								</Button>
							</InputWrapper>
						</>
					) }
				</div>
			</Section>
		</>
	);
};
