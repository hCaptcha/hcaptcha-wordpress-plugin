/* global jQuery */

/**
 * Base settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const settingsBase = function( $ ) {
	const h2Selector = '.hcaptcha-header h2';
	const msgSelector = '#hcaptcha-message';

	function setHeaderBarTop() {
		/**
		 * @type {HTMLElement}
		 */
		const adminBar = document.querySelector( '#wpadminbar' );

		/**
		 * @type {HTMLElement}
		 */
		const tabs = document.querySelector( '.hcaptcha-settings-tabs' );

		/**
		 * @type {HTMLElement}
		 */
		const headerBar = document.querySelector( '.hcaptcha-header-bar' );

		const isAbsolute = adminBar ? window.getComputedStyle( adminBar ).position === 'absolute' : true;
		const adminBarHeight = ( adminBar && ! isAbsolute ) ? adminBar.offsetHeight : 0;
		const tabsHeight = tabs ? tabs.offsetHeight : 0;
		// The -1 to put header bar a bit under tabs. It is a precaution when heights are in fractional pixels.
		const totalHeight = adminBarHeight + tabsHeight - 1;

		if ( tabs ) {
			tabs.style.top = `${ adminBarHeight }px`;
		}

		if ( headerBar ) {
			headerBar.style.top = `${ totalHeight }px`;
		}
	}

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

	// Move WP notices to the message area.
	$( h2Selector ).siblings().appendTo( msgSelector );

	window.addEventListener( 'resize', function() {
		setHeaderBarTop();
	} );

	setHeaderBarTop();

	highLight();
};

window.hCaptchaSettingsBase = settingsBase;

jQuery( document ).ready( settingsBase );
