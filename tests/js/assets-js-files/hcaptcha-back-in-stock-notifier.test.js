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
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		document.body.innerHTML = `
			<form class="cwginstock-subscribe-form">
				<input class="cwg-product-id" name="cwg-product-id" value="123">
				<textarea name="h-captcha-response">response</textarea>
				<textarea name="g-recaptcha-response">response</textarea>
			</form>
		`;
	} );

	afterEach( () => {
		$( document ).off( 'ajaxSuccess cwginstock_success_ajax cwginstock_error_ajax' );
		$.ajaxPrefilter = originalAjaxPrefilter;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
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

	test.each( [ 'cwginstock_success_ajax', 'cwginstock_error_ajax' ] )(
		'resets hCaptcha and refreshes FST after %s',
		( eventName ) => {
			loadNotifier();

			$( document ).trigger( eventName, [ { product_id: 123 } ] );

			const form = document.querySelector( '.cwginstock-subscribe-form' );

			expect( window.hCaptchaReset ).toHaveBeenCalledWith( form );
			expect( form.querySelector( '[name="h-captcha-response"]' ).value ).toBe( '' );
			expect( form.querySelector( '[name="g-recaptcha-response"]' ).value ).toBe( '' );
			expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
		},
	);

	test( 'does not refresh tokens when the submitted product form is missing', () => {
		loadNotifier();

		$( document ).trigger( 'cwginstock_error_ajax', [ { product_id: 456 } ] );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
