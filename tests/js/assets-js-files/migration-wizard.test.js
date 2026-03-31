/**
 * Tests for assets/js/migration-wizard.js
 */

/* global kaggDialog */

const flushPromises = () => new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

const buildDOM = ( { hasSavedState = false, savedStateValue = '', hasKeys = '0', withSurfaceCb = true, withButtons = true } = {} ) => {
	document.body.innerHTML = `
		<div id="hcaptcha-migration-wizard"
			data-has-keys="${ hasKeys }"
			${ hasSavedState ? `data-saved-state='${ savedStateValue }'` : '' }
		>
			<div class="wizard-step" data-step="welcome"></div>
			<div class="wizard-step" data-step="scanning"></div>
			<div class="wizard-step" data-step="results"></div>
			<div class="wizard-step" data-step="applying"></div>
			<div class="wizard-step" data-step="complete"></div>
			<div id="wizard-no-results"></div>
			<div id="wizard-has-results"></div>
			<div id="wizard-supported-section"><table><tbody></tbody></table></div>
			<div id="wizard-unsupported-section"><table><tbody></tbody></table></div>
			<div id="wizard-keys-warning"></div>
			<div id="wizard-apply-section"></div>
			<div id="wizard-summary-message"></div>
			<div id="wizard-complete-summary"></div>
			${ withButtons ? '<button id="wizard-scan-btn"></button>' : '' }
			${ withButtons ? '<button id="wizard-apply-btn"></button>' : '' }
			${ withButtons ? '<button id="wizard-rescan-btn"></button>' : '' }
			${ withButtons ? '<input type="checkbox" id="wizard-select-all">' : '' }
			${ withSurfaceCb ? '<input type="checkbox" class="wizard-surface-cb" data-surface="s1" data-option-key="k1" data-option-value="v1" checked>' : '' }
		</div>
	`;
};

const i18n = {
	providerRecaptcha: 'reCAPTCHA',
	providerTurnstile: 'Turnstile',
	confidenceHigh: 'High',
	confidenceMedium: 'Medium',
	confidenceLow: 'Low',
	foundSurfaces: [ 'Found %d surface', 'Found %d surfaces' ],
	migratableCount: [ '%d migratable', '%d migratable' ],
	alreadyEnabled: 'Already enabled',
	enabledFailed: 'Failed',
	scanError: 'Scan error',
	applyError: 'Apply error',
	noSurfacesSelected: 'No surfaces selected',
	okBtnText: 'OK',
};

const setupGlobals = () => {
	window.HCaptchaMigrationWizardObject = {
		i18n,
		ajaxUrl: '/wp-admin/admin-ajax.php',
		scanAction: 'hcaptcha_migration_scan',
		scanNonce: 'scan_nonce_val',
		applyAction: 'hcaptcha_migration_apply',
		applyNonce: 'apply_nonce_val',
	};
	window.kaggDialog = { confirm: jest.fn() };
};

const loadModule = () => {
	jest.resetModules();
	require( '../../../assets/js/migration-wizard.js' );
};

