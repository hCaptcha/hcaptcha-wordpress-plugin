// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'integration fallback behavior', () => {
	let originalAjaxPrefilter;
	let originalFetch;
	let originalRequest;

	beforeEach( () => {
		jest.resetModules();
		originalAjaxPrefilter = $.ajaxPrefilter;
		originalFetch = window.fetch;
		originalRequest = global.Request;
		document.body.innerHTML = '';
		delete window.hCaptchaReset;
		delete window.hCaptchaFST;
		delete window.hCaptchaBindEvents;
	} );

	afterEach( () => {
		$( document ).off();
		$( document.body ).off();
		$.ajaxPrefilter = originalAjaxPrefilter;
		window.fetch = originalFetch;
		global.Request = originalRequest;
		delete window.wp;
		delete global.wp;
		delete window.hCaptchaCustomerReviews;
		delete window.hCaptchaEasyDigitalDownloads;
		delete window.hCaptchaFST;
		delete window.hCaptchaLearnPress;
		delete window.hCaptchaMetForm;
		delete window.hCaptchaThemeMyLogin;
		delete window.hCaptchaReset;
		delete window.hCaptchaBindEvents;
		jest.dontMock( '../../../assets/js/hcaptcha-helper.js' );
		jest.restoreAllMocks();
	} );

	test( 'Back In Stock ignores incomplete events and clears a matching form without optional APIs', () => {
		document.body.innerHTML = `
			<form class="cwginstock-subscribe-form">
				<input class="cwg-product-id" value="42">
				<textarea name="h-captcha-response">old-token</textarea>
			</form>
		`;
		$.ajaxPrefilter = jest.fn();
		require( '../../../assets/js/hcaptcha-back-in-stock-notifier.js' );

		$( document ).trigger( 'cwginstock_success_ajax', [ {} ] );
		expect( document.querySelector( 'textarea' ).value ).toBe( 'old-token' );

		$( document ).trigger( 'cwginstock_success_ajax', [ { product_id: 42 } ] );
		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'Classified Listing ignores unsupported data and missing forms before clearing a real form', () => {
		require( '../../../assets/js/hcaptcha-classified-listing.js' );
		const data = new FormData();
		data.set( 'action', 'rtcl_login_request' );

		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=rtcl_login_request' } ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data } ] );
		document.body.innerHTML = '<form id="rtcl-login-form"><textarea name="h-captcha-response">old</textarea></form>';
		$( document ).trigger( 'ajaxComplete', [ {}, { data } ] );

		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'Customer Reviews leaves out the FST parameter when no token field exists', () => {
		global.wp = { hooks: { addFilter: jest.fn() } };
		$.ajaxPrefilter = jest.fn();
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: { addHCaptchaData: jest.fn() },
		} ) );
		require( '../../../assets/js/hcaptcha-customer-reviews.js' );
		window.hCaptchaCustomerReviews.ready();
		const callback = $.ajaxPrefilter.mock.calls[ 0 ][ 0 ];
		const options = { data: 'action=cr_submit_review' };

		callback( options );

		expect( new URLSearchParams( options.data ).has( 'hcap_fst_token' ) ).toBe( false );
	} );

	test( 'Easy Digital Downloads clears responses without a reset hook', () => {
		document.body.innerHTML = '<form id="edd_purchase_form"><textarea name="h-captcha-response">old</textarea></form>';
		require( '../../../assets/js/hcaptcha-easy-digital-downloads.js' );

		window.hCaptchaEasyDigitalDownloads.checkoutErrorHandler();

		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'FST falls back to an empty token when session storage is unavailable', async () => {
		jest.spyOn( Storage.prototype, 'getItem' ).mockImplementation( () => {
			throw new Error( 'Storage unavailable' );
		} );
		window.HCaptchaFSTObject = {
			ajaxUrl: 'http://domain.tld/wp-admin/admin-ajax.php',
			issueTokenAction: 'issue',
			issueTokenNonce: 'nonce',
			issueTokenContext: 'context',
			postId: '1',
		};
		window.fetch = jest.fn().mockResolvedValue( {
			ok: true,
			json: () => Promise.resolve( { success: true, data: { token: 'new-token' } } ),
		} );
		require( '../../../assets/js/hcaptcha-fst.js' );
		await window.hCaptchaFST.getToken();

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'HTML Forms clears a submitted form without reset or FST helpers', () => {
		document.body.innerHTML = '<form class="hf-form"><textarea name="h-captcha-response">old</textarea></form>';
		require( '../../../assets/js/hcaptcha-html-forms.js' );
		document.querySelector( 'form' ).dispatchEvent( new Event( 'hf-submitted', { bubbles: true } ) );

		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'LearnPress reads Request and URL inputs and tolerates missing optional helpers', () => {
		global.Request = class {
			constructor( url ) {
				this.url = url;
			}
		};
		require( '../../../assets/js/hcaptcha-learnpress.js' );
		const app = window.hCaptchaLearnPress;

		expect( app.getAction( new Request( 'http://domain.tld/?lp-ajax=checkout' ) ) ).toBe( 'checkout' );
		expect( app.getAction( new URL( 'http://domain.tld/' ) ) ).toBe( '' );
		expect( app.getAction( 'http://[invalid' ) ).toBe( '' );

		document.body.innerHTML = '<form id="learn-press-checkout-form"><textarea name="h-captcha-response">old</textarea></form>';
		app.resetForm();
		app.refreshFSTToken();
		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'MetForm handles missing bind API and fetch resources without a URL', () => {
		require( '../../../assets/js/hcaptcha-metform.js' );
		const app = window.hCaptchaMetForm;

		app.fetchComplete( { detail: { args: [ {} ] } } );
		expect( () => app.bindEvents() ).not.toThrow();
	} );

	test( 'Simple Download Monitor clears persisted responses and follows a download link', () => {
		document.body.innerHTML = `
			<div class="sdm_download_item">
				<textarea name="h-captcha-response">old</textarea>
				<a class="sdm_download" href="http://domain.tld/#download">Download</a>
			</div>
		`;
		jest.doMock( '../../../assets/js/hcaptcha-helper.js', () => ( {
			helper: { getHCaptchaData: () => ( {} ) },
		} ) );
		const { pageshowHandler } = require( '../../../assets/js/hcaptcha-simple-download-monitor.js' );
		pageshowHandler( { persisted: true } );
		expect( document.querySelector( 'textarea' ).value ).toBe( '' );

		$( 'a.sdm_download' ).trigger( 'click' );
		expect( window.location.hash ).toBe( '#download' );
	} );

	test( 'Support Candy handles missing AJAX actions and absent FST API', () => {
		global.wp = { hooks: { addFilter: jest.fn() } };
		require( '../../../assets/js/hcaptcha-support-candy.js' );
		document.body.innerHTML = '<div id="other">Other</div>';
		document.getElementById( 'other' ).dispatchEvent( new Event( 'click', { bubbles: true } ) );

		$( document ).trigger( 'ajaxSuccess', [ {}, { data: new FormData() } ] );
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: '' } ] );
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: {} } ] );
		$( document ).trigger( 'ajaxSuccess', [ {}, { data: null } ] );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=wpsc_set_ticket_form' } ] );

		expect( window.hCaptchaFST ).toBeUndefined();
	} );

	test( 'Theme My Login handles plain query data, malformed URLs and absent helpers', () => {
		require( '../../../assets/js/hcaptcha-theme-my-login.js' );
		const app = window.hCaptchaThemeMyLogin;
		const form = document.createElement( 'form' );
		form.innerHTML = '<textarea name="h-captcha-response">old</textarea>';

		expect( app.getParams( 'ajax=1' ).get( 'ajax' ) ).toBe( '1' );
		expect( app.getParams( '?ajax=1' ).get( 'ajax' ) ).toBe( '1' );
		expect( app.getForm( 'http://[invalid' ) ).toBeNull();
		app.resetForm( form );
		app.refreshFSTToken();
		expect( form.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'UsersWP clears a matching login form without optional APIs', () => {
		document.body.innerHTML = '<form class="uwp-login-form"><textarea name="h-captcha-response">old</textarea></form>';
		require( '../../../assets/js/hcaptcha-users-wp.js' );
		$( document ).trigger( 'ajaxComplete', [ {}, { data: 'action=uwp_ajax_login' } ] );

		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );

	test( 'WP Job Openings clears a form without optional APIs', () => {
		document.body.innerHTML = '<form class="awsm-application-form"><textarea name="h-captcha-response">old</textarea></form>';
		require( '../../../assets/js/hcaptcha-wp-job-openings.js' );
		$( '.awsm-application-form' ).trigger( 'awsmjobs_application_failed' );

		expect( document.querySelector( 'textarea' ).value ).toBe( '' );
	} );
} );
