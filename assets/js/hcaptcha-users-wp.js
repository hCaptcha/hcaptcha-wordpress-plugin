/* global jQuery */

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if (
		! (
			params.get( 'action' ) === 'uwp_ajax_forgot_password_form' ||
			params.get( 'action' ) === 'uwp_ajax_login_form' ||
			params.get( 'action' ) === 'uwp_ajax_register_form'
		)
	) {
		return;
	}

	window.hCaptchaBindEvents();
} );
