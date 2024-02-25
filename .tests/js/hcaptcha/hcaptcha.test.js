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
		expect( hCaptcha.getFoundFormById( 'non-existent-id' ) ).toBeUndefined();
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
		// Mock hcaptcha object
		global.hcaptcha = {
			render: jest.fn( ( hcaptchaElement ) => {
				// Mock the rendering of the hCaptcha widget by adding a dataset attribute
				const iframe = document.createElement( 'iframe' );
				iframe.dataset.hcaptchaWidgetId = 'mock-widget-id';
				iframe.dataset.hcaptchaResponse = '';
				hcaptchaElement.appendChild( iframe );
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
		expect( global.hcaptcha.reset ).toHaveBeenCalledWith( 'mock-widget-id' );

		// Clean up DOM elements
		document.body.removeChild( form1 );
		document.body.removeChild( form2 );
		document.body.removeChild( form3 );
	} );
} );
