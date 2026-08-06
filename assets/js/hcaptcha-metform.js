/* global jQuery */

import { helper } from './hcaptcha-helper.js';

const hCaptchaMetForm = window.hCaptchaMetForm || ( function( window, $ ) {
	const nonceName = 'hcaptcha_metform_nonce';
	const restRoute = '/metform/v1/entries/insert/';

	const app = {
		init() {
			helper.installFetchEvents();
			$( document ).on( 'metform/after_form_load.hcaptchaMetForm', '.mf-form-wrapper', app.bindEvents );
			$( document ).on( 'metform/before_submit.hcaptchaMetForm', '.mf-form-wrapper', app.beforeSubmit );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
			app.bindEvents();
		},

		bindEvents() {
			$( '.hcaptcha-metform-placeholder[data-hcaptcha-html]' ).each( function() {
				const placeholder = $( this );
				const html = window.decodeURIComponent( placeholder.attr( 'data-hcaptcha-html' ) );

				placeholder.html( html ).removeAttr( 'data-hcaptcha-html' );
			} );

			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}
		},

		beforeSubmit( event, formData ) {
			if ( ! formData || typeof formData !== 'object' ) {
				return;
			}

			Object.assign( formData, helper.getHCaptchaData( event.currentTarget, nonceName ) );
		},

		fetchComplete( event ) {
			const resource = event?.detail?.args?.[ 0 ];
			const url = typeof resource === 'string' ? resource : ( resource?.url || '' );

			if ( ! url.includes( restRoute ) ) {
				return;
			}

			app.bindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaMetForm = hCaptchaMetForm;

hCaptchaMetForm.init();
