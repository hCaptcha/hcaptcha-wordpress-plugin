// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha FST', () => {
	let listenerSpy;
	const waitForToken = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	const getTokenStorageKey = () => [
		'hcaptcha-fst-token',
		window.HCaptchaFSTObject.issueTokenContext,
		window.location.pathname,
		window.location.search,
	].join( ':' );
	const tokenResponse = ( token = 'ready-token' ) => ( {
		ok: true,
		status: 200,
		json: () => Promise.resolve( { success: true, data: { token } } ),
	} );

	beforeEach( () => {
		jest.resetModules();
		fetch.resetMocks();
		window.sessionStorage.clear();
		listenerSpy = jest.spyOn( document, 'addEventListener' );

		window.HCaptchaFSTObject = {
			ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
			issueTokenAction: 'hcaptcha-fst-issue-token',
			issueTokenNonce: 'nonce',
			issueTokenContext: '123|context-signature',
			postId: '123',
		};

		document.body.className = 'page-id-123';
		document.body.innerHTML = '<input name="hcap_fst_token" value="old-token">';
	} );

	afterEach( () => {
		listenerSpy.mock.calls.forEach( ( [ type, listener, options ] ) => {
			document.removeEventListener( type, listener, options );
		} );
		listenerSpy.mockRestore();
		delete window.HCaptchaFSTObject;
		delete window.hCaptchaFST;
		delete window.hCaptcha;
		document.body.className = '';
		document.body.innerHTML = '';
		window.sessionStorage.clear();
		jest.restoreAllMocks();
	} );

	test( 'coalesces issuance and atomically refreshes after hCaptcha bind events', async () => {
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: {
					token: 'new-token',
				},
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
		await waitForToken();

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( fetch.mock.calls[ 0 ][ 0 ] ).toBe( window.HCaptchaFSTObject.ajaxUrl );
		expect( fetch.mock.calls[ 0 ][ 1 ].body ).toContain( 'postId=123' );
		expect( fetch.mock.calls[ 0 ][ 1 ].body ).toContain( 'context=123%7Ccontext-signature' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'new-token' );
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBe( 'new-token' );

		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: {
					token: 'refreshed-token',
				},
			} ),
		);
		document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
		await waitForToken();

		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( fetch.mock.calls[ 1 ][ 1 ].body ).toContain( 'replaceToken=new-token' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'refreshed-token' );
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBe( 'refreshed-token' );
	} );

	test( 'replaces the previous tab token after a page reload', async () => {
		window.sessionStorage.setItem( getTokenStorageKey(), 'previous-page-token' );
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: {
					token: 'reloaded-page-token',
				},
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		await waitForToken();

		expect( fetch.mock.calls[ 0 ][ 1 ].body ).toContain( 'replaceToken=previous-page-token' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'reloaded-page-token' );
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBe( 'reloaded-page-token' );
	} );

	test( 'does not replace a token retained for another page context', async () => {
		window.sessionStorage.setItem( getTokenStorageKey(), 'another-page-token' );
		window.HCaptchaFSTObject.issueTokenContext = '456|another-context-signature';
		window.HCaptchaFSTObject.postId = '456';
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: {
					token: 'current-page-token',
				},
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		await waitForToken();

		expect( fetch.mock.calls[ 0 ][ 1 ].body ).not.toContain( 'replaceToken=' );
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBe( 'current-page-token' );
	} );

	test( 'clears an ambiguous prior token when a refresh response fails', async () => {
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: {
					token: 'new-token',
				},
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		await waitForToken();

		fetch.mockRejectOnce( new Error( 'network' ) );
		await window.hCaptchaFST.getToken();

		expect( fetch.mock.calls[ 1 ][ 1 ].body ).toContain( 'replaceToken=new-token' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( '' );
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBeNull();
	} );

	test( 'refreshes an aged token before resuming form submission', async () => {
		const now = jest.spyOn( Date, 'now' ).mockReturnValue( 100000 );

		try {
			document.body.innerHTML = '<form><input name="hcap_fst_token"></form>';
			const form = document.querySelector( 'form' );
			const submit = jest.fn();

			fetch.mockResponseOnce( JSON.stringify( {
				success: true,
				data: { token: 'first-token', ttl: 60 },
			} ) );
			fetch.mockResponseOnce( JSON.stringify( {
				success: true,
				data: { token: 'refreshed-token', ttl: 60 },
			} ) );

			require( '../../../assets/js/hcaptcha-fst.js' );
			await window.hCaptchaFST.getToken();
			expect( window.hCaptchaFST.isReady( form ) ).toBe( true );

			now.mockReturnValue( 156000 );
			expect( window.hCaptchaFST.isReady( form ) ).toBe( false );

			const result = await window.hCaptchaFST.runWhenReady( form, submit );

			expect( result ).toBe( true );
			expect( submit ).toHaveBeenCalledTimes( 1 );
			expect( fetch ).toHaveBeenCalledTimes( 2 );
			expect( form.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'refreshed-token' );
		} finally {
			now.mockRestore();
		}
	} );

	test( 'refreshes an invalid nonce and resumes the pending form submission', async () => {
		document.body.innerHTML = '<form><input name="hcap_fst_token"></form>';
		const form = document.querySelector( 'form' );
		const submit = jest.fn();

		fetch.mockResponseOnce(
			JSON.stringify( {
				success: false,
				data: { code: 'fst-invalid-nonce', nonce: 'fresh-nonce' },
			} ),
			{ status: 403 },
		);
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: true,
				data: { token: 'new-token' },
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		const result = await window.hCaptchaFST.runWhenReady( form, submit );

		expect( result ).toBe( true );
		expect( submit ).toHaveBeenCalledTimes( 1 );
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( new URLSearchParams( fetch.mock.calls[ 0 ][ 1 ].body ).get( 'nonce' ) ).toBe( 'nonce' );
		expect( new URLSearchParams( fetch.mock.calls[ 1 ][ 1 ].body ).get( 'nonce' ) ).toBe( 'fresh-nonce' );
		expect( window.HCaptchaFSTObject.issueTokenNonce ).toBe( 'fresh-nonce' );
		expect( form.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'new-token' );
		expect( form.querySelector( '.hcaptcha-fst-message' ) ).toBeNull();
	} );

	test( 'stops after one nonce retry when the server rejects it again', async () => {
		document.body.innerHTML = '<form><input name="hcap_fst_token"></form>';
		const form = document.querySelector( 'form' );
		const submit = jest.fn();

		fetch.mockResponseOnce(
			JSON.stringify( {
				success: false,
				data: { code: 'fst-invalid-nonce', nonce: 'fresh-nonce' },
			} ),
			{ status: 403 },
		);
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: false,
				data: { code: 'fst-invalid-nonce', nonce: 'another-nonce' },
			} ),
			{ status: 403 },
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		const result = await window.hCaptchaFST.runWhenReady( form, submit );

		expect( result ).toBe( false );
		expect( submit ).not.toHaveBeenCalled();
		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( form.querySelector( '.hcaptcha-fst-message' ).textContent ).toBe(
			'Unable to prepare the form. Please check your connection and try submitting again.',
		);
	} );

	test( 'preserves a rate limit error for server-side form validation', async () => {
		fetch.mockResponseOnce(
			JSON.stringify( {
				success: false,
				data: {
					code: 'fst-rate-limited',
				},
			} ),
			{ status: 429 },
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		await waitForToken();

		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe(
			'hcaptcha-fst-error:fst-rate-limited',
		);
		expect( window.sessionStorage.getItem( getTokenStorageKey() ) ).toBeNull();
	} );

	test.each( [ true, false ] )( 'continues when storage is unavailable on a %s token response', async ( success ) => {
		jest.spyOn( Storage.prototype, 'getItem' ).mockImplementation( () => {
			throw new Error( 'Storage unavailable.' );
		} );
		jest.spyOn( Storage.prototype, 'setItem' ).mockImplementation( () => {
			throw new Error( 'Storage unavailable.' );
		} );
		jest.spyOn( Storage.prototype, 'removeItem' ).mockImplementation( () => {
			throw new Error( 'Storage unavailable.' );
		} );
		fetch.mockResolvedValueOnce( success ? tokenResponse() : {
			ok: false,
			status: 400,
			json: () => Promise.resolve( { success: false } ),
		} );

		require( '../../../assets/js/hcaptcha-fst.js' );
		await window.hCaptchaFST.getToken();

		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( success ? 'ready-token' : '' );
	} );

	test( 'runs immediately when the form already has a fresh token', async () => {
		document.body.innerHTML = '<form><input name="hcap_fst_token"></form>';
		fetch.mockResolvedValueOnce( tokenResponse() );
		require( '../../../assets/js/hcaptcha-fst.js' );
		await window.hCaptchaFST.getToken();

		const form = document.querySelector( 'form' );
		const callback = jest.fn();

		await expect( window.hCaptchaFST.runWhenReady( form, callback ) ).resolves.toBe( true );
		expect( callback ).toHaveBeenCalledTimes( 1 );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'coalesces pending submissions and restores a detached form and submitter', async () => {
		let resolveRequest;
		fetch.mockImplementationOnce( () => new Promise( ( resolve ) => {
			resolveRequest = resolve;
		} ) );
		document.body.innerHTML = '<form aria-busy="waiting"><input name="hcap_fst_token"><button type="button">Send</button></form>';
		require( '../../../assets/js/hcaptcha-fst.js' );

		const form = document.querySelector( 'form' );
		const button = form.querySelector( 'button' );
		const callback = jest.fn();
		const first = window.hCaptchaFST.runWhenReady( form, callback, button );
		const second = window.hCaptchaFST.runWhenReady( form, callback, button );

		expect( second ).toBe( first );
		expect( button.disabled ).toBe( true );
		expect( form.getAttribute( 'aria-busy' ) ).toBe( 'true' );
		expect( form.querySelector( '.hcaptcha-fst-message' ).getAttribute( 'role' ) ).toBe( 'status' );

		form.remove();
		resolveRequest( tokenResponse() );
		await expect( first ).resolves.toBe( false );

		expect( button.disabled ).toBe( false );
		expect( form.getAttribute( 'aria-busy' ) ).toBe( 'waiting' );
		expect( callback ).not.toHaveBeenCalled();
	} );

	test( 'intercepts an unready external submit button using configured selectors', async () => {
		let resolveRequest;
		fetch.mockImplementationOnce( () => new Promise( ( resolve ) => {
			resolveRequest = resolve;
		} ) );
		document.body.innerHTML = '<form id="protected"><input name="hcap_fst_token"></form><button class="custom-submit" form="protected" type="button">Send</button>';
		window.hCaptcha = { submitButtonSelector: '.custom-submit', formSelector: '.custom-form' };
		require( '../../../assets/js/hcaptcha-fst.js' );

		const button = document.querySelector( 'button' );
		const click = jest.spyOn( button, 'click' ).mockImplementation( () => {} );
		const event = { target: button, preventDefault: jest.fn(), stopImmediatePropagation: jest.fn() };
		window.hCaptchaFST.handleClick( event );

		expect( event.preventDefault ).toHaveBeenCalledTimes( 1 );
		expect( button.disabled ).toBe( true );
		resolveRequest( tokenResponse() );
		await waitForToken();
		expect( click ).toHaveBeenCalledTimes( 1 );
		expect( button.disabled ).toBe( false );
		delete window.hCaptcha;
	} );

	test( 'ignores clicks outside protected forms and when the token is ready', async () => {
		document.body.innerHTML = '<form><input name="hcap_fst_token"><button type="submit">Send</button></form><button type="button">Other</button>';
		fetch.mockResolvedValueOnce( tokenResponse() );
		require( '../../../assets/js/hcaptcha-fst.js' );
		const app = window.hCaptchaFST;
		const preventDefault = jest.fn();
		const stopImmediatePropagation = jest.fn();
		const event = { preventDefault, stopImmediatePropagation };

		app.handleClick( { ...event, target: document } );
		app.handleClick( { ...event, target: document.querySelector( 'button[type="button"]' ) } );
		await app.getToken();
		app.handleClick( { ...event, target: document.querySelector( 'button[type="submit"]' ) } );

		expect( preventDefault ).not.toHaveBeenCalled();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'requestSubmit', true, false, false ],
		[ 'submitter click', false, true, false ],
		[ 'native submit', false, false, false ],
		[ 'canceled native submit', false, false, true ],
	] )( 'resumes a captured form submission through %s', async ( description, hasRequestSubmit, hasSubmitter, cancelNative ) => {
		let resolveRequest;
		fetch.mockImplementationOnce( () => new Promise( ( resolve ) => {
			resolveRequest = resolve;
		} ) );
		document.body.innerHTML = '<form><input name="hcap_fst_token"><button type="button">Send</button></form>';
		require( '../../../assets/js/hcaptcha-fst.js' );
		const form = document.querySelector( 'form' );
		const button = form.querySelector( 'button' );
		const submitter = hasSubmitter ? button : undefined;
		const requestSubmit = jest.fn();
		const click = jest.spyOn( button, 'click' ).mockImplementation( () => {} );
		const nativeSubmit = jest.spyOn( HTMLFormElement.prototype, 'submit' ).mockImplementation( () => {} );
		form.requestSubmit = hasRequestSubmit ? requestSubmit : undefined;
		if ( cancelNative ) {
			form.addEventListener( 'submit', ( submitEvent ) => submitEvent.preventDefault() );
		}
		const event = { target: form, submitter, preventDefault: jest.fn(), stopImmediatePropagation: jest.fn() };

		window.hCaptchaFST.handleSubmit( event );
		expect( event.preventDefault ).toHaveBeenCalledTimes( 1 );
		resolveRequest( tokenResponse() );
		await waitForToken();

		expect( requestSubmit ).toHaveBeenCalledTimes( hasRequestSubmit ? 1 : 0 );
		expect( click ).toHaveBeenCalledTimes( hasSubmitter && ! hasRequestSubmit ? 1 : 0 );
		expect( nativeSubmit ).toHaveBeenCalledTimes( ! hasRequestSubmit && ! hasSubmitter && ! cancelNative ? 1 : 0 );
	} );

	test.each( [ false, true ] )( 'aborts a stalled %s token request after ten seconds', async ( retry ) => {
		jest.useFakeTimers();
		try {
			if ( retry ) {
				fetch.mockResolvedValueOnce( {
					status: 403,
					ok: false,
					json: () => Promise.resolve( { data: { code: 'fst-invalid-nonce', nonce: 'fresh-nonce' } } ),
				} );
			}
			fetch.mockImplementationOnce( ( url, options ) => new Promise( ( resolve, reject ) => {
				options.signal.addEventListener( 'abort', () => reject( new Error( 'Timed out.' ) ) );
			} ) );

			require( '../../../assets/js/hcaptcha-fst.js' );
			if ( retry ) {
				for ( let attempt = 0; attempt < 10 && fetch.mock.calls.length < 2; attempt++ ) {
					await Promise.resolve();
				}
				expect( fetch ).toHaveBeenCalledTimes( 2 );
			}
			jest.advanceTimersByTime( 10_000 );
			await window.hCaptchaFST.getToken();

			expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( '' );
			expect( fetch.mock.calls[ retry ? 1 : 0 ][ 1 ].signal.aborted ).toBe( true );
		} finally {
			jest.useRealTimers();
		}
	} );
} );
