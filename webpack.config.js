const glob = require('glob');
const path = require('path');
const CssMinimizerWebpackPlugin = require('css-minimizer-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const WebpackRemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');

const webPackModule = (production) => {
	return {
		rules: [
			{
				test: /\.js$/,
				exclude: /node_modules/,
				loader: 'babel-loader',
				options: {
					presets: ['@babel/preset-env'],
				},
			},
			{
				test: /\.s?css$/,
				use: [
					{
						loader: MiniCssExtractPlugin.loader,
						options: {
							publicPath: path.join(__dirname, 'assets'),
						},
					},
					{
						loader: 'css-loader',
						options: {
							sourceMap: !production,
							url: false,
						},
					},
				],
			},
		],
	};
};

const hcaptcha = (env) => {
	/**
	 * @param  env.production
	 */
	const production = env.production ? env.production : false;
	const cssEntries = {};
	const jsEntries = {};
	const appEntries = {
		hcaptcha: './src/js/hcaptcha/app.js',
	};

	glob.sync('./assets/css/*.css').map((entry) => {
		if (entry.includes('.min.css')) {
			return entry;
		}
		const filename = entry.replace(/^.*[\\\/]/, '').replace(/\..+$/, '');
		cssEntries[filename] = entry;
		return entry;
	});
	glob.sync('./assets/js/*.js').map((entry) => {
		if (entry.includes('.min.js')) {
			return entry;
		}
		const filename = entry.replace(/^.*[\\\/]/, '').replace(/\..+$/, '');
		jsEntries[filename] = entry;
		return entry;
	});

	const entries = {
		...cssEntries,
		...jsEntries,
		...appEntries,
	};

	return {
		devtool: production ? false : 'eval-source-map',
		entry: entries,
		module: webPackModule(production),
		output: {
			path: path.join(__dirname, 'assets'),
			filename: (pathData) => {
				return pathData.chunk.name === 'hcaptcha'
					? 'js/apps/[name].js'
					: 'js/[name].min.js';
			},
		},
		plugins: [
			new WebpackRemoveEmptyScriptsPlugin(),
			new MiniCssExtractPlugin({
				filename: 'css/[name].min.css',
			}),
		],
		optimization: {
			minimizer: [
				new TerserPlugin({
					extractComments: false,
				}),
				new CssMinimizerWebpackPlugin(),
			],
		},
	};
};

module.exports = hcaptcha;
