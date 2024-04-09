<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Update the wizard status.
update_option( 'wc_square_wizard_completed', true );

?>
<div id="wiz-outer">
	<div id="top-black-strip">
		<span id="setup-logo">
			<img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/assets/images/setup-logo.svg' ); ?>" />
		</span>
		<span id="wiz-title">
			<h1>Setup Wizard - Enable Payment Methods</h1>
		</span>
	</div>
	<div id="wiz-body">
		<div id="wiz-box-row">
			<div id="wiz-box-left">
				<div id="wiz-content center">
					<h2>You're connected to Square! <img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/assets/images/tada-icon.svg' ); ?>" /></h2>
					<p>Congratulations! You've successfully connected your Square account.</p>
					<p>Now, let's enable the payment methods you want to offer on your site. This is where you can tailor your checkout experience to meet your customers' needs.</p>
				</div>
			</div>
			<div id="wiz-box wiz-box-right">
				<div id="wiz-content center">
					<h2>Enable Payment Methods</h2>
					<p>Simply toggle the payment methods you wish to activate. Each method you enable here will be available to your customers at checkout, making their purchase process smooth and effortless.</p>
					<div id="wiz-payment-methods">
						<?php
						$payment_methods = $this->get_plugin()->get_gateways();

						foreach ( $payment_methods as $payment_method ) {
							$title = $payment_method->get_title();
							$gateway_id   = $payment_method->get_id();
							$url = admin_url( esc_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $gateway_id ) );
							$status = $payment_method->get_option( 'enabled', 'no' );
						}
						?>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
