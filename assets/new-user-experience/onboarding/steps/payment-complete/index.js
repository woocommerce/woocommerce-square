/* eslint-disable import/named */
/**
 * External dependencies.
 */
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	__experimentalDivider as Divider, // eslint-disable-line @wordpress/no-unsafe-wp-apis
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { SectionTitle, SectionDescription } from '../../../components';
import { RightArrowInCircle, Sync, Manage } from '../../../icons';
import { useSteps, usePaymentGatewaySettings } from '../../hooks';
import { recordEvent, queueRecordEvent, ONBOARDING_TRACK_EVENTS } from '../../../../tracks';

export const PaymentComplete = () => {
	const {
		paymentGatewaySettings,
		giftCardsGatewaySettings,
		cashAppGatewaySettings,
	} = usePaymentGatewaySettings();

	const creditCardEnabled = paymentGatewaySettings.enabled;
	const digitalWalletsEnabled = paymentGatewaySettings.enable_digital_wallets;
	const giftCardsEnabled = giftCardsGatewaySettings.enabled;
	const cashAppEnabled = cashAppGatewaySettings.enabled;

	const { setStep } = useSteps();

	return (
		<>
			<div className="woo-square-onbarding__payment-settings">
				<div className="woo-square-onbarding__payment-settings--left">
					<div className="woo-square-onbarding__payment-settings__intro">
						<div className="woo-square-onbarding__payment-settings__intro--title">
							{__('Congratulations,', 'woocommerce-square')}
							<br />
							{__(
								'Your Payment Setup is Complete!',
								'woocommerce-square'
							)}
						</div>
						<SectionDescription>
							{__(
								'Your online store is now equipped to accept payments, making you ready to welcome customers and start generating sales.',
								'woocommerce-square'
							)}
							<p>
								{__(
									"Ready to see your store in action? Explore the front-end of your online shop. It's a great way to experience what your customers will see and ensure everything looks perfect.",
									'woocommerce-square'
								)}
							</p>
						</SectionDescription>
						<Button
							variant="button-primary"
							className="button-primary"
							onClick={ () => {
								queueRecordEvent(
									ONBOARDING_TRACK_EVENTS.VISIT_STOREFRONT_CLICKED
								);
								window.location.href = window.wcSettings.homeUrl;
							} }
						>
							{ __( 'Visit my Storefront', 'woocommerce-square' ) }
						</Button>
					</div>
					<div className="woo-square-onbarding__payment-settings__center-icon">
						<RightArrowInCircle />
					</div>
				</div>
				<div className="woo-square-onbarding__payment-settings--right">
					<div className="woo-square-onbarding__payment-settings__toggles">
						<SectionTitle
							title={__(
								'Synchronize your Items and Inventory',
								'woocommerce-square'
							)}
						/>
						<SectionDescription>
							{__(
								'Discover additional settings to further refine and personalize your e-commerce experience.',
								'woocommerce-square'
							)}
						</SectionDescription>

						<Divider margin="10" />

						<Flex direction={['column', 'row']}>
							<FlexItem className="flexItem iconBox">
								<Sync />
							</FlexItem>
							<FlexBlock className="flexItem contentBox">
								<b>
									{__(
										'Synchronize Your Inventory',
										'woocommerce-square'
									)}
								</b>
								<p>
									{__(
										'Sync your items and inventory effortlessly. Ensure your online and offline sales channels are always up to date.',
										'woocommerce-square'
									)}
								</p>
							</FlexBlock>
							<FlexItem>
								<Button
									data-testid="configure-sync-button"
									variant="secondary"
									onClick={ () => {
										recordEvent(
											ONBOARDING_TRACK_EVENTS.VISIT_SYNC_SETTINGS_CLICKED
										);
										setStep( 'sync-settings' )
									} }
								>
									{__(
										'Configure Sync Settings',
										'woocommerce-square'
									)}
								</Button>
							</FlexItem>
						</Flex>

						{(creditCardEnabled === 'yes' ||
							digitalWalletsEnabled === 'yes' ||
							cashAppEnabled === 'yes' ||
							giftCardsEnabled === 'yes') && (
							<>
								<Divider margin="10" />

								<Flex direction={['column', 'row']}>
									<FlexItem className="flexItem iconBox">
										<Manage />
									</FlexItem>
									<FlexBlock className="flexItem contentBox">
										<b>
											{__(
												'Manage Payment Methods',
												'woocommerce-square'
											)}
										</b>
										<p>
											{__(
												'Easily add, edit, or remove your credit cards, digital wallets, and Cash App settings to streamline your payments securely and efficiently.',
												'woocommerce-square'
											)}
										</p>
									</FlexBlock>
									<FlexItem>
										{creditCardEnabled === 'yes' && (
											<Button
												data-testid="credit-card-settings-button"
												variant="secondary"
												onClick={ () => {
													recordEvent(
														ONBOARDING_TRACK_EVENTS.VISIT_CREDIT_CARD_SETTINGS_CLICKED
													);
													setStep( 'credit-card' )
												} }
											>
												{__(
													'Credit Card Settings',
													'woocommerce-square'
												)}
											</Button>
										)}
										{digitalWalletsEnabled === 'yes' && (
											<Button
												data-testid="digital-wallet-settings-button"
												variant="secondary"
												onClick={ () => {
													recordEvent(
														ONBOARDING_TRACK_EVENTS.VISIT_DIGITAL_WALLET_SETTINGS_CLICKED
													);
													setStep( 'digital-wallets' )
												} }
											>
												{__(
													'Digital Wallet Settings',
													'woocommerce-square'
												)}
											</Button>
										)}
										{cashAppEnabled === 'yes' && (
											<Button
												data-testid="cash-app-settings-button"
												variant="secondary"
												onClick={ () => {
													recordEvent(
														ONBOARDING_TRACK_EVENTS.VISIT_CASH_APP_SETTINGS_CLICKED
													);
													setStep( 'cash-app' )
												} }
											>
												{__(
													'Cash App Pay Settings',
													'woocommerce-square'
												)}
											</Button>
										)}
										{giftCardsEnabled === 'yes' && (
											<Button
												data-testid="gift-card-settings-button"
												variant="secondary"
												onClick={ () => {
													recordEvent(
														ONBOARDING_TRACK_EVENTS.VISIT_GIFT_CARD_SETTINGS_CLICKED
													);
													setStep( 'gift-card' )
												} }
											>
												{__(
													'Gift Cards Settings',
													'woocommerce-square'
												)}
											</Button>
										)}
									</FlexItem>
								</Flex>
							</>
						)}

						<Divider margin="10" />

						<Flex direction={['column', 'row']} align="top">
							<FlexBlock>
								<Button
									data-testid="advanced-settings-button"
									variant="link"
									onClick={ () => {
										recordEvent(
											ONBOARDING_TRACK_EVENTS.VISIT_ADVANCED_SETTINGS_CLICKED
										);
										setStep( 'advanced-settings' )
									} }
								>
									{__(
										'Go to Advanced Settings',
										'woocommerce-square'
									)}
								</Button>
								<p className="adv-settings-copy">
									{__(
										'Gain greater control over your payment processes. Customize and manage detailed settings to optimize your transactions and checkout flow.',
										'woocommerce-square'
									)}
								</p>
							</FlexBlock>
							<FlexBlock>
								<Button
									data-testid="sandbox-settings-button"
									variant="link"
									onClick={ () => {
										recordEvent(
											ONBOARDING_TRACK_EVENTS.VISIT_SANDBOX_SETTINGS_CLICKED
										);
										setStep( 'sandbox-settings' )
									} }
								>
									{__(
										'Go to Sandbox Settings',
										'woocommerce-square'
									)}
								</Button>
								<p>
									{__(
										'Test new features and payment scenarios safely. Experiment in a risk-free environment to make sure everything is set up correctly before going live.',
										'woocommerce-square'
									)}
								</p>
							</FlexBlock>
						</Flex>
					</div>
				</div>
			</div>
		</>
	);
};
