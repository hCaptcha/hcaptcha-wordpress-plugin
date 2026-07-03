// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

function resetDocumentEvents() {
	$( document ).off();
}

describe( 'asset coverage fill: admin scripts and hook callbacks', () => {
	afterEach( () => {
		resetDocumentEvents();
		jest.useRealTimers();
		jest.restoreAllMocks();
		delete global.HCaptchaCF7Object;
		delete global.HCaptchaFluentFormObject;
		delete global.HCaptchaForminatorObject;
		delete global.hCaptcha;
		delete global.hCaptchaBindEvents;
		delete global.wp;
		delete global._;
		delete window.hCaptchaCF7;
		delete window.hCaptchaFluentForm;
		delete window.hCaptchaForminator;
		delete window.hCaptchaAdminElementorPro;
		document.body.innerHTML = '';
	} );

	test( 'admin CF7 aborts an in-flight preview request and ignores unsuccessful responses', async () => {
		jest.useFakeTimers();
		jest.resetModules();
		document.body.innerHTML = `
			<input id="wpcf7-shortcode" value="shortcode">
			<textarea id="wpcf7-form"><div>initial</div></textarea>
			<div id="form-live"></div>
			<div><div class="tag-generator-dialog"></div></div>
		`;
		global.HCaptchaCF7Object = {
			ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
			updateFormAction: 'update-cf7',
			updateFormNonce: 'nonce',
		};
		global.hCaptcha = {
			bindEvents: jest.fn(),
		};
		const requests = [];
		const postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const deferred = $.Deferred();
			deferred.abort = jest.fn();
			requests.push( deferred );
			deferred.resolve( { success: false } );
			return deferred;
		} );

		const originalJQuery = global.jQuery;
		const immediateJQuery = ( selector, context ) => {
			if ( typeof selector === 'function' ) {
				selector();

				return originalJQuery( document );
			}

			return originalJQuery( selector, context );
		};
		Object.assign( immediateJQuery, originalJQuery );
		immediateJQuery.fn = originalJQuery.fn;
		global.jQuery = immediateJQuery;
		global.$ = immediateJQuery;
		window.jQuery = immediateJQuery;
		window.$ = immediateJQuery;

		require( '../../../assets/js/admin-cf7.js' );
		global.jQuery = originalJQuery;
		global.$ = originalJQuery;
		window.jQuery = originalJQuery;
		window.$ = originalJQuery;
		$( '#wpcf7-form' ).val( '<div>first</div>' ).trigger( 'input' );
		jest.runOnlyPendingTimers();
		$( '#wpcf7-form' ).val( '<div>second</div>' ).trigger( 'input' );
		jest.runOnlyPendingTimers();

		document.querySelector( '.tag-generator-dialog' ).parentElement.setAttribute( 'open', 'open' );
		await Promise.resolve();

		expect( postSpy ).toHaveBeenCalledTimes( 2 );
		expect( requests[ 0 ].abort ).toHaveBeenCalledTimes( 1 );
		expect( global.hCaptcha.bindEvents ).not.toHaveBeenCalled();

		jest.runOnlyPendingTimers();
		jest.useRealTimers();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	} );

	test( 'admin Elementor Pro renders setup message when hCaptcha is disabled', () => {
		jest.resetModules();
		require( '../__mocks__/elementorModules' );
		require( '../__mocks__/elementorPro' );
		global.elementor = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global._ = {
			escape: ( value ) => value,
		};
		require( '../../../assets/js/admin-elementor-pro.js' );
		global.elementorPro.config.forms.hcaptcha.enabled = false;

		const html = window.hCaptchaAdminElementorPro.renderField(
			'',
			{
				field_type: 'hcaptcha',
				custom_id: 'hcaptcha-field',
				css_classes: '',
			},
		);

		expect( html ).toContain( 'Setup message' );
	} );

	test( 'admin FluentForm returns when the hCaptcha wrapper is not present', async () => {
		jest.resetModules();
		document.body.innerHTML = '<div id="ff_global_settings_option_app"></div>';
		global.HCaptchaFluentFormObject = {
			noticeLabel: 'Label',
			noticeDescription: 'Description',
		};
		require( '../../../assets/js/admin-fluentform.js' );
		window.hCaptchaFluentForm.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=fluent_forms_settings';

		expect( () => window.hCaptchaFluentForm.ready() ).not.toThrow();

		window.hCaptchaFluentForm.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=dashboard';
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	} );

	test( 'admin Forminator mutation returns when hCaptcha tab is inactive', async () => {
		jest.resetModules();
		document.body.innerHTML = `
			<div id="forminator-modal-body--captcha">
				<div class="sui-tabs-content">
					<div class="sui-tab-content">
						<div class="sui-tabs-menu">
							<div class="sui-tab-item">reCAPTCHA</div>
							<div class="sui-tab-item">hCaptcha</div>
						</div>
						<div class="sui-box-settings-row"></div>
						<div class="sui-box-settings-row"><div class="sui-form-field"></div></div>
					</div>
				</div>
			</div>
			<div id="forminator-field-hcaptcha_size"></div>
		`;
		global.HCaptchaForminatorObject = {
			noticeLabel: 'Label',
			noticeDescription: 'Description',
		};
		global.hCaptchaBindEvents = jest.fn();
		require( '../../../assets/js/admin-forminator.js' );
		window.hCaptchaForminator.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=forminator-cform';
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		document.getElementById( 'forminator-field-hcaptcha_size' ).setAttribute( 'data-test', '1' );
		await Promise.resolve();

		expect( document.querySelector( '.sui-form-field' ).style.display ).toBe( '' );
	} );
} );

