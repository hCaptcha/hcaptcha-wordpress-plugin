/* global jQuery, hCaptchaBindEvents */

jQuery( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'forminator_submit_form_custom-forms' ) {
		return;
	}

	hCaptchaBindEvents();
} );
