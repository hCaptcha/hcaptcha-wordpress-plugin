/**
 * HCaptcha Application.
 *
 * @file HCaptcha Application.
 */

import HCaptcha from './hcaptcha';
import HCaptchaCustomElement from './hcaptcha-custom-element';

const hCaptcha = new HCaptcha();

window.hCaptcha = hCaptcha;

new HCaptchaCustomElement();

window.hCaptchaGetWidgetId = ( el ) => {
	hCaptcha.getWidgetId( el );
};

window.hCaptchaReset = ( el ) => {
	hCaptcha.reset( el );
};

window.hCaptchaBindEvents = () => {
	hCaptcha.bindEvents();
};

window.hCaptchaSubmit = () => {
	hCaptcha.submit();
};

window.hCaptchaOnLoad = () => {
	function hCaptchaOnLoad() {
		window.hCaptchaBindEvents();
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded' ) );
	}

	hCaptcha.addSyncedEventListener( hCaptchaOnLoad );
};

document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeAPI' ) );