describe( 'asset coverage fill: integration callbacks', () => {
	afterEach( () => {
		resetDocumentEvents();
		jest.restoreAllMocks();
		delete global.wp;
		delete window.wp;
		delete global.elementorFrontend;
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
		delete window.hCaptchaBeaverBuilder;
		delete window.hCaptchaBlocksy;
		delete window.hCaptchaSupportCandy;
		delete window.hCaptchaElementorPro;
		delete window.hCaptchaOtter;
		delete window.__hcapFetchWrapped;
		document.body.innerHTML = '';
	} );

	test( 'Beaver Builder hook callbacks extend selectors', () => {
		jest.resetModules();
		let ajaxPrefilterCallback;
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );
		require( '../../../assets/js/hcaptcha-beaver-builder.js' );

		expect( global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ]( 'form' ) ).toBe( 'form, div.fl-login-form' );
		expect( global.wp.hooks.addFilter.mock.calls[ 1 ][ 2 ]( 'button' ) ).toBe( 'button, a.fl-button' );

		const options = {};
		ajaxPrefilterCallback( options );
		expect( options.data ).toBeUndefined();
	} );

	test( 'Support Candy hook callbacks and nonmatching ajax action are covered', () => {
		jest.resetModules();
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.hCaptchaBindEvents = jest.fn();
		require( '../../../assets/js/hcaptcha-support-candy.js' );

		const formSelector = global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const submitSelector = global.wp.hooks.addFilter.mock.calls[ 1 ][ 2 ];
		const ajaxButton = global.wp.hooks.addFilter.mock.calls[ 2 ][ 2 ];
		const primaryButton = document.createElement( 'button' );
		const secondaryButton = document.createElement( 'button' );

		primaryButton.className = 'wpsc-button primary';
		expect( formSelector( 'form.ticket, form.other,' ) ).toContain( 'form.ticket:not(.wpsc-create-ticket)' );
		expect( submitSelector( 'button' ) ).toBe( 'button, button.wpsc-button.primary' );
		expect( ajaxButton( false, primaryButton ) ).toBe( true );
		expect( ajaxButton( false, secondaryButton ) ).toBe( false );
		expect( ajaxButton( true, secondaryButton ) ).toBe( true );

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'Elementor Pro frontend handles early returns and parent params callback', () => {
		jest.resetModules();
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.hCaptchaBindEvents = jest.fn();
		require( '../../../assets/js/hcaptcha-elementor-pro.js' );

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		delete global.elementorFrontend;
		window.hCaptchaElementorPro();
		expect( global.wp.hooks.addFilter ).not.toHaveBeenCalled();

		global.elementorFrontend = {};
		window.parent.HCaptchaMainObject = { params: 'parent-params' };
		window.hCaptchaElementorPro();
		const callback = global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		expect( callback() ).toBe( 'parent-params' );
		delete window.parent.HCaptchaMainObject;
		expect( callback() ).toBe( '' );
	} );

	test( 'Divi handles missing ajax data as an empty string', () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		require( '../../../assets/js/hcaptcha-divi.js' );

		$( document ).trigger( 'ajaxSuccess', [ {}, {} ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'Blocksy action extraction covers object, URLSearchParams, string, and empty event shapes', () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		require( '../../../assets/js/hcaptcha-blocksy.js' );

		expect( window.hCaptchaBlocksy.getAction( { action: 'object-action' } ) ).toBe( 'object-action' );
		expect( window.hCaptchaBlocksy.getAction( {} ) ).toBe( '' );
		expect( window.hCaptchaBlocksy.getAction( new URLSearchParams( 'action=params-action' ) ) ).toBe( 'params-action' );
		expect( window.hCaptchaBlocksy.getAction( 'action=string-action' ) ).toBe( 'string-action' );
		expect( window.hCaptchaBlocksy.getAction( 1 ) ).toBe( '' );
		expect( window.hCaptchaBlocksy.getAction( 'foo=bar' ) ).toBe( '' );

		window.hCaptchaBlocksy.fetchComplete( {} );
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
		window.hCaptchaBlocksy.fetchComplete( {
			detail: {
				args: [ '/url', { body: { action: 'blc_subcribe_to_waitlist' } } ],
			},
		} );
	} );

	test( 'Otter covers ajax button callback and fetch early returns', () => {
		jest.resetModules();
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.wp = global.wp;
		window.hCaptchaBindEvents = jest.fn();
		require( '../../../assets/js/hcaptcha-otter.js' );

		const ajaxButton = global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const button = document.createElement( 'button' );
		button.className = 'wp-block-button__link';
		expect( ajaxButton( false, button ) ).toBe( true );
		expect( ajaxButton( false, document.createElement( 'button' ) ) ).toBe( false );

		window.hCaptchaOtter.fetchBefore( {} );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/wrong', { body: new FormData() } ] } } );
		const body = new FormData();
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/otter/v1/form/frontend', { body } ] } } );
		body.set( 'form_data', '{bad json' );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/otter/v1/form/frontend', { body } ] } } );
		body.set( 'form_data', JSON.stringify( { payload: {} } ) );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ { url: '/otter/v1/form/frontend' }, { body } ] } } );
		body.set( 'form_data', JSON.stringify( { payload: { formId: 'missing' } } ) );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/otter/v1/form/frontend', { body } ] } } );
		window.hCaptchaOtter.fetchComplete( {} );
		window.hCaptchaOtter.fetchComplete( { detail: { args: [ { url: '/wrong' } ] } } );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
