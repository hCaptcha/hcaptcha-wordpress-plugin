/* global jQuery */

const hCaptchaWPForms = window.hCaptchaWPForms || ( function( window, $ ) {
	const app = {
		init() {
			$( app.ready );
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		// jQuery ajaxSuccess handler.
		ajaxSuccessHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'wpforms_submit' ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaWPForms = hCaptchaWPForms;

hCaptchaWPForms.init();
