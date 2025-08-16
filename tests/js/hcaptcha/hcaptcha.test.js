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
		// Build DOM: form with submit button
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );
		form.appendChild( submit );
		document.body.appendChild( form );

		// Ensure selector is set for closest()
		hCaptcha.formSelector = 'form';

		// Map form hCaptchaId -> widgetId in foundForms
		const hCaptchaId = 'form-1';
		const widgetId = 'widget-1';

		form.dataset.hCaptchaId = hCaptchaId;
		hCaptcha.foundForms.push( { hCaptchaId, widgetId, submitButtonElement: submit } );

		// Mock event as it would be on the submit button (listener attached to button)
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
		// Build DOM: form with submit button, h-captcha widget and empty response
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
		// Build DOM: form with submit button and h-captcha widget
		const form = document.createElement( 'form' );
		const submit = document.createElement( 'button' );

		submit.setAttribute( 'type', 'submit' );

		const widget = document.createElement( 'div' );

		widget.className = 'h-captcha';

		const response = document.createElement( 'textarea' );

		response.setAttribute( 'name', 'h-captcha-response' );
		response.value = ''; // empty token to follow execute path

		form.appendChild( widget );
		form.appendChild( response );
		form.appendChild( submit );
		document.body.appendChild( form );

		// Setup selectors and found form mapping
		hCaptcha.formSelector = 'form';
		hCaptcha.responseSelector = 'textarea[name="h-captcha-response"]';

		const hCaptchaId = 'isvalidated-1';
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
		form.dataset.hCaptchaId = 'no-map-isvalidated';

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

		// Build DOM form with visible widget and submit button
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

		// Start observing and then add LastPass element
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
		// form C has no submit button; nothing to assert beyond no throw

		// Cleanup
		document.body.removeChild( lastPass );
		document.body.removeChild( formA );
		document.body.removeChild( formB );
		document.body.removeChild( formC );
		global.MutationObserver = MOBackup;
		global.requestAnimationFrame = rafBackup;
	} );
} );
