// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Tests for HCaptchaCustomElement
import HCaptchaCustomElement from '../../../src/js/hcaptcha/hcaptcha-custom-element.js';

describe( 'HCaptchaCustomElement', () => {
	beforeEach( () => {
		// Fresh mocks before each test
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptcha = {
			addSyncedEventListener: jest.fn(),
		};
	} );

	test( 'connectedCallback registers synced listener with hCaptchaBindEvents', () => {
		// Ensure the element is registered in the custom elements registry (required by JSDOM)
		if ( ! window.customElements.get( 'h-captcha' ) ) {
			window.customElements.define( 'h-captcha', HCaptchaCustomElement );
		}
		const el = document.createElement( 'h-captcha' );

		// Act
		el.connectedCallback();

		// Assert
		expect( window.hCaptcha.addSyncedEventListener ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptcha.addSyncedEventListener ).toHaveBeenCalledWith( window.hCaptchaBindEvents );
	} );

	test( 'connectedCallback works when element is attached to DOM', () => {
		if ( ! window.customElements.get( 'h-captcha' ) ) {
			window.customElements.define( 'h-captcha', HCaptchaCustomElement );
		}
		const el = document.createElement( 'h-captcha' );
		document.body.appendChild( el );

		// Manually call connectedCallback to simulate lifecycle hook in JSDOM
		el.connectedCallback();

		expect( window.hCaptcha.addSyncedEventListener ).toHaveBeenCalledWith( window.hCaptchaBindEvents );

		// Cleanup
		document.body.removeChild( el );
	} );
} );
