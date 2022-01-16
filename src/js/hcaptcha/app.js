/**
 * HCaptcha Application.
 *
 * @file HCaptcha Application.
 */

import HCaptcha from './hcaptcha';

const hCaptcha = new HCaptcha();

window.hCaptchaGetWidgetId = ( el ) => {
	hCaptcha.getWidgetId( el );
};

window.hCaptchaBindEvents = () => {
	hCaptcha.bindEvents();
};

window.hCaptchaSubmit = () => {
	hCaptcha.submit();
};

window.hCaptchaOnLoad = window.hCaptchaBindEvents;
