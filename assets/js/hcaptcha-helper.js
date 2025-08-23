/* global jQuery */

export class helper {
	static addHCaptchaData( options, action, nonceName, $node ) {
		const data = options.data ?? '';

		if ( ! ( typeof data === 'string' && data.startsWith( `action=${ action }` ) ) ) {
			return;
		}

		options.data += helper.getHCaptchaData( $node, nonceName );
	}

	/**
	 * Get hCaptcha data from a node.
	 *
	 * @param {jQuery} $node     Node.
	 * @param {string} nonceName Nonce name.
	 * @return {string} Data.
	 */
	static getHCaptchaData( $node, nonceName ) {
		const hpName = $node.find( '[name^="hcap_hp_"]' ).first().attr( 'name' ) ?? '';
		const names = [ 'h-captcha-response', 'hcaptcha-widget-id', nonceName, hpName, 'hcap_hp_sig', 'hcap_fst_token' ];

		let data = '';

		for ( const name of names ) {
			if ( ! name ) {
				continue;
			}

			const val = $node.find( `[name="${ name }"]` ).first().val() ?? '';

			data += `&${ name }=${ val }`;
		}

		return data;
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
