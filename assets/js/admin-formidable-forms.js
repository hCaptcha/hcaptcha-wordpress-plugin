/* global jQuery, HCaptchaFormidableFormsObject */

/**
 * @param hCaptchaFormidableForms.noticeLabel
 * @param hCaptchaFormidableForms.noticeDescription
 */

const formidableForms = window.hCaptchaFormidableForms || ( function( document, window, $ ) {
	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		init() {
			$( app.ready );
		},

		ready() {
			if ( ! app.getLocationHref().includes( 'page=formidable-settings' ) ) {
				return;
			}

			const $howTo = $( '#hcaptcha_settings .howto' );

			$howTo.html( HCaptchaFormidableFormsObject.noticeLabel );
			$( '<p class="howto">' + HCaptchaFormidableFormsObject.noticeDescription + '</p>' ).insertAfter( $howTo );

			$( '#hcaptcha_settings input' ).attr( {
				disabled: true,
				class: 'frm_noallow',
			} );
		},

		getLocationHref() {
			return window.location.href;
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaFormidableForms = formidableForms;

formidableForms.init();
