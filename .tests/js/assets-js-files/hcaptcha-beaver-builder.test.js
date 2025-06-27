// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Beaver Builder', () => {
	let ajaxPrefilterCallback;
	const options = {
		data: '',
	};

	beforeEach( () => {
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Mock jQuery.ajaxPrefilter
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		document.body.innerHTML = `
	      <div data-node="123">
	        <input type="hidden" name="h-captcha-response" value="responseValue">
	        <input type="hidden" name="hcaptcha_beaver_builder_nonce" value="nonceValue">
	        <input type="hidden" name="hcaptcha_login_nonce" value="loginNonceValue">
	      </div>
	    `;

		require( '../../../assets/js/hcaptcha-beaver-builder.js' );
	} );

	test( 'appends h-captcha-response and hcaptcha_beaver_builder_nonce when data starts with action=fl_builder_email', () => {
		options.data = 'action=fl_builder_email&node_id=123';
		ajaxPrefilterCallback( options );
		expect( options.data ).toContain( 'h-captcha-response=responseValue' );
		expect( options.data ).toContain( 'hcaptcha_beaver_builder_nonce=nonceValue' );
	} );

	test( 'appends h-captcha-response and hcaptcha_login_nonce when data starts with action=fl_builder_login_form_submit', () => {
		options.data = 'action=fl_builder_login_form_submit&node_id=123';
		ajaxPrefilterCallback( options );
		expect( options.data ).toContain( 'h-captcha-response=responseValue' );
		expect( options.data ).toContain( 'hcaptcha_login_nonce=loginNonceValue' );
	} );

	test( 'does not append anything when data does not start with any expected action', () => {
		options.data = 'action=other_action&node_id=123';
		ajaxPrefilterCallback( options );
		expect( options.data ).not.toContain( 'h-captcha-response' );
		expect( options.data ).not.toContain( 'hcaptcha_beaver_builder_nonce' );
		expect( options.data ).not.toContain( 'hcaptcha_login_nonce' );
	} );

	test( 'does not append anything when data is not a string', () => {
		options.data = {};
		ajaxPrefilterCallback( options );
		expect( typeof options.data ).not.toBe( 'string' );
		expect( options.data ).toEqual( {} );
	} );
} );
