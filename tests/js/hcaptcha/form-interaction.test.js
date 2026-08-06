// noinspection JSUnresolvedReference

import FormInteraction from '../../../src/js/hcaptcha/form-interaction';

describe( 'FormInteraction', () => {
	const eventName = 'hCaptchaFormInteraction';
	let app;
	let listener;

	beforeEach( () => {
		document.body.innerHTML = `
			<form id="loginform">
				<input id="user_login">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="lostpasswordform">
				<input>
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="registerform">
				<input>
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="comment-form">
				<textarea></textarea>
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="contact-form">
				<input>
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="wp-block-jetpack-contact-form">
				<button type="submit">Submit</button>
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="wpcf7-form">
				<input name="your-name">
				<span class="wpcf7-form-control h-captcha hcaptcha-api-delayed"></span>
			</form>
			<form class="elementor-form">
				<input name="form_fields[name]">
				<div class="elementor-hcaptcha">
					<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
				</div>
			</form>
			<form class="checkout woocommerce-checkout">
				<input id="billing_first_name">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="wpforms-form">
				<input name="wpforms[fields][1]">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="et_pb_contact_form">
				<input name="et_pb_contact_name_0">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="eael-login-form">
				<input name="log">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="eael-register-form">
				<input name="user_login">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="mc4wp-form">
				<input name="EMAIL">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="uael-form uael-login-form">
				<input class="uael-login-form-username">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<div class="uael-registration-form">
				<form class="elementor-form">
					<input name="user_login">
					<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
				</form>
			</div>
			<form class="fusion-form fusion-form-1">
				<input class="fusion-form-input">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="login-form" class="login-form">
				<input name="log">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form class="uagb-forms-main-form">
				<input class="uagb-forms-input">
				<h-captcha class="h-captcha hcaptcha-api-delayed"></h-captcha>
			</form>
			<form id="unprotected-form">
				<input>
			</form>
		`;

		app = new FormInteraction( eventName );
		listener = jest.fn();
		document.addEventListener( eventName, listener );
	} );

	afterEach( () => {
		app.destroy();
		document.removeEventListener( eventName, listener );
	} );

	test.each( [
		'#loginform input',
		'#lostpasswordform input',
		'#registerform input',
		'.comment-form textarea',
		'.contact-form input',
		'.wp-block-jetpack-contact-form button',
		'.wpcf7-form input',
		'.elementor-form input',
		'.woocommerce-checkout input',
		'.wpforms-form input',
		'.et_pb_contact_form input',
		'#eael-login-form input',
		'#eael-register-form input',
		'.mc4wp-form input',
		'.uael-login-form input',
		'.uael-registration-form input',
		'.fusion-form input',
		'#login-form input',
		'.uagb-forms-main-form input',
	] )( 'dispatches the event on pointer interaction with %s', ( selector ) => {
		app.init();

		document.querySelector( selector ).dispatchEvent(
			new Event( 'pointerdown', { bubbles: true } ),
		);

		expect( listener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'dispatches the event on keyboard interaction inside a protected form', () => {
		app.init();

		document.querySelector( '#loginform input' ).dispatchEvent(
			new KeyboardEvent( 'keydown', { bubbles: true, key: 'a' } ),
		);

		expect( listener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'dispatches the event when Tab moves focus into a protected form', () => {
		app.init();

		document.body.dispatchEvent(
			new KeyboardEvent( 'keydown', { bubbles: true, key: 'Tab' } ),
		);
		document.querySelector( '#loginform input' ).focus();

		expect( listener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores programmatic focus without keyboard navigation', () => {
		app.init();

		document.querySelector( '#loginform input' ).focus();

		expect( listener ).not.toHaveBeenCalled();
	} );

	test( 'ignores interaction with an unprotected form', () => {
		app.init();

		document.querySelector( '#unprotected-form input' ).dispatchEvent(
			new Event( 'pointerdown', { bubbles: true } ),
		);

		expect( listener ).not.toHaveBeenCalled();
	} );

	test( 'dispatches the event only once', () => {
		app.init();

		const input = document.querySelector( '#loginform input' );

		input.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );

		expect( listener ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not listen when the event name is empty', () => {
		app = new FormInteraction( '' );
		app.init();

		document.querySelector( '#loginform input' ).dispatchEvent(
			new Event( 'pointerdown', { bubbles: true } ),
		);

		expect( listener ).not.toHaveBeenCalled();
	} );
} );
