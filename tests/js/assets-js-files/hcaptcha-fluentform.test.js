// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha FluentForm', () => {
	let helperMock;
	let originalMutationObserver;
	let mutationObservers;
	let originalRender;

	function loadFluentForm() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
			addHCaptchaData: jest.fn( ( options ) => {
				options.data = options.data + '&h-captcha-response=added-response';
			} ),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		window.HCaptchaFluentFormObject = {
			id: 'fluentform-conversational-script',
			url: 'https://test.test/fluentform-conversational.js',
		};
		global.HCaptchaFluentFormObject = window.HCaptchaFluentFormObject;
		originalRender = jest.fn();
		window.hcaptcha = {
			render: originalRender,
		};
		global.hcaptcha = window.hcaptcha;
		window.hCaptcha = {
			getParams: jest.fn( () => ( {
				size: 'invisible',
			} ) ),
		};
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaFluentForm;

		require( '../../../assets/js/hcaptcha-fluentform.js' );
	}

	function installMutationObserverMock() {
		mutationObservers = [];
		originalMutationObserver = window.MutationObserver;
		window.MutationObserver = jest.fn( function( callback ) {
			this.callback = callback;
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			mutationObservers.push( this );
		} );
		global.MutationObserver = window.MutationObserver;
	}

	function makeBody( type = 'formData' ) {
		return type === 'urlSearchParams' ? new URLSearchParams() : new FormData();
	}

	function fetchEvent( eventName, body ) {
		return new CustomEvent( eventName, {
			detail: {
				args: [
					'/wp-admin/admin-ajax.php',
					{ body },
				],
			},
		} );
	}

	beforeEach( () => {
		document.body.innerHTML = '<script id="first-script"></script>';
		loadFluentForm();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		if ( window.hCaptchaFluentForm ) {
			window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaFluentForm.fetchBefore );
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaFluentForm.fetchComplete );
			document.removeEventListener( 'hCaptchaLoaded', window.hCaptchaFluentForm.onHCaptchaLoaded );
		}
		if ( originalMutationObserver ) {
			window.MutationObserver = originalMutationObserver;
			global.MutationObserver = originalMutationObserver;
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.HCaptchaFluentFormObject;
		delete global.HCaptchaFluentFormObject;
		delete window.hCaptchaFluentForm;
		delete window.hcaptcha;
		delete global.hcaptcha;
		delete window.hCaptcha;
		delete window.hCaptchaBindEvents;
		delete window.__hcapFetchWrapped;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'installs fetch hooks and ajaxComplete handler', () => {
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=other_action' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=fluentform_submit' } ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'adds hCaptcha data to FluentForm fetch request data', () => {
		document.body.innerHTML = '<div class="ff_conv_app_42"></div>';
		const body = makeBody();

		body.set( 'action', 'fluentform_submit' );
		body.set( 'form_id', '42' );
		body.set( 'data', 'name=value' );

		const event = fetchEvent( 'hCaptchaFetch:before', body );

		window.hCaptchaFluentForm.fetchBefore( event );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			{
				data: 'name=value&h-captcha-response=added-response',
			},
			'',
			'hcaptcha_fluentform_nonce',
			expect.objectContaining( {
				0: document.querySelector( '.ff_conv_app_42' ),
				length: 1,
			} ),
		);
		expect( body.get( 'data' ) ).toBe( 'name=value&h-captcha-response=added-response' );
		expect( event.detail.args[ 1 ].body ).toBe( body );
	} );

	test( 'uses empty fallbacks for missing FluentForm data and form id', () => {
		document.body.innerHTML = '<div class="ff_conv_app_"></div>';
		const body = makeBody();

		body.set( 'action', 'fluentform_submit' );

		window.hCaptchaFluentForm.fetchBefore( fetchEvent( 'hCaptchaFetch:before', body ) );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			{
				data: '&h-captcha-response=added-response',
			},
			'',
			'hcaptcha_fluentform_nonce',
			expect.objectContaining( {
				0: document.querySelector( '.ff_conv_app_' ),
				length: 1,
			} ),
		);
		expect( body.get( 'data' ) ).toBe( '&h-captcha-response=added-response' );
	} );

	test( 'supports URLSearchParams request bodies and skips missing FluentForm nodes', () => {
		const body = makeBody( 'urlSearchParams' );

		body.set( 'action', 'fluentform_submit' );
		body.set( 'form_id', 'missing' );
		body.set( 'data', 'name=value' );

		window.hCaptchaFluentForm.fetchBefore( fetchEvent( 'hCaptchaFetch:before', body ) );

		expect( helperMock.addHCaptchaData ).not.toHaveBeenCalled();
		expect( body.get( 'data' ) ).toBe( 'name=value' );
	} );

	test( 'ignores FluentForm fetch requests that cannot be amended', () => {
		expect( () => window.hCaptchaFluentForm.fetchBefore() ).not.toThrow();
		window.hCaptchaFluentForm.fetchBefore( new CustomEvent( 'hCaptchaFetch:before', {
			detail: {
				args: [ '/endpoint', { body: 'not-form-data' } ],
			},
		} ) );

		const otherAction = makeBody();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaFluentForm.fetchBefore( fetchEvent( 'hCaptchaFetch:before', otherAction ) );

		expect( helperMock.addHCaptchaData ).not.toHaveBeenCalled();
	} );

	test( 'rebinds hCaptcha after FluentForm fetch complete only', () => {
		window.hCaptchaFluentForm.fetchComplete();
		window.hCaptchaFluentForm.fetchComplete( new CustomEvent( 'hCaptchaFetch:complete', {
			detail: {
				args: [ '/endpoint', { body: 'not-form-data' } ],
			},
		} ) );

		const otherAction = makeBody();
		otherAction.set( 'action', 'other_action' );
		window.hCaptchaFluentForm.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', otherAction ) );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		const body = makeBody( 'urlSearchParams' );
		body.set( 'action', 'fluentform_submit' );
		window.hCaptchaFluentForm.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', body ) );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does nothing when conversational form is missing or already has hCaptcha', () => {
		expect( () => window.hCaptchaFluentForm.onHCaptchaLoaded() ).not.toThrow();

		document.body.innerHTML = '<script></script><div class="ffc_conv_form"><h-captcha></h-captcha></div>';

		window.hCaptchaFluentForm.onHCaptchaLoaded();

		expect( document.getElementById( window.HCaptchaFluentFormObject.id ) ).toBeNull();
	} );

	test( 'returns when conversational form disappears after the initial lookup', () => {
		const originalQuerySelector = document.querySelector.bind( document );
		const fakeForm = {
			querySelector: jest.fn( () => null ),
		};
		let formLookupCount = 0;

		jest.spyOn( document, 'querySelector' ).mockImplementation( ( selector ) => {
			if ( selector === '.ffc_conv_form' ) {
				formLookupCount++;

				return formLookupCount === 1 ? fakeForm : null;
			}

			return originalQuerySelector( selector );
		} );

		expect( () => window.hCaptchaFluentForm.onHCaptchaLoaded() ).not.toThrow();
	} );

	test( 'processes conversational form after footer appears and adds visible hCaptcha', async () => {
		installMutationObserverMock();
		document.body.innerHTML = `
			<script id="first-script"></script>
			<div class="ffc_conv_form">
				<div class="vff"></div>
				<div class="ff-el-group"><button class="ff-btn">Submit</button></div>
			</div>
			<div class="h-captcha-hidden" style="display:none"><h-captcha></h-captcha></div>
		`;

		window.hCaptchaFluentForm.onHCaptchaLoaded();

		mutationObservers[ 0 ].callback();
		expect( mutationObservers[ 0 ].disconnect ).not.toHaveBeenCalled();

		expect( document.getElementById( window.HCaptchaFluentFormObject.id ).src ).toBe( window.HCaptchaFluentFormObject.url );
		expect( window.hcaptcha.render ).not.toBe( originalRender );

		document.querySelector( '.ffc_conv_form' ).insertAdjacentHTML( 'beforeend', '<div class="vff-footer"></div>' );
		mutationObservers[ 0 ].callback();
		await Promise.resolve();

		const formObserver = mutationObservers[ 1 ];
		const step = document.querySelector( '.vff' );

		expect( formObserver.observe ).toHaveBeenCalledWith(
			document.querySelector( '.ffc_conv_form' ),
			{
				attributes: true,
				attributeFilter: [ 'class' ],
				subtree: true,
			},
		);

		formObserver.callback( [ { type: 'childList', target: step } ] );
		formObserver.callback( [ { type: 'attributes', attributeName: 'style', target: step } ] );
		formObserver.callback( [ { type: 'attributes', attributeName: 'class', target: step } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		step.classList.add( 'ffc_last_step' );
		formObserver.callback( [ { type: 'attributes', attributeName: 'class', target: step } ] );
		formObserver.callback( [ { type: 'attributes', attributeName: 'class', target: step } ] );

		const visibleCaptcha = document.querySelector( '.ff-el-group .h-captcha-hidden' );

		expect( visibleCaptcha ).toBeNull();
		expect( document.querySelector( '.ff-el-group .h-captcha' ) ).not.toBeNull();
		expect( document.querySelector( '.ff-el-group form .ff-btn' ) ).not.toBeNull();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		const callback = jest.fn();
		window.hcaptcha.render( '#container', { callback } );
		expect( originalRender ).toHaveBeenCalledWith(
			'#container',
			expect.objectContaining( {
				callback,
				size: 'invisible',
			} ),
		);
	} );

	test( 'keeps conversational hCaptcha visible and normal sized when form owns captcha container', async () => {
		installMutationObserverMock();
		document.body.innerHTML = `
			<script id="first-script"></script>
			<div id="hcaptcha-container"></div>
			<div class="ffc_conv_form">
				<div class="vff-footer"></div>
				<button class="ff-btn">Submit</button>
			</div>
		`;

		window.hCaptchaFluentForm.onHCaptchaLoaded();
		await Promise.resolve();

		expect( mutationObservers ).toHaveLength( 0 );

		const callback = jest.fn();
		window.hcaptcha.render( '#hcaptcha-container', { callback } );

		expect( originalRender ).toHaveBeenCalledWith(
			'#hcaptcha-container',
			expect.objectContaining( {
				callback,
				size: 'normal',
			} ),
		);
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaFluentForm = existingApp;

		require( '../../../assets/js/hcaptcha-fluentform.js' );

		expect( window.hCaptchaFluentForm ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
