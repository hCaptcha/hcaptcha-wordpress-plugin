/* global HCaptchaListPageBaseObject */
// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mocks for base and list page objects used by events.js
const baseMock = {
	showSuccessMessage: jest.fn(),
	showErrorMessage: jest.fn(),
};

global.hCaptchaSettingsBase = baseMock;

global.HCaptchaListPageBaseObject = {
	noAction: 'No action selected',
	noItems: 'No items selected',
	DoingBulk: 'Doing bulk...',
};

// Default events object
const defaultEventsObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	bulkAction: 'hcap_events_bulk',
	bulkNonce: 'nonce-xyz',
	bulkMessage: 'You can perform bulk actions',
	succeedLabel: 'Passed',
	failedLabel: 'Failed',
	succeed: [ { x: Date.now(), y: 3 } ],
	failed: [ { x: Date.now(), y: 1 } ],
	unit: 'day',
};

global.HCaptchaEventsObject = { ...defaultEventsObject };

// Chart mock (constructor spy)
const ChartMock = jest.fn();

global.Chart = ChartMock;

function getDom( { withButton = true } = {} ) {
	return `
<html lang="en">
<body>
<div id="wpwrap">
	<div class="hcaptcha-header-bar"></div>
	<canvas id="eventsChart"></canvas>
	<form id="events-form">
		<select name="action">
			<option value="-1" selected>— Bulk actions —</option>
			<option value="delete">Delete</option>
		</select>
		<label><input type="checkbox" name="bulk-checkbox[]" value="101" /></label>
		<label><input type="checkbox" name="bulk-checkbox[]" value="202" /></label>
		${ withButton ? '<button id="doaction" type="button">Apply</button>' : '' }
	</form>
	<div id="hcaptcha-message"></div>
</div>
</body>
</html>
	`;
}

function bootEvents( domOverrides = {} ) {
	document.body.innerHTML = getDom( domOverrides );
	Object.assign( window.HCaptchaEventsObject, defaultEventsObject );
	// (Re)require the module only once; it attaches window.hCaptchaEvents
	try {
		// eslint-disable-next-line global-require
		require( '../../../assets/js/events.js' );
	} catch ( e ) {
		// module may already be loaded in Jest cache; ignore
	}
	// Trigger jQuery ready by calling the exported function directly
	window.hCaptchaEvents( $ );
}

