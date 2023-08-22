/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'action' ) !== 'forminator_submit_form_custom-forms' ) {
		return;
	}

	const formId = params.get( 'form_id' );
	const form = jQuery( 'form[data-form-id="' + formId + '"]' );

	window.hCaptchaReset( form[ 0 ] );
} );
