// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Import the script you want to test
require( '../../../assets/js/hcaptcha-wc-checkout' );

// Simulate jQuery.ready
window.hCaptchaWC( $ );

describe( 'hCaptcha WooCommerce', () => {
	let hCaptchaReset;
	let hCaptchaBindEvents;

	beforeEach( () => {
		hCaptchaReset = jest.fn();
		global.hCaptchaReset = hCaptchaReset;

		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;
	} );

	afterEach( () => {
		global.hCaptchaReset.mockRestore();
		global.hCaptchaBindEvents.mockRestore();
	} );

	test( 'checkout_error event triggers hCaptchaReset', () => {
		const event = new CustomEvent( 'checkout_error' );
		document.body.dispatchEvent( event );

		expect( hCaptchaReset ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'updated_checkout event triggers hCaptchaBindEvents and hCaptchaReset', () => {
		const event = new CustomEvent( 'updated_checkout' );
		document.body.dispatchEvent( event );

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( hCaptchaReset ).toHaveBeenCalledTimes( 1 );
	} );
} );
