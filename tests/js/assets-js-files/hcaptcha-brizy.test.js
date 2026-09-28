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
				'hcaptcha-widget-id': 'widget-id',
				hcaptcha_brizy_nonce: 'nonce-value',
				hcap_hp_dynamic: '',
				hcap_hp_sig: 'honeypot-signature',
				hcap_fst_token: 'fst-token',
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
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		require( '../../../assets/js/hcaptcha-brizy.js' );
	}

	beforeEach( () => {
		ajaxPrefilterCallback = null;
		loadBrizy();
	} );

	afterEach( () => {
		$( document ).off( 'ajaxComplete' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		document.body.innerHTML = '';
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
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

	test( 'adds hCaptcha, honeypot, and FST data to Brizy form data', () => {
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
				{
					name: 'hcap_hp_dynamic',
					value: '',
					required: false,
				},
				{
					name: 'hcap_hp_sig',
					value: 'honeypot-signature',
					required: false,
				},
				{
					name: 'hcap_fst_token',
					value: 'fst-token',
					required: false,
				},
			],
		);
	} );

	test( 'updates an hCaptcha field already serialized by Brizy', () => {
		const data = new FormData();

		data.set(
			'data',
			JSON.stringify(
				[
					{
						name: 'hcap_fst_token',
						value: 'stale-token',
						required: false,
					},
				],
			),
		);

		ajaxPrefilterCallback( {
			url: 'https://test.test/wp-admin/admin-ajax.php?action=brizy_submit_form',
			data,
		} );

		const fields = JSON.parse( data.get( 'data' ) );
		const fstFields = fields.filter( ( field ) => field.name === 'hcap_fst_token' );

		expect( fstFields ).toEqual(
			[
				{
					name: 'hcap_fst_token',
					value: 'fst-token',
					required: false,
				},
			],
		);
	} );

	test( 'refreshes hCaptcha and FST after a Brizy AJAX request', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, {
			url: 'https://test.test/wp-admin/admin-ajax.php?action=brizy_submit_form',
		} ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unrelated AJAX requests', () => {
		$( document ).trigger( 'ajaxComplete', [ {}, {
			url: 'https://test.test/wp-admin/admin-ajax.php?action=other_action',
		} ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );

	test( 'refreshes hCaptcha when FST is disabled', () => {
		delete window.hCaptchaFST;

		expect( () => {
			$( document ).trigger( 'ajaxComplete', [ {}, {
				url: 'https://test.test/wp-admin/admin-ajax.php?action=brizy_submit_form',
			} ] );
		} ).not.toThrow();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
