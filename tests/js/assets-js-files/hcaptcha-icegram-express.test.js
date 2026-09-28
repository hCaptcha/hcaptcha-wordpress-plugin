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
		window.hCaptchaBindEvents = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};
		delete window.hCaptchaIcegramExpress;
	} );

	afterEach( () => {
		$( window ).off( 'init.icegram' );
		$( window ).off( 'es.send_response' );
		delete window.HCaptchaIcegramExpressObject;
		delete global.HCaptchaIcegramExpressObject;
		delete window.hCaptchaIcegramExpress;
		delete window.hCaptchaBindEvents;
		delete window.hCaptchaFST;
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

	test( 'refreshes hCaptcha and FST after a protected subscription response', () => {
		document.body.innerHTML = '<form class="es_ajax_subscription_form"><div class="h-captcha"></div></form>';
		require( '../../../assets/js/hcaptcha-icegram-express.js' );

		$( window ).trigger( 'es.send_response', [ $( 'form' ), { status: 'ERROR' } ] );

		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ignores unprotected subscription responses', () => {
		document.body.innerHTML = '<form class="es_ajax_subscription_form"></form>';
		require( '../../../assets/js/hcaptcha-icegram-express.js' );

		$( window ).trigger( 'es.send_response', [ $( 'form' ), { status: 'ERROR' } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );

	test( 'refreshes hCaptcha when FST is disabled', () => {
		document.body.innerHTML = '<form class="es_ajax_subscription_form"><div class="h-captcha"></div></form>';
		delete window.hCaptchaFST;
		require( '../../../assets/js/hcaptcha-icegram-express.js' );

		expect( () => {
			$( window ).trigger( 'es.send_response', [ $( 'form' ), { status: 'ERROR' } ] );
		} ).not.toThrow();
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );
} );
