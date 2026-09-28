// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Simple Download Monitor', () => {
	let getDownloadUrl;
	let pageshowHandler;

	beforeEach( () => {
		jest.resetModules();
		window.history.pushState( {}, '', '/' );
		document.body.innerHTML = `
			<div class="sdm_download_item">
				<input id="hcaptcha_simple_download_monitor_nonce" name="hcaptcha_simple_download_monitor_nonce" value="nonce-value">
				<textarea name="h-captcha-response">response-token</textarea>
				<input name="hcaptcha-widget-id" value="widget-id">
				<input id="hcap_hp_dynamic" name="hcap_hp_dynamic" value="">
				<input name="hcap_hp_sig" value="honeypot-signature">
				<input name="hcap_fst_token" value="fst-token">
				<a class="sdm_download" href="/?sdm_process_download=1#download"><span>Download</span></a>
			</div>
		`;
		window.hCaptchaReset = jest.fn();
		window.hCaptchaFST = {
			getToken: jest.fn(),
		};

		( { getDownloadUrl, pageshowHandler } = require( '../../../assets/js/hcaptcha-simple-download-monitor.js' ) );
	} );

	afterEach( () => {
		window.removeEventListener( 'pageshow', pageshowHandler );
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'adds all hCaptcha fields to the download URL query', () => {
		const href = getDownloadUrl(
			$( '.sdm_download_item' ),
			document.querySelector( 'a.sdm_download' ).href,
		);
		const url = new URL( href );

		expect( url.hash ).toBe( '#download' );
		expect( url.searchParams.get( 'sdm_process_download' ) ).toBe( '1' );
		expect( url.searchParams.get( 'hcaptcha_simple_download_monitor_nonce' ) ).toBe( 'nonce-value' );
		expect( url.searchParams.get( 'h-captcha-response' ) ).toBe( 'response-token' );
		expect( url.searchParams.get( 'hcaptcha-widget-id' ) ).toBe( 'widget-id' );
		expect( url.searchParams.get( 'hcap_hp_dynamic' ) ).toBe( '' );
		expect( url.searchParams.get( 'hcap_hp_sig' ) ).toBe( 'honeypot-signature' );
		expect( url.searchParams.get( 'hcap_fst_token' ) ).toBe( 'fst-token' );
	} );

	test( 'refreshes hCaptcha and FST after returning from the browser cache', () => {
		const event = new Event( 'pageshow' );
		Object.defineProperty( event, 'persisted', { value: true } );

		window.dispatchEvent( event );

		const item = document.querySelector( '.sdm_download_item' );

		expect( window.hCaptchaReset ).toHaveBeenCalledWith( item );
		expect( item.querySelector( 'textarea[name="h-captcha-response"]' ).value ).toBe( '' );
		expect( window.hCaptchaFST.getToken ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'does not refresh tokens on the initial page load', () => {
		window.dispatchEvent( new Event( 'pageshow' ) );

		expect( window.hCaptchaReset ).not.toHaveBeenCalled();
		expect( window.hCaptchaFST.getToken ).not.toHaveBeenCalled();
	} );
} );
