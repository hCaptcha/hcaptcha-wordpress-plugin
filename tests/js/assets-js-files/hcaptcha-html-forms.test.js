// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha HTML Forms', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		require( '../../../assets/js/hcaptcha-html-forms.js' );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
	} );

	test( 'refreshes tokens after an HTML Forms submission completes', () => {
		document.body.innerHTML = `
			<form class="hf-form">
				<textarea name="h-captcha-response">used-token</textarea>
				<textarea name="g-recaptcha-response">used-token</textarea>
			</form>`;

		const form = document.querySelector( 'form.hf-form' );

		form.dispatchEvent( new CustomEvent( 'hf-submitted', { bubbles: true } ) );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
		expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( form.querySelector( '[name="g-recaptcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores the event from an unrelated element', () => {
		document.body.innerHTML = '<form class="other-form"></form>';

		document.querySelector( 'form' )
			.dispatchEvent( new CustomEvent( 'hf-submitted', { bubbles: true } ) );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
