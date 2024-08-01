// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* global nfRadio */

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

import '../__mocks__/backboneMarionette';
import '../__mocks__/backboneRadio';

describe( 'Ninja Forms hCaptcha', () => {
	let controller;

	beforeEach( () => {
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		require( '../../../assets/js/hcaptcha-nf.js' );

		// Execute DOMContentLoaded event
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
		controller = window.hCaptchaFieldController;

		// Reset the request mock function
		nfRadio.channel( 'fields' ).request.mockReset();
	} );

	test( 'initialize registers listeners', () => {
		controller.initialize();
		expect( nfRadio.channel ).toHaveBeenCalledWith( 'submit' );
		expect( nfRadio.channel ).toHaveBeenCalledWith( 'fields' );

		expect( controller.listenTo ).toHaveBeenCalledWith(
			expect.any( Object ),
			'validate:field',
			controller.updateHcaptcha
		);

		expect( controller.listenTo ).toHaveBeenCalledWith(
			expect.any( Object ),
			'change:modelValue',
			controller.updateHcaptcha
		);
	} );

	test( 'updateHcaptcha adds error if value is empty', () => {
		const model = {
			get: jest.fn( () => '' ),
			set: jest.fn(),
		};

		controller.updateHcaptcha( model );

		expect( nfRadio.channel( 'fields' ).request ).not.toHaveBeenCalled();
	} );

	test( 'updateHcaptcha removes error if value is set', () => {
		const model = {
			get: ( key ) => ( key === 'type' ? 'hcaptcha-for-ninja-forms' : 'some-value' ),
			set: jest.fn(),
		};

		controller.updateHcaptcha( model );

		expect( nfRadio.channel( 'fields' ).request ).toHaveBeenCalledWith(
			'remove:error',
			expect.anything(),
			'required-error'
		);
	} );
} );
