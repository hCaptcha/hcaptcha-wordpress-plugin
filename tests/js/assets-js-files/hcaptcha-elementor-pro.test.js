// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import jquery from 'jquery';

// Set up global jQuery for the script under test
global.jQuery = jquery;
global.$ = jquery;

// Import the script to test
require( '../../../assets/js/hcaptcha-elementor-pro' );

describe( 'Elementor Frontend hCaptcha', () => {
	beforeEach( () => {
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Ensure elementorFrontend is defined so hCaptchaElementorPro does not early-return
		global.elementorFrontend = {};

		// Reset possible global callback
		window.hCaptchaBindEvents = jest.fn();
	} );

	test( 'registers wp filter with correct arguments when elementorFrontend is present', () => {
		// Call entry function (normally invoked on jQuery ready)
		window.hCaptchaElementorPro();

		expect( wp.hooks.addFilter ).toHaveBeenCalledTimes( 1 );
		expect( wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.params',
			'hcaptcha',
			expect.any( Function )
		);
	} );

	test( 'triggers hCaptchaBindEvents on ajaxSuccess for Elementor Pro form submission', () => {
		// Fire jQuery ajaxSuccess with matching action
		const fakeXhr = {};
		const settings = { data: 'action=elementor_pro_forms_send_form&foo=bar' };
		jquery( document ).trigger( 'ajaxSuccess', [ fakeXhr, settings ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
