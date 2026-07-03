/* global acf, jQuery */

const hCaptchaACFE = window.hCaptchaACFE || ( function( window, $ ) {
	const app = {
		savedOnLoad: null,

		init() {
			app.blockRecaptchaScript();

			// Disable the native ACFE reCAPTCHA controller before ACF initializes fields.
			app.disableRecaptchaField();
			app.removeRecaptchaNodes();

			// Install custom callbacks to sync token into ACFE hidden field.
			app.installCallbacks();

			// Sync tokens even when the widget was rendered before this integration wrapped params.callback.
			document.addEventListener( 'hCaptchaSubmitted', app.submittedHandler );

			// Bind ajaxComplete event to re-bind hCaptcha after AJAX requests.
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );

			// Back/forward cache can restore an already rendered native reCAPTCHA widget.
			window.addEventListener( 'pageshow', app.pageShowHandler );
		},

		blockRecaptchaScript() {
			if ( typeof $.ajaxPrefilter !== 'function' ) {
				return;
			}

			$.ajaxPrefilter( ( options, originalOptions, jqXHR ) => {
				const url = options.url || originalOptions?.url || '';

				if ( ! app.isRecaptchaUrl( url ) ) {
					return;
				}

				jqXHR.abort();
			} );
		},

		isRecaptchaUrl( url ) {
			const recaptchaUrl = String( url ).toLowerCase();

			return recaptchaUrl.includes( 'google.com/recaptcha' ) || recaptchaUrl.includes( 'recaptcha.net/recaptcha' );
		},

		disableRecaptchaField() {
			if ( typeof acf === 'undefined' || typeof acf.getFieldType !== 'function' ) {
				return;
			}

			const reCaptchaField = acf.getFieldType( 'acfe_recaptcha' );

			if ( ! reCaptchaField || ! reCaptchaField.prototype ) {
				return;
			}

			reCaptchaField.prototype.initialize = function() {};
			reCaptchaField.prototype.render = function() {};
			reCaptchaField.prototype.reset = function() {};
			reCaptchaField.prototype.onInvalidField = function() {};
		},

		removeRecaptchaNodes() {
			document.querySelectorAll( '.acfe-field-recaptcha iframe' ).forEach( ( el ) => {
				const src = el.getAttribute( 'src' ) || '';
				const title = el.getAttribute( 'title' ) || '';
				const isRecaptcha = ( src + title ).toLowerCase().includes( 'recaptcha' );

				if ( ! isRecaptcha ) {
					return;
				}

				const widget = el.closest( '.g-recaptcha' ) || el.parentElement;

				if ( widget ) {
					widget.remove();
				}
			} );

			document.querySelectorAll( '.acfe-field-recaptcha .g-recaptcha, .acfe-field-recaptcha .grecaptcha-badge' ).forEach( ( el ) => {
				el.remove();
			} );
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
				if ( typeof acf !== 'undefined' && typeof acf.val === 'function' ) {
					acf.val( $( el ), response, true );
				} else {
					el.value = response;
				}

				el.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );

			if ( callback ) {
				callback( response );
			}
		},

		submittedHandler( event ) {
			app.updateHidden( event.detail?.token || '' );
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

		pageShowHandler() {
			app.disableRecaptchaField();
			app.removeRecaptchaNodes();
			app.ajaxCompleteHandler();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaACFE = hCaptchaACFE;

hCaptchaACFE.init();
