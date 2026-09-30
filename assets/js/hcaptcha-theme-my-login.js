/* global jQuery */

const hCaptchaThemeMyLogin = window.hCaptchaThemeMyLogin || ( function( window, $ ) {
	const app = {
		init() {
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		ajaxCompleteHandler( event, xhr, settings ) {
			const params = app.getParams( settings?.data );

			if ( '1' !== params.get( 'ajax' ) ) {
				return;
			}

			const form = app.getForm( settings?.url );

			if ( ! form ) {
				return;
			}

			app.resetForm( form );
			app.refreshFSTToken();
		},

		getParams( data ) {
			if ( typeof data !== 'string' ) {
				return new URLSearchParams();
			}

			return new URLSearchParams( data.startsWith( '?' ) ? data.slice( 1 ) : data );
		},

		getForm( requestUrl ) {
			if ( typeof requestUrl !== 'string' ) {
				return null;
			}

			let url;

			try {
				url = new URL( requestUrl, window.location.href );
			} catch {
				return null;
			}

			return [ ...document.querySelectorAll( '.tml form[data-ajax="1"]' ) ]
				.find( ( form ) => form.action === url.href ) ?? null;
		},

		resetForm( form ) {
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
}( window, jQuery ) );

window.hCaptchaThemeMyLogin = hCaptchaThemeMyLogin;

hCaptchaThemeMyLogin.init();
