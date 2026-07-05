// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

describe( 'admin-quform', () => {
	let readySpy;
	let originalMutationObserver;

	function loadQuform( url, html ) {
		window.history.pushState( {}, '', url );
		document.body.innerHTML = html;

		readySpy = jest.spyOn( $.fn, 'ready' ).mockImplementation( function( callback ) {
			callback( $ );

			return this;
		} );

		jest.resetModules();
		require( '../../../assets/js/admin-quform.js' );
	}

	beforeEach( () => {
		window.HCaptchaQuformObject = {
			noticeLabel: 'hCaptcha Notice',
			noticeDescription: 'Use hCaptcha settings from the plugin.',
		};
		global.HCaptchaQuformObject = window.HCaptchaQuformObject;

		originalMutationObserver = window.MutationObserver;
		window.MutationObserver = jest.fn( function( callback ) {
			this.callback = callback;
			this.observe = jest.fn();
		} );
		global.MutationObserver = window.MutationObserver;
	} );

	afterEach( () => {
		readySpy?.mockRestore();
		window.MutationObserver = originalMutationObserver;
		global.MutationObserver = originalMutationObserver;
		delete window.HCaptchaQuformObject;
		delete global.HCaptchaQuformObject;
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'updates hCaptcha settings copy and hides native settings on settings page', () => {
		loadQuform(
			'/wp-admin/admin.php?page=quform.settings',
			`
				<div class="qfb-settings-heading"><span class="qfb-icon qfb-icon-hand-paper-o"></span></div>
				<p>Original description.</p>
				<div class="qfb-setting" style="display:block">Size</div>
				<div class="qfb-setting" style="display:block">Theme</div>
			`,
		);

		expect( $( '.qfb-settings-heading' ).text() ).toContain( 'hCaptcha Notice' );
		expect( $( '.qfb-settings-heading' ).next( 'p' ).html() ).toBe( 'Use hCaptcha settings from the plugin.' );
		expect( $( '.qfb-setting' ).eq( 0 ).css( 'display' ) ).toBe( 'none' );
		expect( $( '.qfb-setting' ).eq( 1 ).css( 'display' ) ).toBe( 'none' );
		expect( window.MutationObserver ).not.toHaveBeenCalled();
	} );

	test( 'blocks Quform captcha settings when hCaptcha provider is selected', () => {
		loadQuform(
			'/wp-admin/admin.php?page=quform.forms',
			`
				<div class="qfb-setting">
					<select id="qfb_recaptcha_provider">
						<option value="recaptcha">reCAPTCHA</option>
						<option value="hcaptcha">hCaptcha</option>
					</select>
				</div>
				<div class="qfb-setting"><select id="qfb_recaptcha_size"></select></div>
				<div class="qfb-setting"><select id="qfb_recaptcha_theme"></select></div>
				<div class="qfb-setting"><select id="qfb_hcaptcha_lang"></select></div>
			`,
		);

		const provider = $( '#qfb_recaptcha_provider' );

		provider.val( 'hcaptcha' ).trigger( 'change' );

		expect( window.MutationObserver ).toHaveBeenCalled();
		expect( window.MutationObserver.mock.instances[ 0 ].observe ).toHaveBeenCalledWith(
			document.getElementById( 'qfb_recaptcha_provider' ).closest( '.qfb-setting' ),
			{
				attributes: true,
			},
		);
		expect( $( '#qfb_recaptcha_size' ).closest( '.qfb-setting' ).css( 'display' ) ).toBe( 'none' );
		expect( $( '#qfb_recaptcha_theme' ).closest( '.qfb-setting' ).css( 'display' ) ).toBe( 'none' );
		expect( $( '#qfb_hcaptcha_lang' ).closest( '.qfb-setting' ).css( 'display' ) ).toBe( 'none' );
		expect( $( '.hcaptcha-notice-label' ).text() ).toContain( 'hCaptcha Notice' );
		expect( $( '.hcaptcha-notice-description' ).html() ).toBe( 'Use hCaptcha settings from the plugin.' );

		provider.val( 'hcaptcha' ).trigger( 'change' );

		expect( $( '.hcaptcha-notice-label' ) ).toHaveLength( 1 );
		expect( $( '.hcaptcha-notice-description' ) ).toHaveLength( 1 );
	} );

	test( 'restores Quform captcha settings when another provider is selected', () => {
		loadQuform(
			'/wp-admin/admin.php?page=quform.forms',
			`
				<div class="qfb-setting">
					<select id="qfb_recaptcha_provider">
						<option value="recaptcha">reCAPTCHA</option>
						<option value="hcaptcha">hCaptcha</option>
					</select>
				</div>
				<div class="qfb-setting"><select id="qfb_recaptcha_size"></select></div>
				<div class="qfb-setting"><select id="qfb_recaptcha_theme"></select></div>
				<div class="qfb-setting"><select id="qfb_hcaptcha_lang"></select></div>
			`,
		);

		const provider = $( '#qfb_recaptcha_provider' );

		provider.val( 'hcaptcha' ).trigger( 'change' );
		provider.val( 'recaptcha' ).trigger( 'change' );

		expect( $( '#qfb_recaptcha_size' ).closest( '.qfb-setting' ).css( 'display' ) ).not.toBe( 'none' );
		expect( $( '#qfb_recaptcha_theme' ).closest( '.qfb-setting' ).css( 'display' ) ).not.toBe( 'none' );
		expect( $( '#qfb_hcaptcha_lang' ).closest( '.qfb-setting' ).css( 'display' ) ).not.toBe( 'none' );
		expect( $( '.hcaptcha-notice-label' ) ).toHaveLength( 0 );
		expect( $( '.hcaptcha-notice-description' ) ).toHaveLength( 0 );
	} );
} );
