// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Classified Listing', () => {
	beforeEach( () => {
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		document.body.innerHTML = `
			<form id="rtcl-login-form" class="rtcl-login-form">
				<textarea name="h-captcha-response">login</textarea>
			</form>
			<form id="rtcl-register-form" class="rtcl-register-form">
				<textarea name="h-captcha-response">register</textarea>
			</form>
			<form id="rtcl-contact-form" class="rtcl-contact-form">
				<textarea name="h-captcha-response">contact</textarea>
			</form>
		`;

		require( '../../../assets/js/hcaptcha-classified-listing.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		document.body.innerHTML = '';
		jest.resetModules();
	} );

	test.each( [
		[ 'rtcl_login_request', '#rtcl-login-form' ],
		[ 'rtcl_registration_request', '#rtcl-register-form' ],
		[ 'rtcl_public_send_contact_email', '#rtcl-contact-form' ],
	] )( 'refreshes tokens after %s', ( action, selector ) => {
		const data = new FormData();
		data.append( 'action', action );

		$( document ).trigger( 'ajaxComplete', [ {}, { data } ] );

		const form = document.querySelector( selector );

		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated ajax requests', () => {
		const data = new FormData();
		data.append( 'action', 'other_action' );

		$( document ).trigger( 'ajaxComplete', [ {}, { data } ] );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
