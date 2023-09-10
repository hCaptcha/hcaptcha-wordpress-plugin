// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha ajaxStop binding', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-support-candy.js' );
	} );

	afterEach( () => {
		global.hCaptchaBindEvents.mockRestore();
	} );

	test( 'hCaptchaBindEvents is called when ajaxStop event is triggered', () => {
		const xhr = {};
		const settings = {};

		settings.data = '?some_data&action=wpsc_get_ticket_form';
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
