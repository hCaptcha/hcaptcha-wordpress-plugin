// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha WooCommerce PayPal Payments early wrapper', () => {
	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers();
		delete window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped;
		delete window.hCaptchaWooCommercePayPalPayments;
		delete window.paypal;
		delete window.ppcpBlocksPaypalExpressButtons;
		delete window.widgetBuilder;
	} );

	afterEach( () => {
		jest.runOnlyPendingTimers();
		jest.useRealTimers();
		delete window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped;
		delete window.hCaptchaWooCommercePayPalPayments;
		delete window.paypal;
		delete window.ppcpBlocksPaypalExpressButtons;
		delete window.widgetBuilder;
		jest.restoreAllMocks();
	} );

	test( 'wraps existing PayPal Buttons and widgetBuilder options', async () => {
		const originalButtons = jest.fn( ( options ) => options );
		const originalRegisterButtons = jest.fn( ( wrapper, options ) => options );
		const originalClick = jest.fn( () => 'clicked' );
		const actions = {
			resolve: jest.fn( () => 'resolved' ),
		};

		window.hCaptchaWooCommercePayPalPayments = {
			executeCaptchaBeforePayPal: jest.fn( () => Promise.resolve() ),
		};
		window.paypal = {
			Buttons: originalButtons,
		};
		window.widgetBuilder = {
			registerButtons: originalRegisterButtons,
			buttons: new Map( [
				[ 'button', { wrapper: '#wrapper', options: { onClick: originalClick } } ],
			] ),
		};

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		const buttonOptions = window.paypal.Buttons( { onClick: originalClick } );
		const registeredOptions = window.widgetBuilder.registerButtons( '#wrapper', { onClick: originalClick } );

		expect( window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped ).toBe( true );
		expect( buttonOptions.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( registeredOptions.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( window.widgetBuilder.buttons.get( 'button' ).options.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		await expect( buttonOptions.onClick( 'data', actions ) ).resolves.toBe( 'clicked' );
		expect( window.hCaptchaWooCommercePayPalPayments.executeCaptchaBeforePayPal ).toHaveBeenCalledWith( '' );
		expect( originalClick ).toHaveBeenCalledWith( 'data', actions );
	} );

	test( 'resolves PayPal onClick with actions.resolve when original click has no result', async () => {
		const actions = {
			resolve: jest.fn( () => 'resolved' ),
		};

		window.hCaptchaWooCommercePayPalPayments = {
			executeCaptchaBeforePayPal: jest.fn( () => Promise.resolve() ),
		};
		window.paypal = {
			Buttons: jest.fn( ( options ) => options ),
		};

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		const buttonOptions = window.paypal.Buttons( {} );

		await expect( buttonOptions.onClick( 'data', actions ) ).resolves.toBe( 'resolved' );
		expect( actions.resolve ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'wraps PayPal and widgetBuilder objects assigned after early script loads', () => {
		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		window.paypal = {
			Buttons: jest.fn( ( options ) => options ),
		};
		window.widgetBuilder = {
			registerButtons: jest.fn( ( wrapper, options ) => options ),
		};

		expect( window.paypal.Buttons( {} ).__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
		expect( window.widgetBuilder.registerButtons( '#wrapper', {} ).__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
	} );

	test( 'waits for the main PayPal app and gives up after retry limit', async () => {
		window.paypal = {
			Buttons: jest.fn( ( options ) => options ),
		};

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		const withApp = window.paypal.Buttons( {} );
		const withAppPromise = withApp.onClick( 'data', {} );
		window.hCaptchaWooCommercePayPalPayments = {
			executeCaptchaBeforePayPal: jest.fn( () => Promise.resolve( 'captcha' ) ),
		};
		jest.advanceTimersByTime( 50 );
		await expect( withAppPromise ).resolves.toBeUndefined();

		delete window.hCaptchaWooCommercePayPalPayments;
		const withoutApp = window.paypal.Buttons( {} );
		const withoutAppPromise = withoutApp.onClick( 'data', {} );
		jest.advanceTimersByTime( 2100 );
		await expect( withoutAppPromise ).resolves.toBeUndefined();
	} );

	test( 'returns early when already wrapped and tolerates defineProperty failures', () => {
		window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped = true;
		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );
		expect( window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped ).toBe( true );

		jest.resetModules();
		delete window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped;
		window.paypal = {
			Buttons: jest.fn(),
		};
		window.widgetBuilder = {
			buttons: [],
		};
		jest.spyOn( Object, 'defineProperty' ).mockImplementation( () => {
			throw new Error( 'define blocked' );
		} );

		expect( () => require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' ) ).not.toThrow();
	} );

	test( 'falls back when PayPal Buttons property wrapping fails', () => {
		const paypal = {
			Buttons: jest.fn( ( options ) => options ),
		};
		const originalDefineProperty = Object.defineProperty;

		window.paypal = paypal;
		jest.spyOn( Object, 'defineProperty' ).mockImplementation( ( target, property, descriptor ) => {
			if ( target === paypal && property === 'Buttons' ) {
				throw new Error( 'buttons blocked' );
			}

			return originalDefineProperty( target, property, descriptor );
		} );

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		expect( window.paypal.Buttons.__hCaptchaWooCommercePayPalPaymentsWrapped ).toBe( true );
	} );
	test( 'covers early wrapper guard branches for empty and pre-wrapped values', () => {
		const wrappedButtons = jest.fn();
		wrappedButtons.__hCaptchaWooCommercePayPalPaymentsWrapped = true;
		const watchedMarker = '__hCaptchaWooCommercePayPalPaymentsWrappedButtonsWatched';

		window.paypal = {
			Buttons: null,
		};
		window.ppcpBlocksPaypalExpressButtons = {
			Buttons: jest.fn(),
			[ watchedMarker ]: true,
		};
		window.widgetBuilder = {
			buttons: new Map( [
				[ 'empty', { wrapper: '#wrapper', options: null } ],
			] ),
		};

		require( '../../../assets/js/hcaptcha-woocommerce-paypal-payments-early.js' );

		expect( window.paypal.Buttons ).toBeNull();
		expect( window.ppcpBlocksPaypalExpressButtons[ watchedMarker ] ).toBe( true );
		expect( window.widgetBuilder.buttons.get( 'empty' ).options ).toBeNull();

		window.paypal.Buttons = wrappedButtons;
		expect( window.paypal.Buttons ).toBe( wrappedButtons );
	} );
} );
