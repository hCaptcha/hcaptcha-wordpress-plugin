// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha WooCommerce block checkout', () => {
	let helperMock;

	function loadCheckout() {
		jest.resetModules();
		helperMock = {
			installFetchEvents: jest.fn(),
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
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaWCBlockCheckout;

		require( '../../../assets/js/hcaptcha-wc-block-checkout.js' );
	}

	function eventWithArgs( eventName, args ) {
		return new CustomEvent( eventName, {
			detail: { args },
		} );
	}

	beforeEach( () => {
		document.body.innerHTML = '';
		loadCheckout();
	} );

	afterEach( () => {
		if ( window.hCaptchaWCBlockCheckout ) {
			window.removeEventListener( 'hCaptchaFetch:before', window.hCaptchaWCBlockCheckout.fetchBefore );
			window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaWCBlockCheckout.fetchComplete );
		}
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaWCBlockCheckout;
		delete window.hCaptchaBindEvents;
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'registers checkout button selectors and installs fetch events', () => {
		const selectorFilter = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const ajaxFilter = window.wp.hooks.addFilter.mock.calls[ 1 ][ 2 ];
		const checkoutButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		checkoutButton.classList.add( 'wc-block-components-checkout-place-order-button' );

		expect( selectorFilter( 'button[type="submit"]' ) ).toBe( 'button[type="submit"], .wc-block-components-checkout-place-order-button' );
		expect( ajaxFilter( false, checkoutButton ) ).toBe( true );
		expect( ajaxFilter( false, otherButton ) ).toBe( false );
		expect( ajaxFilter( true, otherButton ) ).toBe( true );
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'adds hCaptcha fields to WooCommerce checkout block fetch body', () => {
		document.body.innerHTML = `
			<div data-block-name="woocommerce/checkout">
				<input name="hcaptcha-widget-id" value="widget-id">
				<textarea name="h-captcha-response">response-token</textarea>
				<input id="hcap_hp_test" value="hp-value">
				<input name="hcap_hp_sig" value="hp-sig">
				<input name="hcap_fst_token" value="fst-token">
			</div>
		`;
		const config = { body: JSON.stringify( { existing: 'value' } ) };
		const event = eventWithArgs( 'hCaptchaFetch:before', [ '/wc/store/v1/checkout', config ] );

		window.hCaptchaWCBlockCheckout.fetchBefore( event );

		const body = JSON.parse( config.body );

		expect( body.existing ).toBe( 'value' );
		expect( body[ 'hcaptcha-widget-id' ] ).toBe( 'widget-id' );
		expect( body[ 'h-captcha-response' ] ).toBe( 'response-token' );
		expect( body.hcap_hp_test ).toBe( 'hp-value' );
		expect( body.hcap_hp_sig ).toBe( 'hp-sig' );
		expect( body.hcap_fst_token ).toBe( 'fst-token' );
		expect( event.detail.args.config ).toBe( config );
	} );

	test( 'uses empty fallback values when checkout hCaptcha inputs are missing', () => {
		document.body.innerHTML = '<div data-block-name="woocommerce/checkout"></div>';
		const config = { body: '{' };

		window.hCaptchaWCBlockCheckout.fetchBefore( eventWithArgs( 'hCaptchaFetch:before', [ '/wc/store/v1/checkout', config ] ) );

		const body = JSON.parse( config.body );

		expect( body[ 'hcaptcha-widget-id' ] ).toBe( '' );
		expect( body[ 'h-captcha-response' ] ).toBe( '' );
		expect( body[ '' ] ).toBe( '' );
		expect( body.hcap_hp_sig ).toBe( '' );
		expect( body.hcap_fst_token ).toBe( '' );
	} );

	test( 'ignores non-checkout fetches and rebinds after checkout fetch complete only', () => {
		const config = { body: '{}' };

		window.hCaptchaWCBlockCheckout.fetchBefore( eventWithArgs( 'hCaptchaFetch:before', [ '/other-route', config ] ) );
		expect( config.body ).toBe( '{}' );

		window.hCaptchaWCBlockCheckout.fetchComplete();
		window.hCaptchaWCBlockCheckout.fetchComplete( eventWithArgs( 'hCaptchaFetch:complete', [ '/other-route' ] ) );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		window.hCaptchaWCBlockCheckout.fetchComplete( eventWithArgs( 'hCaptchaFetch:complete', [ '/wc/store/v1/checkout' ] ) );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaWCBlockCheckout = existingApp;

		require( '../../../assets/js/hcaptcha-wc-block-checkout.js' );

		expect( window.hCaptchaWCBlockCheckout ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