describe( 'events.js', () => {
	let postSpy;
	const originalInnerWidth = window.innerWidth;

	beforeEach( () => {
		jest.clearAllMocks();
		ChartMock.mockClear();
		postSpy = jest.spyOn( $, 'post' );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		Object.defineProperty( window, 'innerWidth', { value: originalInnerWidth, configurable: true } );
	} );

	test( 'initChart constructs Chart with labels/data and correct aspect ratio (wide)', () => {
		Object.defineProperty( window, 'innerWidth', { value: 1024, configurable: true } );
		bootEvents();
		expect( ChartMock ).toHaveBeenCalledTimes( 1 );
		const args = ChartMock.mock.calls[ 0 ];
		// args[0] is the ctx (canvas), args[1] is the config
		const cfg = args[ 1 ];
		expect( cfg.type ).toBe( 'bar' );
		expect( cfg.data.datasets[ 0 ].label ).toBe( defaultEventsObject.succeedLabel );
		expect( cfg.data.datasets[ 0 ].data ).toEqual( defaultEventsObject.succeed );
		expect( cfg.data.datasets[ 1 ].label ).toBe( defaultEventsObject.failedLabel );
		expect( cfg.data.datasets[ 1 ].data ).toEqual( defaultEventsObject.failed );
		expect( cfg.options.aspectRatio ).toBe( 3 );
		expect( cfg.options.scales.x.time.unit ).toBe( defaultEventsObject.unit );
	} );

	test( 'initChart sets aspect ratio 2 on small screens', () => {
		Object.defineProperty( window, 'innerWidth', { value: 480, configurable: true } );
		bootEvents();
		const cfg = ChartMock.mock.calls[ 0 ][ 1 ];
		expect( cfg.options.aspectRatio ).toBe( 2 );
	} );

	test( 'on ready shows bulk message and attaches click handler when #doaction exists', () => {
		bootEvents();
		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( defaultEventsObject.bulkMessage );
		// Simulate click to ensure a handler is attached (will trigger early noAction branch by default)
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noAction );
	} );

	test( 'gracefully handles missing #doaction (no listener) but still shows message', () => {
		bootEvents( { withButton: false } );
		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( defaultEventsObject.bulkMessage );
		// No button means anything to click. Ensure no exception and Chart was created
		expect( ChartMock ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'bulk action early return when action == -1 shows noAction and does not post', () => {
		bootEvents();
		postSpy.mockImplementation( () => $.Deferred() );
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noAction );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'bulk action with no ids shows noItems and does not post', () => {
		bootEvents();
		$( 'select[name="action"]' ).val( 'delete' );
		// Ensure all checkboxes are unchecked
		$( 'input[name="bulk-checkbox[]"]' ).prop( 'checked', false );
		postSpy.mockImplementation( () => $.Deferred() );
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noItems );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'beforeSend shows DoingBulk; success:false shows error and no reload', async () => {
		bootEvents();
		$( 'select[name="action"]' ).val( 'delete' );
		$( 'input[name="bulk-checkbox[]"]' ).first().prop( 'checked', true );

		const d = $.Deferred();
		postSpy.mockImplementation( ( opts ) => {
			// Call beforeSend immediately
			opts?.beforeSend?.();
			return d;
		} );

		// Spy on console.error to detect jsdom navigation/reload attempts
		const consoleSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {
		} );

		$( '#doaction' ).trigger( 'click' );
		// Resolve with failure
		d.resolve( { success: false, data: 'Bad request' } );
		await Promise.resolve();

		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.DoingBulk );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( 'Bad request' );
		// No reload attempt expected on failure
		expect( consoleSpy ).not.toHaveBeenCalled();

		consoleSpy.mockRestore();
	} );

	test( 'success:true triggers window.location.reload and posts expected data payload', async () => {
		bootEvents();
		$( 'select[name="action"]' ).val( 'delete' );

		const $inputBulkCheckbox = $( 'input[name="bulk-checkbox[]"]' );

		$inputBulkCheckbox.eq( 0 ).prop( 'checked', true );
		$inputBulkCheckbox.eq( 1 ).prop( 'checked', true );

		let captured;
		const d = $.Deferred();
		postSpy.mockImplementation( ( opts ) => {
			captured = opts;
			opts?.beforeSend?.();
			return d;
		} );

		// Spy on console.error to observe jsdom navigation error produced by reload()
		const consoleSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {
		} );

		$( '#doaction' ).trigger( 'click' );
		d.resolve( { success: true, data: 'Done' } );
		await Promise.resolve();

		// Reload in jsdom triggers a navigation not implemented error; treat it as a proxy for a reload attempt
		expect( consoleSpy ).toHaveBeenCalled();
		expect( captured ).toBeTruthy();
		expect( captured.url ).toBe( defaultEventsObject.ajaxUrl );
		expect( captured.data.action ).toBe( defaultEventsObject.bulkAction );
		expect( captured.data.nonce ).toBe( defaultEventsObject.bulkNonce );
		expect( captured.data.bulk ).toBe( 'delete' );
		// IDs should be a JSON string of the checked values
		const ids = JSON.parse( captured.data.ids );
		expect( ids ).toEqual( [ '101', '202' ] );
		consoleSpy.mockRestore();
	} );

	test( 'fail path shows error message statusText', async () => {
		bootEvents();
		$( 'select[name="action"]' ).val( 'delete' );
		$( 'input[name="bulk-checkbox[]"]' ).first().prop( 'checked', true );

		const d = $.Deferred();
		postSpy.mockImplementation( () => d );

		$( '#doaction' ).trigger( 'click' );
		d.reject( { statusText: 'Boom' } );
		await Promise.resolve();

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( 'Boom' );
	} );
} );
