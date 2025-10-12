module.exports = {
	root: true,
	extends: [
		'@woocommerce/eslint-plugin/recommended',
	],
	env: {
		browser: true,
		es6: true,
		node: true,
	},
	parserOptions: {
		ecmaVersion: 2020,
		sourceType: 'module',
	},
	globals: {
		kickbox_integration_admin: 'readonly',
		kickbox_integration_checkout: 'readonly',
	},
	rules: {
		'@wordpress/no-global-event-listener': 'off',
		'@wordpress/no-global-get-selection': 'off',
	},
};
