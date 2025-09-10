import { helper } from './hcaptcha-helper.js';

const jetpack = window.hCaptchaJetpack || ( function( window ) {
	const app = {
		init() {
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:success', app.fetchSuccess );
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
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

			const responseData = await response.clone().text().catch( () => null );

			if ( 'grunion-contact-form' !== body.get( 'action' ) || typeof responseData !== 'string' ) {
				return;
			}

			const formId = body.get( 'contact-form-id' );
			const form = document.getElementById( `contact-form-${ formId }` );

			// Remove previous error message (if exists) in the current block.
			const errorEl = form.querySelector( '.contact-form__error[data-wp-text="context.submissionError"]' );

			errorEl.innerHTML = responseData;

			const errorMsgEl = errorEl?.querySelector( '.form-errors .form-error-message' );

			errorMsgEl?.style.setProperty( 'color', 'var(--jetpack--contact-form--inverted-text-color)' );
		},

		fetchComplete( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! ( body instanceof FormData ) ) {
				return;
			}

			if ( body.get( 'action' ) !== 'grunion-contact-form' ) {
				return;
			}

			window.hCaptchaBindEvents();
		},
	};

	return app;
}( window ) );

window.hCaptchaJetpack = jetpack;

jetpack.init();
