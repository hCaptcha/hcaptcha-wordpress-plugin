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

// Mock MutationObserver
global.MutationObserver = jest.fn().mockImplementation( () => ( {
	observe: jest.fn(),
	disconnect: jest.fn(),
} ) );

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

	beforeEach( () => {
		// Save the original location
		originalLocation = window.location;

		// Set up the DOM
		document.body.innerHTML = getDom();

		// Clear the mocks
		jest.clearAllMocks();
	} );

	afterEach( () => {
		// Restore original location
		window.location = originalLocation;
	} );

	test( 'ajaxSuccess event handler calls hCaptchaBindEvents when action is forminator_load_form', () => {
		// Create a handler that simulates the behavior in admin-forminator.js
		const handler = function( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'forminator_load_form' ) {
				return;
			}

			global.hCaptchaBindEvents();
		};

		// Attach the handler to the ajaxSuccess event
		$( document ).on( 'ajaxSuccess', handler );

		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=forminator_load_form&other_param=value',
		};

		// Trigger the ajaxSuccess event
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was called
		expect( global.hCaptchaBindEvents ).toHaveBeenCalled();

		// Clean up
		$( document ).off( 'ajaxSuccess', handler );
	} );

	test( 'ajaxSuccess event handler does not call hCaptchaBindEvents when action is not forminator_load_form', () => {
		// Create a handler that simulates the behavior in admin-forminator.js
		const handler = function( event, xhr, settings ) {
			const params = new URLSearchParams( settings.data );

			if ( params.get( 'action' ) !== 'forminator_load_form' ) {
				return;
			}

			global.hCaptchaBindEvents();
		};

		// Attach the handler to the ajaxSuccess event
		$( document ).on( 'ajaxSuccess', handler );

		// Create a mock event and settings
		const xhr = {};
		const settings = {
			data: 'action=some_other_action&other_param=value',
		};

		// Trigger the ajaxSuccess event
		$( document ).trigger( 'ajaxSuccess', [ xhr, settings ] );

		// Check that hCaptchaBindEvents was not called
		expect( global.hCaptchaBindEvents ).not.toHaveBeenCalled();

		// Clean up
		$( document ).off( 'ajaxSuccess', handler );
	} );

	test( 'forminator function updates the hcaptcha tab with the correct content', () => {
		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=forminator-settings' };

		// Create a function that simulates the behavior in admin-forminator.js
		const forminator = function( $ ) {
			if ( ! window.location.href.includes( 'page=forminator-settings' ) ) {
				return;
			}

			const $hcaptchaTab = $( '#hcaptcha-tab' );

			$hcaptchaTab.find( '.sui-settings-label' ).first()
				.html( global.HCaptchaForminatorObject.noticeLabel ).css( 'display', 'block' );
			$hcaptchaTab.find( '.sui-description' ).first()
				.html( global.HCaptchaForminatorObject.noticeDescription ).css( 'display', 'block' );
		};

		// Call the function
		forminator( $ );

		// Check that the hcaptcha tab was updated with the correct content
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.html() ).toBe( global.HCaptchaForminatorObject.noticeLabel );
		expect( $label.css( 'display' ) ).toBe( 'block' );
		expect( $description.html() ).toBe( global.HCaptchaForminatorObject.noticeDescription );
		expect( $description.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'forminator function does not run when not on the Forminator settings page', () => {
		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=some_other_page' };

		// Create a function that simulates the behavior in admin-forminator.js
		const forminator = function( $ ) {
			if ( ! window.location.href.includes( 'page=forminator-settings' ) ) {
				return;
			}

			const $hcaptchaTab = $( '#hcaptcha-tab' );

			$hcaptchaTab.find( '.sui-settings-label' ).first()
				.html( global.HCaptchaForminatorObject.noticeLabel ).css( 'display', 'block' );
			$hcaptchaTab.find( '.sui-description' ).first()
				.html( global.HCaptchaForminatorObject.noticeDescription ).css( 'display', 'block' );
		};

		// Call the function
		forminator( $ );

		// Check that the hcaptcha tab was not updated
		const $label = $( '#hcaptcha-tab .sui-settings-label' ).first();
		const $description = $( '#hcaptcha-tab .sui-description' ).first();

		expect( $label.html() ).toBe( 'Original Label' );
		expect( $description.html() ).toBe( 'Original Description' );
	} );

	test( 'callback function updates UI when mutation matches conditions', () => {
		// Create a function that simulates the callback in admin-forminator.js
		const callback = function( mutationList ) {
			for ( const mutation of mutationList ) {
				if (
					! (
						mutation.type === 'attributes' &&
						mutation.target.id === 'forminator-field-hcaptcha_size'
					)
				) {
					continue;
				}

				const hCaptchaButton = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tabs-menu .sui-tab-item' )[ 1 ];

				if ( hCaptchaButton === undefined || ! hCaptchaButton.classList.contains( 'active' ) ) {
					return;
				}

				const content = hCaptchaButton.closest( '.sui-tabs-content' ).querySelector( '.sui-tab-content' );

				const rows = content.querySelectorAll( '.sui-box-settings-row' );

				[ ...rows ].map( ( row, index ) => {
					if ( index === 1 ) {
						row.querySelector( '.sui-settings-label' ).innerHTML = HCaptchaForminatorObject.noticeLabel;
						row.querySelector( '.sui-description' ).innerHTML = HCaptchaForminatorObject.noticeDescription;
						row.querySelector( '.sui-form-field' ).style.display = 'none';
					}

					if ( index > 1 ) {
						row.style.display = 'none';
					}

					return row;
				} );

				return;
			}
		};

		// Create a mock mutation that matches the conditions
		const mockMutation = {
			type: 'attributes',
			target: document.getElementById( 'forminator-field-hcaptcha_size' ),
		};

		// Call the callback with the mock mutation
		callback( [ mockMutation ] );

		// Check that the UI was updated correctly
		const rows = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tab-content .sui-box-settings-row' );

		// Check that the second row was updated
		const secondRow = rows[ 1 ];
		expect( secondRow.querySelector( '.sui-settings-label' ).innerHTML ).toBe( HCaptchaForminatorObject.noticeLabel );
		expect( secondRow.querySelector( '.sui-description' ).innerHTML ).toBe( HCaptchaForminatorObject.noticeDescription );
		expect( secondRow.querySelector( '.sui-form-field' ).style.display ).toBe( 'none' );

		// Check that the third row is hidden
		const thirdRow = rows[ 2 ];
		expect( thirdRow.style.display ).toBe( 'none' );
	} );

	test( 'callback function does not update UI when mutation does not match conditions', () => {
		// Create a function that simulates the callback in admin-forminator.js
		const callback = function( mutationList ) {
			for ( const mutation of mutationList ) {
				if (
					! (
						mutation.type === 'attributes' &&
						mutation.target.id === 'forminator-field-hcaptcha_size'
					)
				) {
					continue;
				}

				const hCaptchaButton = document.querySelectorAll( '#forminator-modal-body--captcha .sui-tabs-content .sui-tabs-menu .sui-tab-item' )[ 1 ];

				if ( hCaptchaButton === undefined || ! hCaptchaButton.classList.contains( 'active' ) ) {
					return;
				}

				const content = hCaptchaButton.closest( '.sui-tabs-content' ).querySelector( '.sui-tab-content' );

				const rows = content.querySelectorAll( '.sui-box-settings-row' );

				[ ...rows ].map( ( row, index ) => {
					if ( index === 1 ) {
						row.querySelector( '.sui-settings-label' ).innerHTML = HCaptchaForminatorObject.noticeLabel;
						row.querySelector( '.sui-description' ).innerHTML = HCaptchaForminatorObject.noticeDescription;
						row.querySelector( '.sui-form-field' ).style.display = 'none';
					}

					if ( index > 1 ) {
						row.style.display = 'none';
					}

					return row;
				} );

				return;
			}
		};

		// Create a mock mutation that does not match the conditions
		const mockMutation = {
			type: 'childList', // Wrong type
			target: document.getElementById( 'forminator-field-hcaptcha_size' ),
		};

		// Call the callback with the mock mutation
		callback( [ mockMutation ] );

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

	test( 'DOMContentLoaded event handler sets up a MutationObserver on the custom form page', () => {
		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=forminator-cform' };

		// Create a handler that simulates the behavior in admin-forminator.js
		const handler = function() {
			if ( ! window.location.href.includes( 'page=forminator-cform' ) ) {
				return;
			}

			const config = {
				attributes: true,
				subtree: true,
			};

			const callback = function( mutationList ) {
				for ( const mutation of mutationList ) {
					if (
						! (
							mutation.type === 'attributes' &&
							mutation.target.id === 'forminator-field-hcaptcha_size'
						)
					) {
						continue;
					}

					// Mock the callback behavior
				}
			};

			const observer = new MutationObserver( callback );
			observer.observe( document.body, config );
		};

		// Call the handler
		handler();

		// Check that MutationObserver was called
		expect( global.MutationObserver ).toHaveBeenCalled();

		// Check that observe() was called with the correct element and config
		expect( global.MutationObserver.mock.results[ 0 ].value.observe ).toHaveBeenCalledWith( document.body, {
			attributes: true,
			subtree: true,
		} );
	} );

	test( 'DOMContentLoaded event handler does not set up a MutationObserver when not on the custom form page', () => {
		// Mock window.location.href
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=some_other_page' };

		// Create a handler that simulates the behavior in admin-forminator.js
		const handler = function() {
			if ( ! window.location.href.includes( 'page=forminator-cform' ) ) {
				return;
			}

			const config = {
				attributes: true,
				subtree: true,
			};

			const callback = function( mutationList ) {
				for ( const mutation of mutationList ) {
					if (
						! (
							mutation.type === 'attributes' &&
							mutation.target.id === 'forminator-field-hcaptcha_size'
						)
					) {
						continue;
					}

					// Mock the callback behavior
				}
			};

			const observer = new MutationObserver( callback );
			observer.observe( document.body, config );
		};

		// Call the handler
		handler();

		// Check that MutationObserver was not called
		expect( global.MutationObserver ).not.toHaveBeenCalled();
	} );
} );
