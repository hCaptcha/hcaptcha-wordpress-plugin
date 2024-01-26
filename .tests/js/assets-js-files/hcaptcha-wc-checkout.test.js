// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Import the script you want to test
require( '../../../assets/js/hcaptcha-wc-checkout' );

// Simulate jQuery.ready
window.hCaptchaWC( $ );

describe( 'hCaptcha WooCommerce', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		hCaptchaBindEvents = jest.fn();
		window.hCaptchaBindEvents = hCaptchaBindEvents;
	} );

	afterEach( () => {
		window.hCaptchaBindEvents.mockRestore();
	} );

	test( 'checkout_error event triggers hCaptchaBindEvents', () => {
		const event = new CustomEvent( 'checkout_error' );
		document.body.dispatchEvent( event );

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'updated_checkout event triggers hCaptchaBindEvents', () => {
		const event = new CustomEvent( 'updated_checkout' );
		document.body.dispatchEvent( event );

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
