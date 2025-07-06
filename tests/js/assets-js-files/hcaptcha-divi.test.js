// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Divi', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-divi.js' );
	} );

	afterEach( () => {
		global.hCaptchaBindEvents.mockRestore();
	} );

	test( 'hCaptchaBindEvents is called when ajaxStop event is triggered', () => {
		const xhr = {};
		const settings = {};

		settings.data = '?some_data&et_pb_contactform_submit_0=some_value';
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'hCaptchaBindEvents is not called when ajaxSuccess event is triggered with unexpected data', () => {
		const xhr = {};
		const settings = {};

		// Unexpected object having data which is not a string.
		settings.data = {};
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );
		expect( hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
