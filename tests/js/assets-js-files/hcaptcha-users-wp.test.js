// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha UsersWP', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		require( '../../../assets/js/hcaptcha-users-wp.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess ajaxComplete' );
		document.body.innerHTML = '';
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		jest.restoreAllMocks();
	} );

	test.each( [
		'uwp_ajax_forgot_password_form',
		'uwp_ajax_login_form',
		'uwp_ajax_register_form',
	] )( 'rebinds hCaptcha after UsersWP %s ajax success', ( action ) => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: `action=${ action }` } ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated UsersWP ajax actions', () => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ 'uwp_ajax_forgot_password', 'uwp-forgot-form' ],
		[ 'uwp_ajax_login', 'uwp-login-form' ],
		[ 'uwp_ajax_register', 'uwp-registration-form' ],
	] )( 'refreshes tokens after UsersWP %s completes', ( action, formClass ) => {
		document.body.innerHTML = `
			<form class="${ formClass }">
				<textarea name="h-captcha-response">used-token</textarea>
				<textarea name="g-recaptcha-response">used-token</textarea>
			</form>`;

		const form = document.querySelector( `form.${ formClass }` );

		$( document ).trigger( 'ajaxComplete', [ {}, { data: `action=${ action }` } ] );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( form.querySelector( '[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not refresh tokens after an unrelated request', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=other_action' } ] );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
