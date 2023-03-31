const glob = require( 'glob' );
const path = require( 'path' );
const CssMinimizerWebpackPlugin = require( 'css-minimizer-webpack-plugin' );
const MiniCssExtractPlugin = require( 'mini-css-extract-plugin' );
const TerserPlugin = require( 'terser-webpack-plugin' );
const WebpackRemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );

const webPackModule = ( production ) => {
	return {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				loader: 'babel-loader',
				options: {
					presets: [ '@babel/preset-env' ],
				},
			},
			{
				test: /\.s?css$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
						options: {
							publicPath: path.join( __dirname, 'assets' ),
						},
					},
					{
						loader: 'css-loader',
						options: {
							sourceMap: ! production,
							url: false,
						},
					},
				],
			},
		],
	};
};

const lookup = ( lookupPath, prefix ) => {
	const ext = path.extname( lookupPath );
	const entries = {};

	glob.sync( lookupPath ).map( ( filePath ) => {
		if ( filePath.includes( '.min' + ext ) ) {
			return filePath;
		}

		let filename = path.basename( filePath, ext );

		if ( 'app' === filename ) {
			filename = path.basename( path.dirname( filePath ) );
		}

		entries[ prefix + '/' + filename ] = path.resolve( filePath );

		return filePath;
	} );

	return entries;
};

const hcaptcha = ( env ) => {
	/**
	 * @param env.production
	 */
	const production = env.production ? env.production : false;
	const cssEntries = lookup( './assets/css/*.css', 'css' );
	const jsEntries = lookup( './assets/js/*.js', 'js' );
	const appEntries = lookup( './src/js/**/app.js', 'js/apps' );

	const entries = {
		...cssEntries,
		...jsEntries,
		...appEntries,
	};

	return {
		devtool: production ? false : 'eval-source-map',
		entry: entries,
		module: webPackModule( production ),
		output: {
			path: path.join( __dirname, 'assets' ),
			filename: ( pathData ) => {
				return pathData.chunk.name.includes( 'apps' )
					? '[name].js'
					: '[name].min.js';
			},
		},
		plugins: [
			new WebpackRemoveEmptyScriptsPlugin(),
			new MiniCssExtractPlugin( {
				filename: '[name].min.css',
			} ),
		],
		optimization: {
			minimizer: [
				new TerserPlugin( {
					extractComments: false,
				} ),
				new CssMinimizerWebpackPlugin(),
			],
		},
	};
};

module.exports = hcaptcha;
