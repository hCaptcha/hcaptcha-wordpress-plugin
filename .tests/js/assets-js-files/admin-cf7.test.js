// noinspection JSUnresolvedFunction,JSUnresolvedVariable,HtmlUnknownAttribute

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
	// language=HTML
	return `
		<input id="wpcf7-shortcode" value="test-shortcode"/>
		<textarea id="wpcf7-form">
        	<div class="h-captcha" data-sitekey="test-site-key"></div>
    	</textarea>
		<div id="form-live"></div>
		<div class="tag-generator-dialog" open>
			<div class="tag-generator-content"></div>
		</div>
	`;
}

describe( 'admin-cf7', () => {
	let postSpy;

	beforeEach( () => {
		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaFluentForm
		window.hCaptchaCF7 = undefined;

		// Setup fake timers for debounce
		jest.useFakeTimers();

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

		// Force reloading the tested file.
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-cf7.js' );

		// Simulate jQuery.ready event
		window.hCaptchaCF7.ready();
	} );

	afterEach( () => {
		postSpy.mockRestore();
		jest.clearAllMocks();
		jest.clearAllTimers();
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

	test( 'mutation observer triggers form change when attribute is changed', async () => {
		// Clear any previous calls to postSpy.
		postSpy.mockClear();

		// Change form value
		const $form = $( '#wpcf7-form' );
		$form.val( '<div class="h-captcha" data-sitekey="updated-site-key"></div>' );

		// Change the attribute
		$( '.tag-generator-dialog' ).attr( 'open', 'open' );

		// Wait for the next microtask queue to allow MutationObserver to fire
		await Promise.resolve();

		// Fast-forward timers to trigger a debounced function.
		jest.runAllTimers();

		// Check that the AJAX request was made
		expect( postSpy ).toHaveBeenCalled();

		// Check that the correct data was sent
		const postData = postSpy.mock.calls[ 0 ][ 0 ].data;
		expect( postData.action ).toBe( global.HCaptchaCF7Object.updateFormAction );
		expect( postData.nonce ).toBe( global.HCaptchaCF7Object.updateFormNonce );
		expect( postData.shortcode ).toBe( 'test-shortcode' );
		expect( postData.form ).toContain( 'updated-site-key' );
	} );

	test( 'mutation observer does not trigger form change when conditions are not met', () => {
		// Reset the spy to check if it gets called.
		postSpy.mockClear();

		// Change the attribute but do not change the form value
		$( '.tag-generator-dialog' ).attr( 'open', 'open' );

		// Check that the AJAX request was not made.
		expect( postSpy ).not.toHaveBeenCalled();
	} );
} );
