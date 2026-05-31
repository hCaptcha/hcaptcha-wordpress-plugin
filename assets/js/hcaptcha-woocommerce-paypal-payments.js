/* global hcaptcha */

import { helper } from './hcaptcha-helper.js';

const app = {
	observer: null,
	widgetBuilderTimer: null,
	widgetBuilderAttempts: 0,
	payPalSdkTimer: null,
	payPalSdkAttempts: 0,
	checkoutCaptchaData: null,
	payPalNamespaces: [
		'paypal',
		'ppcpBlocksPaypalExpressButtons',
		'ppcp-blocks-editor-paypal-buttons',
	],

	init() {
		window.hCaptchaWooCommercePayPalPayments = app;

		wp.hooks.addFilter(
			'hcaptcha.formSelector',
			'hcaptcha',
			( formSelector ) => {
				return app.getFormSelector( formSelector );
			},
		);

		app.installPayPalButtonsWrapper();
		app.installPayPalSdkWrapper();
		app.listenForPayPalSdk();
		app.installFetchWrapper();
		document.addEventListener( 'hCaptchaSubmitted', app.storeCheckoutCaptchaData );

		if ( app.hasPayPalCaptcha() ) {
			app.moveBlockCaptcha();
			app.observeBlockCaptcha();
		}
	},

	getFormSelector( formSelector ) {
		return formSelector + ', .hcaptcha-woocommerce-paypal-payments, .widget_shopping_cart';
	},

	installPayPalButtonsWrapper() {
		// noinspection JSUnresolvedReference
		const paypalWidgetBuilder = window.widgetBuilder;

		if ( ! paypalWidgetBuilder ) {
			app.schedulePayPalButtonsWrapper();

			return;
		}

		if ( paypalWidgetBuilder.__hCaptchaWooCommercePayPalPaymentsWrapped ) {
			return;
		}

		const previousRegisterButtons = paypalWidgetBuilder.registerButtons.bind( paypalWidgetBuilder );

		paypalWidgetBuilder.registerButtons = ( wrapper, options ) => {
			return previousRegisterButtons(
				wrapper,
				app.wrapPayPalButtonOptions( wrapper, options ),
			);
		};

		if ( paypalWidgetBuilder.buttons instanceof Map ) {
			for ( const entry of paypalWidgetBuilder.buttons.values() ) {
				entry.options = app.wrapPayPalButtonOptions(
					entry.wrapper,
					entry.options,
				);
			}
		}

		paypalWidgetBuilder.__hCaptchaWooCommercePayPalPaymentsWrapped = true;
	},

	schedulePayPalButtonsWrapper() {
		if ( app.widgetBuilderTimer || app.widgetBuilderAttempts > 40 ) {
			return;
		}

		app.widgetBuilderAttempts += 1;
		app.widgetBuilderTimer = setTimeout( () => {
			app.widgetBuilderTimer = null;
			app.installPayPalButtonsWrapper();
		}, 50 );
	},

	installPayPalSdkWrapper() {
		let wrapped = false;

		for ( const namespace of app.payPalNamespaces ) {
			wrapped = app.wrapPayPalNamespace( namespace ) || wrapped;
		}

		if ( ! wrapped ) {
			app.schedulePayPalSdkWrapper();
		}
	},

	wrapPayPalNamespace( namespace ) {
		const paypal = window[ namespace ];

		if ( ! paypal ) {
			return false;
		}

		app.watchPayPalButtons( paypal );

		return true;
	},

	listenForPayPalSdk() {
		if ( ! window.jQuery ) {
			return;
		}

		window.jQuery( document ).on( 'ppcp-paypal-loaded', ( ...args ) => {
			app.wrapLoadedPayPalNamespace( args[ 1 ] );
		} );
	},

	wrapLoadedPayPalNamespace( paypal ) {
		if ( ! paypal ) {
			return;
		}

		app.watchPayPalButtons( paypal );
	},

	watchPayPalButtons( paypal ) {
		const marker = '__hCaptchaWooCommercePayPalPaymentsButtonsWatched';
		let buttons = paypal.Buttons;

		if ( paypal[ marker ] ) {
			return;
		}

		try {
			Object.defineProperty( paypal, 'Buttons', {
				configurable: true,
				get() {
					return buttons;
				},
				set( nextButtons ) {
					buttons = app.wrapPayPalButtons( paypal, nextButtons );
				},
			} );
		} catch {
			paypal.Buttons = app.wrapPayPalButtons( paypal, buttons );
			paypal[ marker ] = true;

			return;
		}

		buttons = app.wrapPayPalButtons( paypal, buttons );
		paypal[ marker ] = true;
	},

	wrapPayPalButtons( paypal, buttons ) {
		if ( ! buttons || buttons.__hCaptchaWooCommercePayPalPaymentsWrapped ) {
			return buttons;
		}

		const previousButtons = buttons.bind( paypal );

		const wrappedButtons = ( options ) => {
			return previousButtons( app.wrapPayPalButtonOptions( '', options ) );
		};

		Object.assign( wrappedButtons, buttons );

		wrappedButtons.__hCaptchaWooCommercePayPalPaymentsWrapped = true;

		return wrappedButtons;
	},

	schedulePayPalSdkWrapper() {
		if ( app.payPalSdkTimer || app.payPalSdkAttempts > 40 ) {
			return;
		}

		app.payPalSdkAttempts += 1;
		app.payPalSdkTimer = setTimeout( () => {
			app.payPalSdkTimer = null;
			app.installPayPalSdkWrapper();
		}, 50 );
	},

	wrapPayPalButtonOptions( wrapper, options ) {
		if ( ! options || options.__hCaptchaWooCommercePayPalPaymentsWrapped ) {
			return options;
		}

		const onClick = options.onClick;

		return {
			...options,
			__hCaptchaWooCommercePayPalPaymentsWrapped: true,
			onClick( data, actions ) {
				return Promise.resolve( app.executeCaptchaBeforePayPal( wrapper ) )
					.then( () => {
						return typeof onClick === 'function'
							? onClick.call( this, data, actions )
							: undefined;
					} )
					.then( ( result ) => {
						if ( result !== undefined ) {
							return result;
						}

						return actions?.resolve ? actions.resolve() : undefined;
					} );
			},
		};
	},

	installFetchWrapper() {
		if ( window.__hCaptchaWooCommercePayPalPaymentsFetchWrapped ) {
			return;
		}

		const previousFetch = window.fetch;

		window.fetch = async ( resource, config = {} ) => {
			const url = app.getUrl( resource );

			if ( ! url.includes( 'wc-ajax=ppc-create-order' ) ) {
				return previousFetch( resource, config );
			}

			let requestConfig = app.prepareCreateOrderConfig( config );
			const requestBody = app.getJsonBody( requestConfig.body );

			if (
				app.isCheckoutContext( requestBody.context ) &&
				! requestBody[ 'h-captcha-response' ]
			) {
				await app.executeCheckoutCaptcha();

				requestConfig = app.prepareCreateOrderConfig( config );
			} else if ( ! requestBody[ 'h-captcha-response' ] ) {
				await app.executePayPalCaptcha( '' );

				requestConfig = app.prepareCreateOrderConfig( config );
			}

			return previousFetch( resource, requestConfig ).finally( () => {
				app.checkoutCaptchaData = null;
				window.hCaptchaBindEvents?.();

				if ( app.hasPayPalCaptcha() ) {
					app.moveBlockCaptcha();
				}
			} );
		};

		window.__hCaptchaWooCommercePayPalPaymentsFetchWrapped = true;
	},

	storeCheckoutCaptchaData() {
		const data = helper.getHCaptchaData(
			app.getCheckoutCaptchaRoot(),
			'hcaptcha_wc_checkout_nonce',
		);

		if ( data[ 'h-captcha-response' ] ) {
			app.checkoutCaptchaData = data;
		}
	},

	getUrl( resource ) {
		return typeof resource === 'string' ? resource : resource?.url ?? '';
	},

	prepareCreateOrderConfig( config ) {
		const requestConfig = { ...config };
		const body = app.getJsonBody( requestConfig.body );

		if ( app.isCheckoutContext( body.context ) ) {
			Object.assign( body, app.getCheckoutCaptchaData() );
		} else {
			Object.assign(
				body,
				app.getCaptchaData( 'hcaptcha_woocommerce_paypal_payments_nonce' ),
			);
		}

		requestConfig.body = JSON.stringify( body );

		return requestConfig;
	},

	getJsonBody( rawBody ) {
		try {
			return JSON.parse( rawBody );
			// eslint-disable-next-line no-unused-vars
		} catch ( e ) {
			return {};
		}
	},

	isCheckoutContext( context ) {
		return [ 'checkout', 'checkout-block' ].includes( context );
	},

	async executeCaptchaBeforePayPal( wrapper ) {
		if ( wrapper && ! app.isCheckoutButtonWrapper( wrapper ) ) {
			await app.executePayPalCaptcha( wrapper );

			return;
		}

		const checkoutRoot = app.getCheckoutCaptchaRoot();

		if ( app.hasCheckoutCaptcha( checkoutRoot ) ) {
			await app.executeCheckoutCaptcha();

			return;
		}

		await app.executePayPalCaptcha( wrapper );
	},

	async executeCheckoutCaptcha() {
		if ( app.getCheckoutCaptchaData()[ 'h-captcha-response' ] ) {
			return;
		}

		const checkoutRoot = app.getCheckoutCaptchaRoot();

		await app.executeCaptcha( checkoutRoot );
	},

	async executePayPalCaptcha( wrapper ) {
		const paypalRoot = app.getClosestPayPalCaptchaRoot( wrapper );

		await app.executeCaptcha( paypalRoot );
	},

	async executeCaptcha( root ) {
		const captcha = root?.querySelector( '.h-captcha' );
		const response = root?.querySelector( '[name="h-captcha-response"]' );

		if ( ! captcha || response?.value ) {
			return;
		}

		const widgetId = window.hCaptcha?.getWidgetId( captcha );

		if ( ! widgetId || typeof hcaptcha === 'undefined' ) {
			return;
		}

		await app.waitForCaptcha( () => {
			hcaptcha.execute( widgetId, { async: false } );
		} );
	},

	waitForCaptcha( execute ) {
		return new Promise( ( resolve ) => {
			const listener = () => {
				document.removeEventListener( 'hCaptchaSubmitted', listener );
				resolve();
			};

			document.addEventListener( 'hCaptchaSubmitted', listener );
			execute();
		} );
	},

	isCheckoutButtonWrapper( wrapper ) {
		const checkoutRoot = app.getCheckoutCaptchaRoot();
		const wrapperElement = app.getWrapperElement( wrapper );

		return !! (
			checkoutRoot &&
			wrapperElement &&
			checkoutRoot.contains( wrapperElement )
		);
	},

	getWrapperElement( wrapper ) {
		const wrapperSelector = Array.isArray( wrapper ) ? wrapper[ 0 ] : wrapper;

		if ( typeof wrapperSelector === 'string' ) {
			if ( ! wrapperSelector ) {
				return null;
			}

			return document.querySelector( wrapperSelector );
		}

		return wrapperSelector instanceof Element ? wrapperSelector : null;
	},

	getClosestPayPalCaptchaRoot( wrapper ) {
		const wrapperElement = app.getWrapperElement( wrapper );
		const captchaSelector = '.hcaptcha-woocommerce-paypal-payments';
		const wrapperCaptcha = wrapperElement
			?.closest( '.ppc-button-wrapper, .wc-block-components-express-payment' )
			?.querySelector( captchaSelector );

		return wrapperCaptcha || document.querySelector( captchaSelector );
	},

	getCheckoutCaptchaData() {
		const data = helper.getHCaptchaData(
			app.getCheckoutCaptchaRoot(),
			'hcaptcha_wc_checkout_nonce',
		);

		if ( data[ 'h-captcha-response' ] || ! app.checkoutCaptchaData ) {
			return data;
		}

		return {
			...data,
			...app.checkoutCaptchaData,
		};
	},

	getCheckoutCaptchaRoot() {
		return (
			document.querySelector( 'div[data-block-name="woocommerce/checkout"]' ) ||
			document.querySelector( '.wp-block-woocommerce-checkout' ) ||
			document.querySelector( '.wc-block-checkout' ) ||
			document.querySelector( 'form.checkout' )
		);
	},

	hasCheckoutCaptcha( checkoutRoot ) {
		return !! checkoutRoot?.querySelector( '[name="hcaptcha_wc_checkout_nonce"]' );
	},

	hasPayPalCaptcha() {
		return document.querySelector( '.hcaptcha-woocommerce-paypal-payments' ) !== null;
	},

	getCaptchaData( nonceName ) {
		const captchaRoots = app.getCaptchaRoots();
		const data = {};

		for ( const captchaRoot of captchaRoots ) {
			const rootData = helper.getHCaptchaData( captchaRoot, nonceName );

			for ( const [ name, value ] of Object.entries( rootData ) ) {
				const hasData = Object.prototype.hasOwnProperty.call( data, name );

				if (
					! hasData ||
					( data[ name ] === '' && value !== '' )
				) {
					data[ name ] = value;
				}
			}
		}

		return data;
	},

	getCaptchaRoots() {
		const captchaSelector = '.hcaptcha-woocommerce-paypal-payments';
		const roots = [];
		const addRoot = ( root ) => {
			if ( root && ! roots.includes( root ) ) {
				roots.push( root );
			}
		};

		const paypalCaptcha =
			document.querySelector(
				'.wc-block-components-express-payment ' + captchaSelector,
			) || document.querySelector( captchaSelector );

		addRoot( paypalCaptcha );
		addRoot( paypalCaptcha?.closest( 'form' ) );
		addRoot(
			paypalCaptcha?.closest( 'div[data-block-name="woocommerce/checkout"]' ),
		);
		addRoot(
			paypalCaptcha?.closest( 'div[data-block-name="woocommerce/cart"]' ),
		);
		addRoot( document );

		return roots;
	},

	observeBlockCaptcha() {
		if (
			app.observer ||
			typeof MutationObserver === 'undefined' ||
			! document.body
		) {
			return;
		}

		app.observer = new MutationObserver( app.moveBlockCaptcha );

		app.observer.observe( document.body, {
			childList: true,
			subtree: true,
		} );
	},

	moveBlockCaptcha() {
		const buttonContainers = document.querySelectorAll(
			'.wc-block-components-express-payment__event-buttons',
		);

		for ( const buttonContainer of buttonContainers ) {
			const wrapper = buttonContainer.closest( '.wc-block-components-express-payment' );
			const captcha = app.getBlockCaptcha( wrapper );

			if ( ! captcha ) {
				continue;
			}

			if ( buttonContainer.nextElementSibling !== captcha ) {
				buttonContainer.after( captcha );
			}

			captcha.style.removeProperty( 'display' );
		}
	},

	getBlockCaptcha( wrapper ) {
		const captchaSelector = '.hcaptcha-woocommerce-paypal-payments';

		if ( ! wrapper ) {
			return null;
		}

		const currentCaptcha = wrapper.querySelector( captchaSelector );

		if ( currentCaptcha ) {
			return currentCaptcha;
		}

		return Array.from( document.querySelectorAll( captchaSelector ) ).find( ( element ) => {
			return ! element.closest( '.wc-block-components-express-payment' );
		} );
	},
};

app.init();
