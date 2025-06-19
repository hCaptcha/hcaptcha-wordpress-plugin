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
	return `
<html lang="en">
<body>
    <div id="ff_global_settings_option_app">
        <div class="ff_hcaptcha_wrap">
            <div class="ff_card_head">
                <h5></h5>
                <p></p>
            </div>
        </div>
    </div>
</body>
</html>
    `;
}

describe( 'admin-fluentform', () => {
	let mockMutationObserver;
	let mockObserve;
	let originalLocation;

	beforeEach( () => {
		// Save the original location.
		originalLocation = window.location;

		// Mock window.location.href.
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=fluent_forms_settings' };

		// Set up fake timers for any potential debouncing.
		jest.useFakeTimers();

		document.body.innerHTML = getDom();

		// Mock MutationObserver
		mockObserve = jest.fn();
		mockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe: mockObserve,
			disconnect: jest.fn(),
		} ) );
		global.MutationObserver = mockMutationObserver;

		// Load the script
		require( '../../../assets/js/admin-fluentform.js' );

		// Simulate jQuery.ready event
		window.hCaptchaFluentForm( $ );
	} );

	afterEach( () => {
		// Restore original location
		window.location = originalLocation;

		jest.clearAllMocks();
		jest.clearAllTimers();
	} );

	test( 'MutationObserver is initialized with correct parameters', () => {
		expect( mockMutationObserver ).toHaveBeenCalled();
		expect( mockObserve ).toHaveBeenCalled();

		// Check that observe() was called with the correct element and config.
		const observeElement = document.querySelector( '#ff_global_settings_option_app' );
		expect( mockObserve.mock.calls[ 0 ][ 0 ] ).toBe( observeElement );

		const config = mockObserve.mock.calls[ 0 ][ 1 ];
		expect( config.childList ).toBe( true );
		expect( config.subtree ).toBe( true );
	} );

	test( 'updateHCaptchaWrap updates the hCaptcha wrap with correct content', () => {
		// Check that the hCaptcha wrap was updated with the correct content
		const $h5 = $( '.ff_hcaptcha_wrap .ff_card_head h5' );
		const $p = $( '.ff_hcaptcha_wrap .ff_card_head p' ).first();

		expect( $h5.html() ).toBe( global.HCaptchaFluentFormObject.noticeLabel );
		expect( $h5.css( 'display' ) ).toBe( 'block' );
		expect( $p.html() ).toBe( global.HCaptchaFluentFormObject.noticeDescription );
		expect( $p.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'observeHCaptchaWrap calls updateHCaptchaWrap when a ff_hcaptcha_wrap node is added', () => {
		// Clear the existing hCaptcha wrap
		document.querySelector( '.ff_hcaptcha_wrap' ).remove();

		// Create a new hCaptcha wrap element
		const newWrap = document.createElement( 'div' );
		newWrap.className = 'ff_hcaptcha_wrap';
		newWrap.innerHTML = `
            <div class="ff_card_head">
                <h5></h5>
                <p></p>
            </div>
        `;

		// Add the new wrap to the DOM
		document.querySelector( '#ff_global_settings_option_app' ).appendChild( newWrap );

		// Get the callback function passed to MutationObserver.
		const observerCallback = mockMutationObserver.mock.calls[ 0 ][ 0 ];

		// Create a mock mutation that simulates adding the hCaptcha wrap.
		const mockMutation = {
			type: 'childList',
			addedNodes: [ newWrap ],
		};

		// Call the callback with mock mutations.
		observerCallback( [ mockMutation ] );

		// Check that the hCaptcha wrap was updated.
		const $h5 = $( newWrap ).find( '.ff_card_head h5' );
		const $p = $( newWrap ).find( '.ff_card_head p' ).first();

		expect( $h5.html() ).toBe( global.HCaptchaFluentFormObject.noticeLabel );
		expect( $h5.css( 'display' ) ).toBe( 'block' );
		expect( $p.html() ).toBe( global.HCaptchaFluentFormObject.noticeDescription );
		expect( $p.css( 'display' ) ).toBe( 'block' );
	} );

	test( 'observeHCaptchaWrap does not call updateHCaptchaWrap for non-ff_hcaptcha_wrap nodes', () => {
		// Create a spy for jQuery.html to track calls to it.
		const htmlSpy = jest.spyOn( $.fn, 'html' );

		// Clear previous calls.
		htmlSpy.mockClear();

		// Create a new non-hCaptcha wrap element.
		const newElement = document.createElement( 'div' );
		newElement.className = 'some-other-class';

		// Get the callback function passed to MutationObserver.
		const observerCallback = mockMutationObserver.mock.calls[ 0 ][ 0 ];

		// Create a mock mutation that simulates adding a non-hCaptcha wrap.
		const mockMutation = {
			type: 'childList',
			addedNodes: [ newElement ],
		};

		// Call the callback with mock mutations.
		observerCallback( [ mockMutation ] );

		// Check that HTML was not called (updateHCaptchaWrap was not called).
		expect( htmlSpy ).not.toHaveBeenCalled();

		// Restore the spy.
		htmlSpy.mockRestore();
	} );

	test( 'script does not run when not on the FluentForm settings page', () => {
		// Change window.location to a non-FluentForm settings page.
		delete window.location;
		window.location = { href: 'https://test.test/wp-admin/admin.php?page=some_other_page' };

		// Clear mocks.
		mockMutationObserver.mockClear();
		mockObserve.mockClear();

		// Reload the script.
		jest.resetModules();
		require( '../../../assets/js/admin-fluentform.js' );

		// Simulate jQuery.ready event.
		window.hCaptchaFluentForm( $ );

		// Check that MutationObserver was not called.
		expect( mockMutationObserver ).not.toHaveBeenCalled();
		expect( mockObserve ).not.toHaveBeenCalled();
	} );
} );
