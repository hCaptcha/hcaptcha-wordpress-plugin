/* global jQuery, HCaptchaWordfenceObject */
// noinspection ES6ShorthandObjectProperty

/**
 * @param HCaptchaWordfenceObject.noticeLabel
 * @param HCaptchaWordfenceObject.noticeDescription
 */

const wordfence = window.hCaptchaWordfence || ( function( document, window, $ ) {
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
			if ( ! app.getLocationHref().includes( 'page=WFLS' ) ) {
				return;
			}

			const $settings = $( '#wfls-hcaptcha-settings' );

			if ( ! $settings.length ) {
				return;
			}

			const notice = document.createElement( 'ul' );
			const noticeItem = document.createElement( 'li' );
			const noticeLabel = document.createElement( 'strong' );
			const noticeDescription = document.createElement( 'p' );

			notice.classList.add( 'wfls-block-list', 'hcaptcha-wordfence-notice' );
			noticeLabel.textContent = HCaptchaWordfenceObject.noticeLabel;
			noticeDescription.innerHTML = HCaptchaWordfenceObject.noticeDescription;
			noticeItem.append( noticeLabel, noticeDescription );
			notice.append( noticeItem );
			$settings.find( '.wfls-block-content' ).empty().append( notice );
		},

		getLocationHref() {
			return window.location.href;
		},
	};

	return app;
}( document, window, jQuery ) );

window.hCaptchaWordfence = wordfence;

wordfence.init();
