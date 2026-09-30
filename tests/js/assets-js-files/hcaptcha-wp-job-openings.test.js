// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha WP Job Openings', () => {
	beforeEach( () => {
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		document.body.innerHTML = `
			<form class="awsm-application-form">
				<textarea name="h-captcha-response">response</textarea>
				<textarea name="g-recaptcha-response">response</textarea>
			</form>
		`;

		require( '../../../assets/js/hcaptcha-wp-job-openings.js' );
	} );

	afterEach( () => {
		$( document ).off( 'awsmjobs_application_failed awsmjobs_application_submitted' );
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		document.body.innerHTML = '';
		jest.resetModules();
	} );

	test.each( [ 'awsmjobs_application_failed', 'awsmjobs_application_submitted' ] )(
		'resets hCaptcha and refreshes FST after %s',
		( eventName ) => {
			const form = document.querySelector( '.awsm-application-form' );

			$( form ).trigger( eventName );

			expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
			expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
			expect( form.querySelector( '[name="g-recaptcha-response"]' ).value ).toBe( '' );
			expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
		},
	);

	test( 'ignores events outside an application form', () => {
		$( document.body ).trigger( 'awsmjobs_application_failed' );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
