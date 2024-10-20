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

window.customElements.define( 'h-captcha', HCaptchaCustomElement );

export default HCaptchaCustomElement;
