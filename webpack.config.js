const path = require( 'path' );

const webPackModule = () => {
	return {
		rules: [
			{
				loader: 'babel-loader',
				test: /\.js$/,
				exclude: /node_modules/,
				options: {
					presets: [ 'env' ],
				},
			},
		],
	};
};

const hcaptcha = ( env ) => {
	const isProduction = 'production' === env;

	return {
		entry: [ 'cross-fetch', './src/js/hcaptcha/app.js' ],
		output: {
			path: path.join( __dirname, 'assets', 'js' ),
			filename: path.join( 'hcaptcha', 'app.js' ),
		},
		module: webPackModule( ! isProduction ),
		devtool: isProduction ? '' : 'inline-source-map',
	};
};

module.exports = [ hcaptcha ];
