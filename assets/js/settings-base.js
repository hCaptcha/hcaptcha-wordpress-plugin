/* global jQuery, HCaptchaSettingsBaseObject */

import { helper } from './hcaptcha-helper.js';

/**
 * @param HCaptchaSettingsBaseObject.ajaxUrl
 * @param HCaptchaSettingsBaseObject.toggleSectionAction
 * @param HCaptchaSettingsBaseObject.toggleSectionNonce
 */

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
	const headerBarSelector = '.hcaptcha-header-bar';

	/**
	 * @type {HTMLElement}
	 */
	const headerBar = document.querySelector( headerBarSelector );

	const h2Selector = '.hcaptcha-header h2';
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
	 * Get highlighted element by hash.
	 *
	 * @param {string} hash URL hash without a leading #.
	 * @return {HTMLElement|null} Highlighted element.
	 */
	function getHighlightElement( hash ) {
		if ( ! hash ) {
			return null;
		}

		// Try to find by id.
		let element = document.getElementById( hash );

		if ( ! element ) {
			element = document.querySelector( `[name="hcaptcha_settings[${ hash }]"]` );
		}

		if ( ! element ) {
			return null;
		}

		return element;
	}

	/**
	 * Highlight the element by hash.
	 *
	 * @param {string} hash URL hash without a leading #.
	 */
	function highlightHash( hash ) {
		const element = getHighlightElement( hash );

		if ( ! element ) {
			return;
		}

		app.highlightElement( element );
	}

	/**
	 * Highlight the element if a hash is present in the URL.
	 */
	function highLight() {
		const url = window.location.href;
		const referrer = document.referrer;

		if ( ! referrer || referrer === url ) {
			return;
		}

		highlightHash( window.location.hash.slice( 1 ) );
	}

	/**
	 * Setup same-page hash links.
	 */
	function setupHashLinks() {
		$( document ).on( 'click', 'a[href*="#"]', function( event ) {
			const href = this.getAttribute( 'href' );

			if ( ! href || '#' === href ) {
				return;
			}

			const targetUrl = new URL( href, window.location.href );
			const currentUrl = new URL( window.location.href );

			if (
				targetUrl.origin !== currentUrl.origin ||
				targetUrl.pathname !== currentUrl.pathname ||
				targetUrl.search !== currentUrl.search ||
				! targetUrl.hash
			) {
				return;
			}

			const element = getHighlightElement( targetUrl.hash.slice( 1 ) );

			if ( ! element ) {
				return;
			}

			event.preventDefault();

			if ( window.location.hash !== targetUrl.hash ) {
				window.history.pushState( null, '', targetUrl.hash );
			}

			app.highlightElement( element );
		} );

		window.addEventListener( 'hashchange', function() {
			highlightHash( window.location.hash.slice( 1 ) );
		} );
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
			$( '<div id="hcaptcha-message">' + '</div>' ).insertAfter( '#hcaptcha-admin-notices' );
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

			if ( element?.type === 'select' || element?.type === 'select-multiple' ) {
				target = element.closest( 'td' );
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

	setupHashLinks();

	setupLightBox();

	// Toggle a section.
	$( '#hcaptcha-options h3.togglable' ).on( 'click', function( event ) {
		const $h3 = $( event.currentTarget );

		$h3.toggleClass( 'closed' );

		$.post( {
			url: HCaptchaSettingsBaseObject.ajaxUrl,
			data: {
				action: HCaptchaSettingsBaseObject.toggleSectionAction,
				nonce: HCaptchaSettingsBaseObject.toggleSectionNonce,
				section: $h3.attr( 'class' ).replaceAll( /(hcaptcha-section-|closed|togglable)/g, '' ).trim(),
				status: ! $h3.hasClass( 'closed' ),
			},
		} )
			.done( function( response ) {
				if ( ! response.success ) {
					app.showErrorMessage( response.data );
				}
			} )
			.fail(
				/**
				 * @param {Object} response
				 */
				function( response ) {
					app.showErrorMessage( response.statusText );
				},
			);
	} );

	return app;
}( jQuery ) );

window.hCaptchaSettingsBase = settingsBase;

jQuery( document ).ready( settingsBase );
