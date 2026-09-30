/* global jQuery */

/**
 * Easy Digital Downloads script file.
 */

const easyDigitalDownloads = window.hCaptchaEasyDigitalDownloads || ( function( document, window, $ ) {
	const app = {
		init() {
			$( document.body ).on( 'edd_checkout_error', app.checkoutErrorHandler );
		},

		checkoutErrorHandler() {
			const form = document.getElementById( 'edd_purchase_form' );

			if ( form ) {
				if ( typeof window.hCaptchaReset === 'function' ) {
					window.hCaptchaReset( form );
				}

				form.querySelectorAll( 'textarea[name="h-captcha-response"], textarea[name="g-recaptcha-response"]' )
					.forEach( ( response ) => {
						response.value = '';
					} );
			}

			if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
				window.hCaptchaFST.getToken();
			}
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaEasyDigitalDownloads = easyDigitalDownloads;

easyDigitalDownloads.init();
