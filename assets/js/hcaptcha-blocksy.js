import { helper } from './hcaptcha-helper.js';

const hCaptchaBlocksy = window.hCaptchaBlocksy || ( function( window ) {
	const actions = [
		'blc_newsletter_subscribe_process_ajax_subscribe',
		'blc_subcribe_to_waitlist',
	];

	const app = {
		init() {
			helper.installFetchEvents();
			window.addEventListener( 'hCaptchaFetch:complete', app.fetchComplete );
		},

		fetchComplete( event ) {
			const config = event?.detail?.args?.[ 1 ] ?? {};
			const body = config.body;

			if ( ! actions.includes( app.getAction( body ) ) ) {
				return;
			}

			if ( typeof window.hCaptchaBindEvents === 'function' ) {
				window.hCaptchaBindEvents();
			}

			if ( typeof window.hCaptchaFST?.getToken === 'function' ) {
				window.hCaptchaFST.getToken();
			}
		},

		getAction( body ) {
			if ( body instanceof FormData || body instanceof URLSearchParams ) {
				return body.get( 'action' ) ?? '';
			}

			if ( typeof body === 'object' && body !== null ) {
				return body.action ?? '';
			}

			if ( typeof body !== 'string' ) {
				return '';
			}

			return new URLSearchParams( body ).get( 'action' ) ?? '';
		},
	};

	return app;
}( window ) );

window.hCaptchaBlocksy = hCaptchaBlocksy;

hCaptchaBlocksy.init();
