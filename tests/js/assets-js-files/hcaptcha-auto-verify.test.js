// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha auto verify', () => {
	let domReadyCallback;
	let addEventListenerSpy;

	const waitForSubmit = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

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
		};
		global.HCaptchaAutoVerifyObject = window.HCaptchaAutoVerifyObject;
		window.hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = window.hCaptchaBindEvents;
	} );

	afterEach( () => {
		addEventListenerSpy?.mockRestore();
		delete window.HCaptchaAutoVerifyObject;
		delete global.HCaptchaAutoVerifyObject;
		delete window.hCaptchaBindEvents;
		delete global.hCaptchaBindEvents;
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
	} );

	test( 'shows fetch errors and rebinds hCaptcha after catch handler', async () => {
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

		expect( document.querySelector( '.autoverify-result' ).textContent ).toContain( 'Network failed.' );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
