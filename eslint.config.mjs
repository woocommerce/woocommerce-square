import woocommerce from '@woocommerce/eslint-plugin';

export default [
	{
		ignores: [
			'**/*.min.js',
			'**/js/',
			'**/build/',
			'**/docker/',
			'**/bin/',
			'**/docs/',
			'**/includes/',
			'**/i18n/',
			'**/templates/',
			'**/vendor/',
			'**/tests/',
			'**/docker-compose.override.yml.template',
		],
	},
	...woocommerce.configs.recommended,
	{
		languageOptions: {
			globals: {
				_: 'readonly',
				Backbone: 'readonly',
				jQuery: 'readonly',
				wp: 'readonly',
				wc: 'readonly',
				wcSquareSettings: 'readonly',
			},
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
			'import/resolver': {
				node: {
					extensions: [ '.js', '.jsx', '.ts', '.tsx' ],
				},
			},
		},
		rules: {
			'no-console': 'off',
			'react/react-in-jsx-scope': 'off',
			'react-hooks/exhaustive-deps': 'off',
			'react-hooks/rules-of-hooks': 'off',
			camelcase: 'off',
			'@wordpress/i18n-text-domain': [
				'error',
				{
					allowedTextDomain: 'woocommerce-square',
				},
			],
		},
	},
	{
		// The Node build config is CommonJS, which the old
		// `@typescript-eslint/no-var-requires: off` override allowed.
		files: [ 'webpack.config.js' ],
		rules: {
			'@typescript-eslint/no-require-imports': 'off',
		},
	},
	{
		files: [ '**/assets/blocks/**/**.js' ],
		languageOptions: {
			globals: {
				jQuery: 'writable',
				browser: 'writable',
				wc: 'writable',
			},
		},
		rules: {
			camelcase: 'off',
			'no-alert': 'off',
			'no-shadow': 'off',
		},
	},
];
