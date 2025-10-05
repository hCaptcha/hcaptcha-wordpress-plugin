// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import HCaptcha from '../../../src/js/hcaptcha/hcaptcha.js';

// Helper function to create a DOM element with optional attributes
function createElement( tagName, attributes = {} ) {
	const element = document.createElement( tagName );
	for ( const key in attributes ) {
		element.setAttribute( key, attributes[ key ] );
	}
	return element;
}

describe( 'HCaptcha', () => {
	let hCaptcha;

	beforeEach( () => {
		hCaptcha = new HCaptcha();

		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};
	} );

	test( 'GenerateID', () => {
		expect( hCaptcha.generateID() ).toMatch( /^(?:[0-9|a-f]{4}-){3}[0-9|a-f]{4}$/ );
	} );

	test( 'getFoundFormById', () => {
		const testForm = {
			hCaptchaId: 'test-id',
			submitButtonSelector: 'test-selector',
		};

		hCaptcha.foundForms.push( testForm );

		expect( hCaptcha.getFoundFormById( 'test-id' ) ).toEqual( testForm );
		expect( hCaptcha.getFoundFormById( 'non-existent-id' ) ).toBeNull();
	} );

	test( 'isSameOrDescendant', () => {
		const parent = document.createElement( 'div' );
		const child = document.createElement( 'div' );
		const grandChild = document.createElement( 'div' );
		const unrelatedElement = document.createElement( 'div' );

		parent.appendChild( child );
		child.appendChild( grandChild );

		expect( hCaptcha.isSameOrDescendant( parent, parent ) ).toBeTruthy();
		expect( hCaptcha.isSameOrDescendant( parent, child ) ).toBeTruthy();
		expect( hCaptcha.isSameOrDescendant( parent, grandChild ) ).toBeTruthy();
		expect( hCaptcha.isSameOrDescendant( parent, unrelatedElement ) ).toBeFalsy();
	} );

	test( 'getParams and setParams', () => {
		const testParams = { test: 'value' };

		expect( hCaptcha.getParams() ).not.toEqual( testParams );
		hCaptcha.setParams( testParams );
		expect( hCaptcha.getParams() ).toEqual( testParams );
	} );

	test( 'bindEvents and reset', () => {
		function generateUniqueId() {
			return Math.random().toString( 36 ).substring( 2, 9 );
		}

		// Mock hcaptcha object
		global.hcaptcha = {
			render: jest.fn( () => {
				return generateUniqueId();
			} ),
			execute: jest.fn(),
			reset: jest.fn(),
		};

		// Mock HCaptchaMainObject
		global.HCaptchaMainObject = {
			params: JSON.stringify( { test: 'value' } ),
		};

		// Create DOM elements
		const form1 = createElement( 'form' );
		const form2 = createElement( 'form' );
		const form3 = createElement( 'form' );
		const widget1 = createElement( 'div', { class: 'h-captcha', 'data-size': 'invisible' } );
		const widget2 = createElement( 'div', { class: 'h-captcha', 'data-size': 'normal' } );
		const submit1 = createElement( 'input', { type: 'submit' } );
		const submit2 = createElement( 'input', { type: 'submit' } );

		form1.appendChild( widget1 );
		form1.appendChild( submit1 );
		form2.appendChild( widget2 );
		form2.appendChild( submit2 );

		document.body.appendChild( form1 );
		document.body.appendChild( form2 );
		document.body.appendChild( form3 );

		// Spy on addEventListener before calling bindEvents
		const submit1ClickHandler = jest.spyOn( submit1, 'addEventListener' );

		hCaptcha.bindEvents();

		// Check that hcaptcha.render was called twice (for form1 and form2)
		expect( global.hcaptcha.render ).toHaveBeenCalledTimes( 2 );

		// Check that an event listener was added to form1 for invisible hCaptcha
		expect( submit1ClickHandler ).toHaveBeenCalledWith( 'click', expect.any( Function ), true );

		// Simulate click event on form1
		const clickEvent = new Event( 'click', { bubbles: true } );
		submit1.dispatchEvent( clickEvent );

		// Check that hcaptcha.execute was called
		expect( global.hcaptcha.execute ).toHaveBeenCalled();

		// Mock requestSubmit on the form element
		form1.requestSubmit = jest.fn();

		// Call submit method
		hCaptcha.submit();

		// Check if requestSubmit was called on the form element
		expect( form1.requestSubmit ).toHaveBeenCalled();

		// Call reset method
		hCaptcha.reset( form1 );

		// Check if hcaptcha.reset was called with the correct widget id
		expect( global.hcaptcha.reset ).toHaveBeenCalled();

		// Clean up DOM elements
		document.body.removeChild( form1 );
		document.body.removeChild( form2 );
		document.body.removeChild( form3 );
	} );

	test( 'getWidgetId returns widget id for element inside form with matching data attribute', () => {
		const form = document.createElement( 'form' );
		const child = document.createElement( 'div' );

		form.appendChild( child );
		document.body.appendChild( form );

		// Ensure the selector is set for closest()
		hCaptcha.formSelector = 'form';

		const hCaptchaId = 'abc-123';
		const widgetId = 'wid-123';
		form.dataset.hCaptchaId = hCaptchaId;

		// Found forms should contain an entry mapping id -> widgetId
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: null } );

		expect( hCaptcha.getWidgetId( child ) ).toBe( widgetId );

		document.body.removeChild( form );
	} );

	test( 'getWidgetId returns empty string when element is undefined', () => {
		expect( hCaptcha.getWidgetId( undefined ) ).toBe( '' );
	} );

	test( 'getWidgetId returns empty string when form has no data-h-captcha-id', () => {
		const form = document.createElement( 'form' );
		const child = document.createElement( 'div' );

		form.appendChild( child );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';

		expect( hCaptcha.getWidgetId( child ) ).toBe( '' );

		document.body.removeChild( form );
	} );

	test( 'getWidgetId returns empty string when no matching found form exists', () => {
		const form = document.createElement( 'form' );
		const child = document.createElement( 'div' );

		form.appendChild( child );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';
		form.dataset.hCaptchaId = 'non-existent-id';

		expect( hCaptcha.getWidgetId( child ) ).toBe( '' );

		document.body.removeChild( form );
	} );

	test( 'reset does nothing when no widgetId can be resolved', () => {
		// Ensure hcaptcha API mock exists
		global.hcaptcha = {
			reset: jest.fn(),
		};

		// Case: undefined element
		hCaptcha.reset( undefined );

		// Should not call the API when widgetId is empty
		expect( global.hcaptcha.reset ).not.toHaveBeenCalled();
	} );

	test( 'reset calls hcaptcha.reset with resolved widgetId', () => {
		// Prepare DOM with a form and a child element
		const form = document.createElement( 'form' );
		const child = document.createElement( 'div' );

		form.appendChild( child );
		document.body.appendChild( form );

		// Ensure the selector is set for closest()
		hCaptcha.formSelector = 'form';

		// Map form hCaptchaId -> widgetId in foundForms
		const hCaptchaId = 'id-1234';
		const widgetId = 'widget-5678';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: null } );

		// Mock hcaptcha API
		global.hcaptcha = {
			reset: jest.fn(),
		};

		// Invoke reset
		hCaptcha.reset( child );

		// Expect API called with the correct widget id
		expect( global.hcaptcha.reset ).toHaveBeenCalledWith( widgetId );

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'getCurrentForm returns form context and prevents default when clicking submit button', () => {
		// Build DOM: form with the 'submit' button
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );
		form.appendChild( submit );
		document.body.appendChild( form );

		// Ensure the selector is set for closest()
		hCaptcha.formSelector = 'form';

		// Map form hCaptchaId -> widgetId in foundForms
		const hCaptchaId = 'form-1';
		const widgetId = 'widget-1';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		// Mock the event as it would be on the 'submit' button (listener attached to button)
		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = {
			currentTarget: submit,
			target: submit,
			preventDefault,
			stopPropagation,
		};

		const result = hCaptcha.getCurrentForm( event );

		expect( result ).toBeDefined();
		expect( result.formElement ).toBe( form );
		expect( result.submitButtonElement ).toBe( submit );
		expect( result.widgetId ).toBe( widgetId );
		expect( preventDefault ).toHaveBeenCalled();
		expect( stopPropagation ).toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'getCurrentForm returns undefined when event target is not submit button or its descendant', () => {
		// Build DOM
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );
		const other = document.createElement( 'div' );

		submit.setAttribute( 'type', 'submit' );

		form.appendChild( submit );

		document.body.appendChild( form );
		document.body.appendChild( other );

		// Setup
		hCaptcha.formSelector = 'form';

		const hCaptchaId = 'form-2';
		const widgetId = 'widget-2';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = {
			currentTarget: submit,
			target: other, // Not a descendant of the 'submit' button
			preventDefault,
			stopPropagation,
		};

		const result = hCaptcha.getCurrentForm( event );

		expect( result ).toBeUndefined();
		expect( preventDefault ).not.toHaveBeenCalled();
		expect( stopPropagation ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
		document.body.removeChild( other );
	} );

	test( 'getCurrentForm returns undefined when widgetId is missing', () => {
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );
		form.appendChild( submit );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';
		const hCaptchaId = 'form-3';

		form.dataset.hCaptchaId = hCaptchaId;

		// Push found form without widgetId
		hCaptcha.foundForms.push( { hCaptchaId, widgetId: '', submitButtonElement: submit } );

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = {
			currentTarget: submit,
			target: submit,
			preventDefault,
			stopPropagation,
		};

		const result = hCaptcha.getCurrentForm( event );

		expect( result ).toBeUndefined();
		expect( preventDefault ).not.toHaveBeenCalled();
		expect( stopPropagation ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'validate executes hcaptcha when token is empty', () => {
		// Build DOM: form with the 'submit' button, h-captcha widget and empty response
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );

		const widget = document.createElement( 'div' );

		widget.className = 'h-captcha';

		const response = document.createElement( 'textarea' );

		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = ''; // empty token

		form.appendChild( widget );
		form.appendChild( response );
		form.appendChild( submit );
		document.body.appendChild( form );

		// Setup selectors and found form mapping
		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';

		const hCaptchaId = 'validate-1';
		const widgetId = 'wid-1';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		// Mock hcaptcha API
		global.hcaptcha = { execute: jest.fn() };

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = { currentTarget: submit, target: submit, preventDefault, stopPropagation };

		// Call validate
		hCaptcha.validate( event );

		// Should execute invisible widget
		expect( global.hcaptcha.execute ).toHaveBeenCalledWith( widgetId, { async: false } );

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'validate calls callback with token and does not execute when token is present', () => {
		// Build DOM
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );

		const widget = document.createElement( 'div' );

		widget.className = 'h-captcha';

		const response = document.createElement( 'textarea' );

		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = 'tok-123'; // token present

		form.appendChild( widget );
		form.appendChild( response );
		form.appendChild( submit );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';

		const hCaptchaId = 'validate-2';
		const widgetId = 'wid-2';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		// Mocks
		global.hcaptcha = { execute: jest.fn() };

		const callbackSpy = jest.spyOn( hCaptcha, 'callback' );

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = { currentTarget: submit, target: submit, preventDefault, stopPropagation };

		hCaptcha.validate( event );

		expect( callbackSpy ).toHaveBeenCalledWith( 'tok-123' );
		expect( global.hcaptcha.execute ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'validate does nothing when getCurrentForm returns undefined', () => {
		// Build DOM with a form but no matching foundForms entry
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );

		const response = document.createElement( 'textarea' );

		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = ''; // empty

		form.appendChild( response );
		form.appendChild( submit );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';
		form.dataset.hCaptchaId = 'no-found-form';

		global.hcaptcha = { execute: jest.fn() };

		const callbackSpy = jest.spyOn( hCaptcha, 'callback' );

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = { currentTarget: submit, target: submit, preventDefault, stopPropagation };

		// With no foundForms mapping, getCurrentForm returns undefined and validate early-returns
		hCaptcha.validate( event );

		expect( global.hcaptcha.execute ).not.toHaveBeenCalled();
		expect( callbackSpy ).not.toHaveBeenCalled();
		expect( preventDefault ).not.toHaveBeenCalled();
		expect( stopPropagation ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	// Tests for isValidated()
	test( 'isValidated returns false initially', () => {
		expect( hCaptcha.isValidated() ).toBe( false );
	} );

	test( 'isValidated returns true after validate sets currentForm', () => {
		// Build DOM: form with the 'submit' button and h-captcha widget
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );

		const widget = document.createElement( 'div' );

		widget.className = 'h-captcha';

		const response = document.createElement( 'textarea' );

		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = ''; // Empty token to follow an executed path

		form.appendChild( widget );
		form.appendChild( response );
		form.appendChild( submit );
		document.body.appendChild( form );

		// Setup selectors and found form mapping
		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';

		const hCaptchaId = 'is-validated-1';
		const widgetId = 'wid-x';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		// Mock hcaptcha API so execute exists
		global.hcaptcha = { execute: jest.fn() };

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = { currentTarget: submit, target: submit, preventDefault, stopPropagation };

		// Perform validate to set currentForm
		hCaptcha.validate( event );

		expect( hCaptcha.isValidated() ).toBe( true );

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'isValidated remains false when getCurrentForm returns undefined', () => {
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );
		form.appendChild( submit );
		document.body.appendChild( form );

		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';
		form.dataset.hCaptchaId = 'no-map-is-validated';

		// No foundForms mapping – getCurrentForm will return undefined
		global.hcaptcha = { execute: jest.fn() };

		const preventDefault = jest.fn();
		const stopPropagation = jest.fn();
		const event = { currentTarget: submit, target: submit, preventDefault, stopPropagation };

		hCaptcha.validate( event );

		expect( hCaptcha.isValidated() ).toBe( false );

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'setDarkData sets darkElement and darkClass when known style is present', () => {
		// Ensure applyFilters returns the provided map as-is
		wp.hooks.applyFilters.mockImplementationOnce( ( hook, content ) => content );

		// Add a style element that matches Twenty Twenty-One
		const style = document.createElement( 'style' );

		style.id = 'twenty-twenty-one-style-css';
		document.body.appendChild( style );

		// Preconditions
		expect( hCaptcha.darkElement ).toBeNull();
		expect( hCaptcha.darkClass ).toBeNull();

		hCaptcha.setDarkData();

		expect( hCaptcha.darkElement ).toBe( document.body );
		expect( hCaptcha.darkClass ).toBe( 'is-dark-theme' );

		// Cleanup
		document.body.removeChild( style );
	} );

	test( 'setDarkData uses filtered darkData including custom provider', () => {
		// Create a custom style that our filter will reference
		const customStyle = document.createElement( 'style' );

		customStyle.id = 'custom-style';
		document.body.appendChild( customStyle );

		// Filter adds a custom provider entry
		wp.hooks.applyFilters.mockImplementationOnce( ( hook, content ) => {
			return {
				...content,
				custom: {
					darkStyleId: 'custom-style',
					darkElement: document.documentElement,
					darkClass: 'my-dark',
				},
			};
		} );

		hCaptcha.setDarkData();

		expect( hCaptcha.darkElement ).toBe( document.documentElement );
		expect( hCaptcha.darkClass ).toBe( 'my-dark' );

		// Cleanup
		document.body.removeChild( customStyle );
	} );

	test( 'setDarkData leaves darkElement/darkClass unset when no styles found', () => {
		// Ensure no matching style elements are present; applyFilters passthrough
		wp.hooks.applyFilters.mockImplementationOnce( ( hook, content ) => content );

		hCaptcha.setDarkData();

		expect( hCaptcha.darkElement ).toBeNull();
		expect( hCaptcha.darkClass ).toBeNull();
	} );

	// observeDarkMode tests
	test( 'observeDarkMode does nothing if already observing', () => {
		// Arrange
		hCaptcha.observingDarkMode = true;

		const getParamsSpy = jest.spyOn( hCaptcha, 'getParams' );
		const setDarkDataSpy = jest.spyOn( hCaptcha, 'setDarkData' );
		const bindSpy = jest.spyOn( hCaptcha, 'bindEvents' );
		const MOBackup = global.MutationObserver;

		global.MutationObserver = jest.fn( function() {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
		} );

		// Act
		hCaptcha.observeDarkMode();

		// Assert
		expect( getParamsSpy ).not.toHaveBeenCalled();
		expect( setDarkDataSpy ).not.toHaveBeenCalled();
		expect( bindSpy ).not.toHaveBeenCalled();
		expect( global.MutationObserver ).not.toHaveBeenCalled();

		// Cleanup
		global.MutationObserver = MOBackup;
	} );

	test( 'observeDarkMode returns early when theme is not auto', () => {
		// Arrange
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { theme: 'light' } );

		const bindSpy = jest.spyOn( hCaptcha, 'bindEvents' );
		const MOBackup = global.MutationObserver;

		global.MutationObserver = jest.fn( function() {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
		} );

		// Precondition
		expect( hCaptcha.observingDarkMode ).toBe( false );

		// Act
		hCaptcha.observeDarkMode();

		// Assert
		expect( hCaptcha.observingDarkMode ).toBe( true );
		expect( global.MutationObserver ).not.toHaveBeenCalled();
		expect( bindSpy ).not.toHaveBeenCalled();

		// Cleanup
		global.MutationObserver = MOBackup;
	} );

	test( 'observeDarkMode sets observer and triggers bindEvents on dark class change', () => {
		// Arrange
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { theme: 'auto' } );

		const darkHost = document.createElement( 'div' );

		darkHost.className = '';

		const setDarkDataSpy = jest.spyOn( hCaptcha, 'setDarkData' ).mockImplementation( () => {
			hCaptcha.darkElement = darkHost;
			hCaptcha.darkClass = 'dark-on';
		} );
		const bindSpy = jest.spyOn( hCaptcha, 'bindEvents' );
		const MOBackup = global.MutationObserver;
		const rafBackup = global.requestAnimationFrame;
		global.requestAnimationFrame = ( cb ) => cb();
		let instance;

		global.MutationObserver = jest.fn( function( cb ) {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			this.__cb = cb;
			instance = this;
		} );

		// Act: start observing
		hCaptcha.observeDarkMode();

		// Assert: observer set on our element
		expect( setDarkDataSpy ).toHaveBeenCalled();
		expect( global.MutationObserver ).toHaveBeenCalledTimes( 1 );
		expect( instance.observe ).toHaveBeenCalledWith( darkHost, expect.objectContaining( { attributes: true, attributeOldValue: true } ) );
		expect( bindSpy ).not.toHaveBeenCalled();

		// Simulate class change that includes darkClass
		const oldVal = darkHost.getAttribute( 'class' );

		darkHost.className = 'x dark-on';
		instance.__cb( [ { oldValue: oldVal } ] );

		expect( bindSpy ).toHaveBeenCalled();

		// Cleanup
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );

	test( 'observeDarkMode does not set observer when no dark provider found', () => {
		// Arrange
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { theme: 'auto' } );
		jest.spyOn( hCaptcha, 'setDarkData' ).mockImplementation( () => {
			hCaptcha.darkElement = null;
			hCaptcha.darkClass = null;
		} );

		const bindSpy = jest.spyOn( hCaptcha, 'bindEvents' );
		const MOBackup = global.MutationObserver;

		global.MutationObserver = jest.fn( function() {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
		} );

		// Act
		hCaptcha.observeDarkMode();

		// Assert
		expect( global.MutationObserver ).not.toHaveBeenCalled();
		expect( bindSpy ).not.toHaveBeenCalled();

		// Cleanup
		global.MutationObserver = MOBackup;
	} );

	// observePasswordManagers tests
	test( 'observePasswordManagers does nothing if already observing', () => {
		// Arrange
		hCaptcha.observingPasswordManagers = true;
		const MOBackup = global.MutationObserver;
		global.MutationObserver = jest.fn( function() {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
		} );

		// Act
		hCaptcha.observePasswordManagers();

		// Assert
		expect( global.MutationObserver ).not.toHaveBeenCalled();

		// Cleanup
		global.MutationObserver = MOBackup;
	} );

	test( 'observePasswordManagers sets observer on document.body and reacts to 1Password', () => {
		// Arrange
		const MOBackup = global.MutationObserver;
		const rafBackup = global.requestAnimationFrame;
		global.requestAnimationFrame = ( cb ) => cb();
		let instance;
		global.MutationObserver = jest.fn( function( cb ) {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			this.__cb = cb;
			instance = this;
		} );

		// Build DOM form with a visible widget and submit button
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.size = 'normal';
		const submit = document.createElement( 'button' );
		submit.setAttribute( 'type', 'submit' );
		form.appendChild( widget );
		form.appendChild( submit );
		document.body.appendChild( form );

		const hCaptchaId = 'pm-1';
		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, submitButtonElement: submit, widgetId: 'w1' } );

		const aelSpy = jest.spyOn( submit, 'addEventListener' );

		// Act: start observing
		hCaptcha.observePasswordManagers();

		// Assert observer was set
		expect( global.MutationObserver ).toHaveBeenCalledTimes( 1 );
		expect( instance.observe ).toHaveBeenCalledWith( document.body, expect.objectContaining( { childList: true, subtree: true } ) );

		// Add 1Password element and trigger mutation
		const onePass = document.createElement( 'com-1password-button' );
		document.body.appendChild( onePass );
		instance.__cb( [ { type: 'childList' } ] );

		// After rAF, it should disconnect, set force, and add click listener
		expect( instance.disconnect ).toHaveBeenCalled();
		expect( widget.dataset.force ).toBe( 'true' );
		expect( aelSpy ).toHaveBeenCalledWith( 'click', hCaptcha.validate, true );

		// Cleanup
		document.body.removeChild( onePass );
		document.body.removeChild( form );
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );

	test( 'observePasswordManagers does nothing when no password manager element present', () => {
		// Arrange
		const MOBackup = global.MutationObserver;
		const rafBackup = global.requestAnimationFrame;
		global.requestAnimationFrame = ( cb ) => cb();
		let instance;
		global.MutationObserver = jest.fn( function( cb ) {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			this.__cb = cb;
			instance = this;
		} );

		// Build DOM with form and widget
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.size = 'normal';
		const submit = document.createElement( 'button' );
		submit.setAttribute( 'type', 'submit' );
		form.appendChild( widget );
		form.appendChild( submit );
		document.body.appendChild( form );

		const hCaptchaId = 'pm-2';
		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, submitButtonElement: submit, widgetId: 'w2' } );

		const aelSpy = jest.spyOn( submit, 'addEventListener' );

		// Act
		hCaptcha.observePasswordManagers();
		instance.__cb( [ { type: 'childList' } ] ); // no PM elements in DOM

		// Assert: no disconnect and no changes
		expect( instance.disconnect ).not.toHaveBeenCalled();
		expect( widget.dataset.force ).toBeUndefined();
		expect( aelSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );

	test( 'observePasswordManagers skips invisible/forced widgets and forms without submit button', () => {
		// Arrange
		const MOBackup = global.MutationObserver;
		const rafBackup = global.requestAnimationFrame;
		global.requestAnimationFrame = ( cb ) => cb();
		let instance;
		global.MutationObserver = jest.fn( function( cb ) {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			this.__cb = cb;
			instance = this;
		} );

		// Form A: invisible widget -> skip
		const formA = document.createElement( 'form' );
		const widgetA = document.createElement( 'div' );
		widgetA.className = 'h-captcha';
		widgetA.dataset.size = 'invisible';
		const submitA = document.createElement( 'button' );
		submitA.setAttribute( 'type', 'submit' );
		formA.appendChild( widgetA );
		formA.appendChild( submitA );
		document.body.appendChild( formA );
		const idA = 'pm-a';
		formA.dataset.hCaptchaId = idA;
		hCaptcha.foundForms.push( { hCaptchaId: idA, submitButtonElement: submitA, widgetId: 'wa' } );
		const aelASpy = jest.spyOn( submitA, 'addEventListener' );

		// Form B: already forced -> skip
		const formB = document.createElement( 'form' );
		const widgetB = document.createElement( 'div' );
		widgetB.className = 'h-captcha';
		widgetB.dataset.size = 'normal';
		widgetB.dataset.force = 'true';
		const submitB = document.createElement( 'button' );
		submitB.setAttribute( 'type', 'submit' );
		formB.appendChild( widgetB );
		formB.appendChild( submitB );
		document.body.appendChild( formB );
		const idB = 'pm-b';
		formB.dataset.hCaptchaId = idB;
		hCaptcha.foundForms.push( { hCaptchaId: idB, submitButtonElement: submitB, widgetId: 'wb' } );
		const aelBSpy = jest.spyOn( submitB, 'addEventListener' );

		// Form C: no submit button -> skip
		const formC = document.createElement( 'form' );
		const widgetC = document.createElement( 'div' );
		widgetC.className = 'h-captcha';
		widgetC.dataset.size = 'normal';
		formC.appendChild( widgetC );
		document.body.appendChild( formC );
		const idC = 'pm-c';
		formC.dataset.hCaptchaId = idC;
		hCaptcha.foundForms.push( { hCaptchaId: idC, submitButtonElement: null, widgetId: 'wc' } );

		// Start observing and then add the LastPass element
		hCaptcha.observePasswordManagers();
		const lastPass = document.createElement( 'div' );
		lastPass.setAttribute( 'data-lastpass-icon-root', '' );
		document.body.appendChild( lastPass );
		instance.__cb( [ { type: 'childList' } ] );

		// Assert all skipped
		expect( widgetA.dataset.force ).toBeUndefined();
		expect( aelASpy ).not.toHaveBeenCalled();
		expect( widgetB.dataset.force ).toBe( 'true' ); // remained true, no new listener
		expect( aelBSpy ).not.toHaveBeenCalled();
		// Form C has no 'submit' button; nothing to assert beyond no throw

		// Cleanup
		document.body.removeChild( lastPass );
		document.body.removeChild( formA );
		document.body.removeChild( formB );
		document.body.removeChild( formC );
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );

	test( 'observePasswordManagers ignores non-childList mutations (continue branch)', () => {
		// Arrange
		const MOBackup = global.MutationObserver;
		const rafBackup = global.requestAnimationFrame;
		global.requestAnimationFrame = ( cb ) => cb();
		let instance;
		global.MutationObserver = jest.fn( function( cb ) {
			this.observe = jest.fn();
			this.disconnect = jest.fn();
			this.__cb = cb;
			instance = this;
		} );

		// Build DOM form with a visible widget and submit button
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.size = 'normal';
		const submit = document.createElement( 'button' );
		submit.setAttribute( 'type', 'submit' );
		form.appendChild( widget );
		form.appendChild( submit );
		document.body.appendChild( form );

		const hCaptchaId = 'pm-continue';
		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, submitButtonElement: submit, widgetId: 'w-cont' } );

		const aelSpy = jest.spyOn( submit, 'addEventListener' );

		// Also add a password manager element BEFORE triggering mutations
		const onePass = document.createElement( 'com-1password-button' );
		document.body.appendChild( onePass );

		// Act: start observing and trigger a non-childList mutation
		hCaptcha.observePasswordManagers();
		instance.__cb( [ { type: 'attributes' } ] ); // should be ignored by the loop (continue)

		// Assert: observer not disconnected, and no changes applied
		expect( instance.disconnect ).not.toHaveBeenCalled();
		expect( widget.dataset.force ).toBeUndefined();
		expect( aelSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( onePass );
		document.body.removeChild( form );
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );

	// callback tests
	test( 'callback dispatches event and submits when size is invisible', () => {
		const token = 'tok-invisible';

		// Spy on submit and mock getParams
		const submitSpy = jest.spyOn( hCaptcha, 'submit' ).mockImplementation( () => {} );
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { size: 'invisible' } );

		// Listen to the dispatched event
		let eventReceived = false;
		let receivedToken = null;
		document.addEventListener( 'hCaptchaSubmitted', ( e ) => {
			eventReceived = true;
			receivedToken = e.detail.token;
		} );

		// Act
		hCaptcha.callback( token );

		// Assert
		expect( eventReceived ).toBe( true );
		expect( receivedToken ).toBe( token );
		expect( submitSpy ).toHaveBeenCalled();
	} );

	test( 'callback submits when force is true and isValidated is true', () => {
		const token = 'tok-force-validated';

		// Build DOM: widget containing the response textarea (so closest(".h-captcha") resolves)
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.force = 'true';
		const response = document.createElement( 'textarea' );
		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = token;
		widget.appendChild( response );
		form.appendChild( widget );
		document.body.appendChild( form );

		// Configure selectors and mocks
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { size: 'normal' } );
		jest.spyOn( hCaptcha, 'isValidated' ).mockReturnValue( true );
		const submitSpy = jest.spyOn( hCaptcha, 'submit' ).mockImplementation( () => {} );

		// Event listener check
		let eventReceived = false;
		document.addEventListener( 'hCaptchaSubmitted', () => {
			eventReceived = true;
		} );

		// Act
		hCaptcha.callback( token );

		// Assert
		expect( eventReceived ).toBe( true );
		expect( submitSpy ).toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'callback does not submit when force is true but isValidated is false', () => {
		const token = 'tok-force-not-validated';

		// Build DOM: widget containing response, forced
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.force = 'true';
		const response = document.createElement( 'textarea' );
		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = token;
		widget.appendChild( response );
		form.appendChild( widget );
		document.body.appendChild( form );

		// Configure
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { size: 'normal' } );
		jest.spyOn( hCaptcha, 'isValidated' ).mockReturnValue( false );
		const submitSpy = jest.spyOn( hCaptcha, 'submit' ).mockImplementation( () => {} );

		// Act
		hCaptcha.callback( token );

		// Assert
		expect( submitSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'callback does not submit when visible size and not forced', () => {
		const token = 'tok-visible-not-forced';

		// Build DOM: widget containing response, not forced
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		const response = document.createElement( 'textarea' );
		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = token;
		widget.appendChild( response );
		form.appendChild( widget );
		document.body.appendChild( form );

		// Configure
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { size: 'normal' } );
		jest.spyOn( hCaptcha, 'isValidated' ).mockReturnValue( true );
		const submitSpy = jest.spyOn( hCaptcha, 'submit' ).mockImplementation( () => {} );

		// Act
		hCaptcha.callback( token );

		// Assert
		expect( submitSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	// applyAutoTheme tests
	test( 'applyAutoTheme returns original params when theme is not auto', () => {
		const params = { theme: 'light' };
		const result = hCaptcha.applyAutoTheme( params );
		expect( result ).toBe( params );
		expect( result.theme ).toBe( 'light' );
	} );

	test( 'applyAutoTheme uses matchMedia when no darkElement and prefers dark', () => {
		const mmBackup = window.matchMedia;
		window.matchMedia = jest.fn( ( q ) => ( { matches: true, media: q, addListener: jest.fn(), removeListener: jest.fn() } ) );
		const params = { theme: 'auto' };
		const result = hCaptcha.applyAutoTheme( params );
		expect( result.theme ).toBe( 'dark' );
		window.matchMedia = mmBackup;
	} );

	test( 'applyAutoTheme uses matchMedia when no darkElement and prefers light', () => {
		const mmBackup = window.matchMedia;
		window.matchMedia = jest.fn( ( q ) => ( { matches: false, media: q, addListener: jest.fn(), removeListener: jest.fn() } ) );
		const params = { theme: 'auto' };
		const result = hCaptcha.applyAutoTheme( params );
		expect( result.theme ).toBe( 'light' );
		window.matchMedia = mmBackup;
	} );

	test( 'applyAutoTheme sets dark when darkElement has darkClass', () => {
		const host = document.createElement( 'div' );
		hCaptcha.darkElement = host;
		hCaptcha.darkClass = 'dark-on';
		host.className = 'x dark-on y';
		const params = { theme: 'auto' };
		const result = hCaptcha.applyAutoTheme( params );
		expect( result.theme ).toBe( 'dark' );
	} );

	test( 'applyAutoTheme stays light when darkElement does not have darkClass', () => {
		const host = document.createElement( 'div' );
		hCaptcha.darkElement = host;
		hCaptcha.darkClass = 'dark-on';
		host.className = 'x y';
		const params = { theme: 'auto' };
		const result = hCaptcha.applyAutoTheme( params );
		expect( result.theme ).toBe( 'light' );
	} );

	// render tests
	test( 'render sets custom theme and passes params, calls observers', () => {
		// Arrange DOM element
		const el = document.createElement( 'div' );
		el.className = 'h-captcha';
		el.dataset.size = 'normal';
		// Ensure no pre-set theme
		delete el.dataset.theme;
		document.body.appendChild( el );

		// Spies/mocks
		const obsDarkSpy = jest.spyOn( hCaptcha, 'observeDarkMode' ).mockImplementation( () => {} );
		const obsPMSpy = jest.spyOn( hCaptcha, 'observePasswordManagers' ).mockImplementation( () => {} );

		const customTheme = { component: { checkbox: { main: { fill: '#fff' } } } };
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { theme: customTheme } );

		const applySpy = jest.spyOn( hCaptcha, 'applyAutoTheme' ).mockImplementation( ( p ) => p );

		global.hcaptcha = {
			render: jest.fn( () => 'wid-custom' ),
		};

		// Act
		const wid = hCaptcha.render( el );

		// Assert observers called
		expect( obsDarkSpy ).toHaveBeenCalled();
		expect( obsPMSpy ).toHaveBeenCalled();

		// Dataset theme should be set to custom
		expect( el.dataset.theme ).toBe( 'custom' );

		// applyAutoTheme should be called with params containing our custom theme and size from dataset
		expect( applySpy ).toHaveBeenCalledWith( expect.objectContaining( { theme: customTheme, size: 'normal' } ) );

		// hcaptcha.render should receive the same
		expect( global.hcaptcha.render ).toHaveBeenCalledWith( el, expect.objectContaining( { theme: customTheme, size: 'normal' } ) );

		// return value propagated
		expect( wid ).toBe( 'wid-custom' );

		// Cleanup
		document.body.removeChild( el );
	} );

	test( 'render uses dataset theme/size when theme not object and returns widget id', () => {
		// Arrange
		const el = document.createElement( 'div' );
		el.className = 'h-captcha';
		el.dataset.theme = 'dark';
		el.dataset.size = 'invisible';
		document.body.appendChild( el );

		const obsDarkSpy = jest.spyOn( hCaptcha, 'observeDarkMode' ).mockImplementation( () => {} );
		const obsPMSpy = jest.spyOn( hCaptcha, 'observePasswordManagers' ).mockImplementation( () => {} );

		// getParams returns a plain theme that will be overwritten by dataset.theme
		jest.spyOn( hCaptcha, 'getParams' ).mockReturnValue( { theme: 'auto' } );

		const applySpy = jest.spyOn( hCaptcha, 'applyAutoTheme' ).mockImplementation( ( p ) => p );

		global.hcaptcha = {
			render: jest.fn( () => 'wid-dataset' ),
		};

		// Act
		const wid = hCaptcha.render( el );

		// Assert observer calls
		expect( obsDarkSpy ).toHaveBeenCalled();
		expect( obsPMSpy ).toHaveBeenCalled();

		// applyAutoTheme should be called with theme/size derived from dataset
		expect( applySpy ).toHaveBeenCalledWith( expect.objectContaining( { theme: 'dark', size: 'invisible' } ) );

		// hcaptcha.render called with expected params
		expect( global.hcaptcha.render ).toHaveBeenCalledWith( el, expect.objectContaining( { theme: 'dark', size: 'invisible' } ) );

		expect( wid ).toBe( 'wid-dataset' );

		// Cleanup
		document.body.removeChild( el );
	} );
} );

// addSyncedEventListener tests
describe( 'addSyncedEventListener', () => {
	let inst;

	beforeEach( () => {
		inst = new HCaptcha();
	} );

	test( 'calls callback immediately when document is not loading', () => {
		const cb = jest.fn();

		// Force readyState to a non-loading value
		const original = Object.getOwnPropertyDescriptor( document, 'readyState' );
		Object.defineProperty( document, 'readyState', { value: 'complete', configurable: true } );

		const aelSpy = jest.spyOn( window, 'addEventListener' );

		inst.addSyncedEventListener( cb );

		expect( cb ).toHaveBeenCalledTimes( 1 );
		expect( aelSpy ).not.toHaveBeenCalledWith( 'DOMContentLoaded', cb );

		// Cleanup
		aelSpy.mockRestore();
		if ( original ) {
			Object.defineProperty( document, 'readyState', original );
		} else {
			delete document.readyState;
		}
	} );

	test( 'registers DOMContentLoaded when loading and does not duplicate the same callback', () => {
		const cb = jest.fn();

		const original = Object.getOwnPropertyDescriptor( document, 'readyState' );
		Object.defineProperty( document, 'readyState', { value: 'loading', configurable: true } );

		const aelSpy = jest.spyOn( window, 'addEventListener' );

		// First registration should add a listener
		inst.addSyncedEventListener( cb );

		// The second registration with the same callback should be ignored
		inst.addSyncedEventListener( cb );

		expect( aelSpy ).toHaveBeenCalledTimes( 1 );
		expect( aelSpy ).toHaveBeenCalledWith( 'DOMContentLoaded', cb );

		// Simulate DOMContentLoaded firing
		const listener = aelSpy.mock.calls[ 0 ][ 1 ];
		listener();

		expect( cb ).toHaveBeenCalledTimes( 1 );

		// Cleanup
		aelSpy.mockRestore();
		if ( original ) {
			Object.defineProperty( document, 'readyState', original );
		} else {
			delete document.readyState;
		}
	} );
} );

// moveHP tests
describe( 'moveHP', () => {
	let inst;

	beforeEach( () => {
		inst = new HCaptcha();
	} );

	test( 'does nothing when honeypot is missing', () => {
		const form = document.createElement( 'form' );
		const a = document.createElement( 'input' ); a.name = 'a';
		const b = document.createElement( 'input' ); b.name = 'b';
		const c = document.createElement( 'input' ); c.name = 'c';
		form.appendChild( a );
		form.appendChild( b );
		form.appendChild( c );
		document.body.appendChild( form );

		inst.moveHP( form );

		// Order should be unchanged
		expect( form.children[ 0 ] ).toBe( a );
		expect( form.children[ 1 ] ).toBe( b );
		expect( form.children[ 2 ] ).toBe( c );

		document.body.removeChild( form );
	} );

	test( 'does nothing when only hidden inputs are present', () => {
		const form = document.createElement( 'form' );
		const hp = document.createElement( 'input' );
		hp.name = 'hcap_hp_main';
		hp.type = 'hidden';
		const hidden = document.createElement( 'input' );
		hidden.type = 'hidden';
		form.appendChild( hidden );
		form.appendChild( hp );
		document.body.appendChild( form );

		const beforeSibling = hp.previousSibling;
		inst.moveHP( form );

		// Should remain in place because there are no visible inputs to position before
		expect( hp.previousSibling ).toBe( beforeSibling );
		expect( hp.parentElement ).toBe( form );

		document.body.removeChild( form );
	} );

	test( 'moves honeypot before a visible input (deterministic with mocked Math.random)', () => {
		const form = document.createElement( 'form' );
		const hidden = document.createElement( 'input' ); hidden.type = 'hidden';
		const vis1 = document.createElement( 'input' ); vis1.name = 'vis1';
		const vis2 = document.createElement( 'textarea' ); vis2.name = 'vis2';
		const btn = document.createElement( 'button' ); btn.type = 'submit';
		const hp = document.createElement( 'input' ); hp.id = 'hcap_hp_test';

		// Initial order: hidden, vis1, vis2, btn, hp
		form.appendChild( hidden );
		form.appendChild( vis1 );
		form.appendChild( vis2 );
		form.appendChild( btn );
		form.appendChild( hp );
		document.body.appendChild( form );

		const originalRandom = Math.random;
		Math.random = jest.fn( () => 0 ); // Pick index 0 in filtered visible inputs => vis1

		inst.moveHP( form );

		// HP should now be before vis1 (and after hidden, since hidden is not in candidates)
		expect( form.children[ 0 ] ).toBe( hidden );
		expect( form.children[ 1 ] ).toBe( hp );
		expect( form.children[ 2 ] ).toBe( vis1 );

		Math.random = originalRandom;
		document.body.removeChild( form );
	} );
} );

// bindEvents early-return tests
describe( 'bindEvents', () => {
	test( 'returns early when global hcaptcha is undefined', () => {
		const inst = new HCaptcha();

		// Ensure wp exists even though it shouldn't be used when early-returning
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Spies for methods that would be used if the guard didn't return
		const getFormsSpy = jest.spyOn( inst, 'getForms' );
		const renderSpy = jest.spyOn( inst, 'render' );

		// Backup and unset global.hcaptcha
		const backup = global.hcaptcha;
		global.hcaptcha = undefined;

		// Call bindEvents; should return immediately without side effects
		inst.bindEvents();

		expect( getFormsSpy ).not.toHaveBeenCalled();
		expect( renderSpy ).not.toHaveBeenCalled();
		expect( inst.formSelector ).toBeUndefined();
		expect( inst.foundForms.length ).toBe( 0 );

		// Restore global.hcaptcha
		global.hcaptcha = backup;
	} );

	test( 'skips forms where .h-captcha has hcaptcha-widget-id class (no render/moveHP)', () => {
		const inst = new HCaptcha();

		// Ensure hooks exist and act as pass-through
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Define a minimal global.hcaptcha so the guard passes
		const backup = global.hcaptcha;
		global.hcaptcha = { render: jest.fn() };

		// Build a form with a skipped hCaptcha element
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha hcaptcha-widget-id'; // mark as skipped
		widget.innerHTML = 'keep'; // should remain unchanged
		form.appendChild( widget );
		document.body.appendChild( form );

		// Spies to ensure they are not called for skipped widget
		const moveHPSpy = jest.spyOn( inst, 'moveHP' );
		const renderSpy = jest.spyOn( inst, 'render' );

		// Act
		inst.bindEvents();

		// Assert: skipped — no render/moveHP, no foundForms, innerHTML intact, no dataset id
		expect( moveHPSpy ).not.toHaveBeenCalled();
		expect( renderSpy ).not.toHaveBeenCalled();
		expect( inst.foundForms.length ).toBe( 0 );
		expect( widget.innerHTML ).toBe( 'keep' );
		expect( form.dataset.hCaptchaId ).toBeUndefined();

		// Cleanup
		document.body.removeChild( form );
		global.hcaptcha = backup;
	} );

	test( 'returns early for forms without submit button (no listener added)', () => {
		const inst = new HCaptcha();

		// Ensure hooks exist and act as pass-through
		global.wp = {
			hooks: {
				addFilter: jest.fn(),
				applyFilters: jest.fn( ( hook, content ) => content ),
			},
		};

		// Minimal global.hcaptcha with render
		const backup = global.hcaptcha;
		global.hcaptcha = { render: jest.fn( () => 'wid-no-submit' ) };

		// Build a form with a regular h-captcha, but NO 'submit' button matching the selector
		const form = document.createElement( 'form' );
		const widget = document.createElement( 'div' );
		widget.className = 'h-captcha';
		widget.dataset.size = 'normal';
		form.appendChild( widget );
		document.body.appendChild( form );

		// Act
		inst.bindEvents();

		// Assert: foundForms has one entry with undefined submitButtonElement and proper widgetId
		expect( global.hcaptcha.render ).toHaveBeenCalledTimes( 1 );
		expect( inst.foundForms.length ).toBe( 1 );
		const mapped = inst.foundForms[ 0 ];
		expect( mapped.submitButtonElement ).toBeUndefined();
		expect( mapped.widgetId ).toBe( 'wid-no-submit' );
		// Form should have received a dataset id even though we returned early afterward
		expect( form.dataset.hCaptchaId ).toBeDefined();

		// Cleanup
		document.body.removeChild( form );
		global.hcaptcha = backup;
	} );
} );

// submit() tests
describe( 'submit', () => {
	let inst;

	beforeEach( () => {
		inst = new HCaptcha();
	} );

	test( 'does nothing when currentForm is undefined', () => {
		// Precondition
		expect( inst.currentForm ).toBeUndefined();

		// Spies that would be called if there was a form
		const clickSpy = jest.fn();
		// Calling submit should not throw and should not call anything
		inst.submit();
		expect( clickSpy ).not.toHaveBeenCalled();
	} );

	test( 'removes listener and clicks for ajax button or non-form element', () => {
		// Build a non-form container with a button (simulates a non-form path)
		const container = document.createElement( 'div' );
		const btn = document.createElement( 'button' );
		btn.setAttribute( 'type', 'button' ); // ajax (non-submit) button
		container.appendChild( btn );
		document.body.appendChild( container );

		const remSpy = jest.spyOn( btn, 'removeEventListener' );
		const clickSpy = jest.spyOn( btn, 'click' ).mockImplementation( () => {} );

		inst.currentForm = { formElement: container, submitButtonElement: btn };

		inst.submit();

		expect( remSpy ).toHaveBeenCalledWith( 'click', inst.validate, true );
		expect( clickSpy ).toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( container );
	} );

	test( 'uses form.requestSubmit when available', () => {
		const form = document.createElement( 'form' );
		const btn = document.createElement( 'button' );
		btn.setAttribute( 'type', 'submit' );
		form.appendChild( btn );
		document.body.appendChild( form );

		const reqSpy = jest.fn();
		form.requestSubmit = reqSpy; // available
		const submitSpy = jest.spyOn( form, 'submit' ); // should not be used
		const remSpy = jest.spyOn( btn, 'removeEventListener' ); // should not be used in this branch
		const clickSpy = jest.spyOn( btn, 'click' ); // should not be used in this branch

		inst.currentForm = { formElement: form, submitButtonElement: btn };

		// Ensure isAjaxSubmitButton returns false to take the normal path
		const ajaxSpy = jest.spyOn( inst, 'isAjaxSubmitButton' ).mockReturnValue( false );

		inst.submit();

		// For regular form path requestSubmit should be used
		expect( ajaxSpy ).toHaveBeenCalledWith( btn );
		expect( reqSpy ).toHaveBeenCalledWith( btn );
		expect( submitSpy ).not.toHaveBeenCalled();
		expect( remSpy ).not.toHaveBeenCalled();
		expect( clickSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );

	test( 'falls back to form.submit when requestSubmit is not available', () => {
		const form = document.createElement( 'form' );
		const btn = document.createElement( 'button' );
		btn.setAttribute( 'type', 'submit' );
		form.appendChild( btn );
		document.body.appendChild( form );

		// Ensure no requestSubmit: shadow prototype with undefined
		Object.defineProperty( form, 'requestSubmit', { value: undefined } );
		const formSubmitSpy = jest.spyOn( form, 'submit' ).mockImplementation( () => {} );
		const remSpy = jest.spyOn( btn, 'removeEventListener' ); // should not be used
		const clickSpy = jest.spyOn( btn, 'click' ); // should not be used

		inst.currentForm = { formElement: form, submitButtonElement: btn };

		// Force a normal path
		jest.spyOn( inst, 'isAjaxSubmitButton' ).mockReturnValue( false );

		inst.submit();

		expect( formSubmitSpy ).toHaveBeenCalled();
		expect( remSpy ).not.toHaveBeenCalled();
		expect( clickSpy ).not.toHaveBeenCalled();

		// Cleanup
		document.body.removeChild( form );
	} );
} );
