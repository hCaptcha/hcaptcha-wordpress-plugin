// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* eslint-disable no-unused-vars,import/no-extraneous-dependencies */

// whatwg-url serializer — same registry as jsdom since it's a plain Node module.
const whatwgURL = require( 'whatwg-url' );

/**
 * Return the LocationImpl instance backing window.location by inspecting its
 * own Symbol-keyed properties for the one that owns _locationObjectSetterNavigate.
 *
 * @return {Object} The actual LocationImpl instance used by jsdom.
 */
function getLocationImplInstance() {
	for ( const sym of Object.getOwnPropertySymbols( window.location ) ) {
		const val = window.location[ sym ];

		if ( val && typeof val._locationObjectSetterNavigate === 'function' ) {
			return val;
		}
	}

	throw new Error( 'LocationImpl instance not found on window.location' );
}

const defaultListPageBaseObject = {
	delimiter: ' to ',
	locale: 'en',
	noAction: 'No action selected',
	noItems: 'No items selected',
	DoingBulk: 'Doing bulk...',
};

global.HCaptchaListPageBaseObject = { ...defaultListPageBaseObject };

// flatpickr mock.
const flatpickrInstance = {
	setDate: jest.fn(),
	clear: jest.fn(),
	altInput: { value: '2024-01-01 to 2024-01-31' },
};

const flatpickrMock = jest.fn( ( _el, opts ) => {
	flatpickrInstance._opts = opts;
	return flatpickrInstance;
} );

flatpickrMock.l10ns = { en: {} };
global.flatpickr = flatpickrMock;

function getDom( { withDatepicker = true, customChecked = false, radioValue = '7days' } = {} ) {
	const datepickerEl = withDatepicker ? '<input id="hcaptcha-datepicker" type="text" value="" />' : '';
	const customCheckedAttr = customChecked ? 'checked' : '';

	return `
<html lang="en">
<body>
<form id="hcaptcha-options">
	<div class="hcaptcha-filter">
		<label>
			<input type="radio" name="date_range" value="7days" data-default ${ radioValue === '7days' ? 'checked' : '' } />
			Last 7 days
		</label>
		<label>
			<input type="radio" name="date_range" value="30days" ${ radioValue === '30days' ? 'checked' : '' } />
			Last 30 days
		</label>
		<label>
			<input type="radio" name="date_range" value="custom" ${ customChecked ? customCheckedAttr : '' } />
			Custom
		</label>
		${ datepickerEl }
		<input type="hidden" value="custom" />
	</div>
	<button id="hcaptcha-datepicker-popover-button" type="button">Filter</button>
	<div class="hcaptcha-datepicker-popover" style="display:none">
		<div>Popover content</div>
	</div>
	<input type="reset" value="Reset" />
	<input id="current-page-selector" type="number" value="1" />
	<button type="submit">Apply</button>
</form>
</body>
</html>
	`.trim();
}

function bootModule( domOptions = {}, objectOverrides = {} ) {
	jest.resetModules();
	document.body.innerHTML = getDom( domOptions );
	global.HCaptchaListPageBaseObject = { ...defaultListPageBaseObject, ...objectOverrides };
	flatpickrMock.mockClear();
	flatpickrInstance.setDate.mockClear();
	flatpickrInstance.clear.mockClear();

	require( '../../../assets/js/settings-list-page-base.js' );
	window.hCaptchaSettingsListPagePage();
}

/**
 * Spy on the real LocationImpl prototype's href getter to control window.location.href.
 *
 * @param {string} url URL to return from window.location.href.
 * @return {jest.SpyInstance} Active spy instance.
 */
function setCurrentUrl( url ) {
	const proto = Object.getPrototypeOf( getLocationImplInstance() );

	return jest.spyOn( proto, 'href', 'get' ).mockReturnValue( url );
}

/**
 * Return the URL that was navigated to via the navigation spy.
 *
 * @param {jest.SpyInstance} navSpy Active spy on _locationObjectSetterNavigate.
 * @return {string|undefined} Serialized URL, or undefined if not called.
 */
function getNavUrl( navSpy ) {
	if ( ! navSpy.mock.calls.length ) {
		return undefined;
	}
	return whatwgURL.serializeURL( navSpy.mock.calls[ 0 ][ 0 ] );
}

