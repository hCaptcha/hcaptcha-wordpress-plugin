// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Login Signup Popup', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-login-signup-popup.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha after login/signup popup ajax success only', () => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'xoo_el_form_action=login' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
