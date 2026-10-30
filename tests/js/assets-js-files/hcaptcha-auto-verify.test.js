// noinspection JSUnresolvedFunction,JSUnresolvedVariable,JSValidateTypes

describe( 'hCaptcha auto verify', () => {
	let domReadyCallback;
	let addEventListenerSpy;

	const waitForSubmit = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	const ajaxForm = '<form action="https://test.test/verify"><h-captcha data-ajax="true"></h-captcha></form>';
	const commentForm = '<form action="https://test.test/wp-comments-post.php"><h-captcha data-ajax="true"></h-captcha><input name="comment_post_ID" value="42"></form>';

	async function submitForm( submitter ) {
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );

		if ( submitter ) {
			Object.defineProperty( event, 'submitter', { value: submitter } );
		}

		document.querySelector( 'form' ).dispatchEvent( event );
		await waitForSubmit();

		return document.querySelector( '.autoverify-result' );
	}

	function loadAutoVerify( html ) {
		document.body.innerHTML = html;
		domReadyCallback = null;
		addEventListenerSpy = jest.spyOn( document, 'addEventListener' ).mockImplementation( ( eventName, callback, options ) => {
			if ( eventName === 'DOMContentLoaded' ) {
				domReadyCallback = callback;

				return undefined;
			}

			return EventTarget.prototype.addEventListener.call( document, eventName, callback, options );
		} );

		jest.resetModules();
		require( '../../../assets/js/hcaptcha-auto-verify.js' );
		domReadyCallback();
	}

	beforeEach( () => {
		fetch.resetMocks();
		window.HCaptchaAutoVerifyObject = {
			successMsg: 'Verification succeeded.',
			submittingMsg: 'Submitting...',
			errorMsg: 'The form failed.',
			networkErrorMsg: 'The result is unknown.',
		};
		global.HCaptchaAutoVerifyObject = window.HCaptchaAutoVerifyObject;
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;
		window.hCaptchaReset = jest.fn();
	} );

	afterEach( () => {
		addEventListenerSpy?.mockRestore();
		delete window.HCaptchaAutoVerifyObject;
		delete global.HCaptchaAutoVerifyObject;
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'ignores forms without ajax hCaptcha field', () => {
		loadAutoVerify(
			`
				<form action="https://test.test/verify">
					<input name="email" value="person@test.test">
				</form>
			`,
		);

		const event = new Event( 'submit', { bubbles: true, cancelable: true } );

		document.querySelector( 'form' ).dispatchEvent( event );

		expect( fetch ).not.toHaveBeenCalled();
		expect( event.defaultPrevented ).toBe( false );
	} );

	test( 'posts ajax form data, creates result container, and rebinds hCaptcha on success', async () => {
		fetch.mockResponseOnce( '', { status: 200 } );

		loadAutoVerify(
			`
				<div id="wrap">
					<form action="https://test.test/verify">
						<h-captcha data-ajax="true"></h-captcha>
						<input name="email" value="person@test.test">
					</form>
				</div>
			`,
		);

		const form = document.querySelector( 'form' );
		const event = new Event( 'submit', { bubbles: true, cancelable: true } );

		form.dispatchEvent( event );
		await waitForSubmit();

		expect( event.defaultPrevented ).toBe( true );
		expect( fetch ).toHaveBeenCalledWith(
			'https://test.test/verify',
			expect.objectContaining( {
				method: 'POST',
				body: expect.any( FormData ),
			} ),
		);
		expect( document.querySelector( '.autoverify-result' ).textContent ).toBe( 'Verification succeeded.' );
		expect( document.querySelector( '#wrap' ).firstElementChild ).toBe( document.querySelector( '.autoverify-result' ) );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing result container and shows server error text', async () => {
		fetch.mockResponseOnce( 'Verification failed.', { status: 400 } );

		loadAutoVerify(
			`
				<div>
					<p class="autoverify-result">Old result.</p>
					<form action="https://test.test/verify">
						<h-captcha data-ajax="true"></h-captcha>
					</form>
				</div>
			`,
		);

		const resultContainer = document.querySelector( '.autoverify-result' );

		document.querySelector( 'form' ).dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
		await waitForSubmit();

		expect( document.querySelectorAll( '.autoverify-result' ) ).toHaveLength( 1 );
		expect( document.querySelector( '.autoverify-result' ) ).toBe( resultContainer );
		expect( resultContainer.textContent ).toBe( 'Verification failed.' );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'shows an uncertain result after a network error and resets hCaptcha', async () => {
		fetch.mockRejectOnce( new Error( 'Network failed.' ) );

		loadAutoVerify(
			`
				<div>
					<form action="https://test.test/verify">
						<h-captcha data-ajax="true"></h-captcha>
					</form>
				</div>
			`,
		);

		document.querySelector( 'form' ).dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
		await waitForSubmit();

		expect( document.querySelector( '.autoverify-result' ).textContent ).toBe( 'The result is unknown.' );
		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test.each( [ 400, 200 ] )( 'shows only the WordPress error message for status %s', async ( status ) => {
		fetch.mockResponseOnce(
			'<!doctype html><html><head><style>body { display: none; }</style></head>' +
			'<body><div class="wp-die-message"><p>Please complete the hCaptcha.</p></div></body></html>',
			{ status },
		);

		loadAutoVerify(
			`<div id="wrap">
				<form action="https://test.test/wp-comments-post.php">
					<h-captcha data-ajax="true"></h-captcha>
					<input name="comment_post_ID" value="42">
					<textarea name="comment">Keep this comment.</textarea>
				</form>
			</div>`,
		);

		const form = document.querySelector( 'form' );
		form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );
		await waitForSubmit();

		const result = document.querySelector( '.autoverify-result' );

		expect( result.textContent ).toBe( 'Please complete the hCaptcha.' );
		expect( result.getAttribute( 'role' ) ).toBe( 'alert' );
		expect( result.querySelector( 'style' ) ).toBeNull();
		expect( form.querySelector( 'textarea' ).value ).toBe( 'Keep this comment.' );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
	} );

	test( 'refreshes the form timing token before allowing another submission', async () => {
		let finishRefresh;

		window.hCaptchaFST = {
			getToken: jest.fn()
				.mockImplementationOnce( () => new Promise( ( resolve ) => {
					finishRefresh = () => {
						document.querySelector( '[name="hcap_fst_token"]' ).value = 'fresh-token';
						resolve();
					};
				} ) )
				.mockResolvedValueOnce(),
		};
		fetch.mockResponseOnce( 'Please complete the hCaptcha.', { status: 400 } );
		fetch.mockResponseOnce( 'Please complete the hCaptcha.', { status: 400 } );

		loadAutoVerify(
			`<form action="https://test.test/wp-comments-post.php">
				<h-captcha data-ajax="true"></h-captcha>
				<input name="comment_post_ID" value="42">
				<input name="hcap_fst_token" value="used-token">
			</form>`,
		);

		const form = document.querySelector( 'form' );
		const submit = () => form.dispatchEvent( new Event( 'submit', { bubbles: true, cancelable: true } ) );

		submit();
		await waitForSubmit();

		expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'hcap_fst_token' ) ).toBe( 'used-token' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );

		submit();
		expect( fetch ).toHaveBeenCalledTimes( 1 );

		finishRefresh();
		await waitForSubmit();
		submit();
		await waitForSubmit();

		expect( fetch ).toHaveBeenCalledTimes( 2 );
		expect( fetch.mock.calls[ 1 ][ 1 ].body.get( 'hcap_fst_token' ) ).toBe( 'fresh-token' );
	} );

	test.each( [
		[ 'empty response', '  ', {}, 'The form failed.' ],
		[ 'nested JSON message', '{"data":{"message":"Nested error."}}', { 'content-type': 'application/json' }, 'Nested error.' ],
		[ 'JSON data string', '{"data":"Data error."}', { 'content-type': 'application/json' }, 'Data error.' ],
		[ 'top-level JSON message', '{"message":"Top-level error."}', { 'content-type': 'application/json' }, 'Top-level error.' ],
		[ 'invalid JSON', '{broken', { 'content-type': 'application/json' }, 'The form failed.' ],
		[ 'non-string JSON message', '{"data":{"message":123}}', { 'content-type': 'application/json' }, 'The form failed.' ],
		[ 'empty JSON message', '{"data":{"message":""}}', { 'content-type': 'application/json' }, 'The form failed.' ],
		[ 'empty WordPress error', '<div class="wp-die-message"> </div>', {}, 'The form failed.' ],
		[ 'full HTML document without an error', '<!doctype html><html><body>Private details</body></html>', {}, 'The form failed.' ],
		[ 'plain HTML fragment', '<p>Fragment error.</p>', {}, 'Fragment error.' ],
		[ 'empty HTML fragment', '<p> </p>', {}, 'The form failed.' ],
	] )( 'displays a safe message for %s', async ( description, body, headers, expectedMessage ) => {
		fetch.mockResponseOnce( body, { status: 400, headers } );
		loadAutoVerify( ajaxForm );

		const result = await submitForm();

		expect( result.textContent ).toBe( expectedMessage );
		expect( result.getAttribute( 'role' ) ).toBe( 'alert' );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( document.querySelector( 'form' ) );
	} );

	test( 'treats a form without a comment post ID as an ordinary ajax form', async () => {
		fetch.mockResponseOnce( '', { status: 200 } );
		loadAutoVerify( '<form action="https://test.test/wp-comments-post.php"><h-captcha data-ajax="true"></h-captcha></form>' );

		const result = await submitForm();

		expect( result.textContent ).toBe( 'Verification succeeded.' );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'non-redirected response', false, 'https://test.test/comment/' ],
		[ 'redirect without a destination', true, '' ],
	] )( 'shows a WordPress comment error for %s', async ( description, redirected, url ) => {
		fetch.mockResolvedValueOnce( {
			ok: true,
			redirected,
			url,
			text: () => Promise.resolve( 'Comment rejected.' ),
			headers: { get: () => 'text/plain' },
		} );
		loadAutoVerify( commentForm );

		const result = await submitForm();

		expect( result.textContent ).toBe( 'Comment rejected.' );
		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'follows a successful WordPress comment redirect', async () => {
		fetch.mockResolvedValueOnce( {
			ok: true,
			redirected: true,
			url: window.location.href,
		} );
		loadAutoVerify( commentForm );

		const result = await submitForm();

		expect( result.textContent ).toBe( 'Submitting...' );
		expect( document.querySelector( 'form' ).hasAttribute( 'aria-busy' ) ).toBe( false );
		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'includes the submitter and reenables it after a failed submission', async () => {
		fetch.mockResponseOnce( 'Try again.', { status: 400 } );
		loadAutoVerify( '<form action="https://test.test/verify"><h-captcha data-ajax="true"></h-captcha><button name="action" value="save" data-h-captcha-disabled="1" disabled>Save</button></form>' );

		const submitter = document.querySelector( 'button' );
		const result = await submitForm( submitter );

		expect( fetch.mock.calls[ 0 ][ 1 ].body.get( 'action' ) ).toBe( 'save' );
		expect( submitter.disabled ).toBe( false );
		expect( submitter.dataset.hCaptchaDisabled ).toBe( '0' );
		expect( result.textContent ).toBe( 'Try again.' );
	} );
} );
