// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Import the script you want to test
require( '../../../assets/js/hcaptcha-wpforo' );

describe( 'hCaptcha WPForo', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		document.body.innerHTML = `
		<div class="wpforo-section">
			<button class="add_wpftopic">			
			</button>
		</div>
		`;

		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		// Simulate jQuery.ready
		window.hCaptchaWPForo( $ );
	} );

	afterEach( () => {
		global.hCaptchaBindEvents.mockRestore();
	} );

	test( 'clicking on new topic button triggers hCaptchaBindEvents', () => {
		const $btn = $( '.wpforo-section .add_wpftopic:not(.not_reg_user)' );

		$( $btn ).trigger( 'click' );

		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
