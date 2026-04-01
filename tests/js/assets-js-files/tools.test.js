// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* eslint-disable no-unused-vars */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const baseMock = {
	clearMessage: jest.fn(),
	showSuccessMessage: jest.fn(),
	showErrorMessage: jest.fn(),
};

global.hCaptchaSettingsBase = baseMock;

const defaultToolsObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	exportAction: 'hcap_export',
	exportFailed: 'Export failed.',
	exportNonce: 'nonce-export',
	importAction: 'hcap_import',
	importFailed: 'Import failed.',
	importNonce: 'nonce-import',
	selectJsonFile: 'Please select a JSON file.',
	toggleSectionAction: 'hcap_toggle_section',
	toggleSectionNonce: 'nonce-toggle',
};

global.HCaptchaToolsObject = { ...defaultToolsObject };

// Mock Blob / URL APIs used in export success handler.
global.Blob = function( content, options ) {
	this.content = content;
	this.type = options && options.type;
};
global.URL.createObjectURL = jest.fn( () => 'blob:mock-url' );
global.URL.revokeObjectURL = jest.fn();

function getDom( { includeKeysChecked = false, includeKeysImportChecked = false, withFile = false } = {} ) {
	return `
<div id="hcaptcha-tools">
	<input type="checkbox" id="include_keys" ${ includeKeysChecked ? 'checked' : '' } />
	<button id="hcaptcha-export-btn" type="button">Export</button>

	<input type="checkbox" id="include_keys_import" ${ includeKeysImportChecked ? 'checked' : '' } />
	<input type="file" id="hcaptcha-import-file" />
	<button id="hcaptcha-import-btn" type="button">Import</button>

	<span class="hcaptcha-file-name" data-empty="No file chosen">No file chosen</span>
</div>
	`.trim();
}

function bootTools( domOptions = {}, objectOverrides = {} ) {
	jest.resetModules();
	$( document ).off();
	document.body.innerHTML = getDom( domOptions );
	global.HCaptchaToolsObject = { ...defaultToolsObject, ...objectOverrides };
	require( '../../../assets/js/tools.js' );
	window.hCaptchaTools( $ );
}

/**
 * Helper: mock $.ajax to invoke callbacks synchronously.
 *
 * @param {Object} callbacks Keys: success, error, complete — each called with provided args.
 * @return {jest.SpyInstance} The spy instance wrapping $.ajax.
 */
function mockAjax( callbacks ) {
	return jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
		if ( callbacks.success !== undefined ) {
			opts.success( callbacks.success );
		}
		if ( callbacks.error !== undefined ) {
			opts.error();
		}
		if ( callbacks.complete !== undefined ) {
			opts.complete();
		}
	} );
}

