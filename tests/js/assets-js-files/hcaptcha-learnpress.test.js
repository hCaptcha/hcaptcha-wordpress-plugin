// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha LearnPress', () => {
	let originalFetch;

	beforeEach( () => {
		jest.resetModules();

		originalFetch = window.fetch;
		window.fetch = jest.fn();
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		document.body.innerHTML = `
			<form id="learn-press-checkout-form">
				<textarea name="h-captcha-response">hcaptcha-token</textarea>
				<textarea name="g-recaptcha-response">recaptcha-token</textarea>
				<input type="hidden" name="hcap_fst_token" value="fst-token">
			</form>
		`;

		delete window.hCaptchaLearnPress;
		delete window.__hcapFetchWrapped;

		require( '../../../assets/js/hcaptcha-learnpress.js' );
	} );

	afterEach( () => {
		window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaLearnPress.fetchComplete );
		window.fetch = originalFetch;

		delete window.hCaptchaLearnPress;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		delete window.__hcapFetchWrapped;
	} );

	test.each( [
		new URL( 'https://test.test/lp-checkout/?lp-ajax=checkout' ),
		'/lp-checkout/?lp-ajax=checkout',
	] )( 'resets hCaptcha and refreshes FST after a checkout request to %s', ( request ) => {
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ request, {} ],
				},
			} ),
		);

		const form = document.getElementById( 'learn-press-checkout-form' );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( form.querySelector( 'textarea[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores the checkout email availability request', () => {
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ '/lp-checkout/?lp-ajax=checkout-user-email-exists', {} ],
				},
			} ),
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );

	test( 'refreshes FST when the checkout form is no longer present', () => {
		document.body.innerHTML = '';

		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ '/lp-checkout/?lp-ajax=checkout', {} ],
				},
			} ),
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [ null, {}, 'not a url%' ] )( 'ignores an invalid request target: %p', ( request ) => {
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ request, {} ],
				},
			} ),
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
