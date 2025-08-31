import { helper } from './hcaptcha-helper.js';

const hCaptchaSpectra = window.hCaptchaSpectra || ( function( window ) {
	let style;

	const app = {
		init() {
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:before', app.fetchBefore );
			window.addEventListener( 'hCaptchaFetch:success', app.fetchSuccess );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchBefore( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof URLSearchParams ) ) {
				return;
			}

			// @param {FormData} body
			const blockId = body.get( 'block_id' );
			const block = document.querySelector( `.uagb-block-${ blockId }` );

			// Remove hCaptcha error message.
			const errorMessage = block.querySelector( '.hcaptcha-error-message' );

			if ( errorMessage ) {
				errorMessage.remove();
			}

			const responseInputName = 'h-captcha-response';
			const formData = JSON.parse( body.get( 'form_data' ) );

			if ( 'uagb_process_forms' !== body.get( 'action' ) || formData.hasOwnProperty( responseInputName ) ) {
				return;
			}

			const widgetName = 'hcaptcha-widget-id';
			const sigInputName = 'hcap_hp_sig';
			const tokenName = 'hcap_fst_token';
			const nonceName = 'hcaptcha_spectra_form_nonce';

			/**
			 * @type {HTMLInputElement}
			 */
			const widgetId = block.querySelector( `[name="${ widgetName }"]` );

			/**
			 * @type {HTMLTextAreaElement}
			 */
			const hCaptchaResponse = block.querySelector( `[name="${ responseInputName }"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const nonce = block.querySelector( `[name="${ nonceName }"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const hcapHp = block.querySelector( `[id^="hcap_hp_"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const hcapSig = block.querySelector( `[name="${ sigInputName }"]` );

			/**
			 * @type {HTMLInputElement}
			 */
			const token = block.querySelector( `[name="${ tokenName }"]` );

			formData[ widgetName ] = widgetId?.value;
			formData[ responseInputName ] = hCaptchaResponse?.value;
			formData[ nonceName ] = nonce?.value;
			formData[ hcapHp?.id ] = hcapHp?.value;
			formData[ sigInputName ] = hcapSig?.value;
			formData[ tokenName ] = token?.value;

			body.set( 'form_data', JSON.stringify( formData ) );

			config.body = body;
			event.detail.args.config = config;
		},

		async fetchSuccess( event ) {
			const config = event?.detail?.args?.[ 1 ];
			const response = event?.detail?.response;

			if ( ! config || ! response ) {
				return;
			}

			const body = config.body;

			if ( ! ( body instanceof FormData || body instanceof URLSearchParams ) ) {
				return;
			}

			const responseData = await response.clone().json().catch( () => null );

			if ( 'uagb_process_forms' !== body.get( 'action' ) || typeof responseData?.data !== 'string' ) {
				return;
			}

			const blockId = body.get( 'block_id' );
			const selector = `[name="uagb-form-${ blockId }"]`;

			style = document.createElement( 'style' );
			style.id = `hcaptcha-style-${ blockId }`;
			style.textContent = `
		${ selector } {
			display: block !important;
		}
`;

			// We have hCaptcha error in responseData.
			const styleToAdd = document.getElementById( style.id );

			if ( ! styleToAdd ) {
				// Add a style preventing hiding the form.
				document.head.appendChild( style );
			}

			// Remove previous error message (if exists) in the current block.
			const prevError = document.querySelector( '.uagb-block-' + blockId + ' .hcaptcha-error-message' );

			if ( prevError ) {
				prevError.remove();
			}

			// Show an error message.
			const errorContainer = document.createElement( 'div' );

			errorContainer.className = 'hcaptcha-error-message';
			errorContainer.textContent = responseData.data;
			errorContainer.style.color = 'red';
			errorContainer.style.padding = '10px 0';

			// Find the form container and append the error message
			const hcaptchaContainer = document.querySelector( '.uagb-block-' + blockId + ' h-captcha' );

			if ( hcaptchaContainer ) {
				hcaptchaContainer.parentNode.insertBefore( errorContainer, hcaptchaContainer );
			}
		},

		fetchComplete( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof URLSearchParams ) ) {
				return;
			}

			if ( 'uagb_process_forms' !== body.get( 'action' ) ) {
				return;
			}

			// Remove a style preventing hiding the form.
			const styleToRemove = document.getElementById( style?.id );

			if ( styleToRemove ) {
				styleToRemove.remove();
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window ) );

window.hCaptchaSpectra = hCaptchaSpectra;

hCaptchaSpectra.init();
