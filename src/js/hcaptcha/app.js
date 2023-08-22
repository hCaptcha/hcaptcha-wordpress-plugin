/**
 * HCaptcha Application.
 *
 * @file HCaptcha Application.
 */

import HCaptcha from './hcaptcha';

const hCaptcha = new HCaptcha();

window.hCaptcha = hCaptcha;

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
	window.hCaptchaBindEvents();

	document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded' ) );
};
