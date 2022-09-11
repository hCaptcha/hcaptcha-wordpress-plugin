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
	return {
		entry: ['./src/js/hcaptcha/app.js'],
		output: {
			path: path.join(__dirname, 'assets', 'js'),
			filename: path.join('hcaptcha', 'app.js'),
		},
		module: webPackModule(),
		devtool: env.production ? false : 'eval-source-map',
	};
};

module.exports = hcaptcha;
