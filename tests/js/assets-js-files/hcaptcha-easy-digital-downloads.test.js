// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

describe( 'hCaptcha Easy Digital Downloads', () => {
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = `
			<form id="edd_purchase_form">
				<textarea name="h-captcha-response">hcaptcha-token</textarea>
				<textarea name="g-recaptcha-response">recaptcha-token</textarea>
				<input type="hidden" name="hcap_fst_token" value="fst-token">
			</form>
		`;
		global.jQuery = $;
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		delete window.hCaptchaEasyDigitalDownloads;

		require( '../../../assets/js/hcaptcha-easy-digital-downloads.js' );
	} );

	afterEach( () => {
		$( document.body ).off( 'edd_checkout_error' );
		delete window.hCaptchaEasyDigitalDownloads;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		delete global.jQuery;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'resets hCaptcha and refreshes FST after a checkout error', () => {
		$( document.body ).trigger( 'edd_checkout_error' );

		const form = document.getElementById( 'edd_purchase_form' );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( form.querySelector( 'textarea[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'refreshes FST when the checkout form is no longer present', () => {
		document.body.innerHTML = '';

		$( document.body ).trigger( 'edd_checkout_error' );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'resets hCaptcha when FST is disabled', () => {
		delete window.hCaptchaFST;

		expect( () => {
			$( document.body ).trigger( 'edd_checkout_error' );
		} ).not.toThrow();
		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 1 );
	} );
} );
