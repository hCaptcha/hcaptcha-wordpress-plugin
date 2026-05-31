( function() {
	const marker = '__hCaptchaWooCommercePayPalPaymentsWrapped';
	const namespaces = [ 'paypal', 'ppcpBlocksPaypalExpressButtons' ];

	if ( window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped ) {
		return;
	}

	window.__hCaptchaWooCommercePayPalPaymentsEarlyWrapped = true;

	function executeCaptchaBeforePayPal( wrapper ) {
		let attempts = 0;

		return new Promise( ( resolve ) => {
			const execute = () => {
				const app = window.hCaptchaWooCommercePayPalPayments;

				if ( app?.executeCaptchaBeforePayPal ) {
					resolve( app.executeCaptchaBeforePayPal( wrapper ) );

					return;
				}

				attempts += 1;

				if ( attempts > 40 ) {
					resolve();

					return;
				}

				setTimeout( execute, 50 );
			};

			execute();
		} );
	}

	function wrapOptions( wrapper, options ) {
		if ( ! options || options[ marker ] ) {
			return options;
		}

		const onClick = options.onClick;

		return {
			...options,
			[ marker ]: true,
			onClick( data, actions ) {
				return executeCaptchaBeforePayPal( wrapper ).then( () => {
					return typeof onClick === 'function'
						? onClick.call( this, data, actions )
						: undefined;
				} ).then( ( result ) => {
					if ( result !== undefined ) {
						return result;
					}

					return actions?.resolve ? actions.resolve() : undefined;
				} );
			},
		};
	}

	function wrapButtons( paypal, buttons ) {
		if ( ! buttons || buttons[ marker ] ) {
			return buttons;
		}

		const previousButtons = buttons.bind( paypal );

		const wrappedButtons = ( options ) => {
			return previousButtons( wrapOptions( '', options ) );
		};

		Object.assign( wrappedButtons, buttons );

		wrappedButtons[ marker ] = true;

		return wrappedButtons;
	}

	function wrapPayPal( paypal ) {
		if ( ! paypal ) {
			return paypal;
		}

		const buttonsMarker = marker + 'ButtonsWatched';
		let buttons = paypal.Buttons;

		if ( paypal[ buttonsMarker ] ) {
			return paypal;
		}

		try {
			Object.defineProperty( paypal, 'Buttons', {
				configurable: true,
				get() {
					return buttons;
				},
				set( nextButtons ) {
					buttons = wrapButtons( paypal, nextButtons );
				},
			} );
		} catch {
			paypal.Buttons = wrapButtons( paypal, buttons );
			paypal[ buttonsMarker ] = true;

			return paypal;
		}

		buttons = wrapButtons( paypal, buttons );
		paypal[ buttonsMarker ] = true;

		return paypal;
	}

	function wrapWidgetBuilder( widgetBuilder ) {
		if ( ! widgetBuilder || widgetBuilder[ marker ] ) {
			return widgetBuilder;
		}

		if ( typeof widgetBuilder.registerButtons === 'function' ) {
			const previousRegisterButtons = widgetBuilder.registerButtons.bind( widgetBuilder );

			widgetBuilder.registerButtons = ( wrapper, options ) => {
				return previousRegisterButtons( wrapper, wrapOptions( wrapper, options ) );
			};
		}

		if ( widgetBuilder.buttons instanceof Map ) {
			for ( const entry of widgetBuilder.buttons.values() ) {
				entry.options = wrapOptions( entry.wrapper, entry.options );
			}
		}

		widgetBuilder[ marker ] = true;

		return widgetBuilder;
	}

	function watchNamespace( namespace ) {
		let value = window[ namespace ];

		try {
			Object.defineProperty( window, namespace, {
				configurable: true,
				get() {
					return value;
				},
				set( nextValue ) {
					value = wrapPayPal( nextValue );
				},
			} );
		} catch {
			return;
		}

		value = wrapPayPal( value );
	}

	function watchWidgetBuilder() {
		let value = window.widgetBuilder;

		try {
			Object.defineProperty( window, 'widgetBuilder', {
				configurable: true,
				get() {
					return value;
				},
				set( nextValue ) {
					value = wrapWidgetBuilder( nextValue );
				},
			} );
		} catch {
			return;
		}

		value = wrapWidgetBuilder( value );
	}

	namespaces.forEach( watchNamespace );
	watchWidgetBuilder();
}() );
