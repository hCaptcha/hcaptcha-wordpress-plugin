/**
 * @file class HCaptchaCustomElement.
 */

/**
 * Class HCaptchaCustomElement.
 */
class HCaptchaCustomElement extends HTMLElement {
	// noinspection JSUnusedGlobalSymbols
	connectedCallback() {
		window.hCaptchaBindEvents();
	}
}

export default HCaptchaCustomElement;
