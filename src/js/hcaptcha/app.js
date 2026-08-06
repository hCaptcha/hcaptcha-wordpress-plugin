/**
 * HCaptcha Application.
 *
 * @file HCaptcha Application.
 */

import HCaptcha from './hcaptcha';
import HCaptchaCustomElement from './hcaptcha-custom-element';
import FormInteraction from './form-interaction';
import ReadyGate from './ready-gate';

if ( ! window.hCaptcha ) {
	const hCaptcha = new HCaptcha();
	const readyGate = new ReadyGate();

	window.hCaptcha = hCaptcha;

	window.hCaptchaGetWidgetId = ( el ) => {
		hCaptcha.getWidgetId( el );
	};

	window.hCaptchaReset = ( el ) => {
		hCaptcha.reset( el );
	};

	window.hCaptchaBindEvents = ( el ) => {
		readyGate.runWhenReady( () => {
			document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeBindEvents' ) );
			hCaptcha.bindEvents( el );
			document.dispatchEvent( new CustomEvent( 'hCaptchaAfterBindEvents' ) );
		} );
	};

	window.hCaptchaSubmit = () => {
		hCaptcha.submit();
	};

	window.hCaptchaOnLoad = () => {
		document.dispatchEvent( new CustomEvent( 'hCaptchaOnLoad' ) );
		document.addEventListener( 'hCaptchaAfterBindEvents', () => {
			document.dispatchEvent( new CustomEvent( 'hCaptchaLoaded', { cancelable: true } ) );
		} );
	};

	window.customElements.define( 'h-captcha', HCaptchaCustomElement );

	const formInteractionEvent = window.HCaptchaMainObject?.formInteractionEvent ?? '';

	new FormInteraction( formInteractionEvent ).init();

	document.dispatchEvent( new CustomEvent( 'hCaptchaBeforeAPI' ) );
}
