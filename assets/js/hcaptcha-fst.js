/* global HCaptchaFSTObject */

/**
 * @param HCaptchaFSTObject.ajaxUrl
 * @param HCaptchaFSTObject.issueTokenAction
 * @param HCaptchaFSTObject.issueTokenNonce
 * @param HCaptchaFSTObject.issueTokenContext
 * @param HCaptchaFSTObject.postId
 */

/**
 * The 'Form Submit Token' script.
 *
 * @param {Document} document The document instance.
 */
const fst = window.hCaptchaFST || ( function( document ) {
	const rateLimitedToken = 'hcaptcha-fst-error:fst-rate-limited';
	const tokenStorageKey = [
		'hcaptcha-fst-token',
		HCaptchaFSTObject.issueTokenContext,
		window.location.pathname,
		window.location.search,
	].join( ':' );

	/**
	 * Read the token retained for the current browser tab.
	 *
	 * @return {string} Stored token.
	 */
	const getStoredToken = () => {
		try {
			return window.sessionStorage.getItem( tokenStorageKey ) ?? '';
		} catch {
			return '';
		}
	};

	/**
	 * Retain or clear the current browser tab token.
	 *
	 * @param {string} token Token value.
	 */
	const storeToken = ( token ) => {
		try {
			if ( token ) {
				window.sessionStorage.setItem( tokenStorageKey, token );
			} else {
				window.sessionStorage.removeItem( tokenStorageKey );
			}
		} catch {
			// Storage can be unavailable in privacy-restricted browser contexts.
		}
	};

	let currentToken = getStoredToken();
	let pendingRequest = null;

	/**
	 * Update all form timing fields together.
	 *
	 * @param {string} token Token value.
	 */
	const setToken = ( token ) => {
		document.querySelectorAll( '[name="hcap_fst_token"]' ).forEach( ( element ) => {
			element.value = token;
		} );
	};

	/**
	 * Public functions and properties.
	 *
	 * @type {Object}
	 */
	const app = {
		init() {
			app.getToken();

			document.addEventListener( 'hCaptchaAfterBindEvents', function() {
				app.getToken();
			} );
		},

		getToken() {
			if ( pendingRequest ) {
				return pendingRequest;
			}

			pendingRequest = ( async function() {
				const formBody = new URLSearchParams();
				let issueError = '';

				formBody.set( 'action', HCaptchaFSTObject.issueTokenAction );
				formBody.set( 'nonce', HCaptchaFSTObject.issueTokenNonce );
				formBody.set( 'context', HCaptchaFSTObject.issueTokenContext );
				formBody.set( 'postId', HCaptchaFSTObject.postId );

				if ( currentToken ) {
					formBody.set( 'replaceToken', currentToken );
				}

				try {
					const res = await fetch( HCaptchaFSTObject.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						cache: 'no-store',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
						},
						body: formBody.toString(),
					} );

					const body = await res.json();

					const token = body?.data?.token ?? '';

					if ( res.ok && body?.success && token ) {
						currentToken = token;
						storeToken( currentToken );
						setToken( currentToken );

						return;
					}

					if ( 429 === res.status && 'fst-rate-limited' === body?.data?.code ) {
						issueError = rateLimitedToken;
					}
				} catch {
					// The server may have replaced the prior token before the response failed.
				} finally {
					pendingRequest = null;
				}

				currentToken = '';
				storeToken( currentToken );
				setToken( issueError );
			}() );

			return pendingRequest;
		},
	};

	return app;
}( document ) );

window.hCaptchaFST = fst;

fst.init();
