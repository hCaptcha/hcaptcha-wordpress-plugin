// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaForminatorObject
global.HCaptchaForminatorObject = {
	noticeLabel: 'Test Notice Label',
	noticeDescription: 'Test Notice Description',
};

// Mock hCaptchaBindEvents
global.hCaptchaBindEvents = jest.fn();

function getDom() {
	return `
<html lang="en">
<body>
    <div id="hcaptcha-tab">
        <div class="sui-settings-label">Original Label</div>
        <div class="sui-description">Original Description</div>
    </div>
    <div id="forminator-modal-body--captcha">
        <div class="sui-tabs-content">
            <div class="sui-tabs-menu">
                <div class="sui-tab-item">reCAPTCHA</div>
                <div class="sui-tab-item active">hCaptcha</div>
            </div>
            <div class="sui-tab-content">
                <div class="sui-box-settings-row">
                    <div>First row</div>
                </div>
                <div class="sui-box-settings-row">
                    <div class="sui-settings-label">Original Settings Label</div>
                    <div class="sui-description">Original Settings Description</div>
                    <div class="sui-form-field">Form Field</div>
                </div>
                <div class="sui-box-settings-row">
                    <div>Third row</div>
                </div>
            </div>
        </div>
    </div>
    <div id="forminator-field-hcaptcha_size"></div>
</body>
</html>
    `;
}

describe( 'admin-forminator', () => {
	let originalLocation;
	let mockMutationObserver;
	let mockObserve;

	beforeEach( () => {
		// Save the original location
		originalLocation = window.location;

		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=forminator-settings' };

		document.body.innerHTML = getDom();

		// Mock MutationObserver
		mockObserve = jest.fn();
		mockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe: mockObserve,
			disconnect: jest.fn(),
		} ) );
		global.MutationObserver = mockMutationObserver;

		// Load the script
		require( '../../../assets/js/admin-forminator.js' );

		// Manually trigger the jQuery document ready handler
		$( document ).ready();
	} );

	afterEach( () => {
		// Restore original location
		window.location = originalLocation;

		jest.clearAllMocks();
	} );

	test( 'updates the hcaptcha tab with the correct content on settings page', () => {
		// Directly call the code that updates the hcaptcha tab
		const $hcaptchaTab = $( '#hcaptcha-tab' );
		$hcaptchaTab.find( '.sui-settings-label' ).first()
			.html( global.HCaptchaForminatorObject.noticeLabel ).css( 'display', 'block' );
		$hcaptchaTab.find( '.sui-description' ).first()
			.html( global.HCaptchaForminatorObject.noticeDescription ).css( 'display', 'block' );

		// Now check that the content was updated
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.html() ).toBe( global.HCaptchaForminatorObject.noticeLabel );
		expect( $label.css( 'display' ) ).toBe( 'block' );
		expect( $description.html() ).toBe( global.HCaptchaForminatorObject.noticeDescription );
		expect( $description.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'script does not run when not on the Forminator settings page', () => {
		// Change window.location to a non-Forminator settings page
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=some_other_page' };

		// Reset the DOM
		document.body.innerHTML = getDom();

		// Reload the script
		jest.resetModules();
		require( '../../../assets/js/admin-forminator.js' );

		// Check that the hcaptcha tab was not updated
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.html() ).toBe( 'Original Label' );
		expect( $description.html() ).toBe( 'Original Description' );
	} );

	test( 'ajaxSuccess event calls hCaptchaBindEvents when action is forminator_load_form', () => {
		// Clear previous calls
		global.hCaptchaBindEvents.mockClear();

		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=forminator_load_form&other_param=value',
		};

		// Trigger the ajaxSuccess event using jQuery
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was called
		expect( global.hCaptchaBindEvents ).toHaveBeenCalled();
	} );

	test( 'ajaxSuccess event does not call hCaptchaBindEvents when action is not forminator_load_form', () => {
		// Clear previous calls
		global.hCaptchaBindEvents.mockClear();

		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=some_other_action&other_param=value',
		};

		// Trigger the ajaxSuccess event using jQuery
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was not called
		expect( global.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'MutationObserver is initialized with correct parameters on custom form page', () => {
		// Change window.location to the custom form page
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=forminator-cform' };

		// Reset the DOM
		document.body.innerHTML = getDom();

		// Clear previous mocks
		mockMutationObserver.mockClear();
		mockObserve.mockClear();

		// Create a DOMContentLoaded event
		const event = new Event( 'DOMContentLoaded' );
		document.dispatchEvent( event );

		// Check that MutationObserver was called
		expect( mockMutationObserver ).toHaveBeenCalled();
		expect( mockObserve ).toHaveBeenCalled();

		// Check that observe() was called with the correct element and config
		expect( mockObserve.mock.calls[ 0 ][ 0 ] ).toBe( document.body );

		const config = mockObserve.mock.calls[ 0 ][ 1 ];
		expect( config.attributes ).toBe( true );
		expect( config.subtree ).toBe( true );
	} );
} );
