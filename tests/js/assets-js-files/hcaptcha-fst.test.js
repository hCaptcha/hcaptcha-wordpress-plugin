// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha FST', () => {
	const waitForToken = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	const getTokenStorageKey = () => [
		'hcaptcha-fst-token',
		window.HCaptchaFSTObject.issueTokenContext,
		window.location.pathname,
		window.location.search,
	].join( ':' );

	beforeEach( () => {
		jest.resetModules();
		fetch.resetMocks();
		window.sessionStorage.clear();

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
		delete window.HCaptchaFSTObject;
		delete window.hCaptchaFST;
		document.body.className = '';
		document.body.innerHTML = '';
		window.sessionStorage.clear();
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
} );
