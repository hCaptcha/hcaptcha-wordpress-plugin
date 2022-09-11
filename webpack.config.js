const path = require('path');

const webPackModule = () => {
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
		],
	};
};

const hcaptcha = (env) => {
	const isProduction = env.production;

	return {
		entry: ['./src/js/hcaptcha/app.js'],
		output: {
			path: path.join(__dirname, 'assets', 'js'),
			filename: path.join('hcaptcha', 'app.js'),
		},
		module: webPackModule(),
		devtool: isProduction ? '' : 'inline-source-map',
	};
};

module.exports = hcaptcha;
