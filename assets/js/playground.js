/* global jQuery, HCaptchaPlaygroundObject */

const hCaptchaPlayground = window.hCaptchaPlayground || ( function( window, document, $ ) {
	const app = {
		init() {
			app.fixMenu();
			$( document ).on( 'ajaxSuccess', app.ajaxSuccessHandler );
		},

		// Fix admin menu.
		fixMenu() {
			const host = window.location.hostname ?? '';

			let inIframe = false;

			try {
				inIframe = window.self !== window.top;
			} catch ( e ) {
				// If cross-origin blocks access to window.top, we are in an iframe.
				inIframe = true;
			}

			// Apply only on playground.wordpress.net.
			if ( inIframe && host === 'playground.wordpress.net' ) {
				const adminBar = document.getElementById( 'wpadminbar' );

				if ( adminBar ) {
					adminBar.style.marginTop = '4px';
				}
			}
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
}( window, document, jQuery ) );

window.hCaptchaPlayground = hCaptchaPlayground;

hCaptchaPlayground.init();
