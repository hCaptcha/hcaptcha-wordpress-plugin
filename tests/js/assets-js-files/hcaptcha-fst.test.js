// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha FST', () => {
	const waitForToken = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

	beforeEach( () => {
		jest.resetModules();
		fetch.resetMocks();

		window.HCaptchaFSTObject = {
			ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
			issueTokenAction: 'hcaptcha-fst-issue-token',
			issueTokenNonce: 'nonce',
		};

		document.body.className = 'page-id-123';
		document.body.innerHTML = '<input name="hcap_fst_token" value="old-token">';
	} );

	afterEach( () => {
		delete window.HCaptchaFSTObject;
		delete window.hCaptchaFST;
		document.body.className = '';
		document.body.innerHTML = '';
	} );

	test( 'refreshes token on init and after hCaptcha bind events', async () => {
		fetch.mockResponse(
			JSON.stringify( {
				success: true,
				data: {
					token: 'new-token',
				},
			} ),
		);

		require( '../../../assets/js/hcaptcha-fst.js' );
		await waitForToken();

		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( fetch.mock.calls[ 0 ][ 0 ] ).toBe( window.HCaptchaFSTObject.ajaxUrl );
		expect( fetch.mock.calls[ 0 ][ 1 ].body ).toContain( 'postId=123' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'new-token' );

		document.querySelector( '[name="hcap_fst_token"]' ).value = 'old-token';
		document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
		await waitForToken();

		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( 'new-token' );
	} );
} );
