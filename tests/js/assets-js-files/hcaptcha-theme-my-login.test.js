// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Theme My Login', () => {
	beforeEach( () => {
		jest.resetModules();
		$( document ).off( 'ajaxComplete' );

		document.body.innerHTML = `
			<div class="tml">
				<form action="https://test.test/login/" data-ajax="1">
					<textarea name="h-captcha-response">hcaptcha-token</textarea>
					<textarea name="g-recaptcha-response">recaptcha-token</textarea>
					<input type="hidden" name="hcap_fst_token" value="fst-token">
				</form>
			</div>
		`;
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		delete window.hCaptchaThemeMyLogin;

		require( '../../../assets/js/hcaptcha-theme-my-login.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );

		delete window.hCaptchaThemeMyLogin;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
	} );

	test( 'resets hCaptcha and refreshes FST after a Theme My Login AJAX request', () => {
		const form = document.querySelector( '.tml form' );

		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: 'log=user&pwd=bad&ajax=1',
					url: 'https://test.test/login/',
				},
			],
		);

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( form.querySelector( 'textarea[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test.each( [
		[ 'log=user&pwd=bad', 'https://test.test/login/' ],
		[ 'log=user&pwd=bad&ajax=1', 'https://test.test/register/' ],
		[ {}, 'https://test.test/login/' ],
		[ 'log=user&pwd=bad&ajax=1', null ],
	] )( 'ignores an unrelated request with data %p and URL %p', ( data, url ) => {
		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{ data, url },
			],
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
