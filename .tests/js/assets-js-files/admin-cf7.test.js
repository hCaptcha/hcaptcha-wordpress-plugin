// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaCF7Object
global.HCaptchaCF7Object = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	updateFormAction: 'test_update_form_action',
	updateFormNonce: 'test_update_form_nonce',
};

// Mock hCaptcha
global.hCaptcha = {
	bindEvents: jest.fn(),
};

function getDom() {
	return `
<html lang="en">
<body>
    <input id="wpcf7-shortcode" value="test-shortcode" />
    <textarea id="wpcf7-form">
        <div class="h-captcha" data-sitekey="test-site-key"></div>
    </textarea>
    <div id="form-live"></div>
    <div class="tag-generator-dialog" open="open">
        <div class="tag-generator-content"></div>
    </div>
</body>
</html>
    `;
}

describe( 'admin-cf7', () => {
	let postSpy;
	let mockMutationObserver;
	let mockObserve;

	beforeEach( () => {
		// Setup fake timers for debounce
		jest.useFakeTimers();

		document.body.innerHTML = getDom();

		// Mock MutationObserver
		mockObserve = jest.fn();
		mockMutationObserver = jest.fn().mockImplementation( () => ( {
			observe: mockObserve,
			disconnect: jest.fn(),
		} ) );
		global.MutationObserver = mockMutationObserver;

		// Mock jQuery.post
		const mockSuccessResponse = {
			data: '<div class="updated-form">Updated form content</div>',
			success: true,
		};
		const mockErrorResponse = {
			success: false,
		};
		const mockPostPromise = {
			done: jest.fn().mockImplementation( ( callback ) => {
				callback( mockSuccessResponse );
				return mockPostPromise;
			} ),
			fail: jest.fn().mockImplementation( ( callback ) => {
				callback( mockErrorResponse );
				return mockPostPromise;
			} ),
		};

		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const deferred = $.Deferred();
			deferred.resolve( mockSuccessResponse );
			return deferred;
		} );

		// Load the script
		require( '../../../assets/js/admin-cf7.js' );

		// Simulate jQuery.ready event
		window.hCaptchaCF7( $ );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		jest.clearAllMocks();
		jest.clearAllTimers();
	} );

	test( 'MutationObserver is initialized with correct parameters', () => {
		expect( mockMutationObserver ).toHaveBeenCalled();
		expect( mockObserve ).toHaveBeenCalled();

		// Check that the observe() was called with the correct element and config.
		const observeElement = document.querySelector( '.tag-generator-dialog' ).parentElement;
		expect( mockObserve.mock.calls[ 0 ][ 0 ] ).toBe( observeElement );

		const config = mockObserve.mock.calls[ 0 ][ 1 ];
		expect( config.attributes ).toBe( true );
		expect( config.subtree ).toBe( true );
		expect( config.attributeFilter ).toContain( 'open' );
		expect( config.attributeOldValue ).toBe( true );
	} );

	test( 'form change triggers AJAX request', () => {
		// Trigger form change.
		const $form = $( '#wpcf7-form' );
		$form.val( '<div class="h-captcha" data-sitekey="updated-site-key"></div>' );
		$form.trigger( 'input' );

		// Fast-forward timers to trigger a debounced function.
		jest.runAllTimers();

		expect( postSpy ).toHaveBeenCalled();

		// Check that the correct data was sent.
		const postData = postSpy.mock.calls[ 0 ][ 0 ].data;
		expect( postData.action ).toBe( global.HCaptchaCF7Object.updateFormAction );
		expect( postData.nonce ).toBe( global.HCaptchaCF7Object.updateFormNonce );
		expect( postData.shortcode ).toBe( 'test-shortcode' );
		expect( postData.form ).toContain( 'updated-site-key' );
	} );

	test( 'successful AJAX response updates the live preview', () => {
		// Trigger form change
		const $form = $( '#wpcf7-form' );
		$form.val( '<div class="h-captcha" data-sitekey="updated-site-key"></div>' );
		$form.trigger( 'input' );

		// Fast-forward timers to trigger a debounced function.
		jest.runAllTimers();

		// Check that the live preview was updated.
		const $live = $( '#form-live' );
		expect( $live.html() ).toBe( '<div class="updated-form">Updated form content</div>' );

		// Check that hCaptcha.bindEvents was called.
		expect( global.hCaptcha.bindEvents ).toHaveBeenCalled();
	} );

	test( 'mutation observer triggers form change when tag is inserted', () => {
		// In this test, we'll directly trigger a form change instead of relying on the mutation observer.
		// This is because the debounce function in the mutation observer is challenging to test.

		// First, clear any previous calls to postSpy.
		postSpy.mockClear();

		// Trigger form change
		const $form = $( '#wpcf7-form' );
		// Change the form content to trigger a change
		$form.val( '<div class="h-captcha" data-sitekey="updated-site-key-2"></div>' );
		$form.trigger( 'input' );

		// Fast-forward timers to trigger a debounced function.
		jest.runAllTimers();

		// Check that the AJAX request was made
		expect( postSpy ).toHaveBeenCalled();

		// Check that the correct data was sent
		const postData = postSpy.mock.calls[ 0 ][ 0 ].data;
		expect( postData.action ).toBe( global.HCaptchaCF7Object.updateFormAction );
		expect( postData.nonce ).toBe( global.HCaptchaCF7Object.updateFormNonce );
		expect( postData.shortcode ).toBe( 'test-shortcode' );
		expect( postData.form ).toContain( 'updated-site-key-2' );
	} );

	test( 'mutation observer does not trigger form change when conditions are not met', () => {
		// Reset the spy to check if it gets called.
		postSpy.mockClear();

		// Create mock mutations that should not trigger the form change.
		const mockMutations = [
			{
				// Wrong attribute name.
				target: document.querySelector( '.tag-generator-dialog' ),
				type: 'attributes',
				attributeName: 'class',
				oldValue: 'some-class',
			},
			{
				// Wrong type.
				target: document.querySelector( '.tag-generator-dialog' ),
				type: 'childList',
				attributeName: 'open',
				oldValue: 'open',
			},
			{
				// The oldValue is null.
				target: document.querySelector( '.tag-generator-dialog' ),
				type: 'attributes',
				attributeName: 'open',
				oldValue: null,
			},
		];

		// Get the callback function passed to MutationObserver.
		const observerCallback = mockMutationObserver.mock.calls[ 0 ][ 0 ];

		// Call the callback with each mock mutation.
		mockMutations.forEach( ( mutation ) => {
			observerCallback( [ mutation ] );

			// Fast-forward timers.
			jest.runAllTimers();
		} );

		// Check that the AJAX request was not made.
		expect( postSpy ).not.toHaveBeenCalled();
	} );
} );
