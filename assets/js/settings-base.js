/* global jQuery */

import { helper } from './hcaptcha-helper.js';

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

	/**
	 * Set the header bar top position.
	 */
	function setHeaderBarTop() {
		const isAbsolute = adminBar ? window.getComputedStyle( adminBar ).position === 'absolute' : true;
		const adminBarHeight = ( adminBar && ! isAbsolute ) ? adminBar.offsetHeight : 0;
		const tabsHeight = tabs ? tabs.offsetHeight : 0;

		// The -1 to put the header bar a bit under tabs. It is a precaution when heights are in fractional pixels.
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

		const hash = window.location.hash.slice( 1 );

		if ( ! hash ) {
			return;
		}

		// Try to find by id.
		let element = document.getElementById( hash );

		if ( ! element ) {
			element = document.querySelector( `[name="hcaptcha_settings[${ hash }]"]` );
		}

		if ( ! element ) {
			return;
		}

		app.highlightElement( element );
	}

	/**
	 * Setup lightbox.
	 */
	function setupLightBox() {
		// Lightbox for images.
		$( document ).on( 'click', '.hcaptcha-lightbox', function( e ) {
			e.preventDefault();

			const imgSrc = $( this ).attr( 'href' );

			$( '#hcaptcha-lightbox-img' ).attr( 'src', imgSrc );
			$( '#hcaptcha-lightbox-modal' ).css( 'display', 'flex' );
		} );

		// Close lightbox by click on the background.
		$( '#hcaptcha-lightbox-modal' ).on( 'click', function() {
			$( this ).css( 'display', 'none' );
			$( '#hcaptcha-lightbox-img' ).attr( 'src', '' );
		} );
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

		/**
		 * Highlight element.
		 *
		 * @param {HTMLElement} element
		 */
		highlightElement( element ) {
			let target = element;

			if ( element?.type === 'checkbox' ) {
				target = element.closest( 'fieldset' );
			}

			target.classList.remove( 'blink' );

			const table = target.closest( 'table' );
			let sectionHeader = null;

			let prev = table?.previousElementSibling;

			while ( prev ) {
				if ( prev.tagName.toLowerCase() === 'h3' ) {
					sectionHeader = prev;

					break;
				}

				prev = prev.previousElementSibling;
			}

			if ( sectionHeader && sectionHeader.classList.contains( 'closed' ) ) {
				setTimeout( function() {
					sectionHeader.click();
				}, 100 );
			}

			setTimeout( function() {
				target.classList.add( 'blink' );
				target.scrollIntoView(
					{
						behavior: 'smooth',
						block: 'center',
					},
				);
			}, 200 );
		},
	};

	/**
	 * Make a referer to the current page.
	 *
	 * @return {string} Relative URL.
	 */
	const makeReferer = () => {
		// Form a "pure" url without one-time params.
		const url = new URL( window.location.href );

		url.searchParams.delete( '_wp_http_referer' );

		return url.toString();
	};

	$.ajaxPrefilter( function( options, original ) {
		// Filter admin-ajax.php only.
		if ( ! /admin-ajax\.php/.test( options.url ?? '' ) ) {
			return;
		}

		const action = helper.getAction( options, 'action' );

		// Filter only hCaptcha actions.
		if ( ! /^hcaptcha/.test( action ) ) {
			return;
		}

		const key = '_wp_http_referer';
		const val = makeReferer();

		// FormData.
		if ( options.data instanceof FormData ) {
			if ( ! options.data.has( key ) ) {
				options.data.append( key, val );
			}

			return;
		}

		// Object|string - merge accurate.
		if ( typeof options.data === 'string' ) {
			// String - just add our parameter.
			options.data = options.data + '&' + $.param( { [ key ]: val } );
		} else if ( options.data && typeof options.data === 'object' ) {
			// Object - add field.
			options.data = { ...original.data, [ key ]: val };
		} else {
			options.data = $.param( { [ key ]: val } );
		}
	} );

	// Move WP notices to the message area.
	$( h2Selector ).siblings().appendTo( msgSelector );

	window.addEventListener( 'resize', function() {
		setHeaderBarTop();
	} );

	setHeaderBarTop();

	highLight();

	setupLightBox();

	return app;
}( jQuery ) );

window.hCaptchaSettingsBase = settingsBase;

jQuery( document ).ready( settingsBase );
