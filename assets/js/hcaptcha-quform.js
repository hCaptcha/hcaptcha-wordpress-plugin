/* global jQuery, hCaptchaBindEvents */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'quform_submit' ) !== 'submit' ) {
		return;
	}

	hCaptchaBindEvents();
} );
