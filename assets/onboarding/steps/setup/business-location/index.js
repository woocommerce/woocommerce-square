import {
	Flex,
	FlexItem,
	Button,
	SelectControl,
} from "@wordpress/components";
import { __ } from '@wordpress/i18n';

import { ModalLayout } from '../../../components/modal-layout';
import { ModalHeader } from '../../../components/modal-header';
import { ModalDescription } from '../../../components/modal-description';
import { EmptySpacer } from '../../../components/spacer';

export const BusinessLocation = () => {
	const locations = [
		{},
	];

	const singleLocation = (
		<>
			<ModalHeader text={ __( 'Confirm Your Business Location', 'woocommerce-square' ) } />
			<ModalDescription>
				<div>{ __( "Great, you're nearly there! We've detected your business location as listed in Square.", 'woocommerce-square' ) }</div>
				<div>{ __( "Please confirm that this is the correct location where you'll be making sales:", 'woocommerce-square' ) }</div>
			</ModalDescription>
		</>
	);

	const multipleLocations = (
		<>
			<ModalHeader text={ __( 'Select Your Business Location', 'woocommerce-square' ) } />
			<ModalDescription htmlText={ __( "You're on your way! It looks like you have multiple business locations associated with your Square account.", 'woocommerce-square' ) } />
			<ModalDescription htmlText={ __( "Please select the location you wish to link with this WooCommerce store", 'woocommerce-square' ) } />
		</>
	);

	return (
		<ModalLayout>
			{ 1 == locations.length && singleLocation }
			{ 1 < locations.length && multipleLocations }

			<EmptySpacer height={ 80 } />

			<div style={ { maxWidth: '450px' } }>
				<SelectControl
					label={ __( 'Business Location', 'woocommerce-square' ) }
					options={[
					{
						disabled: true,
						label: 'Select an Option',
						value: ''
					},
					{
						label: 'Option A',
						value: 'a'
					},
					{
						label: 'Option B',
						value: 'b'
					},
					{
						label: 'Option C',
						value: 'c'
					}
					]}
				/>
			</div>

			<EmptySpacer height={ 180 } />

			<Flex justify="flex-end" gap="3">
				<FlexItem>
					<Button variant="primary">
						{ __( 'Back', 'woocommerce-square' ) }
					</Button>
				</FlexItem>
				<FlexItem>
					<Button variant="secondary">
						{ 1 < locations.length ? __( 'Next', 'woocommerce-square' ) : __( 'Confirm', 'woocommerce-square' ) }
					</Button>
				</FlexItem>
			</Flex>
		</ModalLayout>
	);
};
