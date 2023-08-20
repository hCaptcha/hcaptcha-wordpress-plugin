/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( params.get( 'quform_submit' ) !== 'submit' ) {
		return;
	}

	let type;

	try {
		const response = JSON.parse( xhr.responseText );
		type = response.type;
	} catch ( e ) {
		return;
	}

	if ( type !== 'success' ) {
		return;
	}

	const formId = params.get( 'quform_form_id' );
	const form = jQuery( 'input[name="quform_form_id"][value="' + formId + '"]' ).closest( 'form' );

	window.hCaptchaReset( form[ 0 ] );
} );
