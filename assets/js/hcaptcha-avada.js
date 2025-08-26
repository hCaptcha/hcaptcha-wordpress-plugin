/* global jQuery */

const hCaptchaAvada = window.hCaptchaAvada || ( function( window, $ ) {
	const app = {
		init() {
			$( app.ready );
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		// jQuery ajaxSuccess handler.
		ajaxSuccessHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'fusion_form_submit_ajax' ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaAvada = hCaptchaAvada;

hCaptchaAvada.init();
