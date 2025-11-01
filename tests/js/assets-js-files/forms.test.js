/* global HCaptchaListPageBaseObject */
// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Mocks for base and list page objects used by forms.js
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

// Default forms object
const defaultFormsObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	bulkAction: 'hcap_forms_bulk',
	bulkNonce: 'nonce-forms',
	bulkMessage: 'You can perform bulk actions on forms',
	servedLabel: 'Served',
	served: [ { x: Date.now(), y: 5 } ],
	unit: 'day',
};

global.HCaptchaFormsObject = { ...defaultFormsObject };

// Chart mock (constructor spy)
const ChartMock = jest.fn();

global.Chart = ChartMock;

function getDom( { withButton = true, withDatepicker = true } = {} ) {
	return `
<html lang="en">
<body>
<div id="wpwrap">
	<div class="hcaptcha-header-bar"></div>
	<canvas id="formsChart"></canvas>
	<form id="forms-form">
		<select name="action">
			<option value="-1" selected>— Bulk actions —</option>
			<option value="export">Export</option>
		</select>
		<table class="wp-list-table widefat fixed striped table-view-list">
			<tbody>
				<tr>
					<th scope="row" class="check-column">
						<label>
							<input type="checkbox" name="bulk-checkbox[]" value="101" />
						</label>
					</th>
					<td class="name"><span class="hcaptcha-excerpt" data-source="gravityforms"></span></td>
					<td class="form_id">12</td>
				</tr>
				<tr>
					<th scope="row" class="check-column">
						<label>
							<input type="checkbox" name="bulk-checkbox[]" value="202" />
						</label>
					</th>
					<td class="name"><span class="hcaptcha-excerpt" data-source="wpforms"></span></td>
					<td class="form_id">34</td>
				</tr>
			</tbody>
		</table>
		${ withButton ? '<button id="doaction" type="button">Apply</button>' : '' }
		${ withDatepicker ? '<input type="date" id="hcaptcha-datepicker" value="2025-11-01" />' : '' }
	</form>
	<div id="hcaptcha-message"></div>
</div>
</body>
</html>
	`;
}

function bootForms( domOverrides = {} ) {
	document.body.innerHTML = getDom( domOverrides );
	Object.assign( window.HCaptchaFormsObject, defaultFormsObject );
	// (Re)require the module only once; it attaches window.hCaptchaForms
	try {
		// eslint-disable-next-line global-require
		require( '../../../assets/js/forms.js' );
	} catch ( e ) {
		// module may already be loaded in Jest cache; ignore
	}
	// Trigger jQuery ready by calling the exported function directly
	window.hCaptchaForms( $ );
}

