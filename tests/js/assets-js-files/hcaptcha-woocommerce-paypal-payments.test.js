// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha WooCommerce PayPal Payments', () => {
	let helperMock;
	let previousFetch;
	let originalMutationObserver;
	let observers;

	function setupGlobals( options = {} ) {
		observers = [];
		originalMutationObserver = window.MutationObserver;
		window.MutationObserver = jest.fn( function( callback ) {
			this.callback = callback;
			this.observe = jest.fn();
			observers.push( this );
		} );
		global.MutationObserver = window.MutationObserver;
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		window.jQuery = $;
		previousFetch = jest.fn( () => Promise.resolve( 'fetch-response' ) );
		window.fetch = previousFetch;
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptcha = {
			getWidgetId: jest.fn( () => 'widget-id' ),
		};
		window.hcaptcha = {
			execute: jest.fn( () => {
				document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted' ) );
			} ),
		};
		global.hcaptcha = window.hcaptcha;
		window.widgetBuilder = options.widgetBuilder || {
			registerButtons: jest.fn( ( wrapper, buttonOptions ) => buttonOptions ),
			buttons: new Map( [
				[ 'button', { wrapper: '#paypal-wrapper', options: { label: 'paypal' } } ],
			] ),
		};
		window.paypal = options.paypal || {
			Buttons: jest.fn( ( buttonOptions ) => ( { buttonOptions } ) ),
		};
	}

	function loadPayPal( options = {} ) {
		jest.resetModules();
		helperMock = {
			getHCaptchaData: jest.fn( ( root, nonceName ) => ( {
				'h-captcha-response': root?.dataset?.response || '',
				'hcaptcha-widget-id': root?.dataset?.widget || '',
				[ nonceName ]: root?.dataset?.nonce || '',
				hcap_hp_sig: root?.dataset?.sig || '',
				hcap_fst_token: root?.dataset?.token || '',
			} ) ),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		setupGlobals( options );
		delete window.hCaptchaWooCommercePayPalPayments;
		delete window.__hCaptchaWooCommercePayPalPaymentsFetchWrapped;
		document.body.innerHTML = options.html || '';

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments.js' );

		return window.hCaptchaWooCommercePayPalPayments;
	}

	beforeEach( () => {
		jest.useFakeTimers();
	} );

	afterEach( () => {
		jest.runOnlyPendingTimers();
		jest.useRealTimers();
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		$( document ).off( 'ppcp-paypal-loaded' );
		window.MutationObserver = originalMutationObserver;
		global.MutationObserver = originalMutationObserver;
		delete window.hCaptchaWooCommercePayPalPayments;
		delete window.__hCaptchaWooCommercePayPalPaymentsFetchWrapped;
		delete window.widgetBuilder;
		delete window.paypal;
		delete window.ppcpBlocksPaypalExpressButtons;
		delete window[ 'ppcp-blocks-editor-paypal-buttons' ];
		delete window.wp;
		delete global.wp;
		delete window.hCaptcha;
		delete window.hcaptcha;
		delete global.hcaptcha;
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		document.head.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'initializes filters and wraps widgetBuilder and PayPal Buttons', async () => {
		const app = loadPayPal();
		const selectorFilter = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const registered = window.widgetBuilder.registerButtons( '#paypal-wrapper', { onClick: jest.fn() } );
		const buttonResult = window.paypal.Buttons( { onClick: jest.fn() } );

		expect( selectorFilter( 'form' ) ).toBe( 'form, .hcaptcha-woocommerce-paypal-payments, .widget_shopping_cart' );
		expect( window.widgetBuilder.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( registered.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( window.widgetBuilder.buttons.get( 'button' ).options.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( buttonResult.buttonOptions.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( app.getFormSelector( 'form' ) ).toContain( '.widget_shopping_cart' );
	} );

	test( 'wraps widgetBuilder when stored buttons are not a Map', () => {
		loadPayPal( {
			widgetBuilder: {
				registerButtons: jest.fn( ( wrapper, buttonOptions ) => buttonOptions ),
				buttons: {},
			},
		} );

		const registered = window.widgetBuilder.registerButtons( '#paypal-wrapper', { onClick: jest.fn() } );

		expect( window.widgetBuilder.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( registered.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
	} );
	test( 'wrapper scheduling and namespace guards are idempotent', () => {
		const app = loadPayPal();

		app.installPayPalButtonsWrapper();
		delete window.widgetBuilder;
		app.widgetBuilderTimer = null;
		app.widgetBuilderAttempts = 0;
		app.installPayPalButtonsWrapper();
		expect( app.widgetBuilderTimer ).not.toBeNull();
		app.schedulePayPalButtonsWrapper();
		app.widgetBuilderAttempts = 41;
		app.widgetBuilderTimer = null;
		expect( () => app.schedulePayPalButtonsWrapper() ).not.toThrow();

		delete window.paypal;
		delete window.ppcpBlocksPaypalExpressButtons;
		delete window[ 'ppcp-blocks-editor-paypal-buttons' ];
		app.payPalSdkTimer = null;
		app.payPalSdkAttempts = 0;
		app.installPayPalSdkWrapper();
		expect( app.payPalSdkTimer ).not.toBeNull();
		app.schedulePayPalSdkWrapper();
		app.payPalSdkAttempts = 41;
		app.payPalSdkTimer = null;
		expect( () => app.schedulePayPalSdkWrapper() ).not.toThrow();

		expect( app.wrapPayPalNamespace( 'missingNamespace' ) ).toBe( false );
		window.jQuery = undefined;
		expect( () => app.listenForPayPalSdk() ).not.toThrow();
		expect( () => app.wrapLoadedPayPalNamespace() ).not.toThrow();
	} );

	test( 'watches PayPal namespaces through loaded event and defineProperty fallback', () => {
		const app = loadPayPal();
		const loadedPaypal = {
			Buttons: jest.fn( ( options ) => options ),
		};

		app.wrapLoadedPayPalNamespace( loadedPaypal );
		expect( loadedPaypal.__hCaptchaWooCommercePayPalPaymentsButtonsWatched ).toBe( true );
		app.watchPayPalButtons( loadedPaypal );

		const paypal = {
			Buttons: jest.fn( ( options ) => options ),
		};
		const originalDefineProperty = Object.defineProperty;
		jest.spyOn( Object, 'defineProperty' ).mockImplementationOnce( () => {
			throw new Error( 'blocked' );
		} );

		app.watchPayPalButtons( paypal );

		expect( paypal.Buttons.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		Object.defineProperty = originalDefineProperty;
	} );

	test( 'wrap option helpers handle empty and already wrapped options', () => {
		const app = loadPayPal();
		const wrappedOptions = {
			__hCaptchaWooCommercePayPalPaymentsWrapped: true,
		};
		const wrappedButtons = function() {};
		wrappedButtons.__hCaptchaWooCommercePayPalPaymentsWrapped = true;

		expect( app.wrapPayPalButtonOptions( '', null ) ).toBeNull();
		expect( app.wrapPayPalButtonOptions( '', wrappedOptions ) ).toBe( wrappedOptions );
		expect( app.wrapPayPalButtons( {}, null ) ).toBeNull();
		expect( app.wrapPayPalButtons( {}, wrappedButtons ) ).toBe( wrappedButtons );
	} );

	test( 'wrapped PayPal onClick executes captcha, original handler, and fallback actions', async () => {
		const app = loadPayPal();
		const originalOnClick = jest.fn( function() {
			return 'original-result';
		} );
		const actions = {
			resolve: jest.fn( () => 'resolved' ),
		};
		const wrappedWithResult = app.wrapPayPalButtonOptions( '', { onClick: originalOnClick } );
		const wrappedWithoutResult = app.wrapPayPalButtonOptions( '', {} );

		jest.spyOn( app, 'executeCaptchaBeforePayPal' ).mockResolvedValue( undefined );

		await expect( wrappedWithResult.onClick( 'data', actions ) ).resolves.toBe( 'original-result' );
		await expect( wrappedWithoutResult.onClick( 'data', actions ) ).resolves.toBe( 'resolved' );

		expect( originalOnClick ).toHaveBeenCalledWith( 'data', actions );
		expect( actions.resolve ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'fetch wrapper handles non PayPal and PayPal create-order requests', async () => {
		const app = loadPayPal( {
			html: '<form class="checkout" data-response="checkout-token" data-nonce="checkout-nonce"><div class="h-captcha"></div><input name="hcaptcha_wc_checkout_nonce" value="checkout-nonce"></form><div class="hcaptcha-woocommerce-paypal-payments"></div>',
		} );

		app.executeCheckoutCaptcha = jest.fn( () => Promise.resolve() );
		app.executePayPalCaptcha = jest.fn( () => Promise.resolve() );
		app.moveBlockCaptcha = jest.fn();

		await window.fetch( '/plain-endpoint', { body: '{}' } );
		expect( previousFetch ).toHaveBeenCalledWith( '/plain-endpoint', { body: '{}' } );

		app.prepareCreateOrderConfig = jest.fn()
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'checkout' } ) } )
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'checkout', 'h-captcha-response': 'after-checkout' } ) } );
		await window.fetch( '/?wc-ajax=ppc-create-order', { body: JSON.stringify( { context: 'checkout' } ) } );
		expect( app.executeCheckoutCaptcha ).toHaveBeenCalledTimes( 1 );
		expect( JSON.parse( previousFetch.mock.calls.at( -1 )[ 1 ].body )[ 'h-captcha-response' ] ).toBe( 'after-checkout' );

		app.prepareCreateOrderConfig = jest.fn()
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'cart' } ) } )
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'cart', 'h-captcha-response': 'after-paypal' } ) } );
		await window.fetch( '/?wc-ajax=ppc-create-order', { body: JSON.stringify( { context: 'cart' } ) } );
		expect( app.executePayPalCaptcha ).toHaveBeenCalledWith( '' );

		document.body.innerHTML = '<form class="checkout"></form><div class="hcaptcha-woocommerce-paypal-payments"></div>';
		app.prepareCreateOrderConfig = jest.fn()
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'checkout' } ) } )
			.mockReturnValueOnce( { body: JSON.stringify( { context: 'checkout', 'h-captcha-response': 'after-paypal' } ) } );
		await window.fetch( '/?wc-ajax=ppc-create-order', { body: JSON.stringify( { context: 'checkout' } ) } );
		expect( app.executeCheckoutCaptcha ).toHaveBeenCalledTimes( 1 );
		expect( app.executePayPalCaptcha ).toHaveBeenCalledTimes( 2 );

		app.prepareCreateOrderConfig = jest.fn( ( config ) => ( { ...config, body: JSON.stringify( { context: 'cart', 'h-captcha-response': 'present' } ) } ) );
		await window.fetch( '/?wc-ajax=ppc-create-order' );
		expect( app.executePayPalCaptcha ).toHaveBeenCalledTimes( 2 );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalled();
		expect( app.moveBlockCaptcha ).toHaveBeenCalled();

		const moveBlockCaptchaCalls = app.moveBlockCaptcha.mock.calls.length;
		document.body.innerHTML = '';
		await window.fetch( '/?wc-ajax=ppc-create-order' );
		expect( app.moveBlockCaptcha ).toHaveBeenCalledTimes( moveBlockCaptchaCalls );

		window.__hCaptchaWooCommercePayPalPaymentsFetchWrapped = true;
		expect( () => app.installFetchWrapper() ).not.toThrow();
	} );

	test( 'stores checkout captcha data only when response exists', () => {
		const app = loadPayPal( {
			html: '<form class="checkout" data-response="stored-token" data-nonce="nonce"></form>',
		} );

		app.storeCheckoutCaptchaData();
		expect( app.checkoutCaptchaData[ 'h-captcha-response' ] ).toBe( 'stored-token' );

		document.body.innerHTML = '<form class="checkout" data-response=""></form>';
		app.checkoutCaptchaData = null;
		app.storeCheckoutCaptchaData();
		expect( app.checkoutCaptchaData ).toBeNull();
	} );

	test( 'URL, body, context, and config helpers cover fallback paths', () => {
		const app = loadPayPal();

		expect( app.getUrl( '/url' ) ).toBe( '/url' );
		expect( app.getUrl( { url: '/object-url' } ) ).toBe( '/object-url' );
		expect( app.getUrl( null ) ).toBe( '' );
		expect( app.getJsonBody( '{' ) ).toEqual( {} );
		expect( app.isCheckoutContext( 'checkout' ) ).toBe( true );
		expect( app.isCheckoutContext( 'checkout-block' ) ).toBe( true );
		expect( app.isCheckoutContext( 'cart' ) ).toBe( false );
		expect( app.usesCheckoutCaptcha( 'checkout' ) ).toBe( false );

		jest.spyOn( app, 'getCaptchaData' ).mockReturnValue( { 'h-captcha-response': 'paypal-token' } );
		expect( JSON.parse( app.prepareCreateOrderConfig( { body: JSON.stringify( { context: 'cart' } ) } ).body )[ 'h-captcha-response' ] ).toBe( 'paypal-token' );
	} );

	test( 'execute captcha path handles checkout, PayPal, and guard branches', async () => {
		const app = loadPayPal( {
			html: `
				<form class="checkout" data-response="checkout-token" data-nonce="nonce"><div class="h-captcha"></div><input name="hcaptcha_wc_checkout_nonce" value="nonce"></form>
				<div class="ppc-button-wrapper"><div id="paypal-wrapper"></div><div class="hcaptcha-woocommerce-paypal-payments"><div class="h-captcha"></div><textarea name="h-captcha-response"></textarea></div></div>
			`,
		} );

		jest.spyOn( app, 'executeCheckoutCaptcha' ).mockResolvedValueOnce( undefined );
		jest.spyOn( app, 'executePayPalCaptcha' ).mockResolvedValueOnce( undefined );
		await app.executeCaptchaBeforePayPal( '#paypal-wrapper' );
		expect( app.executePayPalCaptcha ).toHaveBeenCalledWith( '#paypal-wrapper' );

		await app.executeCaptchaBeforePayPal( '' );
		expect( app.executeCheckoutCaptcha ).toHaveBeenCalledTimes( 1 );

		app.executeCheckoutCaptcha.mockRestore();
		await expect( app.executeCheckoutCaptcha() ).resolves.toBeUndefined();

		document.body.innerHTML = '<div id="root"><div class="h-captcha"></div><textarea name="h-captcha-response"></textarea></div>';
		const waitPromise = app.executeCaptcha( document.getElementById( 'root' ) );
		expect( window.hcaptcha.execute ).toHaveBeenCalledWith( 'widget-id', { async: false } );
		await waitPromise;

		expect( app.executeCaptcha( null ) ).resolves.toBeUndefined();
		document.body.innerHTML = '<div id="root"><div class="h-captcha"></div><textarea name="h-captcha-response">already</textarea></div>';
		await expect( app.executeCaptcha( document.getElementById( 'root' ) ) ).resolves.toBeUndefined();
		window.hCaptcha.getWidgetId.mockReturnValueOnce( '' );
		document.querySelector( '[name="h-captcha-response"]' ).value = '';
		await expect( app.executeCaptcha( document.getElementById( 'root' ) ) ).resolves.toBeUndefined();
	} );

	test( 'wrapper, root, and captcha data helpers resolve all supported shapes', () => {
		const app = loadPayPal( {
			html: `
				<div data-block-name="woocommerce/checkout" id="block-checkout" data-response="block-token"></div>
				<div class="wp-block-woocommerce-checkout" id="wp-block-checkout"></div>
				<div class="wc-block-checkout" id="wc-block-checkout"></div>
				<form class="checkout" id="classic-checkout"><input name="hcaptcha_wc_checkout_nonce"></form>
				<div class="wc-block-components-express-payment"><div class="wc-block-components-express-payment__event-buttons"></div><div id="inside-wrapper"><div class="hcaptcha-woocommerce-paypal-payments" data-response="inside-token"></div></div></div>
				<form id="paypal-form" data-response="form-token"><div class="hcaptcha-woocommerce-paypal-payments" data-response="paypal-token"></div></form>
			`,
		} );

		expect( app.getWrapperElement( [ '#inside-wrapper' ] ) ).toBe( document.getElementById( 'inside-wrapper' ) );
		expect( app.getWrapperElement( '' ) ).toBeNull();
		expect( app.getWrapperElement( document.getElementById( 'inside-wrapper' ) ) ).toBe( document.getElementById( 'inside-wrapper' ) );
		expect( app.getWrapperElement( 12 ) ).toBeNull();
		expect( app.isCheckoutButtonWrapper( '#inside-wrapper' ) ).toBe( false );
		expect( app.getClosestPayPalCaptchaRoot( '#inside-wrapper' ).dataset.response ).toBe( 'inside-token' );
		expect( app.getClosestPayPalCaptchaRoot( '' ).dataset.response ).toBe( 'inside-token' );
		expect( app.getCheckoutCaptchaRoot().id ).toBe( 'block-checkout' );
		expect( app.hasCheckoutCaptcha( document.getElementById( 'classic-checkout' ) ) ).toBe( true );
		expect( app.hasPayPalCaptcha() ).toBe( true );

		app.checkoutCaptchaData = { 'h-captcha-response': 'stored-token' };
		expect( app.getCheckoutCaptchaData()[ 'h-captcha-response' ] ).toBe( 'block-token' );
		document.getElementById( 'block-checkout' ).dataset.response = '';
		expect( app.getCheckoutCaptchaData()[ 'h-captcha-response' ] ).toBe( 'stored-token' );

		document.body.innerHTML = '<form id="paypal-form"><div class="hcaptcha-woocommerce-paypal-payments" data-response=""></div></form>';
		helperMock.getHCaptchaData.mockImplementation( ( root ) => ( {
			'h-captcha-response': root?.dataset?.response || '',
			shared: root?.id === 'paypal-form' ? 'form-shared' : '',
		} ) );
		expect( app.getCaptchaData( 'nonce' ).shared ).toBe( 'form-shared' );
	} );

	test( 'block captcha observation and movement cover guard branches', () => {
		const app = loadPayPal( {
			html: `
				<div class="wc-block-components-express-payment">
					<div class="wc-block-components-express-payment__event-buttons"></div>
				</div>
				<div class="hcaptcha-woocommerce-paypal-payments" style="display:none"></div>
			`,
		} );

		expect( observers[ 0 ].observe ).toHaveBeenCalledWith( document.body, { childList: true, subtree: true } );
		app.observeBlockCaptcha();
		app.moveBlockCaptcha();
		expect( document.querySelector( '.wc-block-components-express-payment__event-buttons' ).previousElementSibling.classList.contains( 'hcaptcha-woocommerce-paypal-payments' ) ).toBe( true );
		expect( document.querySelector( '.hcaptcha-woocommerce-paypal-payments' ).style.display ).toBe( '' );
		expect( app.getBlockCaptcha( null ) ).toBeNull();
	} );

	test( 'moves classic cart captcha directly below the checkout button', () => {
		const app = loadPayPal( {
			html: `
				<div class="wc-proceed-to-checkout">
					<a class="checkout-button"></a>
					<div id="wc-stripe-express-checkout-element"></div>
					<div class="ppc-button-wrapper">
						<span class="hcaptcha-woocommerce-paypal-payments" style="display:none"></span>
						<div id="ppc-button-ppcp-gateway"></div>
					</div>
				</div>
			`,
		} );
		const checkoutContainer = document.querySelector(
			'.wc-proceed-to-checkout',
		);
		const checkoutButton = document.querySelector( '.checkout-button' );
		const captcha = document.querySelector(
			'.hcaptcha-woocommerce-paypal-payments',
		);

		app.moveBlockCaptcha();

		expect( checkoutButton.nextElementSibling ).toBe( captcha );
		expect( captcha.nextElementSibling.id ).toBe(
			'wc-stripe-express-checkout-element',
		);
		expect( captcha.parentElement ).toBe( checkoutContainer );
		expect( captcha.style.display ).toBe( '' );
	} );

	test( 'covers PayPal SDK loaded handler and Buttons setter branches', () => {
		const app = loadPayPal();
		const loadedPaypal = {
			Buttons: jest.fn( ( options ) => options ),
		};
		const nextButtons = jest.fn( ( options ) => options );
		const spy = jest.spyOn( app, 'wrapLoadedPayPalNamespace' );

		$( document ).trigger( 'ppcp-paypal-loaded', [ loadedPaypal ] );
		expect( spy ).toHaveBeenCalledWith( loadedPaypal );
		expect( loadedPaypal.__hCaptchaWooCommercePayPalPaymentsButtonsWatched ).toBe( true );

		loadedPaypal.Buttons = nextButtons;
		expect( loadedPaypal.Buttons.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
	} );

	test( 'covers remaining PayPal helper fallback branches', async () => {
		const app = loadPayPal();
		const wrappedWithoutActions = app.wrapPayPalButtonOptions( '', {} );

		jest.spyOn( app, 'executeCaptchaBeforePayPal' ).mockResolvedValue( undefined );
		await expect( wrappedWithoutActions.onClick( 'data', {} ) ).resolves.toBeUndefined();

		app.executeCaptchaBeforePayPal.mockRestore();
		jest.spyOn( app, 'executePayPalCaptcha' ).mockResolvedValue( undefined );
		jest.spyOn( app, 'executeCheckoutCaptcha' ).mockResolvedValue( undefined );
		await app.executeCaptchaBeforePayPal( '' );
		expect( app.executePayPalCaptcha ).toHaveBeenCalledWith( '' );

		app.executePayPalCaptcha.mockRestore();
		app.executeCheckoutCaptcha.mockRestore();
		app.executeCaptcha = jest.fn( () => Promise.resolve() );
		document.body.innerHTML = '<form class="checkout"></form><div class="hcaptcha-woocommerce-paypal-payments"></div>';
		await app.executeCheckoutCaptcha();
		expect( app.executeCaptcha ).toHaveBeenCalledWith( document.querySelector( 'form.checkout' ) );

		await app.executePayPalCaptcha( '' );
		expect( app.executeCaptcha ).toHaveBeenCalledWith( document.querySelector( '.hcaptcha-woocommerce-paypal-payments' ) );

		document.body.innerHTML = '<div class="wc-block-components-express-payment"><div class="wc-block-components-express-payment__event-buttons"></div></div>';
		expect( () => app.moveBlockCaptcha() ).not.toThrow();
	} );

	test( 'real prepareCreateOrderConfig merges checkout captcha data', () => {
		const app = loadPayPal( {
			html: '<form class="checkout" data-response="checkout-token" data-nonce="checkout-nonce"><input name="hcaptcha_wc_checkout_nonce"></form>',
		} );
		const config = app.prepareCreateOrderConfig( {
			body: JSON.stringify( { context: 'checkout' } ),
		} );

		expect( JSON.parse( config.body )[ 'h-captcha-response' ] ).toBe( 'checkout-token' );
	} );

	test( 'prepareCreateOrderConfig falls back to PayPal captcha data for an unprotected checkout', () => {
		const app = loadPayPal( {
			html: '<form class="checkout"></form><div class="hcaptcha-woocommerce-paypal-payments" data-response="paypal-token"></div>',
		} );
		const config = app.prepareCreateOrderConfig( {
			body: JSON.stringify( { context: 'checkout' } ),
		} );

		expect( JSON.parse( config.body )[ 'h-captcha-response' ] ).toBe( 'paypal-token' );
	} );
} );