describe( 'asset coverage fill: direct app methods', () => {
	afterEach( () => {
		resetDocumentEvents();
		jest.useRealTimers();
		jest.restoreAllMocks();
		delete global.wp;
		delete global.Marionette;
		delete global.nfRadio;
		delete window.hCaptchaNF;
		delete window.hCaptchaFieldController;
		delete window.hCaptchaEssentialAddons;
		delete window.hCaptchaFST;
		delete window.HCaptchaFSTObject;
		delete window.hCaptchaOtter;
		delete window.hCaptchaBindEvents;
		delete window.wp;
		delete window.__hcapFetchWrapped;
		document.body.innerHTML = '';
	} );

	test( 'helper reads attribute values from non-form elements', () => {
		jest.resetModules();
		const { helper } = require( '../../../assets/js/hcaptcha-helper.js' );
		document.body.innerHTML = '<div name="h-captcha-response" value="attr-token"></div>';

		expect( helper.getHCaptchaData( document.body, 'nonce' )[ 'h-captcha-response' ] ).toBe( 'attr-token' );
		document.body.innerHTML = '<div name="h-captcha-response"></div>';
		expect( helper.getHCaptchaData( document.body, 'nonce' )[ 'h-captcha-response' ] ).toBe( '' );

		const fakeRoot = {
			querySelector: jest.fn( ( selector ) => selector.includes( 'h-captcha-response' ) ? {
				value: undefined,
				getAttribute: jest.fn(),
			} : null ),
		};
		expect( helper.getHCaptchaData( fakeRoot, 'nonce' )[ 'h-captcha-response' ] ).toBe( '' );

		const fakeAttributeRoot = {
			querySelector: jest.fn( ( selector ) => selector.includes( 'h-captcha-response' ) ? {
				getAttribute: jest.fn(),
			} : null ),
		};
		expect( helper.getHCaptchaData( fakeAttributeRoot, 'nonce' )[ 'h-captcha-response' ] ).toBe( '' );
	} );

	test( 'Ninja Forms callbacks update models and AJAX payloads', () => {
		jest.resetModules();
		require( '../__mocks__/backboneMarionette' );
		require( '../__mocks__/backboneRadio' );
		let ajaxPrefilterCallback;
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.hCaptchaBindEvents = jest.fn();
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );
		document.body.innerHTML = `
			<div data-field-id="12"><textarea name="h-captcha-response">field-token</textarea></div>
			<form id="nf-form-5-cont">
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="hcap_fst_token" value="fst-token">
				<input name="hcap_hp_sig" value="hp-sig">
				<input id="hcap_hp_nf" value="hp-value">
			</form>
		`;

		require( '../../../assets/js/hcaptcha-nf.js' );
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		const controller = window.hCaptchaFieldController;
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );
		submitButton.className = 'nf-element';

		expect( global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ]( false, submitButton ) ).toBe( true );
		expect( global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ]( false, otherButton ) ).toBe( false );
		expect( global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ]( true, otherButton ) ).toBe( true );

		const model = {
			get: jest.fn( ( key ) => {
				const values = {
					type: 'hcaptcha-for-ninja-forms',
					value: '',
					id: '12',
				};

				return values[ key ];
			} ),
			set: jest.fn(),
		};
		controller.updateHcaptcha( model );
		expect( model.set ).toHaveBeenCalledWith( 'value', 'field-token' );

		const existingController = window.hCaptchaFieldController;
		window.hCaptchaNF.onDomReady();
		expect( window.hCaptchaFieldController ).toBe( existingController );

		const options = {
			data: 'action=nf_ajax_submit&formData=' + encodeURIComponent( JSON.stringify( { id: 5 } ) ),
		};
		ajaxPrefilterCallback( {} );
		ajaxPrefilterCallback( { data: {} } );
		ajaxPrefilterCallback( { data: 'action=other' } );
		ajaxPrefilterCallback( options );
		const missingOptions = {
			data: 'action=nf_ajax_submit&formData=' + encodeURIComponent( JSON.stringify( { id: 7 } ) ),
		};
		document.body.insertAdjacentHTML( 'beforeend', '<form id="nf-form-7-cont"></form>' );
		ajaxPrefilterCallback( missingOptions );
		expect( missingOptions.data ).toContain( 'hcaptcha-widget-id=' );
		expect( missingOptions.data ).toContain( 'hcap_fst_token=' );
		expect( missingOptions.data ).toContain( 'hcap_hp_sig=' );
		expect( missingOptions.data ).toContain( '=&' );

		expect( options.data ).toContain( 'hcaptcha-widget-id=widget-id' );
		expect( options.data ).toContain( 'hcap_fst_token=fst-token' );
		expect( options.data ).toContain( 'hcap_hp_sig=hp-sig' );
		expect( options.data ).toContain( 'hcap_hp_nf=hp-value' );

		window.hCaptchaNF.ajaxSuccessHandler( {}, {}, {} );
		window.hCaptchaNF.ajaxSuccessHandler( {}, {}, { data: {} } );
		window.hCaptchaNF.ajaxSuccessHandler( {}, {}, { data: 'action=other' } );
		window.hCaptchaNF.ajaxSuccessHandler( {}, {}, { data: 'action=nf_ajax_submit' } );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'Essential Addons direct helpers cover fallback input shapes', () => {
		jest.resetModules();
		delete window.wp;
		require( '../../../assets/js/hcaptcha-essential-addons.js' );
		expect( window.hCaptchaEssentialAddons ).toBeDefined();

		jest.resetModules();
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.hCaptchaReset = jest.fn();
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		delete window.hCaptchaEssentialAddons;
		require( '../../../assets/js/hcaptcha-essential-addons.js' );
		const app = window.hCaptchaEssentialAddons;
		const idButton = document.createElement( 'button' );
		const classButton = document.createElement( 'button' );
		idButton.id = 'eael-login-submit';
		classButton.className = 'eael-register-submit';

		expect( app.isSubmitButton() ).toBe( false );
		expect( app.isSubmitButton( idButton ) ).toBe( true );
		expect( app.isSubmitButton( classButton ) ).toBe( true );

		const formData = new FormData();
		formData.set( 'action', 'eael-login-register-form' );
		expect( app.getParams( formData ).get( 'action' ) ).toBe( 'eael-login-register-form' );
		expect( app.getParams( { action: 'object-action' } ).get( 'action' ) ).toBe( 'object-action' );
		expect( app.getParams().toString() ).toBe( '' );
		expect( app.getParams( '?action=query-action' ).get( 'action' ) ).toBe( 'query-action' );
		expect( app.getParams( [ { value: 'missing-name' }, { name: 'empty-value' } ] ).get( 'empty-value' ) ).toBe( '' );
		expect( app.getSubmitName( new URLSearchParams( 'eael-login-nonce=nonce' ) ) ).toBe( 'eael-login-submit' );
		expect( app.getSubmitName( new URLSearchParams( 'eael-register-nonce=nonce' ) ) ).toBe( 'eael-register-submit' );
		expect( app.getFormBySubmitName( '', 'widget' ) ).toBeNull();
		expect( app.getFormsByWidgetId( '' ) ).toEqual( [] );

		document.body.innerHTML = '<form><textarea name="h-captcha-response">token</textarea></form>';
		delete window.hCaptchaReset;
		app.resetForm( document.querySelector( 'form' ) );
		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
		delete window.hCaptchaFST;
		expect( () => app.refreshFSTToken() ).not.toThrow();

		delete window.hCaptchaBindEvents;
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'eael-register-nonce=nonce' } ] );
	} );

	test( 'FST keeps token empty for post pages and incomplete responses', async () => {
		jest.resetModules();
		const originalFetch = global.fetch;
		global.fetch = jest.fn();
		window.HCaptchaFSTObject = {
			ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
			issueTokenAction: 'issue-token',
			issueTokenNonce: 'nonce',
		};
		document.body.className = 'single post-id-456';
		document.body.innerHTML = '<input name="hcap_fst_token" value="old-token">';
		global.fetch.mockResolvedValueOnce( {
			ok: true,
			json: () => Promise.resolve( { success: true, data: {} } ),
		} );

		require( '../../../assets/js/hcaptcha-fst.js' );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( global.fetch.mock.calls[ 0 ][ 1 ].body ).toContain( 'postId=456' );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( '' );

		global.fetch.mockResolvedValueOnce( {
			ok: false,
			json: () => Promise.resolve( { success: true, data: { token: 'ignored-token' } } ),
		} );
		document.querySelector( '[name="hcap_fst_token"]' ).value = 'old-token';
		window.hCaptchaFST.getToken();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( '' );

		global.fetch.mockRejectedValueOnce( new Error( 'network' ) );
		document.querySelector( '[name="hcap_fst_token"]' ).value = 'old-token';
		window.hCaptchaFST.getToken();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		expect( document.querySelector( '[name="hcap_fst_token"]' ).value ).toBe( '' );
		global.fetch = originalFetch;
	} );

	test( 'Blocksy and Otter cover remaining empty-value branches', () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		require( '../../../assets/js/hcaptcha-blocksy.js' );
		expect( window.hCaptchaBlocksy.getAction( new FormData() ) ).toBe( '' );

		jest.resetModules();
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		window.wp = global.wp;
		window.hCaptchaBindEvents = jest.fn();
		document.body.innerHTML = `
			<form id="otter-form">
				<div id="hcap_hp_otter"></div>
			</form>
		`;
		require( '../../../assets/js/hcaptcha-otter.js' );
		const body = new FormData();
		body.set(
			'form_data',
			JSON.stringify( {
				payload: { formId: 'otter-form' },
				'h-captcha-response': 'existing-response',
				'hcaptcha-widget-id': 'existing-widget',
				hcaptcha_otter_nonce: 'existing-nonce',
			} ),
		);

		window.hCaptchaOtter.fetchBefore( { detail: { args: [ {}, { body } ] } } );
		expect( JSON.parse( body.get( 'form_data' ) )[ 'h-captcha-response' ] ).toBe( 'existing-response' );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/otter/v1/form/frontend', { body } ] } } );
		const parsed = JSON.parse( body.get( 'form_data' ) );
		expect( parsed[ 'h-captcha-response' ] ).toBe( 'existing-response' );
		expect( parsed.hcap_hp_otter ).toBe( '' );

		document.body.innerHTML = '<form id="otter-form-no-hp"></form>';
		const noHpBody = new FormData();
		noHpBody.set( 'form_data', JSON.stringify( { payload: { formId: 'otter-form-no-hp' } } ) );
		window.hCaptchaOtter.fetchBefore( { detail: { args: [ '/otter/v1/form/frontend', { body: noHpBody } ] } } );
		expect( JSON.parse( noHpBody.get( 'form_data' ) ).hcap_hp_otter ).toBeUndefined();
	} );
} );
