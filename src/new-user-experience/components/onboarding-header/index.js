/* eslint-disable import/named, no-undef */
/**
 * External dependencies.
 */
import { Button, Flex, FlexBlock, FlexItem } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './index.scss';
import { Back, Square, Close } from '../../icons';
import { useSteps } from './../../onboarding/hooks';
import { queueRecordEvent, ONBOARDING_TRACK_EVENTS } from '../../../tracks';

export const OnboardingHeader = () => {
	const { stepData, setStep, getBackStep } = useSteps();

	const backStep = getBackStep();

	return (
		<div className="woo-square-onboarding__header">
			<Flex direction={ [ 'column', 'row' ] }>
				<FlexItem className="flexItem backBtn">
					{ backStep && (
						<Button
							data-testid="previous-step-button"
							onClick={ () => setStep( backStep ) }
						>
							<Back />
							<span>{ __( 'Back', 'woocommerce' ) }</span>
						</Button>
					) }
				</FlexItem>
				<FlexBlock className="wizardTitle">
					<Square />
				</FlexBlock>
				<FlexItem className="flexItem closeWizard">
					<Button
						onClick={ () => {
							queueRecordEvent(
								ONBOARDING_TRACK_EVENTS.EXIT_CLICKED,
								{
									exited_on_step: stepData.step,
								}
							);
							window.location.href =
								wc.wcSettings.getAdminLink( '' );
						} }
					>
						<Close />
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
};
