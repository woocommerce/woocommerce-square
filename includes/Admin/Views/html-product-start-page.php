<?php
// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

// Redirect if square is already connected.
if ( $this->get_access_token() ) {
	wp_safe_redirect( admin_url( 'admin.php?page=square-wizard&step=payments' ) );
	exit;
}

// Update the wizard status. This is required if user navigates back to the wizard,
// And try to connect from here, we need to redirect them to the wizard page after
// successful connection, for that we need this to be false.
update_option( 'wc_square_wizard_completed', false );

?>
<div id="wiz-outer">
	<div id="top-black-strip">
		<span id="setup-logo">
			<img src="<?php echo esc_url( WC_SQUARE_PLUGIN_URL . '/assets/images/setup-logo.svg' ); ?>" />
		</span>
		<span id="wiz-title">
			<h1>Setup Wizard - Plugin Activated</h1>
		</span>
	</div>
	<div id="wiz-body">
		<div id="wiz-box">
			<div id="wiz-content center">
				<h2>Thanks for installing WooCommerce Square!</h2>
				<p>To get started, let’s connect to your Square Account to complete the setup process. </p>
			</div>
			<?php
				if ( $this->get_access_token() ) {
					echo $this->get_plugin()->get_connection_handler()->get_disconnect_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo $this->get_plugin()->get_connection_handler()->get_connect_button_html( $this->is_sandbox() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
			?>
		</div>
	</div>
</div>