describe( 'forms.js', () => {
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

	test( 'initChart constructs Chart with label/data and correct aspect ratio (wide)', () => {
		Object.defineProperty( window, 'innerWidth', { value: 1024, configurable: true } );
		bootForms();
		expect( ChartMock ).toHaveBeenCalledTimes( 1 );
		const cfg = ChartMock.mock.calls[ 0 ][ 1 ];
		expect( cfg.type ).toBe( 'bar' );
		expect( cfg.data.datasets[ 0 ].label ).toBe( defaultFormsObject.servedLabel );
		expect( cfg.data.datasets[ 0 ].data ).toEqual( defaultFormsObject.served );
		expect( cfg.options.aspectRatio ).toBe( 3 );
		expect( cfg.options.scales.x.time.unit ).toBe( defaultFormsObject.unit );
	} );

	test( 'initChart sets aspect ratio 2 on small screens', () => {
		Object.defineProperty( window, 'innerWidth', { value: 480, configurable: true } );
		bootForms();
		const cfg = ChartMock.mock.calls[ 0 ][ 1 ];
		expect( cfg.options.aspectRatio ).toBe( 2 );
	} );

	test( 'on ready shows bulk message and attaches click handler when #doaction exists', () => {
		bootForms();
		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( defaultFormsObject.bulkMessage );
		// Simulate click to ensure a handler is attached (will trigger early noAction branch by default)
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noAction );
	} );

	test( 'gracefully handles missing #doaction (no listener) but still shows message', () => {
		bootForms( { withButton: false } );
		expect( baseMock.showSuccessMessage ).toHaveBeenCalledWith( defaultFormsObject.bulkMessage );
		// No button means anything to click. Ensure no exception and Chart was created
		expect( ChartMock ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'bulk action early return when action == -1 shows noAction and does not post', () => {
		bootForms();
		postSpy.mockImplementation( () => $.Deferred() );
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noAction );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'bulk action with no ids shows noItems and does not post', () => {
		bootForms();
		$( 'select[name="action"]' ).val( 'export' );
		// Ensure all checkboxes are unchecked
		$( 'input[name="bulk-checkbox[]"]' ).prop( 'checked', false );
		postSpy.mockImplementation( () => $.Deferred() );
		$( '#doaction' ).trigger( 'click' );
		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( HCaptchaListPageBaseObject.noItems );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	test( 'payload includes mapped ids [{source, formId}] and datepicker value; success:true reloads', async () => {
		bootForms();
		$( 'select[name="action"]' ).val( 'export' );
		const $checks = $( 'input[name="bulk-checkbox[]"]' );
		$checks.eq( 0 ).prop( 'checked', true );
		$checks.eq( 1 ).prop( 'checked', true );

		let captured;
		const d = $.Deferred();
		postSpy.mockImplementation( ( opts ) => {
			captured = opts;
			opts?.beforeSend?.();
			return d;
		} );

		// Spy on console.error to observe jsdom navigation error produced by reload()
		const consoleSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {} );

		$( '#doaction' ).trigger( 'click' );
		d.resolve( { success: true, data: 'Done' } );
		await Promise.resolve();

		// Reload in jsdom triggers a navigation not implemented error; treat it as a proxy for a reload attempt
		expect( consoleSpy ).toHaveBeenCalled();
		expect( captured ).toBeTruthy();
		expect( captured.url ).toBe( defaultFormsObject.ajaxUrl );
		expect( captured.data.action ).toBe( defaultFormsObject.bulkAction );
		expect( captured.data.nonce ).toBe( defaultFormsObject.bulkNonce );
		expect( captured.data.bulk ).toBe( 'export' );
		// Datepicker present in default DOM with a value
		expect( captured.data.date ).toBe( '2025-11-01' );
		const ids = JSON.parse( captured.data.ids );
		expect( ids ).toEqual( [
			{ source: 'gravityforms', formId: '12' },
			{ source: 'wpforms', formId: '34' },
		] );
		consoleSpy.mockRestore();
	} );

	test( 'when datepicker is absent, data.date is empty string', async () => {
		bootForms( { withDatepicker: false } );
		$( 'select[name="action"]' ).val( 'export' );
		const $checks = $( 'input[name="bulk-checkbox[]"]' );
		$checks.eq( 0 ).prop( 'checked', true );

		let captured;
		const d = $.Deferred();
		postSpy.mockImplementation( ( opts ) => {
			captured = opts;
			return d;
		} );

		$( '#doaction' ).trigger( 'click' );
		// do not need to resolve; just check the payload prepared
		expect( captured.data.date ).toBe( '' );
	} );

	test( 'beforeSend shows DoingBulk; success:false shows error and no reload', async () => {
		bootForms();
		$( 'select[name="action"]' ).val( 'export' );
		$( 'input[name="bulk-checkbox[]"]' ).first().prop( 'checked', true );

		const d = $.Deferred();
		postSpy.mockImplementation( ( opts ) => {
			// Call beforeSend immediately
			opts?.beforeSend?.();
			return d;
		} );

		const consoleSpy = jest.spyOn( console, 'error' ).mockImplementation( () => {} );

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

	test( 'fail path shows error message statusText', async () => {
		bootForms();
		$( 'select[name="action"]' ).val( 'export' );
		$( 'input[name="bulk-checkbox[]"]' ).first().prop( 'checked', true );

		const d = $.Deferred();
		postSpy.mockImplementation( () => d );

		$( '#doaction' ).trigger( 'click' );
		d.reject( { statusText: 'Boom' } );
		await Promise.resolve();

		expect( baseMock.showErrorMessage ).toHaveBeenCalledWith( 'Boom' );
	} );
} );
