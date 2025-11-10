/* global jQuery, HCaptchaPlaygroundObject */

const hCaptchaPlayground = window.hCaptchaPlayground || ( function( window, $ ) {
	const app = {
		init() {
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		// jQuery ajaxSuccess handler.
		ajaxSuccessHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'hcaptcha-integrations-activate' ) {
				return;
			}

			app.updateMenu();
		},

		updateMenu() {
			const data = {
				action: HCaptchaPlaygroundObject.action,
				nonce: HCaptchaPlaygroundObject.nonce,
			};

			$.post( {
				url: HCaptchaPlaygroundObject.ajaxUrl,
				data,
			} )
				.done( function( response ) {
					if ( ! response.success ) {
						return;
					}

					response.data.forEach( ( item ) => {
						$( `#wp-admin-bar-${ item.id } a` ).attr( 'href', item.href );
					} );
				} );
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaPlayground = hCaptchaPlayground;

hCaptchaPlayground.init();
