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
	document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeBindEvents' ) );
	hCaptcha.bindEvents();
	document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
};

window.hCaptchaSubmit = () => {
	hCaptcha.submit();
};

window.hCaptchaOnLoad = () => {
	function hCaptchaOnLoad() {
		window.hCaptchaBindEvents();
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded', { cancelable: true } ) );
	}

	hCaptcha.addSyncedEventListener( hCaptchaOnLoad );
};

window.customElements.define( 'h-captcha', HCaptchaCustomElement );

document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeAPI' ) );
