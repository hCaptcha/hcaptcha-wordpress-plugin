/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const data = settings.data ?? '';

	if ( ! ( typeof data === 'string' && data.includes( 'et_pb_contactform_submit_' ) ) ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
