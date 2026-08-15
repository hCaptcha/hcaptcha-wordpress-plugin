/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const data = settings.data;
	const isLoginSignupPopupRequest =
		( typeof data === 'string' && data.includes( 'xoo_el_form_action' ) ) ||
		( typeof data?.get === 'function' && data.get( 'action' ) === 'xoo_el_form_action' ) ||
		data?.action === 'xoo_el_form_action';

	if ( ! isLoginSignupPopupRequest ) {
		return;
	}

	window.hCaptchaBindEvents();
} );
