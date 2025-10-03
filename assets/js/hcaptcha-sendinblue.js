/* global jQuery */

import { helper } from './hcaptcha-helper.js';

const hCaptchaSendinblue = window.hCaptchaSendinblue || ( function( window, $ ) {
	const app = {
		actionName: 'sib_form_action',
		actionValue: 'subscribe_form_submit',

		init() {
			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		// jQuery ajaxComplete handler.
		ajaxCompleteHandler( event, xhr, settings ) {
			if ( ! helper.checkAction( settings, app.actionName, app.actionValue ) ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaSendinblue = hCaptchaSendinblue;

hCaptchaSendinblue.init();
