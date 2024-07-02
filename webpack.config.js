const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const WooDependencyExtractionWebpackPlugin = require('@woocommerce/dependency-extraction-webpack-plugin');
const path = require('path');

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooDependencyExtractionWebpackPlugin(),
	],
	entry: {
		...defaultConfig.entry(),
		index: './src/blocks/index.js',
		'cash-app-pay': './src/blocks/cash-app-pay/index.js',

		// nux
		onboarding: './src/new-user-experience/onboarding/index.js',
		settings: './src/new-user-experience/settings/index.js',

		// admin files.
		'assets/admin/wc-square-admin-products': './src/js/admin/wc-square-admin-products.js',
		'assets/admin/wc-square-admin-settings': './src/js/admin/wc-square-admin-settings.js',
		'assets/admin/wc-square-payment-gateway-admin-order': './src/js/admin/wc-square-payment-gateway-admin-order.js',
		'assets/admin/wc-square-payment-gateway-token-editor': './src/js/admin/wc-square-payment-gateway-token-editor.js',
		'assets/admin/wc-square-admin': './src/css/admin/wc-square-admin.scss',
		
		// frontend
		'assets/frontend/wc-square': './src/js/frontend/wc-square.js',
		'assets/frontend/wc-square-gift-card': './src/js/frontend/wc-square-gift-card.js',
		'assets/frontend/wc-square-cash-app-pay': './src/js/frontend/wc-square-cash-app-pay.js',
		'assets/frontend/wc-square-digital-wallet': './src/js/frontend/wc-square-digital-wallet.js',
		'assets/frontend/wc-square-payment-gateway-apple-pay': './src/js/frontend/wc-square-payment-gateway-apple-pay.js',
		'assets/frontend/wc-square-payment-gateway-my-payment-methods': './src/js/frontend/wc-square-payment-gateway-my-payment-methods.js',
		'assets/frontend/wc-square-payment-gateway-payment-form': './src/js/frontend/wc-square-payment-gateway-payment-form.js',
		'assets/frontend/wc-square-cart-checkout-blocks': './src/css/frontend/wc-square-cart-checkout-blocks.scss',
	},
};
