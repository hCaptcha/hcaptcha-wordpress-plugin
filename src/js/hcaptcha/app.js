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
	function hCaptchaOnLoad() {
		window.hCaptchaBindEvents();
		document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded' ) );
	}

	// Sync with DOMContentLoaded event.
	if ( document.readyState === 'loading' ) {
		window.addEventListener( 'DOMContentLoaded', hCaptchaOnLoad );
	} else {
		hCaptchaOnLoad();
	}
};
