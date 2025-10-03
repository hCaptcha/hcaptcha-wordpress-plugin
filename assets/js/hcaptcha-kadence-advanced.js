import { helper } from './hcaptcha-helper.js';

const hCaptchaKadenceAdvanced = window.hCaptchaKadenceAdvanced || ( function( window ) {
	const action = 'kb_process_advanced_form_submit';

	const app = {
		init() {
			// Install global fetch event wrapper (idempotent).
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
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
	};

	return app;
}( window ) );

window.hCaptchaKadenceAdvanced = hCaptchaKadenceAdvanced;

hCaptchaKadenceAdvanced.init();
