/* global jQuery */

import { helper } from './hcaptcha-helper.js';

const hCaptchaSendinblue = window.hCaptchaSendinblue || ( function( window, $ ) {
	const app = {
		action: 'subscribe_form_submit',

		init() {
			$.ajaxPrefilter( app.ajaxPrefilter() );
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		ajaxPrefilter() {
			return function( options ) {
				const data = options.data ?? '';

				if ( typeof data !== 'string' ) {
					return;
				}

				const urlParams = new URLSearchParams( data );
				const action = urlParams.get( 'sib_form_action' );

				if ( action !== app.action ) {
					return;
				}

				const $node = $( '.sib_signup_form' );

				helper.addHCaptchaData(
					options,
					action,
					'hcaptcha_sendinblue_nonce',
					$node,
				);
			};
		},

		// jQuery ajaxComplete handler.
		ajaxCompleteHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'sib_form_action' ) !== app.action ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaSendinblue = hCaptchaSendinblue;

hCaptchaSendinblue.init();
