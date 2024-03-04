const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require('path');

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new DependencyExtractionWebpackPlugin(),
	],
	entry: {
		...defaultConfig.entry(),
		'index': path.resolve(process.cwd(), 'assets/blocks', 'index.js'),
		'cash-app-pay': path.resolve(process.cwd(), 'assets/blocks/cash-app-pay', 'index.js'),
	}
};