// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import jquery from 'jquery';
import { hooks as elementorFrontendHooks } from '../__mocks__/elementorFrontend';

// Set up global variables
global.jQuery = jquery;
global.$ = jquery;

// Import the script to test
require( '../../../assets/js/hcaptcha-elementor-pro' );

describe( 'Elementor Frontend hCaptcha', () => {
	beforeEach( () => {
		elementorFrontendHooks.addAction.mockClear();
	} );

	test( 'addAction is called with correct arguments', () => {
		// Simulate jQuery.ready
		window.hCaptchaElementorPro();

		expect( elementorFrontendHooks.addAction ).toHaveBeenCalledTimes( 1 );
		expect( elementorFrontendHooks.addAction ).toHaveBeenCalledWith(
			'frontend/element_ready/widget',
			expect.any( Function )
		);
	} );
} );
