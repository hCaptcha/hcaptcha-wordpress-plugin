/**
 * Classified Listing script file.
 */

/* global jQuery */

( function( $ ) {
	const actions = {
		rtcl_login_request: 'form#rtcl-login-form, form.rtcl-login-form',
		rtcl_registration_request: 'form#rtcl-register-form, form.rtcl-register-form',
		rtcl_public_send_contact_email: 'form#rtcl-contact-form, form.rtcl-contact-form',
	};

	$( document ).on( 'ajaxComplete', function( event, xhr, settings ) {
		if ( ! ( settings.data instanceof FormData ) ) {
			return;
		}

		const selector = actions[ settings.data.get( 'action' ) ];

		if ( ! selector ) {
			return;
		}

		const forms = document.querySelectorAll( selector );

		if ( ! forms.length ) {
			return;
		}

		forms.forEach( ( form ) => {
			if ( typeof window.hCaptchaReset === 'function' ) {
				window.hCaptchaReset( form );
			}

			form.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' ).forEach( ( response ) => {
				response.value = '';
			} );
		} );

		if ( window.hCaptchaFST ) {
			window.hCaptchaFST.getToken();
		}
	} );
}( jQuery ) );
