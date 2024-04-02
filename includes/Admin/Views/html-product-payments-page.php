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
		<div id="wiz-box">
			<div id="wiz-content center">
				<h2>You’re connected to Square! <img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/assets/images/tada-icon.svg' ); ?>" /></h2>
			</div>
		</div>
	</div>
</div>
