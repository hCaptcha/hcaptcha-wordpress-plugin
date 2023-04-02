// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Mock Elementor Modules and Elementor Pro
import '../__mocks__/elementorModules';
import '../__mocks__/elementorPro';

// Import subject
import '../../../assets/js/hcaptcha-elementor-pro';

describe('hCaptcha Elementor', () => {
	let hooks;
	let item;
	let config;

	beforeEach(() => {
		hooks = {
			addFilter: jest.fn(),
		};
		global.elementor = {
			hooks,
		};

		item = {
			field_type: 'hcaptcha',
			custom_id: 'test_custom_id',
			css_classes: 'test_css_classes',
		};

		config = {
			enabled: true,
			site_key: 'test_site_key',
			hcaptcha_theme: 'light',
			hcaptcha_size: 'normal',
		};

		global._ = {
			escape: (str) => str,
		};
	});

	test('hooks are added and renderField is called with correct arguments', () => {
		const hCaptchaElementorInstance = window.hCaptchaElementorPro;

		hCaptchaElementorInstance.onInit();

		expect(hooks.addFilter).toHaveBeenCalledTimes(2);

		const renderedField = hCaptchaElementorInstance.renderField('', item);

		expect(renderedField).toContain('test_custom_id');
		expect(renderedField).toContain('test_css_classes');
		expect(renderedField).toContain('test_site_key');
		expect(renderedField).toContain('light');
		expect(renderedField).toContain('normal');
	});
});
