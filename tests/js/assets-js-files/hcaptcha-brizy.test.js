// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Brizy', () => {
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;
	let helperMock;

	function loadBrizy() {
		jest.resetModules();
		helperMock = {
			getHCaptchaData: jest.fn( () => ( {
				'h-captcha-response': 'response-token',
				hcaptcha_brizy_nonce: 'nonce-value',
			} ) ),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		document.body.innerHTML = '<form class="brz-form"></form>';

		require( '../../../assets/js/hcaptcha-brizy.js' );
	}

	beforeEach( () => {
		ajaxPrefilterCallback = null;
		loadBrizy();
	} );

	afterEach( () => {
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'ignores non-Brizy ajax actions', () => {
		const data = new FormData();

		data.set( 'data', JSON.stringify( [] ) );

		ajaxPrefilterCallback( {
			url: 'https://test.test/wp-admin/admin-ajax.php?action=other_action',
			data,
		} );

		expect( helperMock.getHCaptchaData ).not.toHaveBeenCalled();
		expect( data.get( 'data' ) ).toBe( '[]' );
	} );

	test( 'adds hCaptcha response and nonce to Brizy form data', () => {
		const data = new FormData();

		data.set(
			'data',
			JSON.stringify(
				[
					{
						name: 'email',
						value: 'person@test.test',
						required: true,
					},
				],
			),
		);

		ajaxPrefilterCallback( {
			url: 'https://test.test/wp-admin/admin-ajax.php?action=brizy_submit_form',
			data,
		} );

		const fields = JSON.parse( data.get( 'data' ) );

		expect( helperMock.getHCaptchaData ).toHaveBeenCalledWith(
			expect.objectContaining( {
				length: 1,
			} ),
			'hcaptcha_brizy_nonce',
		);
		expect( fields ).toEqual(
			[
				{
					name: 'email',
					value: 'person@test.test',
					required: true,
				},
				{
					name: 'h-captcha-response',
					value: 'response-token',
					required: false,
				},
				{
					name: 'hcaptcha_brizy_nonce',
					value: 'nonce-value',
					required: false,
				},
			],
		);
	} );
} );
