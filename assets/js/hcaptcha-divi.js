/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	if (
		typeof settings.data.includes === 'function' &&
		! settings.data.includes( 'et_pb_contactform_submit_' )
	) {
		return;
	}

	window.hCaptchaBindEvents();
} );
