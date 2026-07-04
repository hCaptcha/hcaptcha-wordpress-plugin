// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha GiveWP', () => {
	let helperMock;
	let originalMutationObserver;
	let observers;

	function installMutationObserverMock() {
		observers = [];
		originalMutationObserver = window.MutationObserver;
		window.MutationObserver = jest.fn( function( callback ) {
			this.callback = callback;
			this.observe = jest.fn();
			observers.push( this );
		} );
		global.MutationObserver = window.MutationObserver;
	}

	function loadGiveWP( { hcaptchaForm = '<div class="h-captcha">Captcha</div>' } = {} ) {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		installMutationObserverMock();
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
			domReady: jest.fn(),
		};
		global.wp = window.wp;
		window.HCaptchaGiveWPObject = {
			hcaptchaForm: hcaptchaForm ? JSON.stringify( hcaptchaForm ) : hcaptchaForm,
		};
		global.HCaptchaGiveWPObject = window.HCaptchaGiveWPObject;
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaGiveWP;

		require( '../../../assets/js/hcaptcha-givewp.js' );
	}

	function fetchEvent( eventName, resource, body ) {
		return new CustomEvent( eventName, {
			detail: {
				args: [
					resource,
					{ body },
				],
			},
		} );
	}

	beforeEach( () => {
		document.body.innerHTML = '';
		loadGiveWP();
	} );

	afterEach( () => {
		if ( window.hCaptchaGiveWP ) {
			window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaGiveWP.fetchBefore );
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaGiveWP.fetchComplete );
		}
		window.MutationObserver = originalMutationObserver;
		global.MutationObserver = originalMutationObserver;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaGiveWP;
		delete window.HCaptchaGiveWPObject;
		delete global.HCaptchaGiveWPObject;
		delete window.hCaptchaBindEvents;
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers GiveWP ajax submit buttons and init hooks', () => {
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const giveWrapper = document.createElement( 'div' );
		const otherWrapper = document.createElement( 'div' );
		const giveButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		giveWrapper.classList.add( 'givewp-layouts' );
		giveWrapper.appendChild( giveButton );
		otherWrapper.appendChild( otherButton );

		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );
		expect( window.wp.domReady ).toHaveBeenCalledWith( window.hCaptchaGiveWP.insertCaptcha );
		expect( callback( false, giveButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
	} );

	test( 'adds hCaptcha fields to GiveWP donate fetch body', () => {
		document.body.innerHTML = `
			<div id="root-givewp-donation-form">
				<input name="h-captcha-response" value="response-token">
				<input name="hcaptcha-widget-id" value="widget-id">
				<input name="hcaptcha_give_wp_form_nonce" value="nonce-value">
				<input name="hcap_fst_token" value="fst-token">
				<input name="hcap_hp_sig" value="hp-sig">
				<input id="hcap_hp_test" value="hp-value">
			</div>
		`;
		const body = new FormData();
		const event = fetchEvent( 'hCaptchaFetch:before', '/wp-json/givewp/v3?givewp-route=donate', body );

		window.hCaptchaGiveWP.fetchBefore( event );

		expect( body.get( 'h-captcha-response' ) ).toBe( 'response-token' );
		expect( body.get( 'hcaptcha-widget-id' ) ).toBe( 'widget-id' );
		expect( body.get( 'hcaptcha_give_wp_form_nonce' ) ).toBe( 'nonce-value' );
		expect( body.get( 'hcap_fst_token' ) ).toBe( 'fst-token' );
		expect( body.get( 'hcap_hp_sig' ) ).toBe( 'hp-sig' );
		expect( body.get( 'hcap_hp_test' ) ).toBe( 'hp-value' );
		expect( event.detail.args[ 1 ].body ).toBe( body );
	} );

	test( 'uses fetch body fallbacks when optional GiveWP inputs are missing values', () => {
		const fakeForm = {
			querySelector: jest.fn( ( selector ) => {
				if ( selector === '[id^="hcap_hp_"]' ) {
					return { id: 'hcap_hp_empty' };
				}

				return null;
			} ),
		};
		const body = new URLSearchParams();
		const getElementByIdSpy = jest.spyOn( document, 'getElementById' ).mockReturnValueOnce( fakeForm );

		window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', { url: '/wp-json/givewp/v3?givewp-route=donate' }, body ) );

		expect( getElementByIdSpy ).toHaveBeenCalledWith( 'root-givewp-donation-form' );
		expect( body.get( 'h-captcha-response' ) ).toBe( 'undefined' );
		expect( body.get( 'hcap_hp_empty' ) ).toBe( '' );
	} );

	test( 'ignores GiveWP fetches that cannot be amended', () => {
		expect( () => window.hCaptchaGiveWP.fetchBefore() ).not.toThrow();
		window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', '/endpoint?givewp-route=donate', 'not-form-data' ) );
		window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', '/endpoint?givewp-route=other', new FormData() ) );
		window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', 'http://[', new FormData() ) );
		window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', {}, new FormData() ) );

		expect( () => window.hCaptchaGiveWP.fetchBefore( fetchEvent( 'hCaptchaFetch:before', '/endpoint?givewp-route=donate', new FormData() ) ) ).not.toThrow();
	} );

	test( 'rebinds hCaptcha after GiveWP donate fetch completes only', () => {
		window.hCaptchaGiveWP.fetchComplete();
		window.hCaptchaGiveWP.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', '/endpoint?givewp-route=other', new FormData() ) );
		window.hCaptchaGiveWP.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', 'http://[', new FormData() ) );
		window.hCaptchaGiveWP.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', {}, new FormData() ) );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		window.hCaptchaGiveWP.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', { url: '/endpoint?givewp-route=donate' }, new FormData() ) );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		delete window.hCaptchaBindEvents;
		expect( () => window.hCaptchaGiveWP.fetchComplete( fetchEvent( 'hCaptchaFetch:complete', '/endpoint?givewp-route=donate', new FormData() ) ) ).not.toThrow();
	} );

	test( 'insertCaptcha returns when script data or target form is missing', () => {
		window.HCaptchaGiveWPObject.hcaptchaForm = '';
		expect( () => window.hCaptchaGiveWP.insertCaptcha() ).not.toThrow();

		window.HCaptchaGiveWPObject.hcaptchaForm = JSON.stringify( '<div class="h-captcha"></div>' );
		expect( () => window.hCaptchaGiveWP.insertCaptcha() ).not.toThrow();
		expect( observers ).toHaveLength( 0 );
	} );

	test( 'insertCaptcha observes GiveWP form and handles mutation guard branches', () => {
		document.body.innerHTML = '<div id="root-givewp-donation-form"></div>';

		window.hCaptchaGiveWP.insertCaptcha();
		expect( observers[ 0 ].observe ).toHaveBeenCalledWith(
			document.getElementById( 'root-givewp-donation-form' ),
			{
				childList: true,
				subtree: true,
			},
		);

		observers[ 0 ].callback( [ { type: 'attributes' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		observers[ 0 ].callback( [ { type: 'childList' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		document.getElementById( 'root-givewp-donation-form' ).innerHTML = '<button type="submit">Donate</button><div class="h-captcha"></div>';
		observers[ 0 ].callback( [ { type: 'childList' } ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'insertCaptcha adds hCaptcha before submit section and before multistep submit button', () => {
		document.body.innerHTML = `
			<div id="root-givewp-donation-form">
				<section id="submit-section"><button type="submit">Donate</button></section>
			</div>
		`;

		window.hCaptchaGiveWP.insertCaptcha();
		observers[ 0 ].callback( [ { type: 'childList' } ] );

		expect( document.querySelector( '#root-givewp-donation-form > section.givewp-layouts' ) ).not.toBeNull();
		expect( document.querySelector( '#root-givewp-donation-form' ).firstElementChild.classList.contains( 'givewp-layouts' ) ).toBe( true );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		document.body.innerHTML = `
			<div id="root-givewp-donation-form">
				<div id="button-wrap"><button type="submit">Donate</button></div>
			</div>
		`;
		observers = [];
		window.hCaptchaGiveWP.insertCaptcha();
		observers[ 0 ].callback( [ { type: 'childList' } ] );

		expect( document.querySelector( '#button-wrap' ).firstElementChild.classList.contains( 'givewp-layouts' ) ).toBe( true );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaGiveWP = existingApp;

		require( '../../../assets/js/hcaptcha-givewp.js' );

		expect( window.hCaptchaGiveWP ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
