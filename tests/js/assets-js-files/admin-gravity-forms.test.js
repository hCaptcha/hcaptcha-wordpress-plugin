// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mock HCaptchaGravityFormsObject
global.HCaptchaGravityFormsObject = {
	OKBtnText: 'OK',
	noticeDescription: 'Test Notice Description',
	noticeLabel: 'Test Notice Label',
	onlyOne: 'Only one hCaptcha field is allowed per form',
};

// Mock gform
global.gform = {
	addFilter: jest.fn(),
};

// Mock GetFieldsByType
global.GetFieldsByType = jest.fn();

// Mock kaggDialog
global.kaggDialog = {
	confirm: jest.fn(),
};

function getDom() {
	// language=HTML
	return `
		<nav class="gform-settings__navigation">
			<a href="#" class="first-child">
				<span class="icon"><i class="gform-icon"></i></span>
				<span class="label">First Item</span>
			</a>
		</nav>
		<div class="gform-settings__content"></div>
		<form id="test-form"></form>
	`;
}

describe( 'admin-gravity-forms', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		// Mock hCaptchaBindEvents
		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		// Set up DOM
		document.body.innerHTML = getDom();

		// Reset window.hCaptchaGravityForms
		window.hCaptchaGravityForms = undefined;

		// Reset mocks
		global.gform.addFilter.mockClear();
		global.GetFieldsByType.mockClear();
		global.kaggDialog.confirm.mockClear();

		// Force reloading the tested file
		jest.resetModules();

		// Load the script
		require( '../../../assets/js/admin-gravity-forms.js' );
	} );

	afterEach( () => {
		// Clear hCaptchaBindEvents mock
		global.hCaptchaBindEvents.mockClear();
	} );

	test( 'init function initializes the application', () => {
		// Check that the window.hCaptchaGravityForms object is created
		expect( window.hCaptchaGravityForms ).toBeDefined();
		expect( window.SetDefaultValues_hcaptcha ).toBe( window.hCaptchaGravityForms.setDefaultValues );
	} );

	test( 'loaded function calls addFieldFilter', () => {
		// Spy on addFieldFilter
		const addFieldFilterSpy = jest.spyOn( window.hCaptchaGravityForms, 'addFieldFilter' );

		// Trigger DOMContentLoaded event
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );

		// Check that addFieldFilter was called
		expect( addFieldFilterSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'ready function calls bindFieldAddedEvent, addHCaptchaSettings, and bindHCaptchaNavClick', () => {
		// Spy on the functions
		const bindFieldAddedEventSpy = jest.spyOn( window.hCaptchaGravityForms, 'bindFieldAddedEvent' );
		const addHCaptchaSettingsSpy = jest.spyOn( window.hCaptchaGravityForms, 'addHCaptchaSettings' );
		const bindHCaptchaNavClickSpy = jest.spyOn( window.hCaptchaGravityForms, 'bindHCaptchaNavClick' );

		// Directly call the ready function
		window.hCaptchaGravityForms.ready();

		// Check that the functions were called
		expect( bindFieldAddedEventSpy ).toHaveBeenCalledTimes( 1 );
		expect( addHCaptchaSettingsSpy ).toHaveBeenCalledTimes( 1 );
		expect( bindHCaptchaNavClickSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'setDefaultValues sets the correct default values for hCaptcha field', () => {
		const field = {};
		const result = window.hCaptchaGravityForms.setDefaultValues( field );

		expect( result.inputs ).toBeNull();
		expect( result.displayOnly ).toBe( true );
		expect( result.label ).toBe( 'hCaptcha' );
		expect( result.labelPlacement ).toBe( 'hidden_label' );
	} );

	test( 'addFieldFilter adds a filter to limit hCaptcha field to one per form', () => {
		// Call addFieldFilter
		window.hCaptchaGravityForms.addFieldFilter();

		// Check that gform.addFilter was called with the correct arguments
		expect( global.gform.addFilter ).toHaveBeenCalledWith(
			'gform_form_editor_can_field_be_added',
			expect.any( Function ),
		);

		// Get the filter function
		const filterFunction = global.gform.addFilter.mock.calls[ 0 ][ 1 ];

		// Test when type is not hcaptcha
		expect( filterFunction( true, 'other' ) ).toBe( true );

		// Test when the type is hcaptcha but no existing hcaptcha fields
		global.GetFieldsByType.mockReturnValueOnce( [] );
		expect( filterFunction( true, 'hcaptcha' ) ).toBe( true );

		// Test when the type is hcaptcha and there are existing hcaptcha fields
		global.GetFieldsByType.mockReturnValueOnce( [ { id: 1 } ] );
		expect( filterFunction( true, 'hcaptcha' ) ).toBe( false );
		expect( global.kaggDialog.confirm ).toHaveBeenCalledWith( {
			title: global.HCaptchaGravityFormsObject.onlyOne,
			content: '',
			type: 'info',
			buttons: {
				ok: {
					text: global.HCaptchaGravityFormsObject.OKBtnText,
				},
			},
		} );
	} );

	test( 'bindFieldAddedEvent binds the gform_field_added event', () => {
		// Remove the existing hook.
		$( document ).off( 'gform_field_added' );

		// Call bindFieldAddedEvent
		window.hCaptchaGravityForms.bindFieldAddedEvent();

		// Create a field object
		const field = { type: 'hcaptcha' };

		// Trigger the gform_field_added event
		$( document ).trigger( 'gform_field_added', [ null, field ] );

		// Check that hCaptchaBindEvents was called
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );

		// Test with a different field type
		field.type = 'other';
		$( document ).trigger( 'gform_field_added', [ null, field ] );

		// hCaptchaBindEvents should not be called again
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	const hcaptchaNav = 'a.hcaptcha-nav';

	test( 'addHCaptchaSettings adds hCaptcha settings to the GF settings', () => {
		// Call addHCaptchaSettings
		window.hCaptchaGravityForms.addHCaptchaSettings();

		// Check that the hCaptcha nav item was added
		const hCaptchaNav = $( hcaptchaNav );
		expect( hCaptchaNav.length ).toBe( 1 );
		expect( hCaptchaNav.attr( 'href' ) ).toBe( '#' );
		expect( hCaptchaNav.find( 'span.icon i' ).hasClass( 'gform-icon--hcaptcha' ) ).toBe( true );
		expect( hCaptchaNav.find( 'span.label' ).text() ).toBe( 'hCaptcha' );
	} );

	test( 'bindHCaptchaNavClick binds click event for hCaptcha navigation item', () => {
		// Call bindHCaptchaNavClick
		window.hCaptchaGravityForms.bindHCaptchaNavClick();

		// Add hCaptcha nav item
		window.hCaptchaGravityForms.addHCaptchaSettings();

		// Click on the hCaptcha nav item
		$( hcaptchaNav ).trigger( 'click' );

		// Check that the hCaptcha nav item has the active class
		expect( $( hcaptchaNav ).hasClass( 'active' ) ).toBe( true );

		// Check that the content was updated
		const content = $( '.gform-settings__content' );
		expect( content.find( '.gform-settings-panel__title' ).text() ).toBe( global.HCaptchaGravityFormsObject.noticeLabel );
		expect( content.find( '.gform-kitchen-sink' ).text() ).toBe( global.HCaptchaGravityFormsObject.noticeDescription );
	} );

	test( 'addFieldFilter does nothing when gform is undefined', () => {
		// Save the original gform
		const originalGform = global.gform;

		// Set gform to undefined
		global.gform = undefined;

		// Call addFieldFilter
		window.hCaptchaGravityForms.addFieldFilter();

		// Restore gform
		global.gform = originalGform;

		// Check that gform.addFilter was not called
		expect( global.gform.addFilter ).not.toHaveBeenCalled();
	} );

	test( 'addHCaptchaSettings does nothing when nav element is not found', () => {
		// Remove the nav element
		$( 'nav.gform-settings__navigation' ).remove();

		// Call addHCaptchaSettings
		window.hCaptchaGravityForms.addHCaptchaSettings();

		// Check that no hCaptcha nav item was added
		expect( $( hcaptchaNav ).length ).toBe( 0 );
	} );
} );
