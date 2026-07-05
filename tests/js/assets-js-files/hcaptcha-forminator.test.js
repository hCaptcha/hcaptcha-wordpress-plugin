// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Forminator', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-forminator.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha after Forminator submit ajax completes only', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=forminator_submit_form_custom-forms' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
