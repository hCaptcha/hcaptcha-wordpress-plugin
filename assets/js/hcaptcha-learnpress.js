import { helper } from './hcaptcha-helper.js';

const hCaptchaLearnPress = window.hCaptchaLearnPress || ( function( window ) {
	const app = {
		init() {
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchComplete( event ) {
			const request = event?.detail?.args?.[ 0 ];

			if ( 'checkout' !== app.getAction( request ) ) {
				return;
			}

			app.resetForm();
			app.refreshFSTToken();
		},

		getAction( request ) {
			let url = request;

			if ( typeof Request !== 'undefined' && request instanceof Request ) {
				url = request.url;
			}

			if ( url instanceof URL ) {
				return url.searchParams.get( 'lp-ajax' ) ?? '';
			}

			if ( typeof url !== 'string' ) {
				return '';
			}

			try {
				return new URL( url, window.location.href ).searchParams.get( 'lp-ajax' ) ?? '';
			} catch {
				return '';
			}
		},

		resetForm() {
			const form = document.getElementById( 'learn-press-checkout-form' );

			if ( ! form ) {
				return;
			}

			if ( typeof window.hCaptchaReset === 'function' ) {
				window.hCaptchaReset( form );
			}

			form.querySelectorAll( 'textarea[name="h-captcha-response"], textarea[name="g-recaptcha-response"]' )
				.forEach( ( response ) => {
					response.value = '';
				} );
		},

		refreshFSTToken() {
			if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
				window.hCaptchaFST.getToken();
			}
		},
	};

	return app;
}( window ) );

window.hCaptchaLearnPress = hCaptchaLearnPress;

hCaptchaLearnPress.init();
