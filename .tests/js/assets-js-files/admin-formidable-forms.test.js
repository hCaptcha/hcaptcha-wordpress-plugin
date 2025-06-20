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
	return `
<html lang="en">
<body>
    <div id="hcaptcha_settings">
        <p class="howto">Original howto text</p>
        <input type="text" value="test-input" />
    </div>
</body>
</html>
    `;
}

describe( 'admin-formidable-forms', () => {
	let originalLocation;

	beforeEach( () => {
		// Save the original location
		originalLocation = window.location;

		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=formidable-settings' };

		document.body.innerHTML = getDom();

		// Load the script
		require( '../../../assets/js/admin-formidable-forms.js' );

		// Simulate jQuery.ready event
		window.hCaptchaFluentForm( $ );
	} );

	afterEach( () => {
		// Restore original location
		window.location = originalLocation;
	} );

	test( 'updates the howto element with the correct content', () => {
		const $howTo = $( '#hcaptcha_settings .howto' ).first();
		expect( $howTo.html() ).toBe( global.HCaptchaFormidableFormsObject.noticeLabel );
	} );

	test( 'inserts a new paragraph after the howto element', () => {
		const $howTos = $( '#hcaptcha_settings .howto' );
		expect( $howTos.length ).toBe( 2 );
		expect( $howTos.eq( 1 ).html() ).toBe( global.HCaptchaFormidableFormsObject.noticeDescription );
	} );

	test( 'disables inputs within hcaptcha_settings', () => {
		const $input = $( '#hcaptcha_settings input' );
		expect( $input.attr( 'disabled' ) ).toBe( 'disabled' );
		expect( $input.attr( 'class' ) ).toBe( 'frm_noallow' );
	} );

	test( 'script does not run when not on the Formidable Forms settings page', () => {
		// Change window.location to a non-Formidable Forms settings page
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=some_other_page' };

		// Reset the DOM
		document.body.innerHTML = getDom();

		// Reload the script
		jest.resetModules();
		require( '../../../assets/js/admin-formidable-forms.js' );

		// Simulate jQuery.ready event
		window.hCaptchaFluentForm( $ );

		// Check that the howto element was not updated
		const $howTo = $( '#hcaptcha_settings .howto' );
		expect( $howTo.html() ).toBe( 'Original howto text' );

		// Check that there's still only one howto element
		expect( $howTo.length ).toBe( 1 );

		// Check that the input was not disabled
		const $input = $( '#hcaptcha_settings input' );
		expect( $input.attr( 'disabled' ) ).toBeUndefined();
	} );
} );
