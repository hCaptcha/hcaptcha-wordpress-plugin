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
	let tokenExpiresAt = 0;
	let pendingRequest = null;
	let issueError = '';
	const pendingSubmissions = new WeakMap();
	const messages = {
		loading: 'Preparing the form. Please wait.',
		error: 'Unable to prepare the form. Please check your connection and try submitting again.',
		rateLimited: 'Too many requests (429). Please refresh the page and try again.',
		...HCaptchaFSTObject.messages,
	};

	/**
	 * Show a form preparation status outside the widget that is re-rendered.
	 *
	 * @param {HTMLElement} form    Protected form or container.
	 * @param {string}      message Message.
	 * @param {boolean}     error   Whether preparation failed.
	 */
	const showMessage = ( form, message = '', error = false ) => {
		let element = form.querySelector( '.hcaptcha-fst-message' );

		if ( ! message ) {
			element?.remove();
			return;
		}

		if ( ! element ) {
			element = document.createElement( 'div' );
			element.className = 'hcaptcha-fst-message';
			form.appendChild( element );
		}

		element.setAttribute( 'role', error ? 'alert' : 'status' );
		element.textContent = message;
	};

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

			document.addEventListener( 'click', app.handleClick, true );
			document.addEventListener( 'submit', app.handleSubmit, true );
		},

		isReady( form ) {
			return ! pendingRequest && currentToken && Date.now() < tokenExpiresAt &&
				form.querySelector( '[name="hcap_fst_token"]' )?.value === currentToken;
		},

		runWhenReady( form, callback, submitter = null ) {
			if ( pendingSubmissions.has( form ) ) {
				return pendingSubmissions.get( form );
			}

			if ( app.isReady( form ) ) {
				showMessage( form );
				callback();
				return Promise.resolve( true );
			}

			const disabled = submitter?.disabled;
			const busy = form.getAttribute( 'aria-busy' );

			if ( submitter && 'disabled' in submitter ) {
				submitter.disabled = true;
			}

			form.setAttribute( 'aria-busy', 'true' );
			showMessage( form, messages.loading );

			const submission = app.getToken().then( () => {
				pendingSubmissions.delete( form );

				if ( submitter && 'disabled' in submitter ) {
					submitter.disabled = disabled;
				}

				if ( busy === null ) {
					form.removeAttribute( 'aria-busy' );
				} else {
					form.setAttribute( 'aria-busy', busy );
				}

				if ( ! form.isConnected ) {
					return false;
				}

				if ( ! app.isReady( form ) ) {
					showMessage( form, issueError || messages.error, true );
					return false;
				}

				showMessage( form );
				callback();
				return true;
			} );

			pendingSubmissions.set( form, submission );
			return submission;
		},

		handleClick( event ) {
			if ( ! ( event.target instanceof Element ) ) {
				return;
			}

			const button = event.target.closest( window.hCaptcha?.submitButtonSelector || '[type="submit"]' );
			const form = button?.closest( window.hCaptcha?.formSelector || 'form' ) || button?.form;

			if ( ! form?.querySelector( '[name="hcap_fst_token"]' ) || app.isReady( form ) ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();
			app.runWhenReady( form, () => button.click(), button );
		},

		handleSubmit( event ) {
			const form = event.target;

			if ( ! form.querySelector( '[name="hcap_fst_token"]' ) || app.isReady( form ) ) {
				return;
			}

			event.preventDefault();
			event.stopImmediatePropagation();

			const submitter = event.submitter;

			app.runWhenReady( form, () => {
				if ( form.requestSubmit ) {
					form.requestSubmit( submitter || undefined );
				} else if ( submitter ) {
					submitter.click();
				} else if ( form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) ) ) {
					HTMLFormElement.prototype.submit.call( form );
				}
			}, submitter );
		},

		getToken() {
			if ( pendingRequest ) {
				return pendingRequest;
			}

			pendingRequest = ( async function() {
				const formBody = new URLSearchParams();
				const controller = new AbortController();
				// The timer is cleared in finally, including after a successful return.
				let timeout = setTimeout( () => controller.abort(), 10000 );
				issueError = '';

				formBody.set( 'action', HCaptchaFSTObject.issueTokenAction );
				formBody.set( 'nonce', HCaptchaFSTObject.issueTokenNonce );
				formBody.set( 'context', HCaptchaFSTObject.issueTokenContext );
				formBody.set( 'postId', HCaptchaFSTObject.postId );

				if ( currentToken ) {
					formBody.set( 'replaceToken', currentToken );
				}

				const requestToken = () => fetch( HCaptchaFSTObject.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					cache: 'no-store',
					signal: controller.signal,
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
					},
					body: formBody.toString(),
				} );
				let requestedAt = Date.now();

				try {
					let res = await requestToken();
					let body = await res.json();
					const freshNonce = body?.data?.nonce;

					if ( 403 === res.status && 'fst-invalid-nonce' === body?.data?.code && 'string' === typeof freshNonce && freshNonce ) {
						HCaptchaFSTObject.issueTokenNonce = freshNonce;
						formBody.set( 'nonce', freshNonce );
						clearTimeout( timeout );
						timeout = setTimeout( () => controller.abort(), 10000 );
						requestedAt = Date.now();
						res = await requestToken();
						body = await res.json();
					}

					const token = body?.data?.token ?? '';

					if ( res.ok && body?.success && token ) {
						const ttl = Number( body?.data?.ttl );
						const secondsLeft = Number.isFinite( ttl ) && ttl > 0 ? Math.max( ttl - 5, 1 ) : 55;
						tokenExpiresAt = requestedAt + ( secondsLeft * 1000 );
						currentToken = token;
						storeToken( currentToken );
						setToken( currentToken );

						return;
					}

					if ( 429 === res.status && 'fst-rate-limited' === body?.data?.code ) {
						issueError = messages.rateLimited;
					}
				} catch {
					// The server may have replaced the prior token before the response failed.
				} finally {
					clearTimeout( timeout );
				}

				currentToken = '';
				tokenExpiresAt = 0;
				storeToken( currentToken );
				setToken( issueError === messages.rateLimited ? rateLimitedToken : '' );
			}() );

			pendingRequest = pendingRequest.finally( () => {
				pendingRequest = null;
			} );

			return pendingRequest;
		},
	};

	return app;
}( document ) );

window.hCaptchaFST = fst;

fst.init();
