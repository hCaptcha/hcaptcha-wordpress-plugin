/* global jQuery */

/**
 * Normalize a possibly jQuery-wrapped node to a plain DOM Element.
 *
 * @private
 *
 * @param {*} nodeOrJQ
 *
 * @return {HTMLElement} Returns a DOM Element (or document) representing the root for queries.
 */
function asElement( nodeOrJQ ) {
	if ( ! nodeOrJQ ) {
		return document;
	}

	// jQuery wrapper detection
	if ( nodeOrJQ.jquery || Array.isArray( nodeOrJQ ) ) {
		return nodeOrJQ[ 0 ] || document;
	}

	return nodeOrJQ;
}

/**
 * Safe value extractor for typical form controls.
 *
 * @private
 *
 * @param {Element|null} el
 *
 * @return {string} Returns the string value of the element (empty string if absent).
 */
function getElementValue( el ) {
	if ( ! el ) {
		return '';
	}

	// Prefer value property when available (input, select, textarea)
	const anyEl = /** @type {any} */ ( el );

	if ( 'value' in anyEl ) {
		return String( anyEl.value ?? '' );
	}

	return String( el.getAttribute( 'value' ) ?? '' );
}

export class helper {
	/**
	 * Adds hCaptcha data to AJAX options if the action matches.
	 *
	 * @param {Object}             options      The AJAX options object.
	 * @param {string}             options.data Query string of AJAX data.
	 * @param {string}             action       The AJAX action to match against.
	 * @param {string}             nonceName    The name of the nonce field to retrieve.
	 * @param {HTMLElement|jQuery} $node        DOM node or jQuery-wrapped node to search for hCaptcha fields.
	 *
	 * @return {void}
	 */
	static addHCaptchaData( options, action, nonceName, $node ) {
		const data = options.data ?? '';

		if ( typeof data !== 'string' ) {
			return;
		}

		// Parse existing query string to know which params are already present.
		const qs = data.startsWith( '?' ) ? data.slice( 1 ) : data;
		let params;

		try {
			params = new URLSearchParams( qs );
		} catch ( e ) {
			params = new URLSearchParams();
		}

		// Proceed only for the expected ajax call signature.
		if ( params.get( 'action' ) !== action ) {
			return;
		}

		const hCaptchaData = helper.getHCaptchaData( $node, nonceName );
		let append = '';

		// Append only missing keys.
		for ( const [ name, val ] of Object.entries( hCaptchaData ) ) {
			if ( params.has( name ) ) {
				continue;
			}

			append += `&${ name }=${ val }`;
		}

		options.data += append;
	}

	/**
	 * Get hCaptcha data from a node.
	 *
	 * @param {HTMLElement|jQuery} $node     Node or jQuery-wrapped node.
	 * @param {string}             nonceName Nonce field name to read.
	 *
	 * @return {Object} Returns a flat map of field names to values (plain object).
	 */
	static getHCaptchaData( $node, nonceName ) {
		const root = asElement( $node );
		const hpInput = root?.querySelector ? root.querySelector( '[name^="hcap_hp_"]' ) : null;
		const hpName = hpInput?.getAttribute( 'name' ) ?? '';
		const names = [ 'h-captcha-response', 'hcaptcha-widget-id', nonceName, hpName, 'hcap_hp_sig', 'hcap_fst_token' ];
		const hCaptchaData = {};

		for ( const name of names ) {
			if ( ! name ) {
				continue;
			}

			const el = root?.querySelector ? root.querySelector( `[name="${ name }"]` ) : null;

			hCaptchaData[ name ] = getElementValue( el );
		}

		return hCaptchaData;
	}

	/**
	 * Installs a composable wrapper around window.fetch and dispatches custom events.
	 * - Does not alter request/response behavior.
	 * - Returns the original Promise from fetch.
	 * - Safe to call multiple times (idempotent).
	 */
	static installFetchEvents() {
		if ( typeof window === 'undefined' || typeof window.fetch !== 'function' ) {
			return;
		}

		// Prevent double wrapping.
		if ( window.__hcapFetchWrapped ) {
			return;
		}

		( function( prevFetch ) {
			window.fetch = function( ...args ) {
				// Fire "before" event prior to the actual call.
				try {
					window.dispatchEvent(
						new CustomEvent( 'hCaptchaFetch:before', { detail: { args } } )
					);
				} catch ( e ) {
					// Never break the chain because of event listener errors.
				}

				const p = prevFetch( ...args );

				// Side-subscribe without altering the returned Promise.
				p.then( ( response ) => {
					try {
						window.dispatchEvent(
							new CustomEvent( 'hCaptchaFetch:success', {
								detail: { args, response: response.clone() },
							} )
						);
					} catch ( e ) {
					}
				} ).catch( ( error ) => {
					try {
						window.dispatchEvent(
							new CustomEvent( 'hCaptchaFetch:error', { detail: { args, error } } )
						);
					} catch ( e ) {
					}
				} ).finally( () => {
					try {
						window.dispatchEvent(
							new CustomEvent( 'hCaptchaFetch:complete', { detail: { args } } )
						);
					} catch ( e ) {
					}
				} );

				return p;
			};
		}( window.fetch ) );

		// Mark as wrapped (non-enumerable if possible).
		try {
			Object.defineProperty( window, '__hcapFetchWrapped', {
				value: true,
				configurable: true,
			} );
		} catch ( e ) {
			// Fallback if defineProperty is restricted.
			window.__hcapFetchWrapped = true;
		}
	}
}
