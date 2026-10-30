// noinspection JSUnresolvedReference

describe( 'hCaptcha WooCommerce Stripe express checkout', () => {
	let middleware;
	let actions;

	function loadBridge( html = '', settings = {
		nonceName: 'hcaptcha_wc_checkout_nonce',
		missingProofMessage: 'Complete the test CAPTCHA.',
	} ) {
		jest.resetModules();
		document.body.innerHTML = html;
		window.wp = {
			hooks: { addFilter: jest.fn() },
			apiFetch: { use: jest.fn() },
		};
		global.wp = window.wp;
		window.wcStripe = { actions: { addAction: jest.fn() } };
		window.HCaptchaWCStripeExpressObject = settings;
		window.hCaptchaReset = jest.fn();
		delete window.hCaptchaWCStripeExpress;

		require( '../../../assets/js/hcaptcha-wc-stripe-express.js' );
		middleware = window.wp.apiFetch.use.mock.calls[ 0 ][ 0 ];
		actions = Object.fromEntries( window.wcStripe.actions.addAction.mock.calls );

		return window.hCaptchaWCStripeExpress;
	}

	function makeRoot( context, { challenge = true } = {} ) {
		const root = document.createElement( 'div' );
		root.className = 'hcaptcha-wc-stripe-express-proof';
		root.dataset.context = context;
		root.innerHTML = `
			<input name="hcaptcha-widget-id" value="signed-${ context }">
			${ challenge ? `<h-captcha class="h-captcha"></h-captcha>
				<textarea name="h-captcha-response"></textarea>
				<input name="hcaptcha_wc_checkout_nonce" value="nonce-${ context }">
				<input id="hcap_hp_${ context }" name="hcap_hp_${ context }" value="">
				<input name="hcap_hp_sig" value="hp-signature">
				<input name="hcap_fst_token" value="fst-token">` : '' }
		`;
		document.body.appendChild( root );

		return root;
	}

	function makeController( context ) {
		const disablePaymentElement = jest.fn();
		const enablePaymentElement = jest.fn();

		return {
			context: { getPage: () => context },
			gateway: { elementType: 'expressCheckout' },
			disablePaymentElement,
			enablePaymentElement,
			originalDisable: disablePaymentElement,
			originalEnable: enablePaymentElement,
		};
	}

	function solve( app, root, context, token ) {
		root.querySelector( '[name="h-captcha-response"]' ).value = token;
		app[ `on${ context }Solved` ]( token );
	}

	function stripeOptions( context = 'product' ) {
		return {
			method: 'POST',
			url: '/?wc-ajax=wc_stripe_frontend_request&path=%2Fwc-stripe%2Fv1%2Fcart%2Fcheckout',
			data: { context, payment_method: 'wc_stripe_applepay', billing_email: 'buyer@example.test' },
		};
	}

	afterEach( () => {
		document.body.innerHTML = '';
		delete window.wp;
		delete global.wp;
		delete window.wcStripe;
		delete window.HCaptchaWCStripeExpressObject;
		delete window.hCaptchaReset;
		delete window.hCaptchaWCStripeExpress;
		jest.restoreAllMocks();
	} );

	test( 'registers only one middleware, selector, and native readiness hooks', () => {
		loadBridge();
		jest.resetModules();
		require( '../../../assets/js/hcaptcha-wc-stripe-express.js' );

		expect( window.wp.apiFetch.use ).toHaveBeenCalledTimes( 1 );
		expect( window.wp.hooks.addFilter ).toHaveBeenCalledTimes( 1 );
		expect( window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ]( 'form' ) )
			.toBe( 'form, .hcaptcha-wc-stripe-express-proof' );
		expect( Object.keys( actions ) ).toEqual( [ 'payment_element_ready', 'express_element_ready' ] );
	} );

	test( 'gates each wallet by its context and adds only its fresh proof', async () => {
		const app = loadBridge();
		const product = makeRoot( 'product' );
		const minicart = makeRoot( 'minicart' );
		const productController = makeController( 'product' );
		const minicartController = makeController( 'minicart' );
		actions.payment_element_ready( productController );
		actions.express_element_ready( {}, { controller: minicartController } );
		expect( productController.originalDisable ).toHaveBeenCalledTimes( 1 );
		expect( minicartController.originalDisable ).toHaveBeenCalledTimes( 1 );

		solve( app, product, 'Product', 'product-token' );
		expect( productController.originalEnable ).toHaveBeenCalledTimes( 1 );
		expect( minicartController.originalEnable ).not.toHaveBeenCalled();

		const next = jest.fn( () => Promise.resolve( { success: true } ) );
		const options = stripeOptions();
		await expect( middleware( options, next ) ).resolves.toEqual( { success: true } );
		expect( next ).toHaveBeenCalledTimes( 1 );
		expect( next.mock.calls[ 0 ][ 0 ].data ).toEqual( {
			...options.data,
			'h-captcha-response': 'product-token',
			'hcaptcha-widget-id': 'signed-product',
			hcaptcha_wc_checkout_nonce: 'nonce-product',
			hcap_hp_product: '',
			hcap_hp_sig: 'hp-signature',
			hcap_fst_token: 'fst-token',
		} );
		expect( options.data ).toEqual( {
			context: 'product', payment_method: 'wc_stripe_applepay', billing_email: 'buyer@example.test',
		} );
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( product.querySelector( '.h-captcha' ) );
		expect( minicart.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
	} );

	test( 'keeps the wallet gated across native updates without overriding native disable', () => {
		const app = loadBridge();
		const root = makeRoot( 'product' );
		const controller = makeController( 'product' );
		actions.payment_element_ready( controller );

		controller.enablePaymentElement();
		expect( controller.originalEnable ).not.toHaveBeenCalled();
		expect( controller.originalDisable ).toHaveBeenCalledTimes( 2 );

		controller.disablePaymentElement();
		solve( app, root, 'Product', 'first-token' );
		expect( controller.originalEnable ).not.toHaveBeenCalled();

		controller.enablePaymentElement();
		expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );

		app.onProductExpired();
		controller.enablePaymentElement();
		expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );
		expect( controller.originalDisable ).toHaveBeenCalledTimes( 5 );
	} );

	test( 'records a callback even when hCaptcha fills the response immediately afterward', () => {
		jest.useFakeTimers();
		try {
			const app = loadBridge();
			const root = makeRoot( 'cart' );
			const controller = makeController( 'cart' );
			actions.payment_element_ready( controller );
			app.onCartSolved( 'later-token' );
			root.querySelector( '[name="h-captcha-response"]' ).value = 'later-token';
			jest.advanceTimersByTime( 0 );
			expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );
		} finally {
			jest.useRealTimers();
		}
	} );

	test( 'relocks a wallet when an updated checkout fragment replaces its proof', async () => {
		loadBridge();
		const form = document.createElement( 'form' );
		form.className = 'checkout';
		form.innerHTML = '<div class="hcaptcha-wc-checkout-proof"><h-captcha class="h-captcha"></h-captcha><input name="hcaptcha-widget-id" value="first-id"><textarea name="h-captcha-response"></textarea></div>';
		document.body.appendChild( form );
		const controller = makeController( 'express_checkout' );
		actions.payment_element_ready( controller );
		await Promise.resolve();

		form.querySelector( '[name="h-captcha-response"]' ).value = 'first-token';
		document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted', { detail: { token: 'first-token' } } ) );
		expect( controller.originalEnable ).toHaveBeenCalled();

		const disableCount = controller.originalDisable.mock.calls.length;
		form.innerHTML = '<div class="hcaptcha-wc-checkout-proof"><h-captcha class="h-captcha"></h-captcha><input name="hcaptcha-widget-id" value="second-id"><textarea name="h-captcha-response"></textarea></div>';
		await Promise.resolve();
		expect( controller.originalDisable.mock.calls.length ).toBeGreaterThan( disableCount );

		const enableCount = controller.originalEnable.mock.calls.length;
		form.querySelector( '[name="h-captcha-response"]' ).value = 'second-token';
		document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted', { detail: { token: 'second-token' } } ) );
		expect( controller.originalEnable.mock.calls.length ).toBeGreaterThan( enableCount );
	} );

	test( 'expires the wallet gate at 90 seconds without a click', async () => {
		jest.useFakeTimers();
		try {
			const app = loadBridge();
			const root = makeRoot( 'cart' );
			const controller = makeController( 'cart' );
			actions.payment_element_ready( controller );
			solve( app, root, 'Cart', 'cart-token' );
			expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );

			jest.advanceTimersByTime( 89_000 );
			app.onCartSolved( 'cart-token' );
			const disableCount = controller.originalDisable.mock.calls.length;
			jest.advanceTimersByTime( 1_000 );
			expect( controller.originalDisable.mock.calls.length ).toBeGreaterThan( disableCount );
			await expect( middleware( stripeOptions( 'cart' ), jest.fn() ) )
				.rejects.toThrow( 'Complete the test CAPTCHA.' );
		} finally {
			jest.useRealTimers();
		}
	} );

	test( 'rejects missing, reused, expired, and stale challenge proofs before next', async () => {
		const app = loadBridge();
		const cart = makeRoot( 'cart' );
		const controller = makeController( 'cart' );
		actions.payment_element_ready( controller );
		const next = jest.fn( () => new Promise( () => {} ) );

		await expect( middleware( stripeOptions( 'cart' ), next ) )
			.rejects.toThrow( 'Complete the test CAPTCHA.' );
		solve( app, cart, 'Cart', 'cart-token' );
		middleware( stripeOptions( 'cart' ), next );
		expect( next ).toHaveBeenCalledTimes( 1 );
		await expect( middleware( stripeOptions( 'cart' ), next ) )
			.rejects.toThrow( 'Complete the test CAPTCHA.' );

		app.onCartExpired();
		await expect( middleware( stripeOptions( 'cart' ), next ) )
			.rejects.toThrow( 'Complete the test CAPTCHA.' );
		expect( controller.originalDisable ).toHaveBeenCalled();

		jest.spyOn( Date, 'now' ).mockReturnValueOnce( 1000 ).mockReturnValue( 92_000 );
		solve( app, cart, 'Cart', 'new-token' );
		await expect( middleware( stripeOptions( 'cart' ), next ) )
			.rejects.toThrow( 'Complete the test CAPTCHA.' );
		expect( next ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'passes unrelated requests unchanged and rejects unknown checkout contexts', async () => {
		loadBridge();
		const next = jest.fn( () => Promise.resolve( 'untouched' ) );
		const options = stripeOptions();
		options.url = 'https://different.example.test/?wc-ajax=wc_stripe_frontend_request&path=%2Fwc-stripe%2Fv1%2Fcart%2Fcheckout';
		options.path = '/wc-stripe/v1/cart/checkout';
		await expect( middleware( options, next ) ).resolves.toBe( 'untouched' );
		expect( next ).toHaveBeenCalledWith( options );
		delete options.path;
		options.url = '/?wc-ajax=wc_stripe_frontend_request&path=/wc-stripe/v1/cart/other';
		await expect( middleware( options, next ) ).resolves.toBe( 'untouched' );
		options.url = '/?wc-ajax=wc_stripe_frontend_request&path=/wc-stripe/v1/cart/checkout';
		options.data.context = 'order_pay';
		await expect( middleware( options, next ) ).rejects.toThrow( 'Complete the test CAPTCHA.' );
		expect( next ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'lets a signed-ID-only form exemption reach the server', async () => {
		loadBridge();
		const root = makeRoot( 'cart', { challenge: false } );
		const next = jest.fn( () => Promise.resolve( 'server-decides' ) );
		await expect( middleware( stripeOptions( 'cart' ), next ) ).resolves.toBe( 'server-decides' );
		expect( next.mock.calls[ 0 ][ 0 ].data[ 'hcaptcha-widget-id' ] ).toBe( 'signed-cart' );
		expect( next.mock.calls[ 0 ][ 0 ].data[ 'h-captcha-response' ] ).toBeUndefined();
		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( root.isConnected ).toBe( true );
	} );

	test( 'records the existing checkout widget for classic banner gating', () => {
		loadBridge();
		const form = document.createElement( 'form' );
		form.className = 'checkout';
		form.innerHTML = '<div class="hcaptcha-wc-checkout-proof"><h-captcha class="h-captcha"></h-captcha><input name="hcaptcha-widget-id" value="signed-checkout"><textarea name="h-captcha-response"></textarea></div>';
		document.body.appendChild( form );
		const controller = makeController( 'express_checkout' );
		actions.payment_element_ready( controller );
		expect( controller.originalDisable ).toHaveBeenCalledTimes( 1 );
		form.querySelector( '[name="h-captcha-response"]' ).value = 'checkout-token';
		document.dispatchEvent( new CustomEvent( 'hCaptchaSubmitted', { detail: { token: 'checkout-token' } } ) );
		expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'uses default settings and starts without a mutation observer', async () => {
		const mutationObserver = window.MutationObserver;
		window.MutationObserver = undefined;
		try {
			loadBridge( '', null );
			const options = stripeOptions();
			options.data = null;

			await expect( middleware( options, jest.fn() ) ).rejects.toThrow( 'Complete hCaptcha before express checkout.' );
			expect( window.wp.hooks.addFilter ).toHaveBeenCalledTimes( 1 );
		} finally {
			window.MutationObserver = mutationObserver;
		}
	} );

	test( 'ignores controllers outside supported express checkout contexts', () => {
		loadBridge();
		actions.payment_element_ready( null );
		actions.payment_element_ready( { context: { getPage: () => 'unknown' }, gateway: { elementType: 'expressCheckout' } } );
		actions.payment_element_ready( { context: { getPage: () => 'product' }, gateway: { elementType: 'other' } } );

		expect( window.wp.apiFetch.use ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'rejects a root without a signed widget identity', async () => {
		loadBridge();
		const root = makeRoot( 'product', { challenge: false } );
		root.querySelector( '[name="hcaptcha-widget-id"]' ).value = '';
		const next = jest.fn();

		await expect( middleware( stripeOptions(), next ) ).rejects.toThrow( 'Complete the test CAPTCHA.' );
		expect( next ).not.toHaveBeenCalled();
	} );

	test.each( [
		[ undefined ],
		[ [] ],
		[ 'product' ],
		[ { context: 'other' } ],
	] )( 'rejects invalid checkout data %p', async ( data ) => {
		loadBridge();
		const options = stripeOptions();
		options.data = data;

		await expect( middleware( options, jest.fn() ) ).rejects.toThrow( 'Complete the test CAPTCHA.' );
	} );

	test.each( [
		[ 'missing method', { url: '/?wc-ajax=wc_stripe_frontend_request' } ],
		[ 'GET method', { method: 'GET', url: '/?wc-ajax=wc_stripe_frontend_request' } ],
		[ 'different path', { method: 'POST', path: '/other' } ],
		[ 'malformed URL', { method: 'POST', url: 'http://[' } ],
		[ 'ordinary URL', { method: 'POST', url: '/ordinary/' } ],
	] )( 'passes a %s request through unchanged', async ( description, options ) => {
		loadBridge();
		const next = jest.fn( () => 'untouched' );

		expect( middleware( options, next ) ).toBe( 'untouched' );
		expect( next ).toHaveBeenCalledWith( options );
	} );

	test.each( [
		[ 'path option', { method: 'POST', path: '/wc-stripe/v1/cart/checkout' } ],
		[ 'REST route', { method: 'POST', url: '/?rest_route=%2Fwc-stripe%2Fv1%2Fcart%2Fcheckout' } ],
		[ 'pretty REST URL', { method: 'POST', url: '/wp-json/wc-stripe/v1/cart/checkout/' } ],
	] )( 'adds proof for the %s checkout endpoint', async ( description, endpoint ) => {
		loadBridge();
		makeRoot( 'cart', { challenge: false } );
		const next = jest.fn( () => 'sent' );
		const options = { ...endpoint, data: { context: 'cart' } };

		await expect( middleware( options, next ) ).resolves.toBe( 'sent' );
		expect( next.mock.calls[ 0 ][ 0 ].data[ 'hcaptcha-widget-id' ] ).toBe( 'signed-cart' );
	} );

	test.each( [
		[ 'synchronous error', () => {
			throw new Error( 'Transport failed.' );
		} ],
		[ 'rejected request', () => Promise.reject( new Error( 'Transport failed.' ) ) ],
	] )( 'resets the spent proof after a %s', async ( description, transport ) => {
		const app = loadBridge();
		const root = makeRoot( 'product' );
		solve( app, root, 'Product', 'proof-token' );
		const next = jest.fn( transport );

		if ( description === 'synchronous error' ) {
			expect( () => middleware( stripeOptions(), next ) ).toThrow( 'Transport failed.' );
		} else {
			await expect( middleware( stripeOptions(), next ) ).rejects.toThrow( 'Transport failed.' );
		}
		expect( window.hCaptchaReset ).toHaveBeenCalledWith( root.querySelector( '.h-captcha' ) );
		await expect( middleware( stripeOptions(), next ) ).rejects.toThrow( 'Complete the test CAPTCHA.' );
	} );

	test( 'ignores an empty callback token and a stale expiry callback', () => {
		jest.useFakeTimers();
		try {
			const app = loadBridge();
			const root = makeRoot( 'minicart' );
			const controller = makeController( 'minicart' );
			actions.payment_element_ready( controller );
			app.onMinicartSolved( '' );
			jest.advanceTimersByTime( 0 );
			expect( controller.originalEnable ).not.toHaveBeenCalled();

			const timer = jest.spyOn( global, 'setTimeout' );
			solve( app, root, 'Minicart', 'proof-token' );
			const expiry = timer.mock.calls.find( ( call ) => call[ 1 ] === 90_000 )[ 0 ];
			expect( controller.originalEnable ).toHaveBeenCalledTimes( 1 );
			app.onMinicartExpired();
			expect( controller.originalDisable ).toHaveBeenCalled();
			expiry();
			timer.mockRestore();
		} finally {
			jest.useRealTimers();
		}
	} );
} );
