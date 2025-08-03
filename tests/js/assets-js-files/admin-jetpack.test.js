// noinspection JSUnresolvedFunction,JSUnresolvedVariable

// Mock wp.hooks
global.wp = {
	hooks: {
		addFilter: jest.fn(),
	},
};

// Mock HCaptchaJetpackObject
global.HCaptchaJetpackObject = {
	hCaptcha: '<div class="h-captcha"></div>',
};

function getDom() {
	// language=HTML
	return `
		<div class="wp-block">
			<div class="jetpack-contact-form">
				<div class="wp-block-jetpack-button"></div>
			</div>
		</div>
		<div class="wp-block">
			<div class="jetpack-contact-form">
				<div class="wp-block-jetpack-button"></div>
			</div>
		</div>
	`;
}

describe( 'admin-jetpack', () => {
	beforeEach( () => {
		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaJetpack
		window.hCaptchaJetpack = undefined;

		// Reset mocks
		global.wp.hooks.addFilter.mockClear();

		// Force reloading the tested file
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-jetpack.js' );
	} );

	test( 'init function initializes the application', () => {
		// Check that the window.hCaptchaJetpack object is created
		expect( window.hCaptchaJetpack ).toBeDefined();

		// Spy on the functions
		const addFormSelectorFilterSpy = jest.spyOn( window.hCaptchaJetpack, 'addFormSelectorFilter' );
		const beforeBindEventsSpy = jest.spyOn( window.hCaptchaJetpack, 'beforeBindEvents' );

		// Call addFormSelectorFilter
		window.hCaptchaJetpack.init();

		// Check that addFormSelectorFilter was called
		expect( addFormSelectorFilterSpy ).toHaveBeenCalledTimes( 1 );

		// Trigger the hCaptchaBeforeBindEvents event to check if the event listener works
		document.dispatchEvent( new Event( 'hCaptchaBeforeBindEvents' ) );

		// Check that beforeBindEvents was called
		expect( beforeBindEventsSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'addFormSelectorFilter adds a filter to include Jetpack contact forms', () => {
		// Call addFormSelectorFilter
		window.hCaptchaJetpack.addFormSelectorFilter();

		// Check that wp.hooks.addFilter was called with the correct arguments
		expect( global.wp.hooks.addFilter ).toHaveBeenCalledWith(
			'hcaptcha.formSelector',
			'hcaptcha',
			expect.any( Function ),
		);

		// Get the filter function
		const filterFunction = global.wp.hooks.addFilter.mock.calls[ 0 ][ 2 ];

		// Test that the filter function adds the Jetpack contact form selector
		const originalSelector = 'form';
		const newSelector = filterFunction( originalSelector );
		expect( newSelector ).toBe( 'form, div.jetpack-contact-form' );
	} );

	test( 'beforeBindEvents adds hCaptcha to Jetpack contact forms', () => {
		// Call beforeBindEvents
		window.hCaptchaJetpack.beforeBindEvents();

		// Check that hCaptcha was added to each form
		const forms = document.querySelectorAll( '.wp-block .jetpack-contact-form' );
		forms.forEach( ( form ) => {
			const hCaptcha = form.querySelector( '.h-captcha' );
			expect( hCaptcha ).not.toBeNull();
		} );

		// Check that hCaptcha was added before the button
		const buttons = document.querySelectorAll( '.wp-block .jetpack-contact-form .wp-block-jetpack-button' );
		buttons.forEach( ( button ) => {
			const previousSibling = button.previousSibling;
			expect( previousSibling.innerHTML ).toBe( global.HCaptchaJetpackObject.hCaptcha );
		} );
	} );

	test( 'beforeBindEvents does nothing when no Jetpack contact forms are found', () => {
		// Remove all Jetpack contact forms
		document.body.innerHTML = '';

		// Call beforeBindEvents
		window.hCaptchaJetpack.beforeBindEvents();

		// Check that no errors occurred
		expect( document.body.innerHTML ).toBe( '' );
	} );
} );
