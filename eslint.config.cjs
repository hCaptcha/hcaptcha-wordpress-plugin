const globals = require( 'globals' );
const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [ 'assets/js/apps/*.js', 'assets/js/*.min.js' ],
	},
	{
		languageOptions: {
			ecmaVersion: 2017,
			sourceType: 'module',
			globals: {
				...globals.browser,
				...globals.jest,
			},
		},
	},
	...wordpress.configs[ 'recommended-with-formatting' ],
];
