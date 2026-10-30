// noinspection JSUnusedGlobalSymbols

( function() {
	if ( window.hCaptchaWCStripeExpress ) {
		return;
	}

	const settings = window.HCaptchaWCStripeExpressObject || {};
	const nonceName = settings.nonceName || 'hcaptcha_wc_checkout_nonce';
	const missingProofMessage = settings.missingProofMessage || 'Complete hCaptcha before express checkout.';
	const wrapperSelector = '.hcaptcha-wc-stripe-express-proof';
	const maxProofAge = 90 * 1000;
	const proofStates = new WeakMap();
	const controllerGates = new WeakMap();
	const controllers = new Set();
	const contexts = [ 'product', 'cart', 'minicart', 'express_checkout' ];

	function getRoots( context ) {
		if ( 'express_checkout' === context ) {
			return [ ...document.querySelectorAll( 'form.checkout .hcaptcha-wc-checkout-proof' ) ];
		}

		return [ ...document.querySelectorAll( `${ wrapperSelector }[data-context="${ context }"]` ) ];
	}

	function getField( root, name ) {
		return root.querySelector( `[name="${ name }"]` );
	}

	function getResponse( root ) {
		return getField( root, 'h-captcha-response' )?.value || '';
	}

	function hasChallenge( root ) {
		return !! root.querySelector( 'h-captcha.h-captcha' );
	}

	function isReady( root ) {
		if ( ! root?.isConnected || ! getField( root, 'hcaptcha-widget-id' )?.value ) {
			return false;
		}

		// The form may be explicitly exempted, but its signed identity is still required.
		if ( ! hasChallenge( root ) ) {
			return true;
		}

		const state = proofStates.get( root );
		const response = getResponse( root );

		return !! response &&
			! state?.spent &&
			state?.token === response &&
			Date.now() - state?.createdAt < maxProofAge;
	}

	function getReadyRoot( context ) {
		return getRoots( context ).find( isReady ) || null;
	}

	function syncController( controller ) {
		const context = controller?.context?.getPage?.();

		if ( ! contexts.includes( context ) || 'expressCheckout' !== controller?.gateway?.elementType ) {
			return;
		}

		let gate = controllerGates.get( controller );

		if ( ! gate ) {
			gate = {
				nativeEnabled: controller.gateway._isDisabled !== true,
				enable: controller.enablePaymentElement.bind( controller ),
				disable: controller.disablePaymentElement.bind( controller ),
			};
			controllerGates.set( controller, gate );
			controller.enablePaymentElement = ( ...args ) => {
				gate.nativeEnabled = true;

				return getReadyRoot( context ) ? gate.enable( ...args ) : gate.disable();
			};
			controller.disablePaymentElement = ( ...args ) => {
				gate.nativeEnabled = false;

				return gate.disable( ...args );
			};
		}

		controllers.add( controller );

		if ( ! getReadyRoot( context ) ) {
			gate.disable();
		} else if ( gate.nativeEnabled ) {
			gate.enable();
		}
	}

	function syncControllers( context ) {
		for ( const controller of controllers ) {
			if ( context === controller.context?.getPage?.() ) {
				syncController( controller );
			}
		}
	}

	function syncAllControllers() {
		for ( const controller of controllers ) {
			syncController( controller );
		}
	}

	function forgetProof( root ) {
		const state = proofStates.get( root );

		if ( state ) {
			clearTimeout( state.timeout );
			proofStates.delete( root );
		}
	}

	function recordSolved( context, token ) {
		if ( ! token ) {
			return false;
		}

		let matched = false;

		for ( const root of getRoots( context ) ) {
			if ( ! hasChallenge( root ) || getResponse( root ) !== token ) {
				continue;
			}

			const previous = proofStates.get( root );

			if ( previous?.token === token ) {
				matched = matched || ! previous.spent;
				continue;
			}

			forgetProof( root );
			const state = { token, createdAt: Date.now(), spent: false };
			state.timeout = setTimeout( () => {
				if ( proofStates.get( root ) === state ) {
					proofStates.delete( root );
					syncControllers( context );
				}
			}, maxProofAge );
			proofStates.set( root, state );
			matched = true;
		}

		if ( matched ) {
			syncControllers( context );
		}

		return matched;
	}

	function onSolved( context, token ) {
		if ( ! recordSolved( context, token ) ) {
			// hCaptcha can invoke the callback before updating its response field.
			setTimeout( () => recordSolved( context, token ), 0 );
		}
	}

	function onExpired( context ) {
		for ( const root of getRoots( context ) ) {
			forgetProof( root );
		}

		syncControllers( context );
	}

	function getProofFields( root ) {
		const hpInput = root.querySelector( 'input[id^="hcap_hp_"]' );
		const names = [
			'h-captcha-response',
			'hcaptcha-widget-id',
			nonceName,
			hpInput?.name,
			'hcap_hp_sig',
			'hcap_fst_token',
		];
		const fields = {};

		for ( const name of names ) {
			if ( ! name ) {
				continue;
			}

			const field = getField( root, name );

			if ( field ) {
				fields[ name ] = field.value;
			}
		}

		return fields;
	}

	function isStripeCheckout( options ) {
		if ( 'POST' !== String( options?.method || '' ).toUpperCase() ) {
			return false;
		}

		if ( typeof options.url !== 'string' ) {
			return '/wc-stripe/v1/cart/checkout' === options.path;
		}

		let url;

		try {
			url = new URL( options.url, window.location.href );
		} catch {
			return false;
		}

		if ( url.origin !== window.location.origin ) {
			return false;
		}

		if ( 'wc_stripe_frontend_request' === url.searchParams.get( 'wc-ajax' ) ) {
			return '/wc-stripe/v1/cart/checkout' === url.searchParams.get( 'path' );
		}

		if ( '/wc-stripe/v1/cart/checkout' === url.searchParams.get( 'rest_route' ) ) {
			return true;
		}

		return url.pathname.replace( /\/+$/, '' ).endsWith( '/wp-json/wc-stripe/v1/cart/checkout' );
	}

	function resetProof( root, context ) {
		const widget = root.querySelector( 'h-captcha.h-captcha' );
		forgetProof( root );

		if ( widget && typeof window.hCaptchaReset === 'function' ) {
			window.hCaptchaReset( widget );
		}

		syncControllers( context );
	}

	function addProof( options, next ) {
		if ( ! isStripeCheckout( options ) ) {
			return next( options );
		}

		const data = options.data;
		const context = data?.context;

		if ( ! data || Array.isArray( data ) || 'object' !== typeof data || ! contexts.includes( context ) ) {
			return Promise.reject( new Error( missingProofMessage ) );
		}

		const root = getReadyRoot( context );

		if ( ! root ) {
			return Promise.reject( new Error( missingProofMessage ) );
		}

		const fields = getProofFields( root );
		const state = proofStates.get( root );

		if ( state ) {
			state.spent = true;
			clearTimeout( state.timeout );
			syncControllers( context );
		}

		let result;

		try {
			result = next( { ...options, data: { ...data, ...fields } } );
		} catch ( error ) {
			resetProof( root, context );
			throw error;
		}

		return Promise.resolve( result ).then(
			( response ) => {
				resetProof( root, context );
				return response;
			},
			( error ) => {
				resetProof( root, context );
				throw error;
			},
		);
	}

	// noinspection UnnecessaryLocalVariableJS
	const app = {
		onProductSolved: ( token ) => onSolved( 'product', token ),
		onCartSolved: ( token ) => onSolved( 'cart', token ),
		onMinicartSolved: ( token ) => onSolved( 'minicart', token ),
		onProductExpired: () => onExpired( 'product' ),
		onCartExpired: () => onExpired( 'cart' ),
		onMinicartExpired: () => onExpired( 'minicart' ),
	};

	window.hCaptchaWCStripeExpress = app;
	wp.hooks.addFilter( 'hcaptcha.formSelector', 'hcaptcha-wc-stripe-express', ( selector ) => `${ selector }, ${ wrapperSelector }` );
	wp.apiFetch.use( addProof );
	window.wcStripe?.actions?.addAction( 'payment_element_ready', syncController );
	window.wcStripe?.actions?.addAction( 'express_element_ready', ( availableMethods, gateway ) => {
		syncController( gateway?.controller );
	} );
	if ( window.MutationObserver && document.body ) {
		new MutationObserver( syncAllControllers ).observe( document.body, { childList: true, subtree: true } );
	}
	document.addEventListener( 'hCaptchaSubmitted', ( event ) => {
		onSolved( 'express_checkout', event.detail?.token );
	} );
}() );
