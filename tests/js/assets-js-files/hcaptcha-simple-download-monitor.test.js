// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'hCaptcha Simple Download Monitor', () => {
	beforeEach( () => {
		jest.resetModules();
		window.history.pushState( {}, '', '/' );
		document.body.innerHTML = `
			<div class="sdm_download_item">
				<input id="hcaptcha_simple_download_monitor_nonce" value="nonce-value">
				<textarea name="h-captcha-response">response-token</textarea>
				<a class="sdm_download" href="#download">Download</a>
			</div>
		`;

		require( '../../../assets/js/hcaptcha-simple-download-monitor.js' );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'adds hCaptcha fields to the download URL and prevents default navigation', () => {
		const event = $.Event( 'click' );

		$( 'a.sdm_download' ).trigger( event );

		expect( event.isDefaultPrevented() ).toBe( true );
		expect( window.location.href ).toContain( '#download' );
		expect( window.location.href ).toContain( 'hcaptcha_simple_download_monitor_nonce=nonce-value' );
		expect( window.location.href ).toContain( 'h-captcha-response=response-token' );
	} );
} );
