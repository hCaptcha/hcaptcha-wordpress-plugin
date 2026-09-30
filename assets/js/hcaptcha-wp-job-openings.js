/**
 * WP Job Openings script file.
 */

/* global jQuery */

jQuery( document ).on(
	'awsmjobs_application_failed awsmjobs_application_submitted',
	'.awsm-application-form',
	function() {
		if ( typeof window.hCaptchaReset === 'function' ) {
			window.hCaptchaReset( this );
		}

		this.querySelectorAll( '[name="h-captcha-response"], [name="g-recaptcha-response"]' ).forEach( ( response ) => {
			response.value = '';
		} );

		if ( window.hCaptchaFST ) {
			window.hCaptchaFST.getToken();
		}
	},
);
