/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	if ( ! settings.data.includes( 'et_pb_contactform_submit_' ) ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