describe( 'migration-wizard.js', () => {
	beforeEach( () => {
		jest.resetModules();
		global.fetch = jest.fn();
		setupGlobals();
	} );

	afterEach( () => {
		document.body.innerHTML = '';
		jest.restoreAllMocks();
	} );

	test( 'early return when no wizard element', () => {
		document.body.innerHTML = '';
		expect( () => loadModule() ).not.toThrow();
	} );

	test( 'shows welcome step when no savedState', () => {
		buildDOM();
		loadModule();
		const welcome = document.querySelector( '[data-step="welcome"]' );
		expect( welcome.style.display ).toBe( '' );
		const results = document.querySelector( '[data-step="results"]' );
		expect( results.style.display ).toBe( 'none' );
	} );

	test( 'restores state from savedState with scan_data', () => {
		const scanData = {
			results: [],
			already_enabled: [],
			migratable: 0,
		};
		const savedStateValue = JSON.stringify( { scan_data: scanData } );
		buildDOM( { hasSavedState: true, savedStateValue } );
		loadModule();
		const results = document.querySelector( '[data-step="results"]' );
		expect( results.style.display ).toBe( '' );
	} );

	test( 'shows welcome step when savedState has no scan_data', () => {
		const savedStateValue = JSON.stringify( {} );
		buildDOM( { hasSavedState: true, savedStateValue } );
		loadModule();
		const welcome = document.querySelector( '[data-step="welcome"]' );
		expect( welcome.style.display ).toBe( '' );
	} );

	test( 'shows welcome step when savedState JSON is invalid', () => {
		buildDOM( { hasSavedState: true, savedStateValue: 'not-json' } );
		loadModule();
		const welcome = document.querySelector( '[data-step="welcome"]' );
		expect( welcome.style.display ).toBe( '' );
	} );

	test( 'scan button click triggers doScan', async () => {
		buildDOM();
		global.fetch = jest.fn( () => new Promise( () => {} ) );
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		expect( global.fetch ).toHaveBeenCalledWith(
			'/wp-admin/admin-ajax.php',
			expect.objectContaining( { method: 'POST' } )
		);
		const scanning = document.querySelector( '[data-step="scanning"]' );
		expect( scanning.style.display ).toBe( '' );
	} );

	test( 'rescan button click triggers doScan', async () => {
		buildDOM();
		global.fetch = jest.fn( () => new Promise( () => {} ) );
		loadModule();
		document.getElementById( 'wizard-rescan-btn' ).click();
		expect( global.fetch ).toHaveBeenCalled();
	} );

	test( 'doScan: success response builds results and shows results step', async () => {
		buildDOM( { hasKeys: '1' } );
		const scanData = {
			results: [
				{
					surface: 'surf1',
					surface_label: 'Surface 1',
					provider: 'recaptcha',
					source_name: 'Plugin A',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'opt_key',
					hcaptcha_option_value: 'opt_val',
					notes: 'A note',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const results = document.querySelector( '[data-step="results"]' );
		expect( results.style.display ).toBe( '' );
	} );

	test( 'doScan: success with already-enabled surface and low confidence', async () => {
		buildDOM( { hasKeys: '1' } );
		const scanData = {
			results: [
				{
					surface: 'surf_already',
					surface_label: 'Already',
					provider: 'turnstile',
					source_name: 'Plugin B',
					confidence: 'low',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
				{
					surface: 'surf_un',
					surface_label: 'Unsupported',
					provider: 'other_provider',
					source_name: 'Plugin C',
					confidence: 'medium',
					is_migratable: false,
					hcaptcha_option_key: 'k2',
					hcaptcha_option_value: 'v2',
					notes: 'unsupported note',
				},
			],
			already_enabled: [ 'surf_already' ],
			migratable: 0,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const summary = document.getElementById( 'wizard-summary-message' );
		expect( summary.innerHTML ).toContain( 'Found 2 surfaces' );
	} );

	test( 'doScan: success with only unsupported results hides supportedSection', async () => {
		buildDOM( { hasKeys: '1' } );
		const scanData = {
			results: [
				{
					surface: 'surf_un',
					surface_label: 'Unsupported',
					provider: 'recaptcha',
					source_name: 'Plugin C',
					confidence: 'high',
					is_migratable: false,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 0,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const supportedSection = document.getElementById( 'wizard-supported-section' );
		expect( supportedSection.style.display ).toBe( 'none' );
		const unsupportedSection = document.getElementById( 'wizard-unsupported-section' );
		expect( unsupportedSection.style.display ).toBe( '' );
	} );

	test( 'doScan: success with no-keys warning', async () => {
		buildDOM( { hasKeys: '0' } );
		const scanData = {
			results: [
				{
					surface: 's1',
					surface_label: 'S1',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const keysWarning = document.getElementById( 'wizard-keys-warning' );
		expect( keysWarning.style.display ).toBe( '' );
	} );

	test( 'doScan: success with empty results disables applyBtn', async () => {
		buildDOM();
		const scanData = { results: [], already_enabled: [], migratable: 0 };
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const applyBtn = document.getElementById( 'wizard-apply-btn' );
		expect( applyBtn.disabled ).toBe( true );
	} );

	test( 'doScan: failure response with message shows dialog', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( { success: false, data: { message: 'Custom scan error' } } ),
			} )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Custom scan error' } )
		);
	} );

	test( 'doScan: failure response without message uses i18n.scanError', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: false, data: {} } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Scan error' } )
		);
	} );

	test( 'doScan: fetch catch shows welcome and dialog', async () => {
		buildDOM();
		global.fetch = jest.fn( () => Promise.reject( new Error( 'network' ) ) );
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Scan error' } )
		);
		const welcome = document.querySelector( '[data-step="welcome"]' );
		expect( welcome.style.display ).toBe( '' );
	} );

	test( 'doApply: no checkboxes shows noSurfacesSelected dialog', () => {
		buildDOM();
		loadModule();
		// Uncheck the default checkbox.
		document.querySelector( '.wizard-surface-cb' ).checked = false;
		document.getElementById( 'wizard-apply-btn' ).click();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'No surfaces selected' } )
		);
	} );

	test( 'doApply: success response shows complete step', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: {
							enabled: [ 's1' ],
							message: 'Done!',
							failed: [ 'f1' ],
						},
					} ),
			} )
		);
		loadModule();
		document.getElementById( 'wizard-apply-btn' ).click();
		await flushPromises();
		const complete = document.querySelector( '[data-step="complete"]' );
		expect( complete.style.display ).toBe( '' );
		const summary = document.getElementById( 'wizard-complete-summary' );
		expect( summary.innerHTML ).toContain( 'Done!' );
		expect( summary.innerHTML ).toContain( 'f1' );
	} );

	test( 'doApply: success with no enabled and no failed', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: true,
						data: { enabled: [], message: '', failed: [] },
					} ),
			} )
		);
		loadModule();
		document.getElementById( 'wizard-apply-btn' ).click();
		await flushPromises();
		const summary = document.getElementById( 'wizard-complete-summary' );
		expect( summary.innerHTML ).toBe( '' );
	} );

	test( 'doApply: failure with message shows dialog', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: false,
						data: { message: 'Apply failed msg' },
					} ),
			} )
		);
		loadModule();
		document.getElementById( 'wizard-apply-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Apply failed msg' } )
		);
	} );

	test( 'doApply: failure without message uses i18n.applyError', async () => {
		buildDOM();
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () => Promise.resolve( { success: false, data: {} } ),
			} )
		);
		loadModule();
		document.getElementById( 'wizard-apply-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Apply error' } )
		);
	} );

	test( 'doApply: fetch catch shows results step and dialog', async () => {
		buildDOM();
		global.fetch = jest.fn( () => Promise.reject( new Error( 'network' ) ) );
		loadModule();
		document.getElementById( 'wizard-apply-btn' ).click();
		await flushPromises();
		expect( kaggDialog.confirm ).toHaveBeenCalledWith(
			expect.objectContaining( { title: 'Apply error' } )
		);
		const results = document.querySelector( '[data-step="results"]' );
		expect( results.style.display ).toBe( '' );
	} );

	test( 'selectAll change checks/unchecks surface checkboxes', () => {
		buildDOM();
		loadModule();
		const selectAll = document.getElementById( 'wizard-select-all' );
		const cb = document.querySelector( '.wizard-surface-cb' );
		cb.checked = false;
		selectAll.checked = true;
		selectAll.dispatchEvent( new Event( 'change' ) );
		expect( cb.checked ).toBe( true );
		selectAll.checked = false;
		selectAll.dispatchEvent( new Event( 'change' ) );
		expect( cb.checked ).toBe( false );
	} );

	test( 'buildResultsUI: selectAll disabled when all checkboxes disabled', async () => {
		buildDOM( { hasKeys: '1', withSurfaceCb: false } );
		const scanData = {
			results: [
				{
					surface: 'surf_dis',
					surface_label: 'Disabled',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [ 'surf_dis' ],
			migratable: 0,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const selectAll = document.getElementById( 'wizard-select-all' );
		expect( selectAll.disabled ).toBe( true );
		const applyBtn = document.getElementById( 'wizard-apply-btn' );
		expect( applyBtn.disabled ).toBe( true );
	} );

	test( 'pluralize: singular form used for count === 1', async () => {
		buildDOM();
		const scanData = {
			results: [
				{
					surface: 's1',
					surface_label: 'S1',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const summary = document.getElementById( 'wizard-summary-message' );
		expect( summary.innerHTML ).toContain( 'Found 1 surface' );
		expect( summary.innerHTML ).toContain( '1 migratable' );
	} );

	test( 'escapeAttr: special chars escaped in surface checkbox attrs', async () => {
		buildDOM( { hasKeys: '1' } );
		const scanData = {
			results: [
				{
					surface: 'surf&<>"\'',
					surface_label: 'Special',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k&key',
					hcaptcha_option_value: 'v"val',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const tbody = document.querySelector( '#wizard-supported-section tbody' );
		expect( tbody.innerHTML ).toContain( '&amp;' );
		expect( tbody.innerHTML ).toContain( '&quot;' );
	} );

	test( 'getConfidenceName: unknown confidence returns as-is', async () => {
		buildDOM( { hasKeys: '1' } );
		const scanData = {
			results: [
				{
					surface: 's_unknown',
					surface_label: 'Unknown Conf',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'unknown_level',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const tbody = document.querySelector( '#wizard-supported-section tbody' );
		expect( tbody.innerHTML ).toContain( 'unknown_level' );
	} );

	test( 'no buttons/selectAll in DOM — null guards covered', () => {
		buildDOM( { withButtons: false } );
		expect( () => loadModule() ).not.toThrow();
	} );

	test( 'buildResultsUI: null selectAll and applyBtn — null guards covered', async () => {
		buildDOM( { hasKeys: '1', withButtons: false, withSurfaceCb: false } );
		const scanData = {
			results: [
				{
					surface: 's1',
					surface_label: 'S1',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		document.getElementById( 'hcaptcha-migration-wizard' ).dataset.savedState = JSON.stringify( { scan_data: scanData } );
		expect( () => loadModule() ).not.toThrow();
	} );

	test( 'buildResultsUI: undefined results and already_enabled use fallback []', async () => {
		buildDOM();
		const scanData = { migratable: 0 };
		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: true, data: scanData } ) } )
		);
		loadModule();
		document.getElementById( 'wizard-scan-btn' ).click();
		await flushPromises();
		const noResults = document.getElementById( 'wizard-no-results' );
		expect( noResults.style.display ).toBe( '' );
	} );

	test( 'buildResultsUI: empty results with null applyBtn — null guard covered', async () => {
		buildDOM( { withButtons: false } );
		const scanData = { results: [], already_enabled: [], migratable: 0 };
		document.getElementById( 'hcaptcha-migration-wizard' ).dataset.savedState = JSON.stringify( { scan_data: scanData } );
		expect( () => loadModule() ).not.toThrow();
	} );

	test( 'savedState: selectAll checked state preserved when hasEnabledCb', () => {
		const scanData = {
			results: [
				{
					surface: 's1',
					surface_label: 'S1',
					provider: 'recaptcha',
					source_name: 'P',
					confidence: 'high',
					is_migratable: true,
					hcaptcha_option_key: 'k',
					hcaptcha_option_value: 'v',
				},
			],
			already_enabled: [],
			migratable: 1,
		};
		const savedStateValue = JSON.stringify( { scan_data: scanData } );
		buildDOM( { hasSavedState: true, savedStateValue, hasKeys: '1' } );
		const selectAllEl = document.getElementById( 'wizard-select-all' );
		selectAllEl.checked = true;
		loadModule();
		expect( selectAllEl.checked ).toBe( true );
	} );
} );
