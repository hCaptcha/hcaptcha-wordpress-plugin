import { helper } from './hcaptcha-helper.js';

const jetpack = window.hCaptchaJetpack || ( function( window ) {
	const app = {
		init() {
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
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
