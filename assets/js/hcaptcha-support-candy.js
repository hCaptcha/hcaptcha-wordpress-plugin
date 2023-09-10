/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'wpsc_get_ticket_form' ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
