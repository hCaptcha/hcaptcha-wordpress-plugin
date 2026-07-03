// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const defaultSupportModalObject = {
	githubIssueUrl: 'https://github.com/hCaptcha/hcaptcha-wordpress-plugin/issues/new',
	wordpressSupportUrl: 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/#new-topic-0',
	systemInfo: '### Begin System Info ###\n\n-- hCaptcha Info --\n\nVersion: 5.0.0\n\n### End System Info ###',
	strings: {
		copySuccess: 'Report copied to clipboard.',
		copyError: 'Cannot copy the report automatically. Please copy it from the report field.',
		summaryRequired: 'Please enter a summary before continuing.',
		openFailed: 'Your browser blocked the new tab. Please allow popups and try again.',
		emptyValue: 'Not provided',
		report: {
			summary: 'Summary',
			affectedArea: 'Affected area',
			steps: 'Steps to reproduce',
			expected: 'Expected behavior',
			actual: 'Actual behavior',
			additional: 'Additional details',
			diagnostics: 'Diagnostic information',
			feature: 'Feature request',
			problem: 'Problem / use case',
			solution: 'Proposed solution',
			alternatives: 'Alternative considered / notes',
			question: 'Question',
			configure: 'What I am trying to configure',
			tried: 'What I already tried',
		},
	},
};

function getDom() {
	return `
<button type="button" class="hcaptcha-help-button">Help</button>
<div id="hcaptcha-support-modal" hidden role="dialog" aria-modal="true">
	<button type="button" data-hcaptcha-support-close>Close</button>
	<fieldset>
		<label><input type="radio" name="hcaptcha-support-type" value="bug" checked> Report a bug</label>
		<label><input type="radio" name="hcaptcha-support-type" value="feature"> Request a feature</label>
		<label><input type="radio" name="hcaptcha-support-type" value="support"> Ask a setup question</label>
	</fieldset>
	<input id="hcaptcha-support-summary" type="text">
	<select id="hcaptcha-support-area"><option value="Login">Login</option><option value="WooCommerce">WooCommerce</option></select>
	<div data-hcaptcha-support-fields="bug">
		<textarea id="hcaptcha-support-steps"></textarea>
		<textarea id="hcaptcha-support-expected"></textarea>
		<textarea id="hcaptcha-support-actual"></textarea>
	</div>
	<div data-hcaptcha-support-fields="feature" hidden>
		<textarea id="hcaptcha-support-problem"></textarea>
		<textarea id="hcaptcha-support-solution"></textarea>
		<textarea id="hcaptcha-support-alternatives"></textarea>
	</div>
	<div data-hcaptcha-support-fields="support" hidden>
		<textarea id="hcaptcha-support-configure"></textarea>
		<textarea id="hcaptcha-support-tried"></textarea>
	</div>
	<textarea id="hcaptcha-support-details"></textarea>
	<label class="hcaptcha-support-include-system-info">
		<input id="hcaptcha-support-include-system-info" type="checkbox" checked> Add system information to the report
	</label>
	<textarea id="hcaptcha-support-report" readonly></textarea>
	<div class="hcaptcha-support-action" data-hcaptcha-support-action="github">
		<button type="button" data-hcaptcha-support-continue="github">Continue on GitHub</button>
		<span class="hcaptcha-support-recommended" hidden>Recommended</span>
		<button type="button" class="hcaptcha-support-action-help" aria-describedby="hcaptcha-support-github-description" aria-expanded="false">?</button>
		<div class="hcaptcha-support-action-description" role="tooltip">
			<p id="hcaptcha-support-github-description" class="description">Best for bugs and feature requests.</p>
		</div>
	</div>
	<div class="hcaptcha-support-action" data-hcaptcha-support-action="wordpress">
		<button type="button" data-hcaptcha-support-continue="wordpress" disabled>Continue on WordPress.org</button>
		<span class="hcaptcha-support-recommended" hidden>Recommended</span>
		<button type="button" class="hcaptcha-support-action-help" aria-describedby="hcaptcha-support-wordpress-description hcaptcha-support-wordpress-copy-description" aria-expanded="false">?</button>
		<div class="hcaptcha-support-action-description" role="tooltip">
			<p id="hcaptcha-support-wordpress-description" class="description">Best for setup questions.</p>
			<p id="hcaptcha-support-wordpress-copy-description" class="description">First copy the report.</p>
		</div>
	</div>
	<div class="hcaptcha-support-action" data-hcaptcha-support-action="copy">
		<button type="button" data-hcaptcha-support-copy>Copy report</button>
		<div id="hcaptcha-support-status"></div>
	</div>
</div>
	`.trim();
}

