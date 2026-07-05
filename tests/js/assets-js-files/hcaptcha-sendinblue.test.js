// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Sendinblue', () => {
	let helperMock;

	function loadSendinblue() {
		jest.resetModules();
		helperMock = {
			checkAction: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.hCaptchaBindEvents = jest.fn();
		delete window.hCaptchaSendinblue;

		require( '../../../assets/js/hcaptcha-sendinblue.js' );
	}

	beforeEach( () => {
		loadSendinblue();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaSendinblue;
		delete window.hCaptchaBindEvents;
		jest.restoreAllMocks();
	} );

	test( 'rebinds hCaptcha after matching Sendinblue ajax complete only', () => {
		const settings = { data: 'sib_form_action=subscribe_form_submit' };

		helperMock.checkAction.mockReturnValueOnce( false ).mockReturnValueOnce( true );

		$( document ).trigger( 'ajaxComplete', [ {}, settings ] );
		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();

		$( document ).trigger( 'ajaxComplete', [ {}, settings ] );
		expect( helperMock.checkAction ).toHaveBeenCalledWith(
			settings,
			'sib_form_action',
			'subscribe_form_submit',
		);
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses existing app object', () => {
		jest.resetModules();
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaSendinblue = existingApp;

		require( '../../../assets/js/hcaptcha-sendinblue.js' );

		expect( window.hCaptchaSendinblue ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
