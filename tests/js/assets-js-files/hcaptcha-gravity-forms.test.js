// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Gravity Forms frontend', () => {
	beforeEach( () => {
		jest.resetModules();
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-gravity-forms.js' );
	} );

	afterEach( () => {
		$( document ).off( 'gform_post_render' );
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha only for ajax-rendered Gravity Forms', () => {
		document.body.innerHTML = `
			<form id="gform_1"></form>
			<form id="gform_2" target="gform_ajax_frame_2"></form>
		`;

		$( document ).trigger( 'gform_post_render', [ 1 ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'gform_post_render', [ 2 ] );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'hCaptcha Gravity Forms null form branch', () => {
	afterEach( () => {
		global.jQuery = $;
		window.jQuery = $;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'returns when the form lookup fails', () => {
		jest.resetModules();
		const onMock = jest.fn();
		const jQueryStub = jest.fn( ( selector ) => {
			if ( selector === document ) {
				return {
					on: onMock,
				};
			}

			return null;
		} );

		global.jQuery = jQueryStub;
		window.jQuery = jQueryStub;
		window.hCaptchaBindEvents = jest.fn();

		require( '../../../assets/js/hcaptcha-gravity-forms.js' );

		const handler = onMock.mock.calls[ 0 ][ 1 ];

		expect( () => handler( {}, 10 ) ).not.toThrow();
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
