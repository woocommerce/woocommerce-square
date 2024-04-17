/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';

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

import { useSettings } from '../../hooks';

export const GiftCardSetup = () => {
	const { setGiftCardData, getGiftCardData } = useSettings();
	const {
		enable_gift_cards
	} = getGiftCardData();

	return (
		<>
			<Section>
				<SectionTitle title={ __( 'Gift Cards', 'woocommerce-square' ) } />
				<SectionDescription>
					{ __( 'You can receive payments with Square Gift Cards and sell Square Gift Cards by enabling the Gift Cards option here.', 'woocommerce-square' ) }
				</SectionDescription>

				<div className='woo-square-wizard__fields'>
					<InputWrapper
						label={ __( 'Enable Gift Cards', 'woocommerce-square' ) }
						variant="boxed"
					>
						<ToggleControl
							checked={ 'yes' === enable_gift_cards }
							onChange={ ( enable_gift_cards ) => setGiftCardData( { enable_gift_cards: enable_gift_cards ? 'yes' : 'no' } ) }
						/>
					</InputWrapper>
				</div>
			</Section>
		</>
	);
};
