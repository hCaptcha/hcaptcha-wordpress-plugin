/* global jQuery */

/**
 * Base settings page logic.
 *
 * @param {Object} $ jQuery instance.
 */
const settingsBase = ( function( $ ) {
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

	const h2Selector = '.hcaptcha-header h2';
	const headerBarSelector = '.hcaptcha-header-bar';
	const msgSelector = '#hcaptcha-message';
	let $message = $( msgSelector );

	function setHeaderBarTop() {
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

	/**
	 * Public properties and functions.
	 */
	const app = {
		getStickyHeight() {
			const isAbsolute = adminBar ? window.getComputedStyle( adminBar ).position === 'absolute' : true;
			const adminBarHeight = ( adminBar && ! isAbsolute ) ? adminBar.offsetHeight : 0;
			const tabsHeight = tabs ? tabs.offsetHeight : 0;
			const headerBarHeight = headerBar ? headerBar.offsetHeight : 0;

			return adminBarHeight + tabsHeight + headerBarHeight;
		},

		clearMessage() {
			$message.remove();
			// Concat below to avoid an inspection message.
			$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( headerBarSelector );
			$message = $( msgSelector );
		},

		showMessage( message = '', msgClass = '' ) {
			message = message === undefined ? '' : String( message );

			if ( ! message ) {
				return;
			}

			app.clearMessage();
			$message.addClass( msgClass + ' notice is-dismissible' );

			const messageLines = message.split( '\n' ).map( function( line ) {
				return `<p>${ line }</p>`;
			} );

			$message.html( messageLines.join( '' ) );

			$( document ).trigger( 'wp-updates-notice-added' );

			$( 'html, body' ).animate(
				{
					scrollTop: $message.offset().top - app.getStickyHeight(),
				},
				1000,
			);
		},

		showSuccessMessage( message = '' ) {
			app.showMessage( message, 'notice-success' );
		},

		showErrorMessage( message = '' ) {
			app.showMessage( message, 'notice-error' );
		},
	};

	// Move WP notices to the message area.
	$( h2Selector ).siblings().appendTo( msgSelector );

	window.addEventListener( 'resize', function() {
		setHeaderBarTop();
	} );

	setHeaderBarTop();

	highLight();

	return app;
}( jQuery ) );

window.hCaptchaSettingsBase = settingsBase;

jQuery( document ).ready( settingsBase );