function bootSupportModal( objectOverrides = {} ) {
	jest.resetModules();
	document.body.innerHTML = getDom();

	const config = {
		...defaultSupportModalObject,
		...objectOverrides,
	};

	global.HCaptchaSupportModalObject = config;
	window.HCaptchaSupportModalObject = config;

	require( '../../../assets/js/support-modal.js' );
	window.hCaptchaSupportModal();
}

function flushPromises() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 0 );
	} );
}

describe( 'support-modal.js', () => {
	let clipboardSpy;
	let openSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		clipboardSpy = jest.fn().mockResolvedValue();
		Object.defineProperty( window.navigator, 'clipboard', {
			value: { writeText: clipboardSpy },
			configurable: true,
		} );
		openSpy = jest.spyOn( window, 'open' ).mockImplementation( () => ( {} ) );
	} );

	afterEach( () => {
		openSpy.mockRestore();
	} );

	test( 'returns early when modal element is absent', () => {
		jest.resetModules();
		document.body.innerHTML = '<button class="hcaptcha-help-button">Help</button>';
		window.HCaptchaSupportModalObject = defaultSupportModalObject;

		expect( () => {
			require( '../../../assets/js/support-modal.js' );
			window.hCaptchaSupportModal();
		} ).not.toThrow();
	} );

	test( 'opens modal, focuses summary, and builds a bug report', () => {
		bootSupportModal();

		document.querySelector( '.hcaptcha-help-button' ).click();

		const modal = document.getElementById( 'hcaptcha-support-modal' );
		const report = document.getElementById( 'hcaptcha-support-report' ).value;

		expect( modal.hidden ).toBe( false );
		expect( document.body.classList.contains( 'hcaptcha-support-modal-open' ) ).toBe( true );
		expect( document.activeElement ).toBe( document.getElementById( 'hcaptcha-support-summary' ) );
		expect( report ).toContain( '## Summary' );
		expect( report ).toContain( '## Diagnostic information' );
		expect( report ).toContain( '### Begin System Info ###' );
		expect( report ).toContain( 'Version: 5.0.0' );
		expect( document.querySelector( '[data-hcaptcha-support-action="github"] .hcaptcha-support-recommended' ).hidden ).toBe( false );
		expect( document.querySelector( '[data-hcaptcha-support-action="wordpress"] .hcaptcha-support-recommended' ).hidden ).toBe( true );
		expect( document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' ).disabled ).toBe( true );
	} );

	test( 'updates visible fields and report for feature requests', () => {
		bootSupportModal();

		document.querySelector( 'input[value="feature"]' ).checked = true;
		document.getElementById( 'hcaptcha-support-summary' ).value = 'Add a widget option';
		document.getElementById( 'hcaptcha-support-problem' ).value = 'Widget forms are not covered';
		document.getElementById( 'hcaptcha-support-solution' ).value = 'Add a widget toggle';
		document.getElementById( 'hcaptcha-support-alternatives' ).value = 'Shortcode workaround';
		document.getElementById( 'hcaptcha-support-details' ).value = 'More context';
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const report = document.getElementById( 'hcaptcha-support-report' ).value;

		expect( document.querySelector( '[data-hcaptcha-support-fields="feature"]' ).hidden ).toBe( false );
		expect( document.querySelector( '[data-hcaptcha-support-fields="bug"]' ).hidden ).toBe( true );
		expect( report ).toContain( '## Feature request' );
		expect( report ).toContain( 'Add a widget option' );
		expect( report ).toContain( '## Alternative considered / notes\n\nShortcode workaround' );
		expect( report ).toContain( '## Additional details\n\nMore context' );
		expect( report.indexOf( '## Affected area' ) ).toBeLessThan( report.indexOf( '## Problem / use case' ) );
		expect( report.indexOf( '## Problem / use case' ) ).toBeLessThan( report.indexOf( '## Proposed solution' ) );
		expect( report.indexOf( '## Proposed solution' ) ).toBeLessThan( report.indexOf( '## Alternative considered / notes' ) );
		expect( report.indexOf( '## Alternative considered / notes' ) ).toBeLessThan( report.indexOf( '## Additional details' ) );
		expect( document.querySelector( '[data-hcaptcha-support-action="github"] .hcaptcha-support-recommended' ).hidden ).toBe( false );
	} );

	test( 'recommends WordPress.org for setup questions and includes system information when selected', () => {
		bootSupportModal();

		document.querySelector( 'input[value="support"]' ).checked = true;
		document.getElementById( 'hcaptcha-support-summary' ).value = 'Need setup help';
		document.getElementById( 'hcaptcha-support-configure' ).value = 'WooCommerce checkout';
		document.getElementById( 'hcaptcha-support-tried' ).value = 'Enabled integration toggle';
		document.getElementById( 'hcaptcha-support-details' ).value = 'Captcha appears twice';
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const report = document.getElementById( 'hcaptcha-support-report' ).value;

		expect( document.querySelector( '[data-hcaptcha-support-action="github"] .hcaptcha-support-recommended' ).hidden ).toBe( true );
		expect( document.querySelector( '[data-hcaptcha-support-action="wordpress"] .hcaptcha-support-recommended' ).hidden ).toBe( false );
		expect( report ).toContain( '## Question' );
		expect( report ).toContain( '## Additional details\n\nCaptcha appears twice' );
		expect( report.indexOf( '## Affected area' ) ).toBeLessThan( report.indexOf( '## What I am trying to configure' ) );
		expect( report.indexOf( '## What I am trying to configure' ) ).toBeLessThan( report.indexOf( '## What I already tried' ) );
		expect( report.indexOf( '## What I already tried' ) ).toBeLessThan( report.indexOf( '## Additional details' ) );
		expect( report ).toContain( '## Diagnostic information' );
		expect( report ).toContain( '### Begin System Info ###' );
	} );

	test( 'removes system information from report when unchecked', () => {
		bootSupportModal();

		const checkbox = document.getElementById( 'hcaptcha-support-include-system-info' );

		checkbox.checked = false;
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const report = document.getElementById( 'hcaptcha-support-report' ).value;

		expect( report ).not.toContain( '## Diagnostic information' );
		expect( report ).not.toContain( '### Begin System Info ###' );
	} );
	test( 'toggles action recommendation help details', () => {
		bootSupportModal();

		const githubAction = document.querySelector( '[data-hcaptcha-support-action="github"]' );
		const wordpressAction = document.querySelector( '[data-hcaptcha-support-action="wordpress"]' );
		const githubHelp = githubAction.querySelector( '.hcaptcha-support-action-help' );
		const wordpressHelp = wordpressAction.querySelector( '.hcaptcha-support-action-help' );

		githubHelp.click();

		expect( githubAction.classList.contains( 'is-description-open' ) ).toBe( true );
		expect( githubHelp.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		wordpressHelp.click();

		expect( githubAction.classList.contains( 'is-description-open' ) ).toBe( false );
		expect( githubHelp.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( wordpressAction.classList.contains( 'is-description-open' ) ).toBe( true );
		expect( wordpressHelp.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		document.querySelector( '[data-hcaptcha-support-copy]' ).click();

		expect( wordpressAction.classList.contains( 'is-description-open' ) ).toBe( false );
		expect( wordpressHelp.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	test( 'copy button writes report to clipboard and shows success status', async () => {
		bootSupportModal();

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Broken login captcha';
		document.querySelector( '[data-hcaptcha-support-copy]' ).click();
		await flushPromises();

		const wordpressButton = document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' );

		expect( clipboardSpy ).toHaveBeenCalledWith( expect.stringContaining( 'Broken login captcha' ) );
		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( 'Report copied to clipboard.' );
		expect( document.getElementById( 'hcaptcha-support-status' ).className ).toBe( 'hcaptcha-support-status is-success' );
		expect( wordpressButton.disabled ).toBe( false );

		document.getElementById( 'hcaptcha-support-details' ).value = 'New detail';
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( wordpressButton.disabled ).toBe( true );
		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( '' );
		expect( document.getElementById( 'hcaptcha-support-status' ).className ).toBe( 'hcaptcha-support-status' );
	} );

	test( 'GitHub action requires summary before opening', () => {
		bootSupportModal();

		document.querySelector( '[data-hcaptcha-support-continue="github"]' ).click();

		expect( openSpy ).not.toHaveBeenCalled();
		expect( document.getElementById( 'hcaptcha-support-summary' ).getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( 'Please enter a summary before continuing.' );
		expect( document.getElementById( 'hcaptcha-support-status' ).className ).toBe( 'hcaptcha-support-status is-error' );
	} );

	test( 'GitHub action opens issue URL with title and body', () => {
		bootSupportModal();

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Broken login captcha';
		document.getElementById( 'hcaptcha-support-actual' ).value = 'Challenge never renders';
		document.querySelector( '[data-hcaptcha-support-continue="github"]' ).click();

		const openedUrl = new URL( openSpy.mock.calls[ 0 ][ 0 ] );

		expect( openedUrl.origin + openedUrl.pathname ).toBe( defaultSupportModalObject.githubIssueUrl );
		expect( openedUrl.searchParams.get( 'title' ) ).toBe( 'Broken login captcha' );
		expect( openedUrl.searchParams.get( 'body' ) ).toContain( 'Challenge never renders' );
	} );

	test( 'WordPress.org action opens support forum after report copy step', async () => {
		bootSupportModal();

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Need setup help';

		const copyButton = document.querySelector( '[data-hcaptcha-support-copy]' );
		const wordpressButton = document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' );

		expect( wordpressButton.disabled ).toBe( true );

		copyButton.click();
		await flushPromises();

		expect( wordpressButton.disabled ).toBe( false );

		wordpressButton.click();
		await flushPromises();

		expect( clipboardSpy ).toHaveBeenCalledTimes( 1 );
		expect( openSpy ).toHaveBeenCalledWith( defaultSupportModalObject.wordpressSupportUrl, '_blank' );
	} );

	test( 'WordPress.org action can continue after automatic copy fails', async () => {
		clipboardSpy.mockRejectedValueOnce( new Error( 'denied' ) );
		bootSupportModal();

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Need setup help';

		const copyButton = document.querySelector( '[data-hcaptcha-support-copy]' );
		const wordpressButton = document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' );

		copyButton.click();
		await flushPromises();

		expect( wordpressButton.disabled ).toBe( false );
		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( 'Cannot copy the report automatically. Please copy it from the report field.' );
		expect( document.activeElement ).toBe( document.getElementById( 'hcaptcha-support-report' ) );

		wordpressButton.click();

		expect( openSpy ).toHaveBeenCalledWith( defaultSupportModalObject.wordpressSupportUrl, '_blank' );
	} );

	test( 'Escape closes modal and returns focus to opener', () => {
		bootSupportModal();

		const openButton = document.querySelector( '.hcaptcha-help-button' );
		openButton.focus();
		openButton.click();
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );

		expect( document.getElementById( 'hcaptcha-support-modal' ).hidden ).toBe( true );
		expect( document.activeElement ).toBe( openButton );
	} );
	test( 'handles missing optional modal elements and missing configured strings', () => {
		jest.resetModules();
		document.body.innerHTML = getDom()
			.replace( '<button type="button" data-hcaptcha-support-continue="wordpress" disabled>Continue on WordPress.org</button>', '' )
			.replace( '<textarea id="hcaptcha-support-report" readonly></textarea>', '' )
			.replace( '<div id="hcaptcha-support-status"></div>', '' )
			.replace( '<textarea id="hcaptcha-support-tried"></textarea>', '' );
		const config = {
			githubIssueUrl: defaultSupportModalObject.githubIssueUrl,
			wordpressSupportUrl: defaultSupportModalObject.wordpressSupportUrl,
		};
		global.HCaptchaSupportModalObject = config;
		window.HCaptchaSupportModalObject = config;

		require( '../../../assets/js/support-modal.js' );

		expect( () => window.hCaptchaSupportModal() ).not.toThrow();
		expect( document.querySelector( '[data-hcaptcha-support-fields="bug"]' ).hidden ).toBe( false );
	} );

	test( 'focus trap cycles between first and last focusable controls', () => {
		bootSupportModal();
		const modal = document.getElementById( 'hcaptcha-support-modal' );
		const closeButton = modal.querySelector( '[data-hcaptcha-support-close]' );
		const copyButton = modal.querySelector( '[data-hcaptcha-support-copy]' );

		document.querySelector( '.hcaptcha-help-button' ).click();
		closeButton.focus();
		const backward = new KeyboardEvent( 'keydown', { key: 'Tab', shiftKey: true, bubbles: true } );
		const backwardPreventDefault = jest.spyOn( backward, 'preventDefault' );
		document.dispatchEvent( backward );

		expect( backwardPreventDefault ).toHaveBeenCalled();
		expect( document.activeElement ).toBe( copyButton );

		copyButton.focus();
		const forward = new KeyboardEvent( 'keydown', { key: 'Tab', bubbles: true } );
		const forwardPreventDefault = jest.spyOn( forward, 'preventDefault' );
		document.dispatchEvent( forward );

		expect( forwardPreventDefault ).toHaveBeenCalled();
		expect( document.activeElement ).toBe( closeButton );
	} );

	test( 'focus trap returns when there are no visible focusable controls', () => {
		bootSupportModal();
		const modal = document.getElementById( 'hcaptcha-support-modal' );

		document.querySelector( '.hcaptcha-help-button' ).click();
		modal.querySelectorAll( 'a, button, input, select, textarea, [tabindex]' ).forEach( ( element ) => {
			element.hidden = true;
		} );
		const event = new KeyboardEvent( 'keydown', { key: 'Tab', bubbles: true } );
		const preventDefault = jest.spyOn( event, 'preventDefault' );

		document.dispatchEvent( event );

		expect( preventDefault ).not.toHaveBeenCalled();
	} );

	test( 'modal click handler ignores non-element targets and orphan help buttons', () => {
		bootSupportModal();
		const modal = document.getElementById( 'hcaptcha-support-modal' );
		const textNode = document.createTextNode( 'text target' );
		const orphanHelp = document.createElement( 'button' );

		modal.appendChild( textNode );
		textNode.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );

		orphanHelp.type = 'button';
		orphanHelp.className = 'hcaptcha-support-action-help';
		modal.appendChild( orphanHelp );
		expect( () => orphanHelp.click() ).not.toThrow();
	} );

	test( 'clicking inside an action description keeps the current action tip open', () => {
		bootSupportModal();
		const action = document.querySelector( '[data-hcaptcha-support-action="github"]' );
		const help = action.querySelector( '.hcaptcha-support-action-help' );
		const description = action.querySelector( '.hcaptcha-support-action-description' );

		help.click();
		description.click();

		expect( action.classList.contains( 'is-description-open' ) ).toBe( true );
	} );

	test( 'WordPress action validates summary and blocked popups show an error', () => {
		bootSupportModal();
		const wordpressButton = document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' );

		wordpressButton.disabled = false;
		wordpressButton.click();
		expect( openSpy ).not.toHaveBeenCalled();
		expect( document.getElementById( 'hcaptcha-support-summary' ).getAttribute( 'aria-invalid' ) ).toBe( 'true' );

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Need help';
		openSpy.mockReturnValueOnce( null );
		wordpressButton.click();

		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( 'Your browser blocked the new tab. Please allow popups and try again.' );
	} );

	test( 'document keydown is ignored while modal is hidden', () => {
		bootSupportModal();
		const event = new KeyboardEvent( 'keydown', { key: 'Tab', bubbles: true } );
		const preventDefault = jest.spyOn( event, 'preventDefault' );

		document.dispatchEvent( event );

		expect( preventDefault ).not.toHaveBeenCalled();
	} );
	test( 'close button hides modal without a stored opener', () => {
		bootSupportModal();
		const modal = document.getElementById( 'hcaptcha-support-modal' );

		modal.hidden = false;
		document.body.classList.add( 'hcaptcha-support-modal-open' );
		modal.querySelector( '[data-hcaptcha-support-close]' ).click();

		expect( modal.hidden ).toBe( true );
		expect( document.body.classList.contains( 'hcaptcha-support-modal-open' ) ).toBe( false );
	} );

	test( 'non-Tab keydown is ignored while modal is visible', () => {
		bootSupportModal();
		const modal = document.getElementById( 'hcaptcha-support-modal' );
		const event = new KeyboardEvent( 'keydown', { key: 'ArrowDown', bubbles: true } );
		const preventDefault = jest.spyOn( event, 'preventDefault' );

		modal.hidden = false;
		document.dispatchEvent( event );

		expect( preventDefault ).not.toHaveBeenCalled();
	} );

	test( 'report building falls back when type and fields are missing', () => {
		bootSupportModal( {
			strings: {
				report: {},
			},
		} );

		document.querySelectorAll( 'input[name="hcaptcha-support-type"]' ).forEach( ( radio ) => {
			radio.checked = false;
		} );
		document.getElementById( 'hcaptcha-support-details' ).remove();
		document.getElementById( 'hcaptcha-support-summary' ).remove();
		document.getElementById( 'hcaptcha-support-area' ).remove();
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( document.getElementById( 'hcaptcha-support-report' ).value ).toContain( '## ' );
	} );

	test( 'help toggling keeps the current tip during close and handles missing help buttons', () => {
		bootSupportModal();
		const githubAction = document.querySelector( '[data-hcaptcha-support-action="github"]' );
		const wordpressAction = document.querySelector( '[data-hcaptcha-support-action="wordpress"]' );
		const githubHelp = githubAction.querySelector( '.hcaptcha-support-action-help' );
		const wordpressHelp = wordpressAction.querySelector( '.hcaptcha-support-action-help' );

		githubHelp.click();
		githubHelp.click();
		expect( githubAction.classList.contains( 'is-description-open' ) ).toBe( false );

		githubAction.classList.add( 'is-description-open' );
		githubHelp.remove();
		wordpressHelp.click();

		expect( githubAction.classList.contains( 'is-description-open' ) ).toBe( false );
		expect( wordpressAction.classList.contains( 'is-description-open' ) ).toBe( true );
	} );

	test( 'copy and popup failures use empty fallback messages', async () => {
		bootSupportModal( {
			strings: {
				report: {},
			},
		} );

		Object.defineProperty( window.navigator, 'clipboard', {
			configurable: true,
			value: undefined,
		} );
		document.querySelector( '[data-hcaptcha-support-copy]' ).click();
		await flushPromises();

		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( '' );

		document.getElementById( 'hcaptcha-support-summary' ).value = 'Need help';
		openSpy.mockReturnValueOnce( null );
		document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' ).click();

		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( '' );
	} );
	test( 'missing summary field is handled while rebuilding the report', () => {
		jest.resetModules();
		document.body.innerHTML = getDom().replace( '<input id="hcaptcha-support-summary" type="text">', '' );
		const config = {
			...defaultSupportModalObject,
			strings: {
				report: {},
			},
		};
		global.HCaptchaSupportModalObject = config;
		window.HCaptchaSupportModalObject = config;

		require( '../../../assets/js/support-modal.js' );
		window.hCaptchaSupportModal();
		document.getElementById( 'hcaptcha-support-modal' ).dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( document.getElementById( 'hcaptcha-support-report' ).value ).toContain( '## ' );
	} );

	test( 'summary validation can use an empty fallback message', () => {
		bootSupportModal( {
			strings: {
				report: {},
			},
		} );
		document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' ).disabled = false;

		document.querySelector( '[data-hcaptcha-support-continue="wordpress"]' ).click();

		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( '' );
	} );

	test( 'copy success can use an empty fallback message', async () => {
		bootSupportModal( {
			strings: {
				report: {},
			},
		} );

		document.querySelector( '[data-hcaptcha-support-copy]' ).click();
		await flushPromises();

		expect( document.getElementById( 'hcaptcha-support-status' ).textContent ).toBe( '' );
	} );
} );
