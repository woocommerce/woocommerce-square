/**
 * External dependencies.
 */
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';
import { Back, Square, Close } from '../../icons';
import { useSteps } from './../../onboarding/hooks';

export const OnboardingHeader = () => {
	const {
		setStep,
		getBackStep,
	} = useSteps();

	const backStep = getBackStep();
	

	return (
		<div className="woo-square-onboarding__header">
			<Flex direction={[
				'column',
				'row'
			]}>
				<FlexItem className='flexItem backBtn'>
					{ backStep && (
						<Button
							data-testid="previous-step-button"
							onClick={ () => setStep( backStep ) }
						>
							<Back />
							<span>{ __( 'Back', 'woocommerce-square' ) }</span>
						</Button>
					) }
				</FlexItem>
				<FlexBlock className='wizardTitle'>
					<Square />
				</FlexBlock>
				<FlexItem className='flexItem closeWizard'>
					<Button href='/wp-admin/'>
						<Close />
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
};
