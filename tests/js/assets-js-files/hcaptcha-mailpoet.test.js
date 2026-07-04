// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha MailPoet', () => {
	let originalAjaxPrefilter;
	let ajaxPrefilterCallback;

	function loadMailPoet() {
		jest.resetModules();
		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaMailPoet;

		require( '../../../assets/js/hcaptcha-mailpoet.js' );
	}

	function paramsFrom( options ) {
		return new URLSearchParams( options.data );
	}

	beforeEach( () => {
		document.body.innerHTML = '';
		ajaxPrefilterCallback = null;
		loadMailPoet();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		delete window.hCaptchaMailPoet;
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers ajax prefilter and rebinds after MailPoet ajax complete only', () => {
		expect( $.ajaxPrefilter ).toHaveBeenCalledWith( expect.any( Function ) );

		$( document ).trigger( 'ajaxComplete', [ {}, {} ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: { action: 'mailpoet' } } ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=mailpoet' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores ajax prefilter data that is not MailPoet form data', () => {
		const missingData = {};
		const objectData = { data: { action: 'mailpoet' } };
		const otherAction = { data: 'action=other_action' };

		ajaxPrefilterCallback( missingData );
		ajaxPrefilterCallback( objectData );
		ajaxPrefilterCallback( otherAction );

		expect( missingData.data ).toBeUndefined();
		expect( objectData.data ).toEqual( { action: 'mailpoet' } );
		expect( otherAction.data ).toBe( 'action=other_action' );
	} );

	test( 'adds hCaptcha values from a MailPoet form id lookup', () => {
		document.body.innerHTML = `
			<form id="mailpoet-form">
				<input name="data[form_id]" value="12">
				<input name="h-captcha-response" value="response-token">
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="hcaptcha_mailpoet_nonce" value="nonce-value">
				<input name="hcap_fst_token" value="fst-token">
				<input name="hcap_hp_sig" value="hp-sig">
				<input id="hcap_hp_test" value="hp-value">
			</form>
		`;
		const options = {
			data: 'action=mailpoet&data%5Bform_id%5D=12',
		};

		ajaxPrefilterCallback( options );

		const params = paramsFrom( options );

		expect( params.get( 'h-captcha-response' ) ).toBe( 'response-token' );
		expect( params.get( 'hcaptcha-widget-id' ) ).toBe( 'widget-id' );
		expect( params.get( 'hcaptcha_mailpoet_nonce' ) ).toBe( 'nonce-value' );
		expect( params.get( 'hcap_fst_token' ) ).toBe( 'fst-token' );
		expect( params.get( 'hcap_hp_sig' ) ).toBe( 'hp-sig' );
		expect( params.get( 'hcap_hp_test' ) ).toBe( 'hp-value' );
	} );

	test( 'falls back to context form and empty field values', () => {
		document.body.innerHTML = '<form id="context-form"><button id="submit-button">Submit</button></form>';
		const options = {
			context: document.getElementById( 'submit-button' ),
			data: 'action=mailpoet',
		};

		ajaxPrefilterCallback( options );

		const params = paramsFrom( options );

		expect( params.get( 'h-captcha-response' ) ).toBe( '' );
		expect( params.get( 'hcaptcha-widget-id' ) ).toBe( '' );
		expect( params.get( 'hcaptcha_mailpoet_nonce' ) ).toBe( '' );
		expect( params.get( 'hcap_fst_token' ) ).toBe( '' );
		expect( params.get( 'hcap_hp_sig' ) ).toBe( '' );
	} );

	test( 'falls back to active element form and returns when no form can be resolved', () => {
		document.body.innerHTML = '<form id="active-form"><input id="active-input" name="h-captcha-response" value="active-response"></form><div id="outside" tabindex="0"></div>';
		const activeOptions = {
			data: 'action=mailpoet',
		};

		document.getElementById( 'active-input' ).focus();
		ajaxPrefilterCallback( activeOptions );
		expect( paramsFrom( activeOptions ).get( 'h-captcha-response' ) ).toBe( 'active-response' );

		document.getElementById( 'outside' ).focus();
		const missingFormOptions = {
			data: 'action=mailpoet',
		};
		ajaxPrefilterCallback( missingFormOptions );

		expect( missingFormOptions.data ).toBe( 'action=mailpoet' );
	} );

	test( 'uses empty hcap hp fallbacks when the hcap input has no jQuery id or value', () => {
		document.body.innerHTML = `
			<form id="mailpoet-form">
				<input name="data[form_id]" value="12">
				<input id="hcap_hp_test" value="hp-value">
			</form>
		`;
		const options = {
			data: 'action=mailpoet&data%5Bform_id%5D=12',
		};
		const originalAttr = $.fn.attr;
		const originalVal = $.fn.val;

		jest.spyOn( $.fn, 'attr' ).mockImplementation( function( name ) {
			if ( name === 'id' && this.is( '[id^="hcap_hp_"]' ) ) {
				return undefined;
			}

			return originalAttr.apply( this, arguments );
		} );
		jest.spyOn( $.fn, 'val' ).mockImplementation( function() {
			if ( this.is( '[id^="hcap_hp_"]' ) ) {
				return undefined;
			}

			return originalVal.apply( this, arguments );
		} );

		ajaxPrefilterCallback( options );

		expect( paramsFrom( options ).get( '' ) ).toBe( '' );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaMailPoet = existingApp;

		require( '../../../assets/js/hcaptcha-mailpoet.js' );

		expect( window.hCaptchaMailPoet ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
