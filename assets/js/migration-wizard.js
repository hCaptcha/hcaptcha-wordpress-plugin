/**
 * Migration Wizard JavaScript.
 *
 * @package
 */

/**
 * @typedef  {Object}   HCaptchaMigrationWizardI18n
 * @property {string}   providerRecaptcha  - Display name for reCAPTCHA provider.
 * @property {string}   providerTurnstile  - Display name for Turnstile provider.
 * @property {string}   confidenceHigh     - Display name for high confidence level.
 * @property {string}   confidenceMedium   - Display name for medium confidence level.
 * @property {string}   confidenceLow      - Display name for low confidence level.
 * @property {string[]} foundSurfaces      - Message templates [singular, plural] for found surfaces count.
 * @property {string[]} migratableCount    - Message templates [singular, plural] for migratable count.
 * @property {string}   alreadyEnabled     - Message for already enabled surfaces.
 * @property {string}   enabledFailed      - Message for failed surfaces.
 * @property {string}   scanError          - Generic scan error message.
 * @property {string}   applyError         - Generic apply error message.
 * @property {string}   noSurfacesSelected - Message when no surfaces are selected.
 * @property {string}   okBtnText          - Text for the OK button.
 */

/**
 * @typedef  {Object} HCaptchaMigrationWizardObject
 * @property {HCaptchaMigrationWizardI18n} i18n        - Internationalization strings.
 * @property {string}                      ajaxUrl     - WordPress AJAX URL.
 * @property {string}                      scanAction  - AJAX action name for scan.
 * @property {string}                      scanNonce   - Nonce for scan action.
 * @property {string}                      applyAction - AJAX action name for applying.
 * @property {string}                      applyNonce  - Nonce for apply action.
 */

/**
 * @typedef  {Object}  ScanResult
 * @property {string}  surface               - Surface identifier.
 * @property {string}  surface_label         - Display label for the surface.
 * @property {string}  provider              - Provider identifier (e.g., 'recaptcha', 'turnstile').
 * @property {string}  source_name           - Name of the source plugin/theme.
 * @property {string}  confidence            - Confidence level ('high', 'medium', 'low').
 * @property {boolean} is_migratable         - Whether the surface can be migrated.
 * @property {string}  hcaptcha_option_key   - Option key for hCaptcha setting.
 * @property {string}  hcaptcha_option_value - Option value for hCaptcha setting.
 * @property {string}  [notes]               - Additional notes about the surface.
 */

/**
 * @typedef  {Object}       ScanData
 * @property {ScanResult[]} results         - Array of scan results.
 * @property {string[]}     already_enabled - Array of surface identifiers already enabled.
 * @property {number}       migratable      - Count of migratable surfaces.
 */

/**
 * @typedef  {Object}   SavedState
 * @property {ScanData} scan_data - Saved scan data from previous session.
 */

/* global HCaptchaMigrationWizardObject, kaggDialog */

