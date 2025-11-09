/**
 * HCaptcha Application.
 *
 * @file HCaptcha Application.
 */

import HCaptcha from './hcaptcha';
import HCaptchaCustomElement from './hcaptcha-custom-element';

const hCaptcha = new HCaptcha();

window.hCaptcha = hCaptcha;

window.hCaptchaGetWidgetId = ( el ) => {
	hCaptcha.getWidgetId( el );
};

window.hCaptchaReset = ( el ) => {
	hCaptcha.reset( el );
};

window.hCaptchaBindEvents = () => {
	const addSyncedEventListener = () => {
		if ( window.__hCaptchaSyncedEventListenerAdded ) {
			return;
		}

		window.__hCaptchaSyncedEventListenerAdded = true;

		hCaptcha.addSyncedEventListener( () => {
			document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeBindEvents' ) );
			hCaptcha.bindEvents();
			document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
		} );
	};

	if ( window.__hCaptchaOnLoad ) {
		addSyncedEventListener();
	} else {
		document.addEventListener( 'hCaptchaBeforeOnLoad', () => {
			addSyncedEventListener();
		} );
	}
};

window.hCaptchaSubmit = () => {
	hCaptcha.submit();
};

window.hCaptchaOnLoad = () => {
	window.__hCaptchaOnLoad = true;
	document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeOnLoad', { cancelable: true } ) );

	window.hCaptchaBindEvents();

	document.addEventListener( 'hCaptchaAfterBindEvents', () => {
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded', { cancelable: true } ) );
	} );
};

window.customElements.define( 'h-captcha', HCaptchaCustomElement );

document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeAPI' ) );
