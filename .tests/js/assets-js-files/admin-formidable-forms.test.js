// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaFormidableFormsObject
global.HCaptchaFormidableFormsObject = {
	noticeLabel: 'Test Notice Label',
	noticeDescription: 'Test Notice Description',
};

function getDom() {
	// language=HTML
	return `
		<div id="hcaptcha_settings">
			<p class="howto">Original howto text</p>
			<input type="text" value="test-input"/>
		</div>
	`;
}

describe( 'admin-formidable-forms', () => {
	beforeEach( () => {
		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaFormidableForms
		window.hCaptchaFormidableForms = undefined;

		// Force reloading the tested file.
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-formidable-forms.js' );
	} );

	test( 'updates elements on the Formidable Forms settings page', () => {
		// Mock window.location.href
		window.hCaptchaFormidableForms.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=formidable-settings';

		// Simulate jQuery.ready event
		window.hCaptchaFormidableForms.ready();

		const $howTos = $( '#hcaptcha_settings .howto' );
		const $howTo = $howTos.first();
		const $input = $( '#hcaptcha_settings input' );

		expect( $howTo.html() ).toBe( global.HCaptchaFormidableFormsObject.noticeLabel );
		expect( $howTos.length ).toBe( 2 );
		expect( $howTos.eq( 1 ).html() ).toBe( global.HCaptchaFormidableFormsObject.noticeDescription );
		expect( $input.attr( 'disabled' ) ).toBe( 'disabled' );
		expect( $input.attr( 'class' ) ).toBe( 'frm_noallow' );
	} );

	test( 'does not updates elements not on the Formidable Forms settings page', () => {
		// Mock window.location.href
		window.hCaptchaFormidableForms.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=some';

		// Simulate jQuery.ready event
		window.hCaptchaFormidableForms.ready();

		// Check that the howto element was not updated
		const $howTo = $( '#hcaptcha_settings .howto' );
		expect( $howTo.html() ).toBe( 'Original howto text' );

		// Check that there's still only one howto element
		expect( $howTo.length ).toBe( 1 );

		// Check that the input was not disabled
		const $input = $( '#hcaptcha_settings input' );
		expect( $input.attr( 'disabled' ) ).toBeUndefined();
	} );

	test( 'getLocationHref returns the correct location', () => {
		expect( window.hCaptchaFormidableForms.getLocationHref() ).toBe( 'http://domain.tld/' );
	} );
} );
