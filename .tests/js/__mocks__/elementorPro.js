// noinspection JSUnresolvedReference

const elementorPro = {
	config: {
		forms: {
			hcaptcha: {
				enabled: true,
				setup_message: 'Setup message',
				site_key: 'test_site_key',
				hcaptcha_theme: 'light',
				hcaptcha_size: 'normal',
			},
		},
	},
};

global.elementorPro = elementorPro;

// noinspection JSUnusedGlobalSymbols
export default elementorPro;
