// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaForminatorObject
global.HCaptchaForminatorObject = {
	noticeLabel: 'Test Notice Label',
	noticeDescription: 'Test Notice Description',
};

function getDom() {
	// language=HTML
	return `
		<div id="hcaptcha-tab">
			<div class="sui-settings-label">Original Label</div>
			<div class="sui-description">Original Description</div>
		</div>
		<div id="forminator-modal-body--captcha">
			<div class="sui-tabs-content">
				<div class="sui-tab-content">
					<div class="sui-tabs-menu">
						<div class="sui-tab-item">reCAPTCHA</div>
						<div class="sui-tab-item active">hCaptcha</div>
					</div>
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
	`;
}

describe( 'admin-forminator', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		// Mock hCaptchaBindEvents
		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaForminator
		window.hCaptchaForminator = undefined;

		// Force reloading the tested file.
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-forminator.js' );
	} );

	afterEach( () => {
		// Restore hCaptchaBindEvents
		global.hCaptchaBindEvents.mockRestore();
	} );

	test( 'ajaxSuccess event handler calls hCaptchaBindEvents when action is forminator_load_form', () => {
		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=forminator_load_form&other_param=value',
		};

		// Trigger the ajaxSuccess event
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was called
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ajaxSuccess event handler does not call hCaptchaBindEvents when action is not forminator_load_form', () => {
		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=some_other_action&other_param=value',
		};

		// Trigger the ajaxSuccess event
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was not called
		expect( hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );

	test( 'forminator ready function updates the hCaptcha tab with the correct content', () => {
		// Mock window.location.href
		window.hCaptchaForminator.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=forminator-settings';

		// Simulate jQuery.ready event
		window.hCaptchaForminator.ready();

		// Check that the hCaptcha tab was updated with the correct content
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.first().html() ).toBe( global.HCaptchaForminatorObject.noticeLabel );
		expect( $label.css( 'display' ) ).toBe( 'block' );
		expect( $description.first().html() ).toBe( global.HCaptchaForminatorObject.noticeDescription );
		expect( $description.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'forminator ready function does not run when not on the Forminator settings page', () => {
		// Mock window.location.href
		window.hCaptchaForminator.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=some_other_page';

		// Simulate jQuery.ready event
		window.hCaptchaForminator.ready();

		// Check that the hCaptcha tab was not updated
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.html() ).toBe( 'Original Label' );
		expect( $description.html() ).toBe( 'Original Description' );
	} );

	test( 'callback function updates UI when mutation matches conditions', async () => {
		// Mock window.location.href
		window.hCaptchaForminator.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=forminator-cform';

		// Execute DOMContentLoaded event
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		const el = document.getElementById( 'forminator-field-hcaptcha_size' );

		// Change the attribute to make MutationObserver work
		el.setAttribute( 'data-test', 'changed' );

		// Wait for the next microtask queue to allow MutationObserver to fire
		await Promise.resolve();

		// Check that the UI was updated correctly
		const rows = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tab-content .sui-box-settings-row' );

		// Check that the second row was updated
		const secondRow = rows[ 1 ];
		expect( secondRow.querySelector( '.sui-settings-label' ).innerHTML ).toBe( global.HCaptchaForminatorObject.noticeLabel );
		expect( secondRow.querySelector( '.sui-description' ).innerHTML ).toBe( global.HCaptchaForminatorObject.noticeDescription );
		expect( secondRow.querySelector( '.sui-form-field' ).style.display ).toBe( 'none' );

		// Check that the third row is hidden
		const thirdRow = rows[ 2 ];
		expect( thirdRow.style.display ).toBe( 'none' );
	} );

	test( 'callback function does not update UI when mutation does not match conditions', () => {
		// Mock window.location.href
		window.hCaptchaForminator.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=some';

		// Execute DOMContentLoaded event
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		// Check that the UI was not updated
		const rows = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tab-content .sui-box-settings-row' );

		// Check that the second row was not updated
		const secondRow = rows[ 1 ];
		expect( secondRow.querySelector( '.sui-settings-label' ).innerHTML ).toBe( 'Original Settings Label' );
		expect( secondRow.querySelector( '.sui-description' ).innerHTML ).toBe( 'Original Settings Description' );
		expect( secondRow.querySelector( '.sui-form-field' ).style.display ).toBe( '' );

		// Check that the third row is not hidden
		const thirdRow = rows[ 2 ];
		expect( thirdRow.style.display ).toBe( '' );
	} );

	test( 'getLocationHref returns the correct location', () => {
		expect( window.hCaptchaForminator.getLocationHref() ).toBe( 'http://domain.tld/' );
	} );
} );
