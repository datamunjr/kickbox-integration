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
		wckb_admin: 'readonly',
		wckb_checkout: 'readonly',
	},
	rules: {
		'@wordpress/no-global-event-listener': 'off',
		'@wordpress/no-global-get-selection': 'off',
	},
};
