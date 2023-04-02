// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import jquery from 'jquery';

global.jQuery = jquery;
global.$ = jquery;

describe('hCaptcha ajaxStop binding', () => {
	let hCaptchaBindEvents;

	beforeEach(() => {
		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		require('../../../assets/js/hcaptcha-divi.js');
	});

	afterEach(() => {
		global.hCaptchaBindEvents.mockRestore();
	});

	test('hCaptchaBindEvents is called when ajaxStop event is triggered', () => {
		$(document).trigger('ajaxStop');
		expect(hCaptchaBindEvents).toHaveBeenCalledTimes(1);
	});
});
