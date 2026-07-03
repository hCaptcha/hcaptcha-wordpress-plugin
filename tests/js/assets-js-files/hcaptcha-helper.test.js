// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import { helper } from '../../../assets/js/hcaptcha-helper.js';

describe( 'hCaptcha helper', () => {
	let originalFetch;
	let originalDispatchEvent;
	let originalDefineProperty;
	let originalURLSearchParams;

	beforeEach( () => {
		originalFetch = window.fetch;
		originalDispatchEvent = window.dispatchEvent;
		originalDefineProperty = Object.defineProperty;
		originalURLSearchParams = global.URLSearchParams;
		delete window.__hcapFetchWrapped;
		helper.params = null;
	} );

	afterEach( () => {
		window.fetch = originalFetch;
		window.dispatchEvent = originalDispatchEvent;
		Object.defineProperty = originalDefineProperty;
		global.URLSearchParams = originalURLSearchParams;
		delete window.__hcapFetchWrapped;
		jest.restoreAllMocks();
	} );

	test( 'constructor initializes params to null', () => {
		expect( new helper().params ).toBeNull();
	} );

	test( 'gets action values from supported option data shapes', () => {
		const formData = new FormData();
		formData.append( 'action', 'form-action' );

		expect( helper.getAction( { data: formData }, 'action' ) ).toBe( 'form-action' );
		expect( helper.getAction( { data: new FormData() }, 'action' ) ).toBe( '' );
		expect( helper.getAction( { data: { action: 'object-action' } }, 'action' ) ).toBe( 'object-action' );
		expect( helper.getAction( { data: {} }, 'action' ) ).toBe( '' );
		expect( helper.getAction( { data: 12 }, 'action' ) ).toBe( '' );
		expect( helper.getAction( { data: '?action=query-action' }, 'action' ) ).toBe( 'query-action' );
	} );

	test( 'falls back to empty params when query parsing throws', () => {
		global.URLSearchParams = jest
			.fn()
			.mockImplementationOnce( () => {
				throw new Error( 'Bad query.' );
			} )
			.mockImplementation( ( value ) => new originalURLSearchParams( value ) );

		expect( helper.getAction( { data: 'action=broken' }, 'action' ) ).toBe( '' );
		expect( helper.params.get( 'action' ) ).toBe( null );
	} );

	test( 'checks matching actions', () => {
		expect( helper.checkAction( { data: 'action=save' }, 'action', 'save' ) ).toBe( true );
		expect( helper.checkAction( { data: 'action=skip' }, 'action', 'save' ) ).toBe( false );
	} );

	test( 'extracts hCaptcha data from DOM, jQuery wrappers, and empty roots', () => {
		document.body.innerHTML = `
			<form>
				<textarea name="h-captcha-response">token</textarea>
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="nonce_name" value="nonce">
				<input id="hcap_hp_test" name="hcap_hp_test" value="hp-value">
				<input name="hcap_hp_sig" value="sig">
				<input name="hcap_fst_token">
			</form>
		`;

		const form = document.querySelector( 'form' );
		const data = helper.getHCaptchaData( form, 'nonce_name' );

		expect( data ).toMatchObject(
			{
				'h-captcha-response': 'token',
				'hcaptcha-widget-id': 'widget-id',
				nonce_name: 'nonce',
				hcap_hp_test: 'hp-value',
				hcap_hp_sig: 'sig',
				hcap_fst_token: '',
			},
		);

		expect( helper.getHCaptchaData( [ form ], 'nonce_name' ) ).toMatchObject( data );
		expect( helper.getHCaptchaData( [], 'nonce_name' )[ 'h-captcha-response' ] ).toBe( 'token' );
		expect( helper.getHCaptchaData( null, 'nonce_name' )[ 'h-captcha-response' ] ).toBe( 'token' );
		expect( helper.getHCaptchaData( {}, 'nonce_name' )[ 'h-captcha-response' ] ).toBe( '' );
		expect( helper.getHCaptchaData( document.createElement( 'div' ), 'nonce_name' ).nonce_name ).toBe( '' );

		const fakeAttributeRoot = {
			querySelector: jest.fn( ( selector ) => selector.includes( 'h-captcha-response' ) ? {
				getAttribute: jest.fn(),
			} : null ),
		};
		expect( helper.getHCaptchaData( fakeAttributeRoot, 'nonce_name' )[ 'h-captcha-response' ] ).toBe( '' );
	} );

	test( 'adds missing hCaptcha data only for matching actions', () => {
		document.body.innerHTML = `
			<div id="root">
				<input name="h-captcha-response" value="response">
				<input name="hcaptcha-widget-id" value="widget">
				<input name="nonce_name" value="nonce">
				<input id="hcap_hp_id" name="hcap_hp_id" value="hp">
				<input name="hcap_hp_sig" value="sig">
				<input name="hcap_fst_token" value="token">
			</div>
		`;

		const options = {
			data: 'action=expected&hcaptcha-widget-id=already-there',
		};

		helper.addHCaptchaData(
			options,
			'expected',
			'nonce_name',
			document.getElementById( 'root' ),
		);

		expect( options.data ).toContain( 'h-captcha-response=response' );
		expect( options.data ).toContain( 'nonce_name=nonce' );
		expect( options.data ).toContain( 'hcap_hp_id=hp' );
		expect( options.data ).toContain( 'hcap_hp_sig=sig' );
		expect( options.data ).toContain( 'hcap_fst_token=token' );
		expect( options.data ).toContain( 'hcaptcha-widget-id=already-there' );
		expect( options.data ).not.toContain( 'hcaptcha-widget-id=widget' );

		const skippedOptions = {
			data: 'action=other',
		};

		helper.addHCaptchaData(
			skippedOptions,
			'expected',
			'nonce_name',
			document.getElementById( 'root' ),
		);

		expect( skippedOptions.data ).toBe( 'action=other' );
	} );

	test( 'does not install fetch events when fetch is unavailable or already wrapped', () => {
		window.fetch = undefined;

		helper.installFetchEvents();

		expect( window.fetch ).toBeUndefined();

		const fetchMock = jest.fn();
		window.fetch = fetchMock;
		window.__hcapFetchWrapped = true;

		helper.installFetchEvents();

		expect( window.fetch ).toBe( fetchMock );
	} );

	test( 'wraps fetch and dispatches success and complete events', async () => {
		const response = {
			clone: jest.fn( () => 'cloned-response' ),
		};
		const fetchPromise = Promise.resolve( response );
		const fetchMock = jest.fn( () => fetchPromise );
		const events = [];

		window.fetch = fetchMock;
		window.dispatchEvent = jest.fn( ( event ) => events.push( event ) );

		helper.installFetchEvents();
		const returnedPromise = window.fetch( '/endpoint', { method: 'POST' } );

		expect( returnedPromise ).toBe( fetchPromise );
		await returnedPromise;
		await Promise.resolve();
		await Promise.resolve();

		expect( fetchMock ).toHaveBeenCalledWith( '/endpoint', { method: 'POST' } );
		expect( window.__hcapFetchWrapped ).toBe( true );
		expect( events.map( ( event ) => event.type ) ).toEqual(
			[ 'hCaptchaFetch:before', 'hCaptchaFetch:success', 'hCaptchaFetch:complete' ],
		);
		expect( events[ 1 ].detail.response ).toBe( 'cloned-response' );
	} );

	test( 'wraps fetch and dispatches error events', async () => {
		const error = new Error( 'Fetch failed.' );
		const fetchPromise = Promise.reject( error );
		const events = [];

		window.fetch = jest.fn( () => fetchPromise );
		window.dispatchEvent = jest.fn( ( event ) => events.push( event ) );

		helper.installFetchEvents();

		await expect( window.fetch( '/endpoint' ) ).rejects.toThrow( 'Fetch failed.' );
		await Promise.resolve();

		expect( events.map( ( event ) => event.type ) ).toEqual(
			[ 'hCaptchaFetch:before', 'hCaptchaFetch:error', 'hCaptchaFetch:complete' ],
		);
		expect( events[ 1 ].detail.error ).toBe( error );
	} );

	test( 'keeps fetch working when event dispatch or defineProperty fail', async () => {
		const fetchPromise = Promise.resolve( { clone: jest.fn() } );

		window.fetch = jest.fn( () => fetchPromise );
		window.dispatchEvent = jest.fn( () => {
			throw new Error( 'Dispatch blocked.' );
		} );
		Object.defineProperty = jest.fn( () => {
			throw new Error( 'defineProperty blocked.' );
		} );

		helper.installFetchEvents();

		await expect( window.fetch( '/endpoint' ) ).resolves.toBeDefined();
		await Promise.resolve();

		expect( window.__hcapFetchWrapped ).toBe( true );
	} );
} );
