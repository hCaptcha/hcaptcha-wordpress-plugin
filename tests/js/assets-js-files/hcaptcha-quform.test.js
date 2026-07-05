// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Quform frontend', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-quform.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha after Quform submit ajax success only', () => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'quform_submit=preview' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'quform_submit=submit' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
