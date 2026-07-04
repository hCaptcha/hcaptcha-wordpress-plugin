// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha UsersWP', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-users-wp.js' );
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		delete window.hCaptchaBindEvents;
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
} );
