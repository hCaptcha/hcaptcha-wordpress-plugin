// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Passster', () => {
	let helperMock;
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;

	function loadPassster() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );
		window.wp = {
			hooks: {
				addFilter: jest.fn(),
			},
		};
		global.wp = window.wp;
		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		require( '../../../assets/js/hcaptcha-passster.js' );
	}

	beforeEach( () => {
		document.body.innerHTML = `
			<form id="area-form"><div data-area="secret"></div></form>
		`;
		loadPassster();
	} );

	afterEach( () => {
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.wp;
		delete global.wp;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'marks Passster submit buttons as ajax submit buttons', () => {
		const callback = window.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];
		const submitButton = document.createElement( 'button' );
		const otherButton = document.createElement( 'button' );

		submitButton.classList.add( 'passster-submit' );

		expect( callback( false, submitButton ) ).toBe( true );
		expect( callback( false, otherButton ) ).toBe( false );
		expect( callback( true, otherButton ) ).toBe( true );
	} );

	test( 'ignores non-string ajax data and delegates empty query data to helper', () => {
		const emptyOptions = {};

		ajaxPrefilterCallback( emptyOptions );
		ajaxPrefilterCallback( { data: { area: 'secret' } } );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledTimes( 1 );
		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			emptyOptions,
			'validate_input',
			'hcaptcha_passster_nonce',
			expect.any( Object ),
		);
	} );

	test( 'adds hCaptcha data for the Passster area form', () => {
		const options = {
			data: 'action=validate_input&area=secret',
		};

		ajaxPrefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'validate_input',
			'hcaptcha_passster_nonce',
			expect.objectContaining( {
				0: document.getElementById( 'area-form' ),
				length: 1,
			} ),
		);
	} );
} );
