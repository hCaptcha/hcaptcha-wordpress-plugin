/* global jQuery */

const hCaptchaACFE = window.hCaptchaACFE || ( function( window, $ ) {
	const app = {
		savedOnLoad: null,

		init() {
			// Install custom callbacks to sync token into ACFE hidden field.
			app.installCallbacks();

			// Bind ajaxComplete event to re-bind hCaptcha after AJAX requests.
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		installCallbacks() {
			const params = window.hCaptcha.getParams();
			const savedCallback = params.callback;
			const savedErrorCallback = params[ 'error-callback' ];
			const savedExpiredCallback = params[ 'expired-callback' ];

			params.callback = ( response ) => app.updateHidden( response, savedCallback );
			params[ 'error-callback' ] = () => app.updateHidden( '', savedErrorCallback );
			params[ 'expired-callback' ] = () => app.updateHidden( '', savedExpiredCallback );

			window.hCaptcha.setParams( params );

			app.savedOnLoad = window.hCaptchaOnLoad;
			window.hCaptchaOnLoad = app.onLoadWrapper;
		},

		updateHidden( response, callback ) {
			[ ...document.querySelectorAll( '.acfe-field-recaptcha input[id^="acf-field_"]' ) ].forEach( ( el ) => {
				el.value = response;
			} );

			if ( callback ) {
				callback( response );
			}
		},

		onLoadWrapper() {
			window.hCaptchaOnLoad = app.savedOnLoad;
			window.hCaptchaOnLoad();
		},

		ajaxCompleteHandler() {
			// ACFE may perform AJAX validation; simply re-bind safely after requests.
			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaACFE = hCaptchaACFE;

hCaptchaACFE.init();
