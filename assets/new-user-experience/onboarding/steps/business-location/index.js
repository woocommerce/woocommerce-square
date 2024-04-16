import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import parse from 'html-react-parser';

import {
	Section,
	SectionTitle,
	SectionDescription,
} from '../../../components';

import { useBusinessLocations } from '../../../settings/hooks';

export const BusinessLocation = () => {
	useBusinessLocations();
	return (
		<div>
			<Section>
				<SectionTitle title={ __( 'Select your business location', 'woocommerce-square' ) } />
				<SectionDescription>
					{ parse(
						sprintf(
							__( 'Select a location to link to this site. Only active %1$slocations%2$s that support credit card processing in Square can be linked.' ),
							'<a target="_blank" href="https://docs.woocommerce.com/document/woocommerce-square/#section-4">',
							'</a>'
						)
					) }
				</SectionDescription>
			</Section>
		</div>
	);
};
