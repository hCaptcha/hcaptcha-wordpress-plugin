// noinspection JSUnresolvedReference

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha MetForm', () => {
	let helperMock;

	function loadMetForm() {
		jest.resetModules();

		helperMock = {
			getHCaptchaData: jest.fn( () => ( {
				'h-captcha-response': 'response-token',
				'hcaptcha-widget-id': 'widget-id',
				hcaptcha_metform_nonce: 'nonce-value',
			} ) ),
			installFetchEvents: jest.fn(),
		};

		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: helperMock,
		} ) );

		window.hCaptchaBindEvents = jest.fn();
		const hcaptchaHtml = '<h-captcha class="h-captcha"></h-captcha><input name="hcaptcha_metform_nonce">';
		const encodedHtml = window.encodeURIComponent( hcaptchaHtml );

		document.body.innerHTML = `
			<div class="mf-form-wrapper" data-form-id="123">
				<form class="metform-form-content">
					<div class="hcaptcha-metform-placeholder" data-hcaptcha-html="${ encodedHtml }"></div>
				</form>
			</div>
		`;

		require( '../../../assets/js/hcaptcha-metform.js' );
	}

	beforeEach( () => {
		loadMetForm();
	} );

	afterEach( () => {
		$( document ).off( '.hcaptchaMetForm' );
		window.removeEventListener( 'hCaptchaFetch:complete', window.hCaptchaMetForm.fetchComplete );
		delete window.hCaptchaMetForm;
		delete window.hCaptchaBindEvents;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'renders and binds hCaptcha on initialization and after MetForm renders', () => {
		expect( helperMock.installFetchEvents ).toHaveBeenCalledTimes( 1 );
		expect( document.querySelectorAll( '.hcaptcha-metform-placeholder h-captcha' ) ).toHaveLength( 1 );
		expect( document.querySelector( '.hcaptcha-metform-placeholder' ).hasAttribute( 'data-hcaptcha-html' ) ).toBe( false );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		$( '.metform-form-content' ).trigger( 'metform/after_form_load' );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'adds hCaptcha fields before MetForm submission', () => {
		const formData = {
			email: 'person@example.com',
		};
		const wrapper = document.querySelector( '.mf-form-wrapper' );

		$( wrapper ).trigger( 'metform/before_submit', [ formData ] );

		expect( helperMock.getHCaptchaData ).toHaveBeenCalledWith( wrapper, 'hcaptcha_metform_nonce' );
		expect( formData ).toEqual( {
			email: 'person@example.com',
			'h-captcha-response': 'response-token',
			'hcaptcha-widget-id': 'widget-id',
			hcaptcha_metform_nonce: 'nonce-value',
		} );
	} );

	test( 'ignores an invalid MetForm payload', () => {
		$( '.mf-form-wrapper' ).trigger( 'metform/before_submit' );

		expect( helperMock.getHCaptchaData ).not.toHaveBeenCalled();
	} );

	test( 'binds hCaptcha again after a MetForm request completes', () => {
		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ '/wp-json/metform/v1/entries/insert/123' ],
				},
			} ),
		);

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );

		window.dispatchEvent(
			new CustomEvent( 'hCaptchaFetch:complete', {
				detail: {
					args: [ { url: '/wp-json/another/v1/route' } ],
				},
			} ),
		);

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
	} );
} );
