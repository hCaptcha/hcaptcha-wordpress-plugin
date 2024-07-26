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
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		elementorFrontendHooks.addAction.mockClear();
	} );

	test( 'filter and action are called with correct arguments', () => {
		// Simulate jQuery.ready
		window.hCaptchaElementorPro();

		expect( wp.hooks.addFilter ).toHaveBeenCalledTimes( 1 );
		expect( wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.params',
			'hcaptcha',
			expect.any( Function )
		);

		expect( elementorFrontendHooks.addAction ).toHaveBeenCalledTimes( 1 );
		expect( elementorFrontendHooks.addAction ).toHaveBeenCalledWith(
			'frontend/element_ready/widget',
			expect.any( Function )
		);
	} );
} );
