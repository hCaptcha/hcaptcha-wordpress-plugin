// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Avada', () => {
	beforeEach( () => {
		jest.resetModules();
		delete window.hCaptchaAvada;
		window.hCaptchaBindEvents = jest.fn();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		delete window.hCaptchaAvada;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'binds hCaptcha after Avada form ajax success only', () => {
		require( '../../../assets/js/hcaptcha-avada.js' );

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=fusion_form_submit_ajax' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaAvada = existingApp;

		require( '../../../assets/js/hcaptcha-avada.js' );

		expect( window.hCaptchaAvada ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
