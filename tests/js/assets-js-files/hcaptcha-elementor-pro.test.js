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

	test( 'registers wp filters with correct arguments when elementorFrontend is present', () => {
		// Call entry function (normally invoked on jQuery ready)
		window.hCaptchaElementorPro();

		expect( wp.hooks.addFilter ).toHaveBeenCalledTimes( 2 );
		expect( wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.params',
			'hcaptcha',
			expect.any( Function ),
		);
		expect( wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.ajaxSubmitButton',
			'hcaptcha',
			expect.any( Function ),
		);
	} );

	test( 'marks Elementor form submit buttons as Ajax buttons', () => {
		const form = document.createElement( 'form' );
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		form.classList.add( 'elementor-form' );
		form.appendChild( submitButton );
		window.hCaptchaElementorPro();

		const ajaxSubmitButtonFilter = wp.hooks.addFilter.mock.calls.find(
			( call ) => call[ 0 ] === 'hcaptcha.ajaxSubmitButton',
		)[ 2 ];

		expect( ajaxSubmitButtonFilter( false, submitButton ) ).toBe( true );
		expect( ajaxSubmitButtonFilter( false, otherButton ) ).toBe( false );
		expect( ajaxSubmitButtonFilter( true, otherButton ) ).toBe( true );
	} );

	test( 'triggers hCaptchaBindEvents on ajaxSuccess for Elementor Pro form submission', () => {
		// Fire jQuery ajaxSuccess with matching action
		const fakeXhr = {};
		const settings = { data: 'action=elementor_pro_forms_send_form&foo=bar' };
		jquery( document ).trigger( 'ajaxSuccess', [ fakeXhr, settings ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
