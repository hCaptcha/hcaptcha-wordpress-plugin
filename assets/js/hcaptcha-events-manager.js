/**
 * Events Manager script file.
 */

/* global jQuery */

jQuery( document ).on( 'em_booking_ajax_complete', function( event, response, status, bookingForm ) {
	if ( ! bookingForm?.matches( 'form.em-booking-form' ) ) {
		return;
	}

	window.hCaptchaBindEvents();

	if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
		window.hCaptchaFST.getToken();
	}
} );
