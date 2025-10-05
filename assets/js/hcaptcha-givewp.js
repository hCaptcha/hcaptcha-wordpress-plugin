/* global HCaptchaGiveWPObject */

import { helper } from './hcaptcha-helper.js';

/**
 * @param HCaptchaGiveWPObject.hcaptchaForm
 */

const hCaptchaGiveWP = window.hCaptchaGiveWP || ( function( window ) {
	const app = {
		init() {
			wp.hooks.addFilter(
				'hcaptcha.ajaxSubmitButton',
				'hcaptcha',
				( isAjaxSubmitButton, submitButtonElement ) => {
					if ( submitButtonElement.parentElement.classList.contains( 'givewp-layouts' ) ) {
						return true;
					}

					return isAjaxSubmitButton;
				}
			);

			// Install global fetch event wrapper (idempotent).
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );

			// Insert hCaptcha to the GiveWP donation form when DOM is ready.
			wp.domReady( app.insertCaptcha );
		},

		fetchBefore( event ) {
			const args = event?.detail?.args ?? [];
			const resource = args[ 0 ];
			const config = args[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			const url = typeof resource === 'string' ? resource : ( resource?.url || '' );
			let route = '';

			try {
				const u = new URL( url, window.location.href );
				route = u.searchParams.get( 'givewp-route' ) || '';
			} catch ( e ) {
				// ignore
			}

			if ( route !== 'donate' ) {
				return;
			}

			const giveWPForm = document.getElementById( 'root-givewp-donation-form' );

			if ( ! giveWPForm ) {
				return;
			}

			const responseName = 'h-captcha-response';
			const widgetName = 'hcaptcha-widget-id';
			const nonceName = 'hcaptcha_give_wp_form_nonce';
			const tokenName = 'hcap_fst_token';
			const sigName = 'hcap_hp_sig';

			const response = giveWPForm.querySelector( `[name="${ responseName }"]` );
			const widgetId = giveWPForm.querySelector( `[name="${ widgetName }"]` );
			const nonce = giveWPForm.querySelector( `[name="${ nonceName }"]` );
			const tokenInput = giveWPForm.querySelector( `[name="${ tokenName }"]` );
			const sigInput = giveWPForm.querySelector( `[name="${ sigName }"]` );
			const hcapHp = giveWPForm.querySelector( `[id^="hcap_hp_"]` );

			body.set( responseName, response?.value );
			body.set( widgetName, widgetId?.value );
			body.set( nonceName, nonce?.value );
			body.set( tokenName, tokenInput?.value );
			body.set( sigName, sigInput?.value );
			body.set( hcapHp.id, hcapHp.value ?? '' );

			config.body = body;
			args[ 1 ] = config;
			event.detail.args = args;
		},

		fetchComplete( event ) {
			const args = event?.detail?.args ?? [];
			const resource = args[ 0 ];
			const url = typeof resource === 'string' ? resource : ( resource?.url || '' );

			let route = '';
			try {
				const u = new URL( url, window.location.href );
				route = u.searchParams.get( 'givewp-route' ) || '';
			} catch ( e ) {
				// ignore
			}

			if ( route !== 'donate' ) {
				return;
			}

			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}
		},

		// Insert hCaptcha to the GiveWP donation form.
		insertCaptcha() {
			const hcaptchaForm = HCaptchaGiveWPObject?.hcaptchaForm;

			if ( ! hcaptchaForm ) {
				// Something is wrong - no script data available.
				return;
			}

			const targetNode = document.getElementById( 'root-givewp-donation-form' );

			if ( ! targetNode ) {
				// No GiveWP form.
				return;
			}

			const observerOptions = {
				childList: true,
				subtree: true,
			};

			const observerCallback = ( mutationsList ) => {
				for ( const mutation of mutationsList ) {
					if ( mutation.type !== 'childList' ) {
						continue;
					}

					const submitButton = document.querySelector( 'button[type="submit"]' );

					if ( ! submitButton ) {
						// No "submit" button on the current form step page.
						return;
					}

					const hCaptchaElement = targetNode.querySelector( '.h-captcha' );

					if ( hCaptchaElement ) {
						// We have added the hCaptcha already.
						return;
					}

					const submitSection = submitButton ? submitButton.closest( 'section' ) : null;

					// On multistep form, the `submit` button is not in the section.
					const submitElement = submitSection ?? submitButton;

					const hcaptcha = document.createElement( 'section' );

					hcaptcha.classList.add( 'givewp-layouts', 'givewp-layouts-section' );

					hcaptcha.innerHTML = JSON.parse( hcaptchaForm );

					// Prepend the new element to the parent element
					submitElement.parentElement.insertBefore( hcaptcha, submitElement );
					window.hCaptchaBindEvents();

					return;
				}
			};

			const observer = new MutationObserver( observerCallback );
			observer.observe( targetNode, observerOptions );
		},
	};

	return app;
}( window ) );

window.hCaptchaGiveWP = hCaptchaGiveWP;

hCaptchaGiveWP.init();
