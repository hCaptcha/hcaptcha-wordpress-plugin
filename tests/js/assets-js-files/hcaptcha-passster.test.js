// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Passster', () => {
	let helperMock;
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;
	let originalFetch;
	let fetchMock;

	function loadPassster() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
			getHCaptchaData: jest.fn( () => ( {
				'h-captcha-response': 'captcha-response',
				hcap_fst_token: 'fst-token',
			} ) ),
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
		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		require( '../../../assets/js/hcaptcha-passster.js' );
	}

	beforeEach( () => {
		document.body.innerHTML = `
			<form id="area-form">
				<div data-area="secret"></div>
				<textarea name="h-captcha-response">response</textarea>
				<button type="button" class="passster-submit">Submit</button>
			</form>
		`;
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		originalFetch = window.fetch;
		fetchMock = jest.fn().mockResolvedValue( { ok: true } );
		window.fetch = fetchMock;
		loadPassster();
	} );

	afterEach( () => {
		$( document ).off( 'click ajaxComplete' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.wp;
		delete global.wp;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		window.fetch = originalFetch;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'marks Passster submit buttons as ajax submit buttons', () => {
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		submitButton.classList.add( 'passster-submit' );

		expect( callback( false, submitButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
	} );

	test( 'ignores non-string ajax data and delegates empty query data to helper', () => {
		const emptyOptions = {};

		ajaxPrefilterCallback( emptyOptions );
		ajaxPrefilterCallback( { data: { area: 'secret' } } );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledTimes( 1 );
		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			emptyOptions,
			'validate_input',
			'hcaptcha_passster_nonce',
			expect.any( Object ),
		);
	} );

	test( 'adds hCaptcha data for the Passster area form', () => {
		const options = {
			data: 'action=validate_input&area=secret',
		};

		ajaxPrefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'validate_input',
			'hcaptcha_passster_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'area-form' ),
				length: 1,
			} ),
		);
	} );

	test( 'refreshes tokens after a legacy ajax request', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=validate_input&area=secret' } ] );

		const form = document.getElementById( 'area-form' );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'adds hCaptcha data to REST unlock and refreshes tokens', async () => {
		$( '.passster-submit' ).trigger( 'click' );

		const response = await window.fetch(
			'https://example.test/wp-json/passster/v1/unlock',
			{
				method: 'POST',
				body: JSON.stringify( { password: 'bad-password' } ),
			},
		);

		expect( response ).toEqual( { ok: true } );
		expect( helperMock.getHCaptchaData ).toHaveBeenCalledWith(
			document.getElementById( 'area-form' ),
			'hcaptcha_passster_nonce',
		);
		expect( fetchMock ).toHaveBeenCalledTimes( 1 );

		const request = JSON.parse( fetchMock.mock.calls[ 0 ][ 1 ].body );

		expect( request ).toEqual( {
			password: 'bad-password',
			'h-captcha-response': 'captcha-response',
			hcap_fst_token: 'fst-token',
		} );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( document.getElementById( 'area-form' ) );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated and incomplete legacy AJAX responses', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, { data: {} } ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=other&area=secret' } ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=validate_input&area=missing' } ] );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );

	test( 'clears legacy responses when optional reset and FST hooks are absent', () => {
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=validate_input&area=secret' } ] );

		expect( document.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
	} );

	test( 'passes through non-unlock fetches and requests without a selected form', async () => {
		await window.fetch( undefined );
		await window.fetch( { url: '/passster/v1/unlock' } );

		expect( fetchMock ).toHaveBeenCalledWith( undefined, {} );
		expect( fetchMock ).toHaveBeenCalledWith( { url: '/passster/v1/unlock' }, {} );
		expect( helperMock.getHCaptchaData ).not.toHaveBeenCalled();
	} );

	test( 'passes malformed and non-string unlock bodies through before refreshing', async () => {
		$( '.passster-submit' ).trigger( 'click' );

		await window.fetch( '/passster/v1/unlock', { body: '{bad json' } );
		await window.fetch( '/passster/v1/unlock', { body: { password: 'secret' } } );

		expect( fetchMock.mock.calls[ 0 ][ 1 ].body ).toBe( '{bad json' );
		expect( fetchMock.mock.calls[ 1 ][ 1 ].body ).toEqual( { password: 'secret' } );
		expect( window.hCaptchaReset ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'does not wrap fetch when the browser has no fetch implementation', () => {
		window.fetch = undefined;
		jest.resetModules();

		require( '../../../assets/js/hcaptcha-passster.js' );

		expect( window.fetch ).toBeUndefined();
	} );
} );