( function() {
	'use strict';

	const wizard = document.getElementById( 'hcaptcha-migration-wizard' );

	if ( ! wizard ) {
		return;
	}

	const config = HCaptchaMigrationWizardObject;
	const i18n = config.i18n;
	let scanData = null;

	/**
	 * Get the plural or singular form based on count.
	 *
	 * @param {string[]} forms Array with [singular, plural] forms.
	 * @param {number}   count Count to determine the form.
	 * @return {string} The appropriate form with %d replaced.
	 */
	const pluralize = ( forms, count ) => {
		const template = count === 1 ? forms[ 0 ] : forms[ 1 ];

		return template.replace( '%d', count.toString() );
	};

	/**
	 * Show a specific wizard step and hide all others.
	 *
	 * @param {string} stepName Step name.
	 */
	const showStep = ( stepName ) => {
		const steps = wizard.querySelectorAll( '.wizard-step' );

		steps.forEach( ( step ) => {
			/** @type {HTMLElement} */
			const element = step;

			element.style.display = element.dataset.step === stepName ? '' : 'none';
		} );
	};

	/**
	 * Get provider display name.
	 *
	 * @param {string} provider Provider identifier.
	 * @return {string} Display name.
	 */
	const getProviderName = ( provider ) => {
		if ( provider === 'recaptcha' ) {
			return i18n.providerRecaptcha;
		}

		if ( provider === 'turnstile' ) {
			return i18n.providerTurnstile;
		}

		return provider;
	};

	/**
	 * Get confidence display name.
	 *
	 * @param {string} confidence Confidence level.
	 * @return {string} Display name.
	 */
	const getConfidenceName = ( confidence ) => {
		const map = {
			high: i18n.confidenceHigh,
			medium: i18n.confidenceMedium,
			low: i18n.confidenceLow,
		};

		return map[ confidence ] || confidence;
	};

	/**
	 * Get confidence CSS class.
	 *
	 * @param {string} confidence Confidence level.
	 * @return {string} CSS class.
	 */
	const getConfidenceClass = ( confidence ) => {
		return 'confidence-' + confidence;
	};

	/**
	 * Build results UI from scan data.
	 *
	 * @param {ScanData} data Scan data from server.
	 */
	const buildResultsUI = ( data ) => {
		const noResults = document.getElementById( 'wizard-no-results' );
		const hasResults = document.getElementById( 'wizard-has-results' );

		const results = data.results || [];
		const alreadyEnabled = data.already_enabled || [];

		if ( results.length === 0 ) {
			noResults.style.display = '';
			hasResults.style.display = 'none';

			const applySection = document.getElementById( 'wizard-apply-section' );

			applySection.style.display = '';

			if ( applyBtn ) {
				applyBtn.disabled = true;
			}

			return;
		}

		noResults.style.display = 'none';
		hasResults.style.display = '';

		const supportedSection = document.getElementById( 'wizard-supported-section' );
		const unsupportedSection = document.getElementById( 'wizard-unsupported-section' );
		const keysWarning = document.getElementById( 'wizard-keys-warning' );
		const applySection = document.getElementById( 'wizard-apply-section' );
		const summaryMessage = document.getElementById( 'wizard-summary-message' );

		// Summary message.
		const totalMsg = pluralize( i18n.foundSurfaces, results.length );
		const migratableMsg = pluralize( i18n.migratableCount, data.migratable );

		summaryMessage.innerHTML =
			'<p><strong>' + totalMsg + '</strong></p>' +
			'<p>' + migratableMsg + '</p>';

		// Supported results.
		const supported = results.filter( ( r ) => r.is_migratable );
		const unsupported = results.filter( ( r ) => ! r.is_migratable );

		if ( supported.length > 0 ) {
			supportedSection.style.display = '';
			const tbody = supportedSection.querySelector( 'tbody' );
			tbody.innerHTML = '';

			supported.forEach( ( result, index ) => {
				const isAlready = alreadyEnabled.indexOf( result.surface ) !== -1;
				const isLowConfidence = result.confidence === 'low';
				const isChecked = ! isAlready && ! isLowConfidence;
				const notes = [];

				if ( result.notes ) {
					notes.push( result.notes );
				}

				if ( isAlready ) {
					notes.push( '<strong>' + i18n.alreadyEnabled + '</strong>' );
				}

				const tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td class="check-column">' +
					'<input type="checkbox" class="wizard-surface-cb" ' +
					'data-index="' + index + '" ' +
					'data-surface="' + escapeAttr( result.surface ) + '" ' +
					'data-option-key="' + escapeAttr( result.hcaptcha_option_key ) + '" ' +
					'data-option-value="' + escapeAttr( result.hcaptcha_option_value ) + '" ' +
					( isChecked ? 'checked' : '' ) +
					( isAlready ? ' disabled' : '' ) +
					'></td>' +
					'<td>' + result.surface_label + '</td>' +
					'<td>' + getProviderName( result.provider ) + '</td>' +
					'<td>' + result.source_name + '</td>' +
					'<td><span class="confidence-badge ' + getConfidenceClass( result.confidence ) + '">' +
					getConfidenceName( result.confidence ) + '</span></td>' +
					'<td>' + notes.join( '<br>' ) + '</td>';

				tbody.appendChild( tr );
			} );
		} else {
			supportedSection.style.display = 'none';
		}

		// Unsupported results.
		if ( unsupported.length > 0 ) {
			unsupportedSection.style.display = '';
			const tbody = unsupportedSection.querySelector( 'tbody' );
			tbody.innerHTML = '';

			unsupported.forEach( ( result ) => {
				const tr = document.createElement( 'tr' );
				tr.innerHTML =
					'<td>' + result.surface_label + '</td>' +
					'<td>' + getProviderName( result.provider ) + '</td>' +
					'<td>' + result.source_name + '</td>' +
					'<td>' + ( result.notes || '' ) + '</td>';

				tbody.appendChild( tr );
			} );
		} else {
			unsupportedSection.style.display = 'none';
		}

		// Keys check.
		const hasKeys = wizard.dataset.hasKeys === '1';
		const hasEnabledCb = wizard.querySelectorAll( '.wizard-surface-cb:not(:disabled)' ).length > 0;

		keysWarning.style.display = hasKeys ? 'none' : '';
		applySection.style.display = '';

		// Disable select-all and apply button when no enabled checkboxes exist.
		if ( selectAll ) {
			selectAll.disabled = ! hasEnabledCb;
			selectAll.checked = hasEnabledCb && selectAll.checked;
		}

		if ( applyBtn ) {
			applyBtn.disabled = ! hasEnabledCb;
		}
	};

	/**
	 * Build completion summary.
	 *
	 * @param {Object} data Apply response data.
	 */
	const buildCompleteSummary = ( data ) => {
		const summary = document.getElementById( 'wizard-complete-summary' );
		let html = '';

		if ( data.enabled && data.enabled.length > 0 ) {
			html += '<div class="notice notice-success inline"><p>' +
				data.message + '</p></div>';
		}

		if ( data.failed && data.failed.length > 0 ) {
			html += '<div class="notice notice-error inline"><p>' +
				i18n.enabledFailed + ': ' + data.failed.join( ', ' ) + '</p></div>';
		}

		summary.innerHTML = html;
	};

	/**
	 * Perform AJAX scan.
	 */
	const doScan = () => {
		showStep( 'scanning' );

		const formData = new FormData();
		formData.append( 'action', config.scanAction );
		formData.append( 'nonce', config.scanNonce );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response.success ) {
					scanData = response.data;
					buildResultsUI( scanData );
					showStep( 'results' );
				} else {
					showStep( 'welcome' );
					kaggDialog.confirm( {
						title: response.data?.message || i18n.scanError,
						content: '',
						type: 'info',
						buttons: {
							ok: {
								text: i18n.okBtnText,
							},
						},
					} );
				}
			} )
			.catch( () => {
				showStep( 'welcome' );
				kaggDialog.confirm( {
					title: i18n.scanError,
					content: '',
					type: 'info',
					buttons: {
						ok: {
							text: i18n.okBtnText,
						},
					},
				} );
			} );
	};

	/**
	 * Perform AJAX apply.
	 */
	const doApply = () => {
		const checkboxes = wizard.querySelectorAll( '.wizard-surface-cb:checked:not(:disabled)' );

		if ( checkboxes.length === 0 ) {
			kaggDialog.confirm( {
				title: i18n.noSurfacesSelected,
				content: '',
				type: 'info',
				buttons: {
					ok: {
						text: i18n.okBtnText,
					},
				},
			} );

			return;
		}

		const surfaces = [];

		checkboxes.forEach( ( cb ) => {
			/** @type {HTMLInputElement} */
			const checkbox = cb;
			surfaces.push( {
				surface: checkbox.dataset.surface,
				hcaptcha_option_key: checkbox.dataset.optionKey,
				hcaptcha_option_value: checkbox.dataset.optionValue,
			} );
		} );

		showStep( 'applying' );

		const formData = new FormData();
		formData.append( 'action', config.applyAction );
		formData.append( 'nonce', config.applyNonce );
		formData.append( 'surfaces', JSON.stringify( surfaces ) );

		fetch( config.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( response ) => {
				if ( response.success ) {
					buildCompleteSummary( response.data );
					showStep( 'complete' );
				} else {
					showStep( 'results' );
					kaggDialog.confirm( {
						title: response.data?.message || i18n.applyError,
						content: '',
						type: 'info',
						buttons: {
							ok: {
								text: i18n.okBtnText,
							},
						},
					} );
				}
			} )
			.catch( () => {
				showStep( 'results' );
				kaggDialog.confirm( {
					title: i18n.applyError,
					content: '',
					type: 'info',
					buttons: {
						ok: {
							text: i18n.okBtnText,
						},
					},
				} );
			} );
	};

	/**
	 * Escape attribute value.
	 *
	 * @param {string} str Input string.
	 * @return {string} Escaped string.
	 */
	const escapeAttr = ( str ) => {
		return str
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	};

	// Event listeners.
	const scanBtn = document.getElementById( 'wizard-scan-btn' );

	if ( scanBtn ) {
		scanBtn.addEventListener( 'click', doScan );
	}

	const applyBtn = document.getElementById( 'wizard-apply-btn' );

	if ( applyBtn ) {
		applyBtn.addEventListener( 'click', doApply );
	}

	const rescanBtn = document.getElementById( 'wizard-rescan-btn' );

	if ( rescanBtn ) {
		rescanBtn.addEventListener( 'click', doScan );
	}

	// Select all checkboxes.
	/** @type {HTMLInputElement|null} */
	const selectAll = document.getElementById( 'wizard-select-all' );

	if ( selectAll ) {
		selectAll.addEventListener( 'change', () => {
			const checkboxes = wizard.querySelectorAll( '.wizard-surface-cb:not(:disabled)' );

			checkboxes.forEach( ( cb ) => {
				cb.checked = selectAll.checked;
			} );
		} );
	}

	// Restore the saved state if available.
	const savedState = wizard.dataset.savedState;

	if ( savedState ) {
		try {
			/** @type {SavedState} */
			const state = JSON.parse( savedState );

			if ( state.scan_data ) {
				scanData = state.scan_data;
				buildResultsUI( scanData );
				showStep( 'results' );
			}
		} catch ( e ) {
			showStep( 'welcome' );
		}
	} else {
		showStep( 'welcome' );
	}
}() );
