/**
 * @file class HCaptchaCustomElement.
 */

/**
 * Class HCaptchaCustomElement.
 */
class HCaptchaCustomElement extends HTMLElement {
	// noinspection JSUnusedGlobalSymbols
	connectedCallback() {
		window.hCaptcha.addSyncedEventListener( window.hCaptchaBindEvents );
	}
}

export default HCaptchaCustomElement;
