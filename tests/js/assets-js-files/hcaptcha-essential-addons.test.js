// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Essential Addons', () => {
	beforeEach( () => {
		jest.resetModules();

		$( document ).off( 'ajaxComplete' );

		document.body.innerHTML = '';

		window.hCaptchaReset = jest.fn();
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};

		delete window.hCaptchaEssentialAddons;

		require( '../../../assets/js/hcaptcha-essential-addons.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );

		delete window.hCaptchaReset;
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
		delete window.hCaptchaEssentialAddons;
		delete window.wp;
	} );

	test( 'marks Essential Addons submit buttons as ajax buttons', () => {
		const addFilter = window.wp.hooks.addFilter;
		const callback = addFilter.mock.calls[ 0 ][ 2 ];
		const loginButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		loginButton.setAttribute( 'name', 'eael-login-submit' );

		expect( addFilter ).toHaveBeenCalledWith(
			'hcaptcha.ajaxSubmitButton',
			'hcaptcha',
			expect.any( Function ),
		);
		expect( callback( false, loginButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
	} );

	test.each( [
		[ 'login', 'eael-login-submit' ],
		[ 'register', 'eael-register-submit' ],
	] )( 'resets hCaptcha after %s ajax request', ( formId, submitName ) => {
		document.body.innerHTML = `
			<form id="eael-${ formId }-form">
				<input type="hidden" name="widget_id" value="widget-id">
				<button type="submit" name="${ submitName }">Submit</button>
			</form>
		`;

		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: `${ submitName }=1&widget_id=widget-id`,
				},
			],
		);

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( document.getElementById( `eael-${ formId }-form` ) );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'resets register form when Essential Addons sends serializeArray data', () => {
		document.body.innerHTML = `
			<form id="eael-login-form">
				<input type="hidden" name="widget_id" value="widget-id">
				<div class="h-captcha">
					<textarea name="h-captcha-response">login-token</textarea>
				</div>
				<button type="submit" name="eael-login-submit">Log In</button>
			</form>
			<form id="eael-register-form">
				<input type="hidden" name="widget_id" value="widget-id">
				<input type="hidden" name="eael-register-nonce" value="nonce">
				<div class="h-captcha">
					<textarea name="h-captcha-response">register-token</textarea>
				</div>
				<button type="submit" name="eael-register-submit">Register</button>
			</form>
		`;

		const loginForm = document.getElementById( 'eael-login-form' );
		const registerForm = document.getElementById( 'eael-register-form' );

		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: [
						{ name: 'eael-register-nonce', value: 'nonce' },
						{ name: 'widget_id', value: 'widget-id' },
						{ name: 'eael-register-submit', value: true },
						{ name: 'action', value: 'eael-login-register-form' },
					],
				},
			],
		);

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( registerForm );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( loginForm );
		expect( loginForm.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( registerForm.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'resets all widget forms when request is identified by ajax action only', () => {
		document.body.innerHTML = `
			<form id="eael-login-form">
				<input type="hidden" name="widget_id" value="widget-id">
				<div class="h-captcha">
					<textarea name="g-recaptcha-response">login-token</textarea>
				</div>
				<button type="submit" name="eael-login-submit">Log In</button>
			</form>
			<form id="eael-register-form">
				<input type="hidden" name="widget_id" value="widget-id">
				<div class="h-captcha">
					<textarea name="g-recaptcha-response">register-token</textarea>
				</div>
				<button type="submit" name="eael-register-submit">Register</button>
			</form>
		`;

		const loginForm = document.getElementById( 'eael-login-form' );
		const registerForm = document.getElementById( 'eael-register-form' );

		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: 'action=eael-login-register-form&widget_id=widget-id',
				},
			],
		);

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( loginForm );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( registerForm );
		expect( loginForm.querySelector( 'textarea[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( registerForm.querySelector( 'textarea[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'binds events when submitted form cannot be resolved', () => {
		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: 'eael-register-submit=1&widget_id=missing',
				},
			],
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated ajax requests', () => {
		$( document ).trigger(
			'ajaxComplete',
			[
				{},
				{
					data: 'eael-lostpassword-submit=1&widget_id=widget-id',
				},
			],
		);

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
