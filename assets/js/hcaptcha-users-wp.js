/* global jQuery */

const formActions = [
	'uwp_ajax_forgot_password_form',
	'uwp_ajax_login_form',
	'uwp_ajax_register_form',
];

const submitActions = {
	uwp_ajax_forgot_password: 'form.uwp-forgot-form',
	uwp_ajax_login: 'form.uwp-login-form',
	uwp_ajax_register: 'form.uwp-registration-form',
};

jQuery( document ).on( 'ajaxSuccess', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );

	if ( ! formActions.includes( params.get( 'action' ) ) ) {
		return;
	}

	window.hCaptchaBindEvents();
} );

jQuery( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
	const params = new URLSearchParams( settings.data );
	const formSelector = submitActions[ params.get( 'action' ) ];

	if ( ! formSelector ) {
		return;
	}

	document.querySelectorAll( formSelector ).forEach( ( form ) => {
		if ( 'function' === typeof window.hCaptchaReset ) {
			window.hCaptchaReset( form );
		}

		form.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' )
			.forEach( ( response ) => {
				response.value = '';
			} );
	} );

	if ( 'function' === typeof window.hCaptchaFST?.getToken ) {
		window.hCaptchaFST.getToken();
	}
} );