describe( 'tools.js', () => {
	let ajaxSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		URL.createObjectURL.mockClear();
		URL.revokeObjectURL.mockClear();
	} );

	afterEach( () => {
		if ( ajaxSpy ) {
			ajaxSpy.mockRestore();
			ajaxSpy = null;
		}
	} );

	// ── Export ────────────────────────────────────────────────────────────────

	test( 'export: sends correct ajax payload and re-enables button on complete', () => {
		bootTools();
		const calls = [];
		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
			calls.push( opts );
			opts.complete();
		} );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( baseMock.clearMessage ).toHaveBeenCalled();
		expect( calls.length ).toBe( 1 );
		expect( calls[ 0 ].url ).toBe( defaultToolsObject.ajaxUrl );
		expect( calls[ 0 ].type ).toBe( 'POST' );
		expect( calls[ 0 ].data.action ).toBe( defaultToolsObject.exportAction );
		expect( calls[ 0 ].data.nonce ).toBe( defaultToolsObject.exportNonce );
		expect( calls[ 0 ].data.include_keys ).toBe( '' );
		// Button re-enabled by complete().
		expect( $( '#hcaptcha-export-btn' ).prop( 'disabled' ) ).toBe( false );
	} );

	test( 'export: sends include_keys=on when checkbox is checked', () => {
		bootTools( { includeKeysChecked: true } );
		const calls = [];
		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
			calls.push( opts );
		} );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( calls[ 0 ].data.include_keys ).toBe( 'on' );
	} );

	test( 'export: success with truthy response creates blob and triggers download', () => {
		bootTools();
		const response = { success: true, data: { settings: {} } };
		ajaxSpy = mockAjax( { success: response, complete: true } );

		const appendSpy = jest.spyOn( document.body, 'appendChild' );
		const removeSpy = jest.spyOn( document.body, 'removeChild' );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( URL.createObjectURL ).toHaveBeenCalledTimes( 1 );
		expect( appendSpy ).toHaveBeenCalledTimes( 1 );
		expect( URL.revokeObjectURL ).toHaveBeenCalledWith( 'blob:mock-url' );
		expect( removeSpy ).toHaveBeenCalledTimes( 1 );

		appendSpy.mockRestore();
		removeSpy.mockRestore();
	} );

	test( 'export: success false with response.data.message shows that message', () => {
		bootTools();
		const response = { success: false, data: { message: 'Custom export error.' } };
		ajaxSpy = mockAjax( { success: response, complete: true } );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( 'Custom export error.' );
	} );

	test( 'export: success false without response.data.message shows exportFailed', () => {
		bootTools();
		const response = { success: false };
		ajaxSpy = mockAjax( { success: response, complete: true } );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( defaultToolsObject.exportFailed );
	} );

	test( 'export: ajax error shows exportFailed', () => {
		bootTools();
		ajaxSpy = mockAjax( { error: true, complete: true } );

		$( '#hcaptcha-export-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( defaultToolsObject.exportFailed );
	} );

	// ── Import ────────────────────────────────────────────────────────────────

	test( 'import: shows selectJsonFile error when no file is selected', () => {
		bootTools();
		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( () => {} );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( defaultToolsObject.selectJsonFile );
		expect( ajaxSpy ).not.toHaveBeenCalled();
	} );

	test( 'import: sends correct ajax payload with file and re-enables button on complete', () => {
		bootTools();

		// Attach a fake file to the file input.
		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		const calls = [];
		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
			calls.push( opts );
			opts.complete();
		} );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.clearMessage ).toHaveBeenCalled();
		expect( calls.length ).toBe( 1 );
		expect( calls[ 0 ].url ).toBe( defaultToolsObject.ajaxUrl );
		expect( calls[ 0 ].type ).toBe( 'POST' );
		expect( calls[ 0 ].processData ).toBe( false );
		expect( calls[ 0 ].contentType ).toBe( false );
		expect( calls[ 0 ].data ).toBeInstanceOf( FormData );
		// Button re-enabled by complete().
		expect( $( '#hcaptcha-import-btn' ).prop( 'disabled' ) ).toBe( false );
	} );

	test( 'import: sends include_keys_import=on when checkbox is checked', () => {
		bootTools( { includeKeysImportChecked: true } );

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		const calls = [];
		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
			calls.push( opts );
		} );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		// Verify FormData contains include_keys_import=on.
		const formData = calls[ 0 ].data;
		expect( formData.get( 'include_keys_import' ) ).toBe( 'on' );
	} );

	test( 'import: success response shows success message', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		ajaxSpy = mockAjax( { success: { success: true, data: { message: 'Imported!' } }, complete: true } );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( 'Imported!' );
	} );

	test( 'import: success false with response.data.message shows that message', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		ajaxSpy = mockAjax( { success: { success: false, data: { message: 'Custom import error.' } }, complete: true } );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( 'Custom import error.' );
	} );

	test( 'import: success false without response.data.message shows importFailed', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		ajaxSpy = mockAjax( { success: { success: false }, complete: true } );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( defaultToolsObject.importFailed );
	} );

	test( 'import: ajax error shows importFailed', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		ajaxSpy = mockAjax( { error: true, complete: true } );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( defaultToolsObject.importFailed );
	} );

	test( 'import: complete clears file input value', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'settings.json', { type: 'application/json' } );
		Object.defineProperty( document.getElementById( 'hcaptcha-import-file' ), 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		ajaxSpy = jest.spyOn( $, 'ajax' ).mockImplementation( ( opts ) => {
			opts.complete();
		} );

		$( '#hcaptcha-import-btn' ).trigger( 'click' );

		expect( $( '#hcaptcha-import-file' ).val() ).toBe( '' );
	} );

	// ── File input change ─────────────────────────────────────────────────────

	test( 'file input change with file updates label and adds is-selected class', () => {
		bootTools();

		const fakeFile = new File( [ '{}' ], 'my-settings.json', { type: 'application/json' } );
		const input = document.getElementById( 'hcaptcha-import-file' );

		Object.defineProperty( input, 'files', {
			value: [ fakeFile ],
			configurable: true,
		} );

		$( '#hcaptcha-import-file' ).trigger( 'change' );

		const $label = $( '.hcaptcha-file-name' );
		expect( $label.text() ).toBe( 'my-settings.json' );
		expect( $label.hasClass( 'is-selected' ) ).toBe( true );
	} );

	test( 'file input change without file resets label and removes is-selected class', () => {
		bootTools();

		const input = document.getElementById( 'hcaptcha-import-file' );

		// First simulate a file selected to add the class, then clear.
		$( '.hcaptcha-file-name' ).addClass( 'is-selected' ).text( 'old-file.json' );

		Object.defineProperty( input, 'files', {
			value: [],
			configurable: true,
		} );

		$( '#hcaptcha-import-file' ).trigger( 'change' );

		const $label = $( '.hcaptcha-file-name' );
		expect( $label.text() ).toBe( 'No file chosen' );
		expect( $label.hasClass( 'is-selected' ) ).toBe( false );
	} );
} );
