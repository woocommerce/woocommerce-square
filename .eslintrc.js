module.exports = {
	extends: [ 'plugin:@woocommerce/eslint-plugin/recommended' ],
	globals: {
		_: false,
		Backbone: false,
		jQuery: false,
		wp: false,
	},
	settings: {
		jsdoc: { mode: 'typescript' },
		// List of modules that are externals in our webpack config.
		// This helps the `import/no-extraneous-dependencies` and
		//`import/no-unresolved` rules account for them.
		'import/core-modules': [
			'@woocommerce/blocks-registry',
			'@woocommerce/settings',
			'@wordpress/i18n',
			'@wordpress/element',
		],
	},
	rules: {
		'no-console': 'off',
	},
	overrides: [
		{
			files: [ '**/assets/blocks/**/**.js' ],
			globals: {
				jQuery: true,
				browser: true,
				wc: true,
			},
			rules: {
				camelcase: 'off',
				'no-alert': 'off',
				'no-shadow': 'off',
			},
		},
	],
};
