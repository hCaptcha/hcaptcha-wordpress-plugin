class HCaptchaCustomElement extends HTMLElement {
	// noinspection JSUnusedGlobalSymbols
	connectedCallback() {
		window.hCaptchaBindEvents();
	}
}

window.customElements.define( 'h-captcha', HCaptchaCustomElement );
