/* global jQuery */

/**
 * Base settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const settingsBase = function( $ ) {
	/**
	 * Highlight element if hash is present in the URL.
	 */
	function highLight() {
		const url = window.location.href;
		const referrer = document.referrer;

		if ( ! referrer || referrer === url ) {
			return;
		}

		const hash = window.location.hash;

		if ( ! hash ) {
			return;
		}

		const $element = $( hash );

		if ( ! $element ) {
			return;
		}

		if ( $element.is( ':checkbox' ) ) {
			$element.closest( 'fieldset' ).addClass( 'blink' );
		} else {
			$element.addClass( 'blink' );
		}
	}

	highLight();
};

window.hCaptchaSettingsBase = settingsBase;

jQuery( document ).ready( settingsBase );
