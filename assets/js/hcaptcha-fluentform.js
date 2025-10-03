/* global jQuery, hcaptcha, HCaptchaFluentFormObject */

import { helper } from './hcaptcha-helper.js';

const hCaptchaFluentForm = window.hCaptchaFluentForm || ( function( window, $ ) {
	const action = 'fluentform_submit';

	const app = {
		init() {
			// Install global fetch event wrapper (idempotent).
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );

			// Initialize conversational form behavior when hCaptcha library is ready.
			document.addEventListener( 'hCaptchaLoaded', app.onHCaptchaLoaded );

			$( document ).on( 'ajaxComplete', app.ajaxCompleteHandler );
		},

		onHCaptchaLoaded() {
			const formSelector = '.ffc_conv_form';

			const hasOwnCaptcha = () => {
				return document.getElementById( 'hcaptcha-container' ) !== null;
			};

			/**
			 * Process conversational form.
			 */
			const processForm = () => {
				// We assume there should be only one conversational form on the page.
				const form = document.querySelector( formSelector );
				const submitBtnSelector = '.ff-btn';

				const isSubmitVisible = ( qForm ) => {
					return qForm.querySelector( submitBtnSelector ) !== null;
				};

				const addCaptcha = () => {
					const hCaptchaHiddenClass = 'h-captcha-hidden';
					const hCaptchaClass = 'h-captcha';
					const hiddenCaptcha = document.getElementsByClassName( hCaptchaHiddenClass )[ 0 ];
					const submitBtn = form.querySelector( submitBtnSelector );

					/**
					 * @type {HTMLElement}
					 */
					const hCaptchaNode = hiddenCaptcha.cloneNode( true );
					const wrappingForm = document.createElement( 'form' );

					wrappingForm.setAttribute( 'method', 'POST' );
					submitBtn.parentNode.insertBefore( wrappingForm, submitBtn );
					wrappingForm.appendChild( submitBtn );
					submitBtn.before( hCaptchaNode );
					hCaptchaNode.classList.remove( hCaptchaHiddenClass );
					hCaptchaNode.querySelector( 'h-captcha' ).classList.add( hCaptchaClass );
					hCaptchaNode.style.display = 'block';

					window.hCaptchaBindEvents();
				};

				const mutationObserverCallback = ( mutationList ) => {
					for ( const mutation of mutationList ) {
						if (
							! (
								mutation.type === 'attributes' &&
								mutation.attributeName === 'class' &&
								mutation.oldValue && mutation.oldValue.includes( 'q-is-inactive' )
							)
						) {
							continue;
						}

						if ( isSubmitVisible( mutation.target ) ) {
							addCaptcha();
						}
					}
				};

				if ( hasOwnCaptcha() ) {
					return;
				}

				const qFormSelector = '.q-form';
				const qForms = form.querySelectorAll( qFormSelector );
				const config = {
					attributes: true,
					attributeOldValue: true,
				};

				[ ...qForms ].map( ( qForm ) => {
					const observer = new MutationObserver( mutationObserverCallback );
					observer.observe( qForm, config );
					return qForm;
				} );
			};

			function waitForElement( selector ) {
				return new Promise( ( resolve ) => {
					if ( document.querySelector( selector ) ) {
						return resolve( document.querySelector( selector ) );
					}

					const observer = new MutationObserver( () => {
						if ( document.querySelector( selector ) ) {
							resolve( document.querySelector( selector ) );
							observer.disconnect();
						}
					} );

					observer.observe( document.body, {
						childList: true,
						subtree: true,
					} );
				} );
			}

			/**
			 * Custom render function using Fluent Forms conversational callback.
			 *
			 * @param {string} container The hCaptcha container selector.
			 * @param {Object} params    Parameters.
			 */
			const render = ( container, params ) => {
				const renderParams = window.hCaptcha.getParams();

				if ( hasOwnCaptcha() && renderParams.size === 'invisible' ) {
					// Cannot use invisible hCaptcha with conversational form.
					renderParams.size = 'normal';
				}

				renderParams.callback = params.callback;
				originalRender( container, renderParams );
			};

			if ( ! document.querySelector( formSelector ) ) {
				return;
			}

			// Intercept render request.
			const originalRender = hcaptcha.render;
			hcaptcha.render = render;

			// Launch Fluent Forms conversational script.
			const t = document.getElementsByTagName( 'script' )[ 0 ];
			const s = document.createElement( 'script' );

			s.type = 'text/javascript';
			s.id = HCaptchaFluentFormObject.id;
			s.src = HCaptchaFluentFormObject.url;
			t.parentNode.insertBefore( s, t );

			// Process form not having own hCaptcha.
			waitForElement( formSelector + ' .vff-footer' ).then( () => {
				// Launch our form-related code when conversational form is rendered.
				processForm();
			} );
		},

		fetchBefore( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			if ( body.get( 'action' ) !== action ) {
				return;
			}

			const data = body.get( 'data' ) ?? '';
			const formId = body.get( 'form_id' ) ?? '';
			const $node = $( `.ff_conv_app_${ formId }` );
			const nonceName = 'hcaptcha_fluentform_nonce';

			if ( $node?.length ) {
				// Use shared helper to append hCaptcha params only when missing.
				const options = { data };

				helper.addHCaptchaData( options, '', nonceName, $node );
				body.set( 'data', options.data );
				config.body = body;
				event.detail.args[ 1 ] = config;
			}
		},

		fetchComplete( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			if ( body.get( 'action' ) !== action ) {
				return;
			}

			window.hCaptchaBindEvents();
		},

		// jQuery ajaxComplete handler.
		ajaxCompleteHandler( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== action ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window, jQuery ) );

window.hCaptchaFluentForm = hCaptchaFluentForm;

hCaptchaFluentForm.init();
