/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'forminator_load_form' ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
