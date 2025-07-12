/* global jQuery, HCaptchaIcegramExpressObject */

/**
 * @param HCaptchaIcegramExpressObject.hCaptchaWidgets
 */

const icegramExpress = window.hCaptchaIcegramExpress || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		init() {
			$( window ).on( 'init.icegram', app.initIcegram );
			$( app.ready );
		},

		ready() {
		},

		initIcegram() {
			const hCaptchaWidgets = JSON.parse( HCaptchaIcegramExpressObject.hCaptchaWidgets );
			const clearFixDiv = '<div class="ig_clear_fix"></div>';

			$( '.es_form_container' ).each( function() {
				const last = $( this ).find( '.ig_form_els_last' );

				if ( ! last.length ) {
					return;
				}

				const formId = $( this ).data( 'form-id' );
				const hCaptchaHtml = hCaptchaWidgets[ formId ] ?? '';

				if ( ! hCaptchaHtml ) {
					return;
				}

				last.before( `${ clearFixDiv }<div class="ig_form_els">${ hCaptchaHtml }</div>${ clearFixDiv }` );
			} );
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaIcegramExpress = icegramExpress;

icegramExpress.init();
