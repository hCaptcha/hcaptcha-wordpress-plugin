// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

describe( 'hCaptcha Events Manager', () => {
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '<form class="em-booking-form"></form><form class="other-form"></form>';
		global.jQuery = $;
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		require( '../../../assets/js/hcaptcha-events-manager.js' );
	} );

	afterEach( () => {
		$( document ).off( 'em_booking_ajax_complete' );
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
		delete global.jQuery;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'refreshes hCaptcha and FST after a booking AJAX request', () => {
		const bookingForm = document.querySelector( '.em-booking-form' );

		$( document ).trigger( 'em_booking_ajax_complete', [ null, null, bookingForm ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated AJAX events', () => {
		const otherForm = document.querySelector( '.other-form' );

		$( document ).trigger( 'em_booking_ajax_complete', [ null, null, otherForm ] );
		$( document ).trigger( 'em_booking_ajax_complete', [ null, null, null ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );

	test( 'refreshes hCaptcha when FST is disabled', () => {
		const bookingForm = document.querySelector( '.em-booking-form' );

		delete window.hCaptchaFST;

		expect( () => {
			$( document ).trigger( 'em_booking_ajax_complete', [ null, null, bookingForm ] );
		} ).not.toThrow();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
