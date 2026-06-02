/* global jQuery */

const hCaptchaEssentialAddons = window.hCaptchaEssentialAddons || ( function( window, $ ) {
	const submitNames = [ 'eael-login-submit', 'eael-register-submit' ];

	const app = {
		init() {
			app.addAjaxSubmitButtonFilter();

			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		addAjaxSubmitButtonFilter() {
			if ( ! window.wp?.hooks?.addFilter ) {
				return;
			}

			window.wp.hooks.addFilter(
				'hcaptcha.ajaxSubmitButton',
				'hcaptcha',
				( isAjaxSubmitButton, submitButtonElement ) => {
					if ( app.isSubmitButton( submitButtonElement ) ) {
						return true;
					}

					return isAjaxSubmitButton;
				},
			);
		},

		isSubmitButton( submitButtonElement ) {
			if ( ! submitButtonElement ) {
				return false;
			}

			const name = submitButtonElement.getAttribute( 'name' ) ?? '';
			const id = submitButtonElement.getAttribute( 'id' ) ?? '';

			return submitNames.some( ( submitName ) => {
				return (
					name === submitName ||
					id === submitName ||
					submitButtonElement.classList.contains( submitName )
				);
			} );
		},

		ajaxCompleteHandler( event, xhr, settings ) {
			const params = app.getParams( settings?.data );

			if ( ! app.isLoginRegisterRequest( params ) ) {
				return;
			}

			const forms = app.getForms( params );

			if ( forms.length ) {
				forms.forEach( app.resetForm );
				app.refreshFSTToken();

				return;
			}

			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}

			app.refreshFSTToken();
		},

		getParams( data ) {
			if ( Array.isArray( data ) ) {
				const params = new URLSearchParams();

				data.forEach( ( item ) => {
					if ( item?.name ) {
						params.append( item.name, item.value ?? '' );
					}
				} );

				return params;
			}

			if ( data instanceof FormData ) {
				return new URLSearchParams( data );
			}

			if ( typeof data === 'object' && data !== null ) {
				const params = new URLSearchParams();

				Object.entries( data ).forEach( ( [ key, value ] ) => {
					params.set( key, value );
				} );

				return params;
			}

			if ( typeof data !== 'string' ) {
				return new URLSearchParams();
			}

			const queryString = data.startsWith( '?' ) ? data.slice( 1 ) : data;

			return new URLSearchParams( queryString );
		},

		isLoginRegisterRequest( params ) {
			return (
				params.get( 'action' ) === 'eael-login-register-form' ||
				submitNames.some( ( submitName ) => params.has( submitName ) ) ||
				params.has( 'eael-login-nonce' ) ||
				params.has( 'eael-register-nonce' )
			);
		},

		getForms( params ) {
			const widgetId = params.get( 'widget_id' );
			const submitName = app.getSubmitName( params );
			const form = app.getFormBySubmitName( submitName, widgetId );
			const forms = app.getFormsByWidgetId( widgetId );

			if ( form ) {
				return [ ...new Set( [ form, ...forms ] ) ];
			}

			if ( forms.length ) {
				return forms;
			}

			return [ ...document.querySelectorAll( '#eael-login-form, #eael-register-form' ) ];
		},

		resetForm( form ) {
			if ( typeof window.hCaptchaReset === 'function' ) {
				window.hCaptchaReset( form );
			}

			form.querySelectorAll( 'textarea[name="h-captcha-response"], textarea[name="g-recaptcha-response"]' )
				.forEach( ( response ) => {
					response.value = '';
				} );
		},

		refreshFSTToken() {
			if ( typeof window.hCaptchaFST?.getToken !== 'function' ) {
				return;
			}

			window.hCaptchaFST.getToken();
		},

		getSubmitName( params ) {
			const submitName = submitNames.find( ( name ) => params.has( name ) );

			if ( submitName ) {
				return submitName;
			}

			if ( params.has( 'eael-register-nonce' ) ) {
				return 'eael-register-submit';
			}

			if ( params.has( 'eael-login-nonce' ) ) {
				return 'eael-login-submit';
			}

			return '';
		},

		getFormBySubmitName( submitName, widgetId ) {
			if ( ! submitName ) {
				return null;
			}

			const buttons = [ ...document.querySelectorAll( `[name="${ submitName }"], #${ submitName }, .${ submitName }` ) ];

			const button = buttons.find( ( currentButton ) => {
				const form = currentButton.closest( 'form' );

				return ! widgetId || form?.querySelector( 'input[name="widget_id"]' )?.value === widgetId;
			} );

			return ( button ?? buttons[ 0 ] )?.closest( 'form' ) ?? null;
		},

		getFormsByWidgetId( widgetId ) {
			if ( ! widgetId ) {
				return [];
			}

			return [ ...document.querySelectorAll( 'input[name="widget_id"]' ) ]
				.filter( ( input ) => input.value === widgetId )
				.map( ( input ) => input.closest( 'form' ) )
				.filter( ( form ) => form?.querySelector( '.h-captcha' ) );
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaEssentialAddons = hCaptchaEssentialAddons;

hCaptchaEssentialAddons.init();
