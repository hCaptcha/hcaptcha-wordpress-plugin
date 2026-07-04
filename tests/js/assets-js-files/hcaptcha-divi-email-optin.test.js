// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Divi Email Optin', () => {
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;
	let helperMock;

	function loadDiviEmailOptin() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-divi-email-optin.js' );
	}

	beforeEach( () => {
		ajaxPrefilterCallback = null;
		document.body.innerHTML = `
			<form id="active-form"><input id="active-input"></form>
			<div class="et_pb_newsletter_form"><form id="fallback-form"></form></div>
		`;
		loadDiviEmailOptin();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'adds hCaptcha data from the active form', () => {
		const options = {
			data: 'action=et_pb_submit_subscribe_form',
		};

		document.getElementById( 'active-input' ).focus();

		ajaxPrefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'et_pb_submit_subscribe_form',
			'hcaptcha_divi_email_optin_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'active-form' ),
				length: 1,
			} ),
		);
	} );

	test( 'falls back to newsletter form when active element is outside a form', () => {
		const options = {
			data: 'action=et_pb_submit_subscribe_form',
		};

		document.body.focus();

		ajaxPrefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'et_pb_submit_subscribe_form',
			'hcaptcha_divi_email_optin_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'fallback-form' ),
				length: 1,
			} ),
		);
	} );

	test( 'rebinds hCaptcha after Divi email optin ajax success only', () => {
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=et_pb_submit_subscribe_form' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
