/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	if ( ! settings.data.includes( 'xoo_el_form_action' ) ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
