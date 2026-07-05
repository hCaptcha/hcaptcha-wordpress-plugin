// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Back In Stock Notifier', () => {
	let ajaxPrefilterCallback;
	let originalAjaxPrefilter;
	let helperMock;

	function loadNotifier() {
		jest.resetModules();
		helperMock = {
			addHCaptchaData: jest.fn(),
		};
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		originalAjaxPrefilter = $.ajaxPrefilter;
		$.ajaxPrefilter = jest.fn( ( callback ) => {
			ajaxPrefilterCallback = callback;
		} );

		require( '../../../assets/js/hcaptcha-back-in-stock-notifier.js' );
	}

	beforeEach( () => {
		ajaxPrefilterCallback = null;
		window.hCaptchaBindEvents = jest.fn();
		document.body.innerHTML = `
			<form class="cwginstock-subscribe-form"></form>
			<input name="cwg-product-id" value="123">
		`;
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaBindEvents;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'adds hCaptcha data to stock subscribe ajax requests', () => {
		loadNotifier();

		const options = {
			data: 'action=cwginstock_product_subscribe',
		};

		ajaxPrefilterCallback( options );

		expect( helperMock.addHCaptchaData ).toHaveBeenCalledWith(
			options,
			'cwginstock_product_subscribe',
			'hcaptcha_back_in_stock_notifier_nonce',
			expect.objectContaining( {
				length: 1,
			} ),
		);
	} );

	test( 'ignores unrelated ajax success actions', () => {
		loadNotifier();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=other_action&product_id=123' } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'ignores popup ajax success when product input is missing', () => {
		loadNotifier();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=cwg_trigger_popup_ajax&product_id=456' } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'rebinds hCaptcha when popup ajax success belongs to existing product input', () => {
		loadNotifier();

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: 'action=cwg_trigger_popup_ajax&product_id=123' } ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
