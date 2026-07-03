// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Ultimate Addons', () => {
	let helperMock;
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;

	function loadUltimateAddons() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.hCaptchaBindEvents = jest.fn();
		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );
		document.body.innerHTML = `
			<form class="uael-login-form"><h-captcha></h-captcha></form>
			<form class="uael-registration-form"></form>
		`;

		require( '../../../assets/js/hcaptcha-ultimate-addons.js' );
	}

	beforeEach( () => {
		loadUltimateAddons();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.wp;
		delete global.wp;
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'marks Ultimate Addons login and register buttons as ajax submit buttons', () => {
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const loginButton = document.createElement( 'button' );
		const registerButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		loginButton.classList.add( 'uael-login-form-submit' );
		registerButton.classList.add( 'uael-register-submit' );

		expect( callback( false, loginButton ) ).toBe( true );
		expect( callback( false, registerButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
	} );

	test( 'ignores ajax prefilter data that cannot resolve a UAEL action', () => {
		ajaxPrefilterCallback( {} );
		ajaxPrefilterCallback( { data: { action: 'uael_login_form_submit' } } );
		ajaxPrefilterCallback( { data: 'action=' } );
		ajaxPrefilterCallback( { data: 'action=other_action' } );

		expect( helperMock.addHCaptchaData ).not.toHaveBeenCalled();
	} );

	test( 'adds hCaptcha data for login and register actions', () => {
		const loginOptions = { data: 'action=uael_login_form_submit' };
		const registerOptions = { data: 'action=uael_register_user' };

		ajaxPrefilterCallback( loginOptions );
		ajaxPrefilterCallback( registerOptions );

		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			1,
			loginOptions,
			'uael_login_form_submit',
			'hcaptcha_login_nonce',
			expect.objectContaining( { length: 1 } ),
		);
		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			2,
			loginOptions,
			'uael_register_user',
			'hcaptcha_ultimate_addons_register_nonce',
			expect.objectContaining( { length: 1 } ),
		);
		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			3,
			registerOptions,
			'uael_login_form_submit',
			'hcaptcha_login_nonce',
			expect.objectContaining( { length: 1 } ),
		);
		expect( helperMock.addHCaptchaData ).toHaveBeenNthCalledWith(
			4,
			registerOptions,
			'uael_register_user',
			'hcaptcha_ultimate_addons_register_nonce',
			expect.objectContaining( { length: 1 } ),
		);
	} );

	test( 'rebinds hCaptcha and displays hCaptcha error for failed UAEL ajax responses', () => {
		$( document ).trigger( 'ajaxSuccess', [ { responseText: JSON.stringify( { success: true } ) }, { data: 'action=uael_login_form_submit' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( document.querySelector( '.uael-register-field-message' ) ).toBeNull();

		$( document ).trigger( 'ajaxSuccess', [ { responseText: JSON.stringify( { success: false, data: '' } ) }, { data: 'action=uael_register_user' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
		expect( document.querySelector( '.uael-register-field-message' ) ).toBeNull();

		$( document ).trigger( 'ajaxSuccess', [ { responseText: 'null' }, { data: 'action=uael_register_user' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 3 );
		expect( document.querySelector( '.uael-register-field-message' ) ).toBeNull();

		$( document ).trigger( 'ajaxSuccess', [ { responseText: JSON.stringify( { success: false, data: { hCaptchaError: 'Captcha failed.' } } ) }, { data: 'action=uael_register_user' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 4 );
		expect( document.querySelector( '.uael-loginform-error' ).textContent ).toBe( 'Captcha failed.' );
	} );

	test( 'ignores unrelated UAEL ajax success actions', () => {
		$( document ).trigger( 'ajaxSuccess', [ { responseText: '{}' }, { data: 'action=other_action' } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
