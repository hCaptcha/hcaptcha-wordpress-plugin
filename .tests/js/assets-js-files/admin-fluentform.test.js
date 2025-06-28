// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaFluentFormObject
global.HCaptchaFluentFormObject = {
	noticeLabel: 'Test Notice Label',
	noticeDescription: 'Test Notice Description',
};

function getDom() {
	// language=HTML
	return `
		<div id="ff_global_settings_option_app">
			<div class="ff_hcaptcha_wrap">
				<div class="ff_card_head">
					<h5 style="display: none;"></h5>
					<p style="display: none;"></p>
				</div>
			</div>
		</div>
	`;
}

describe( 'admin-fluentform', () => {
	const hCaptchaWrapSelector = '.ff_hcaptcha_wrap';

	beforeEach( () => {
		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaFluentForm
		window.hCaptchaFluentForm = undefined;

		// Force reloading the tested file.
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-fluentform.js' );
	} );

	test( 'updateHCaptchaWrap works on the admin page', async () => {
		// Mock window.location.href
		window.hCaptchaFluentForm.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=fluent_forms_settings';

		// Simulate jQuery.ready event
		window.hCaptchaFluentForm.ready();

		// Check that the hCaptcha wrap was updated with the correct content
		let $hCaptchaWrap = $( hCaptchaWrapSelector );
		let $h5 = $hCaptchaWrap.find( '.ff_card_head h5' );
		let $p = $hCaptchaWrap.find( '.ff_card_head p' ).first();

		expect( $h5.html() ).toBe( global.HCaptchaFluentFormObject.noticeLabel );
		expect( $h5.css( 'display' ) ).toBe( 'block' );
		expect( $p.html() ).toBe( global.HCaptchaFluentFormObject.noticeDescription );
		expect( $p.css( 'display' ) ).toBe( 'block' );

		// Reset hCaptcha wrap.
		$h5.html( '' ).css( 'display', 'none' );
		$p.html( '' ).css( 'display', 'none' );

		// Remove and add hCaptcha wrap to run MutationObserver
		const outerHTML = $hCaptchaWrap.prop( 'outerHTML' );
		$hCaptchaWrap.remove();
		$( '#ff_global_settings_option_app' ).html( outerHTML );

		// Wait for the next microtask queue to allow MutationObserver to fire
		await Promise.resolve();

		// Check that the hCaptcha wrap was updated with the correct content
		$hCaptchaWrap = $( hCaptchaWrapSelector );
		$h5 = $hCaptchaWrap.find( '.ff_card_head h5' );
		$p = $hCaptchaWrap.find( '.ff_card_head p' ).first();

		expect( $h5.html() ).toBe( global.HCaptchaFluentFormObject.noticeLabel );
		expect( $h5.css( 'display' ) ).toBe( 'block' );
		expect( $p.html() ).toBe( global.HCaptchaFluentFormObject.noticeDescription );
		expect( $p.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'updateHCaptchaWrap does not works on the admin page', () => {
		// Mock window.location.href
		window.hCaptchaFluentForm.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=some';

		// Simulate jQuery.ready event
		window.hCaptchaFluentForm.ready();

		// Check that the hCaptcha wrap was updated with the correct content
		const $hCaptchaWrap = $( hCaptchaWrapSelector );
		const $h5 = $hCaptchaWrap.find( '.ff_card_head h5' );
		const $p = $hCaptchaWrap.find( '.ff_card_head p' ).first();

		expect( $h5.html() ).toBe( '' );
		expect( $h5.css( 'display' ) ).toBe( 'none' );
		expect( $p.html() ).toBe( '' );
		expect( $p.css( 'display' ) ).toBe( 'none' );
	} );

	test( 'getLocationHref returns the correct location', () => {
		expect( window.hCaptchaFluentForm.getLocationHref() ).toBe( 'http://domain.tld/' );
	} );
} );