describe( 'settings-list-page-base.js', () => {
	let navSpy;
	let hrefGetterSpy;

	beforeEach( () => {
		// Suppress "Not implemented: navigation" and capture the target URL.
		const proto = Object.getPrototypeOf( getLocationImplInstance() );

		navSpy = jest.spyOn( proto, '_locationObjectSetterNavigate' )
			.mockImplementation( () => {} );
		hrefGetterSpy = null;
	} );

	afterEach( () => {
		jest.resetModules();
		navSpy.mockRestore();

		if ( hrefGetterSpy ) {
			hrefGetterSpy.mockRestore();
			hrefGetterSpy = null;
		}
	} );

	// ── Early return ──────────────────────────────────────────────────────────

	test( 'returns early when #hcaptcha-datepicker is absent', () => {
		jest.resetModules();
		document.body.innerHTML = getDom( { withDatepicker: false } );
		require( '../../../assets/js/settings-list-page-base.js' );
		window.hCaptchaSettingsListPagePage();
		expect( flatpickrMock ).not.toHaveBeenCalled();
	} );

	// ── onToggle ──────────────────────────────────────────────────────────────

	test( 'onToggle: opens popover (display:none → block) and sets aria-expanded="true"', () => {
		bootModule();
		const btn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		popover.style.display = 'none';

		btn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( popover.style.display ).toBe( 'block' );
		expect( popover.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	test( 'onToggle: closes popover (display:block → none) and sets aria-expanded="false"', () => {
		bootModule();
		const btn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		popover.style.display = 'block';

		btn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( popover.style.display ).toBe( 'none' );
		expect( popover.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'onToggle: opens popover when display is empty string', () => {
		bootModule();
		const btn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		popover.style.display = '';

		btn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( popover.style.display ).toBe( 'block' );
	} );

	// ── onClickOutside ────────────────────────────────────────────────────────

	test( 'onClickOutside: hides popover when clicking outside it', () => {
		bootModule();
		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		popover.style.display = 'block';

		document.body.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( popover.style.display ).toBe( 'none' );
	} );

	test( 'onClickOutside: does NOT hide popover when clicking inside it', () => {
		bootModule();
		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		popover.style.display = 'block';

		const inner = popover.querySelector( 'div' );
		inner.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( popover.style.display ).toBe( 'block' );
	} );

	// ── onSubmitDatepicker ────────────────────────────────────────────────────

	test( 'onSubmitDatepicker: clears radio names, hides popover, navigates with date when datepicker has value', () => {
		bootModule();
		const form = document.getElementById( 'hcaptcha-options' );
		const datepicker = document.getElementById( 'hcaptcha-datepicker' );
		datepicker.value = '2024-01-01 to 2024-01-31';

		form.dispatchEvent( new Event( 'submit', { bubbles: true } ) );

		form.querySelectorAll( 'input[type="radio"]' ).forEach( ( r ) => {
			expect( r.name ).toBe( '' );
		} );

		const popover = document.querySelector( '.hcaptcha-datepicker-popover' );
		expect( popover.style.display ).toBe( 'none' );
		expect( popover.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( navSpy ).toHaveBeenCalled();
		expect( getNavUrl( navSpy ) ).toContain( 'date=' );
	} );

	test( 'onSubmitDatepicker: navigates without date param when datepicker is empty', () => {
		bootModule();
		hrefGetterSpy = setCurrentUrl( 'http://localhost/?page=hcaptcha-events&date=2024-01-01' );
		const form = document.getElementById( 'hcaptcha-options' );
		const datepicker = document.getElementById( 'hcaptcha-datepicker' );
		datepicker.value = '';

		form.dispatchEvent( new Event( 'submit', { bubbles: true } ) );

		expect( navSpy ).toHaveBeenCalled();
		expect( getNavUrl( navSpy ) ).not.toContain( 'date=' );
	} );

	// ── onPageNumberEnter ─────────────────────────────────────────────────────

	test( 'onPageNumberEnter: ignores non-Enter keys', () => {
		bootModule();
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = '2';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Tab', bubbles: true } ) );

		expect( navSpy ).not.toHaveBeenCalled();
	} );

	test( 'onPageNumberEnter: does not navigate when newPaged is invalid (NaN)', () => {
		bootModule();
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = 'abc';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		expect( navSpy ).not.toHaveBeenCalled();
	} );

	test( 'onPageNumberEnter: does not navigate when newPaged < 1', () => {
		bootModule();
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = '0';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		expect( navSpy ).not.toHaveBeenCalled();
	} );

	test( 'onPageNumberEnter: does not navigate when newPaged equals current paged', () => {
		bootModule();
		// Set a valid paged=3 in URL: covers false branch of line 105 (paged >= 1).
		// Input=3 equals paged=3: covers the false branch of line 115 (newPaged === paged).
		hrefGetterSpy = setCurrentUrl( 'http://localhost/?paged=3' );
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = '3';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		expect( navSpy ).not.toHaveBeenCalled();
	} );

	test( 'onPageNumberEnter: navigates to new page when newPaged differs from current paged', () => {
		bootModule();
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = '5';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		expect( navSpy ).toHaveBeenCalled();
		expect( getNavUrl( navSpy ) ).toContain( 'paged=5' );
	} );

	test( 'onPageNumberEnter: treats missing paged param as page 1, navigates when newPaged differs', () => {
		bootModule();
		const pageSelector = document.getElementById( 'current-page-selector' );

		pageSelector.value = '2';
		pageSelector.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Enter', bubbles: true } ) );

		expect( navSpy ).toHaveBeenCalled();
		expect( getNavUrl( navSpy ) ).toContain( 'paged=2' );
	} );

	// ── onResetDatepicker ─────────────────────────────────────────────────────

	test( 'onResetDatepicker: checks default radio and triggers onUpdateDatepicker', () => {
		bootModule();
		const radios = document.querySelectorAll( 'input[type="radio"]' );
		radios[ 1 ].checked = true;

		const resetBtn = document.querySelector( '[type="reset"]' );
		resetBtn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		expect( radios[ 0 ].checked ).toBe( true );
		expect( flatpickrInstance.clear ).toHaveBeenCalled();
	} );

	// ── onUpdateDatepicker ────────────────────────────────────────────────────

	test( 'onUpdateDatepicker: calls setDate when value contains two parts separated by delimiter', () => {
		bootModule();
		const defaultRadio = document.querySelector( 'input[type="radio"][data-default]' );
		defaultRadio.value = '2024-01-01 to 2024-01-31';
		defaultRadio.checked = true;

		defaultRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( flatpickrInstance.setDate ).toHaveBeenCalledWith( [ '2024-01-01', '2024-01-31' ] );
	} );

	test( 'onUpdateDatepicker: calls clear when value does not contain delimiter', () => {
		bootModule();
		const defaultRadio = document.querySelector( 'input[type="radio"][data-default]' );
		defaultRadio.value = 'all';
		defaultRadio.checked = true;

		flatpickrInstance.clear.mockClear();
		defaultRadio.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( flatpickrInstance.clear ).toHaveBeenCalled();
	} );

	test( 'onUpdateDatepicker: uses datepicker value and sets filterBtn text via flatpickr onChange with dateStr', () => {
		bootModule();
		const datepicker = document.getElementById( 'hcaptcha-datepicker' );
		datepicker.value = '2024-03-01 to 2024-03-15';

		const opts = flatpickrInstance._opts;
		opts.onChange( [ new Date(), new Date() ], '2024-03-01 to 2024-03-15', flatpickrInstance );

		const filterBtn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
		expect( filterBtn.textContent ).toBe( flatpickrInstance.altInput.value );
	} );

	test( 'onUpdateDatepicker: does NOT update filterBtn when dateStr is empty in onChange', () => {
		bootModule();
		const filterBtn = document.getElementById( 'hcaptcha-datepicker-popover-button' );

		const opts = flatpickrInstance._opts;
		opts.onChange( [], '', flatpickrInstance );

		expect( filterBtn.textContent ).not.toBe( flatpickrInstance.altInput.value );
	} );

	// ── initFlatPicker ────────────────────────────────────────────────────────

	test( 'initFlatPicker: calls flatpickr with correct options', () => {
		bootModule();
		expect( flatpickrMock ).toHaveBeenCalledTimes( 1 );
		const opts = flatpickrMock.mock.calls[ 0 ][ 1 ];
		expect( opts.mode ).toBe( 'range' );
		expect( opts.inline ).toBe( true );
		expect( opts.dateFormat ).toBe( 'Y-m-d' );
		expect( opts.locale.rangeSeparator ).toBe( defaultListPageBaseObject.delimiter );
	} );

	test( 'initFlatPicker: uses empty object when locale is not found in flatpickr.l10ns', () => {
		// Uses a locale not present in flatpickrMock.l10ns → exercises the `|| {}` fallback branch.
		bootModule( {}, { locale: 'fr' } );
		expect( flatpickrMock ).toHaveBeenCalledTimes( 1 );
		const opts = flatpickrMock.mock.calls[ 0 ][ 1 ];
		expect( opts.locale.rangeSeparator ).toBe( defaultListPageBaseObject.delimiter );
	} );

	test( 'initFlatPicker: calls onUpdateDatepicker with isCustomDates=true when custom radio is checked', () => {
		jest.resetModules();
		document.body.innerHTML = getDom( { customChecked: true } );
		global.HCaptchaListPageBaseObject = { ...defaultListPageBaseObject };
		flatpickrMock.mockClear();
		flatpickrInstance.setDate.mockClear();
		flatpickrInstance.clear.mockClear();

		const datepicker = document.getElementById( 'hcaptcha-datepicker' );
		datepicker.value = '2024-05-01 to 2024-05-31';

		require( '../../../assets/js/settings-list-page-base.js' );
		window.hCaptchaSettingsListPagePage();

		expect( flatpickrInstance.setDate ).toHaveBeenCalledWith( [ '2024-05-01', '2024-05-31' ] );
	} );

	// ── selectDatepickerChoice ────────────────────────────────────────────────

	test( 'selectDatepickerChoice: removes selected class from all labels and adds to parent of chosen input', () => {
		bootModule();
		const labels = document.querySelectorAll( '.hcaptcha-filter label' );

		labels.forEach( ( l ) => l.classList.add( 'hcaptcha-is-selected' ) );

		const radio = document.querySelectorAll( 'input[type="radio"]' )[ 1 ];
		radio.checked = true;
		radio.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		let selectedCount = 0;
		labels.forEach( ( l ) => {
			if ( l.classList.contains( 'hcaptcha-is-selected' ) ) {
				selectedCount++;
			}
		} );

		expect( selectedCount ).toBe( 1 );
		expect( radio.parentElement.classList.contains( 'hcaptcha-is-selected' ) ).toBe( true );
	} );
} );
