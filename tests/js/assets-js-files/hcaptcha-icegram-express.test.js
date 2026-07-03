// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Icegram Express', () => {
	beforeEach( () => {
		jest.resetModules();
		window.HCaptchaIcegramExpressObject = {
			hCaptchaWidgets: JSON.stringify( {
				10: '<div class="h-captcha">Captcha</div>',
			} ),
		};
		global.HCaptchaIcegramExpressObject = window.HCaptchaIcegramExpressObject;
		delete window.hCaptchaIcegramExpress;
	} );

	afterEach( () => {
		$( window ).off( 'init.icegram' );
		delete window.HCaptchaIcegramExpressObject;
		delete global.HCaptchaIcegramExpressObject;
		delete window.hCaptchaIcegramExpress;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'initializes immediately when Icegram container already exists', () => {
		document.body.innerHTML = `
			<div id="icegram_messages_container"></div>
			<div class="es_form_container" data-form-id="10">
				<div class="ig_form_els_last"></div>
			</div>
			<div class="es_form_container" data-form-id="20">
				<div class="ig_form_els_last"></div>
			</div>
			<div class="es_form_container" data-form-id="30"></div>
		`;

		require( '../../../assets/js/hcaptcha-icegram-express.js' );

		expect( document.querySelectorAll( '.h-captcha' ) ).toHaveLength( 1 );
		expect( document.querySelectorAll( '.ig_clear_fix' ) ).toHaveLength( 2 );
		expect( document.querySelector( '[data-form-id="20"] .h-captcha' ) ).toBeNull();
	} );

	test( 'waits for init.icegram when Icegram container is not ready yet', () => {
		document.body.innerHTML = `
			<div class="es_form_container" data-form-id="10">
				<div class="ig_form_els_last"></div>
			</div>
		`;

		require( '../../../assets/js/hcaptcha-icegram-express.js' );
		expect( document.querySelector( '.h-captcha' ) ).toBeNull();

		$( window ).trigger( 'init.icegram' );

		expect( document.querySelector( '.h-captcha' ) ).not.toBeNull();
	} );

	test( 'reuses existing app object', () => {
		const existingApp = {
			init: jest.fn(),
		};

		window.hCaptchaIcegramExpress = existingApp;

		require( '../../../assets/js/hcaptcha-icegram-express.js' );

		expect( window.hCaptchaIcegramExpress ).toBe( existingApp );
		expect( existingApp.init ).toHaveBeenCalledTimes( 1 );
	} );
} );
