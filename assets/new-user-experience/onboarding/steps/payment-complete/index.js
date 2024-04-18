/**
 * External dependencies.
 */
import {
	Button,
	Flex,
	FlexBlock,
	FlexItem,
	__experimentalDivider as Divider,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import {
	SectionTitle,
	SectionDescription,
} from '../../../components';
import { RightArrowInCircle, Sync, Manage } from '../../../icons';

export const PaymentComplete = ( { setStep }) => {
	
	return (
		<>
			<div className="woo-square-onbarding__payment-settings">
				<div className="woo-square-onbarding__payment-settings--left">
					<div className="woo-square-onbarding__payment-settings__intro">
						<div className="woo-square-onbarding__payment-settings__intro--title">
							{ __( "Congratulations,", 'woocommerce-square' ) }
							<br />
							{ __( "Your Payment Setup is Complete!", 'woocommerce-square' ) }
						</div>
						<SectionDescription>
							{ __( "Your online store is now equipped to accept payments, making you ready to welcome customers and start generating sales.", 'woocommerce-square' ) }
							<p>
								{ __( "Ready to see your store in action? Explore the front-end of your online shop. It's a great way to experience what your customers will see and ensure everything looks perfect.", 'woocommerce-square' ) }
							</p>
						</SectionDescription>
					</div>
					<div className="woo-square-onbarding__payment-settings__center-icon">
						<RightArrowInCircle />
					</div>
				</div>
				<div className="woo-square-onbarding__payment-settings--right">
					<div className="woo-square-onbarding__payment-settings__toggles">
						<SectionTitle title={ __( 'Synchronize your Items and Inventory', 'woocommerce-square' ) } />
						<SectionDescription>
							{ __( 'Discover additional settings to further refine and personalize your e-commerce experience.', 'woocommerce-square' ) }
						</SectionDescription>

						<Divider margin="10"/>

						<Flex direction={[
							'column',
							'row'
						]}>
							<FlexItem className='flexItem iconBox'>
								<Sync />
							</FlexItem>
							<FlexBlock className='flexItem contentBox'>
								<b>{ __( 'Synchronize Your Inventory', 'woocommerce-square' ) }</b>
								<p>{ __( 'Sync your products and inventory effortlessly. Ensure your online and offline sales channels are always up to date.', 'woocommerce-square' ) }</p>
							</FlexBlock>
							<FlexItem>
								<Button variant="secondary" onClick={ () => setStep( 'sync-settings' ) }>
									{ __( 'Configure Sync Settings', 'woocommerce-square' ) }
								</Button>
							</FlexItem>
						</Flex>

						<Divider margin="10"/>

						<Flex direction={[
							'column',
							'row'
						]}>
							<FlexItem className='flexItem iconBox'>
								<Manage />
							</FlexItem>
							<FlexBlock className='flexItem contentBox'>
								<b>{ __( 'Manage Payment Methods', 'woocommerce-square' ) }</b>
								<p>{ __( 'Easily add, edit, or remove your credit cards, digital wallets, and Cash App settings to streamline your payments securely and efficiently.', 'woocommerce-square' ) }</p>
							</FlexBlock>
							<FlexItem>
								<Button variant="secondary" onClick={ () => setStep( 'credit-card' ) }>
									{ __( 'Credit Card Settings', 'woocommerce-square' ) }
								</Button>
								<Button variant="secondary" onClick={ () => setStep( 'digital-wallets' ) }>
									{ __( 'Digital Wallet Settings', 'woocommerce-square' ) }
								</Button>
								<Button variant="secondary" onClick={ () => setStep( 'cash-app' ) }>
									{ __( 'Cash App Pay Settings', 'woocommerce-square' ) }
								</Button>
							</FlexItem>
						</Flex>

						<Divider margin="10"/>

						<Flex direction={[
							'column',
							'row'
						]}>
							<FlexBlock>
								<Button variant="link" onClick={ () => setStep( 'advanced-settings' ) }>
									{ __( 'Go to Advanced Settings', 'woocommerce-square' ) }
								</Button>
								<p>{ __( 'Gain greater control over your payment processes. Customize and manage detailed settings to optimize your transactions and checkout flow.', 'woocommerce-square' ) }</p>
							</FlexBlock>
							<FlexBlock>
								<Button variant="link" onClick={ () => setStep( 'sandbox-settings' ) }>
									{ __( 'Go to Sandbox Settings', 'woocommerce-square' ) }
								</Button>
								<p>{ __( 'Test new features and payment scenarios safely. Experiment in a risk-free environment to make sure everything is set up correctly before going live.', 'woocommerce-square' ) }</p>
							</FlexBlock>
						</Flex>
					</div>
				</div>
			</div>
		</>
	);
};
