import $ from 'jquery';

global.jQuery = $;
global.$ = $;

const defaultOnboardingObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	updateAction: 'hcap_onboarding_update',
	updateNonce: 'nonce',
	page: 'general',
	currentStep: 'step 3',
	generalUrl: 'https://test.test/wp-admin/admin.php?page=hcaptcha',
	integrationsUrl: 'https://test.test/wp-admin/admin.php?page=hcaptcha-integrations',
	stepParam: 'onboarding_step',
	autoSetupParam: 'auto-setup',
	iconAnimatedUrl: 'https://test.test/wp-content/plugins/hcaptcha/assets/images/icon.svg',
	videoUrl: 'https://youtu.be/khKYehgr8t0',
	ratingUrl: 'https://wordpress.org/support/plugin/hcaptcha-for-forms-and-more/reviews/#new-post',
	selectors: {
		general: {
			site_key: '#site_key',
			secret_key: '#secret_key',
			mode: 'select[name="hcaptcha_settings[mode]"]',
			check_config: '#check_config',
			force: '#force_1',
			honeypot: '#honeypot_1',
			set_min_submit_time: '#set_min_submit_time_1',
			antispam_check: '#antispam_1',
			antispam_provider: 'select[name="hcaptcha_settings[antispam_provider]"]',
			antispam: '.hcaptcha-section-antispam+table',
			save: '#hcaptcha-options #submit',
		},
		integrations: {
			integrations_list: '.hcaptcha-enabled-section+h3+table tr:first-child',
			save: '#hcaptcha-options #submit',
		},
	},
	steps: {
		1: 'Get your keys at hcaptcha.com',
		2: 'Switch Mode to Live',
		3: 'Enter keys, solve hCaptcha and Check Config',
		4: 'Enable Force (recommended)',
		5: 'Enable Anti-spam options (recommended)',
		6: 'Save settings',
		7: 'Enable hCaptcha for installed plugins',
		8: 'Save settings',
	},
	i18n: {
		done: 'Done',
		choiceTitle: 'How would you like to continue?',
		choiceBody: 'Your keys are configured and verified. We can now apply the recommended setup automatically, or you can continue step by step.',
		choiceAutoBody: 'Automatic setup will enable Force, apply the recommended anti-spam options, enable hCaptcha for installed plugins and themes, and save your settings.',
		choiceAutoCta: 'Apply recommended setup automatically',
		choiceManualCta: 'Continue step by step',
		close: 'Close',
		steps: 'Onboarding Steps',
		next: 'Next',
		welcomeTitle: 'Welcome to hCaptcha for WordPress',
		welcomeBody: 'Welcome body.',
		letsGo: 'Let\'s Go!',
		sub1: 'Save your secret key safely.',
		videoCta: 'Watch a quick setup video',
		videoTitle: 'Quick Setup Video',
		ratingTitle: 'Congrats — setup complete!',
		ratingBody: 'You have completed the onboarding wizard.',
		ratingCta: 'Rate hCaptcha on WordPress.org',
	},
};

// Track location changes via the setLocationHref hook on window.hCaptchaOnboarding.
let locationHref = 'http://localhost/';

function getGeneralDom() {
	return `
		<div class="hcaptcha-section-antispam"></div>
		<table>
			<tbody>
				<tr><td><input id="site_key" type="text" value="site-key" /></td></tr>
				<tr><td><input id="secret_key" type="text" value="secret-key" /></td></tr>
				<tr><td><select name="hcaptcha_settings[mode]"><option value="live" selected>Live</option></select></td></tr>
				<tr><td><button id="check_config" type="button">Check Config</button></td></tr>
				<tr><td><input id="force_1" type="checkbox" name="hcaptcha_settings[force][]" value="on" /></td></tr>
				<tr><td><input id="honeypot_1" type="checkbox" name="hcaptcha_settings[honeypot][]" value="on" /></td></tr>
				<tr><td><input id="set_min_submit_time_1" type="checkbox" name="hcaptcha_settings[set_min_submit_time][]" value="on" /></td></tr>
				<tr><td><input id="antispam_1" type="checkbox" name="hcaptcha_settings[antispam][]" value="on" /></td></tr>
				<tr><td><select name="hcaptcha_settings[antispam_provider]"><option value="none">None</option><option value="akismet" selected>Akismet</option></select></td></tr>
			</tbody>
		</table>
		<form id="hcaptcha-options">
			<input type="hidden" name="_wp_http_referer" value="https://test.test/wp-admin/admin.php?page=hcaptcha" />
			<input id="submit" type="submit" value="Save settings" />
		</form>
	`;
}

function getIntegrationsDom() {
	return `
		<form id="hcaptcha-options">
			<hr class="hcaptcha-enabled-section">
			<h3>Active plugins and themes</h3>
			<table>
				<tbody>
					<tr><td><input id="integration-enabled" type="checkbox" name="hcaptcha_settings[cf7_status][]" value="login" /></td></tr>
					<tr><td><input id="integration-disabled" type="checkbox" name="hcaptcha_settings[wpforms_status][]" value="register" disabled /></td></tr>
				</tbody>
			</table>
			<input id="submit" type="submit" value="Save settings" />
		</form>
	`;
}

function bootOnboarding( overrides = {}, dom = getGeneralDom() ) {
	jest.resetModules();
	$( document ).off();
	$( window ).off();
	document.body.innerHTML = dom;
	window.HCaptchaOnboardingObject = {
		...defaultOnboardingObject,
		...overrides,
	};
	require( '../../../assets/js/onboarding-wizard.js' );
	window.hCaptchaOnboarding.setLocationHref = ( url ) => {
		locationHref = url;
	};
	window.hCaptchaOnboarding( $ );
	jest.runOnlyPendingTimers();
	return window.HCaptchaOnboardingObject;
}

describe( 'onboarding.js', () => {
	let postSpy;

	let originalRequestSubmit;
	let consoleErrorSpy;

	beforeEach( () => {
		locationHref = 'http://localhost/';
		jest.useFakeTimers();
		jest.clearAllMocks();
		sessionStorage.clear();
		consoleErrorSpy = jest.spyOn( console, 'error' ).mockImplementation( ( msg ) => {
			if ( msg && msg.message && msg.message.includes( 'Not implemented' ) ) {
				return;
			}
			// eslint-disable-next-line no-console
			console.warn( msg );
		} );
		window.HCaptchaGeneralObject = {
			configuredAntiSpamProviders: [ 'akismet' ],
		};
		Element.prototype.scrollIntoView = jest.fn();
		jest.spyOn( $.fn, 'offset' ).mockImplementation( () => ( { top: 0, left: 0 } ) );
		jest.spyOn( $.fn, 'outerWidth' ).mockImplementation( () => 100 );
		jest.spyOn( $.fn, 'outerHeight' ).mockImplementation( () => 50 );
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const deferred = $.Deferred();
			deferred.resolve( { success: true } );
			return deferred;
		} );
		originalRequestSubmit = HTMLFormElement.prototype.requestSubmit;
		HTMLFormElement.prototype.requestSubmit = jest.fn();
	} );

	afterEach( () => {
		postSpy.mockRestore();
		$.fn.offset.mockRestore();
		$.fn.outerWidth.mockRestore();
		$.fn.outerHeight.mockRestore();
		jest.useRealTimers();
		HTMLFormElement.prototype.requestSubmit = originalRequestSubmit;
		consoleErrorSpy.mockRestore();
	} );

	// ─── existing tests ───────────────────────────────────────────────────────

	test( 'shows a choice modal after completing step 3 and continues step by step', () => {
		bootOnboarding();

		$( '.hcap-onb-done' ).trigger( 'click' );
		expect( document.body.textContent ).toContain( 'How would you like to continue?' );

		$( '.hcap-onb-manual-setup' ).trigger( 'click' );

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'step 4' } )
		);
		expect( $( '.hcap-onb-tip-text' ).first().text() ).toBe( 'Enable Force (recommended)' );
		expect( $( '.hcap-onb-panel [data-step="4"]' ).hasClass( 'current' ) ).toBe( true );
	} );

	test( 'automatic setup on the general page enables recommended options and prepares integrations auto-save', () => {
		bootOnboarding();
		const form = document.getElementById( 'hcaptcha-options' );

		form.submit = jest.fn();

		$( '.hcap-onb-done' ).trigger( 'click' );
		$( '.hcap-onb-auto-setup' ).trigger( 'click' );

		expect( form.submit ).toHaveBeenCalledTimes( 1 );
		expect( sessionStorage.getItem( 'hcapOnbAutoSetup' ) ).toBe( '1' );
		expect( $( '[name="_wp_http_referer"]' ).val() ).toBe(
			'https://test.test/wp-admin/admin.php?page=hcaptcha&auto-setup=1'
		);
	} );

	test( 'automatic setup on the integrations page completes the wizard on step 8', () => {
		sessionStorage.setItem( 'hcapOnbAutoSetup', '1' );
		bootOnboarding(
			{
				page: 'integrations',
				currentStep: 'step 8',
			},
			getIntegrationsDom()
		);

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'completed' } )
		);
		expect( document.body.textContent ).toContain( 'Congrats — setup complete!' );
		expect( sessionStorage.getItem( 'hcapOnbAutoSetup' ) ).toBe( '1' );
	} );

	// ─── welcome modal ────────────────────────────────────────────────────────

	test( 'step 1 on general page shows welcome modal; Let\'s Go builds panel and step-1 tooltip', () => {
		bootOnboarding( { currentStep: 'step 1' } );

		expect( document.body.textContent ).toContain( 'Welcome to hCaptcha for WordPress' );
		expect( $( '.hcap-onb-panel' ).length ).toBe( 0 );

		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers(); // 180 ms positioning timer

		expect( $( '.hcap-onb-panel' ).length ).toBe( 1 );
		expect( $( '.hcap-onb-tip-text' ).first().text() ).toBe( 'Get your keys at hcaptcha.com' );
	} );

	test( 'clicking the welcome overlay also dismisses and proceeds', () => {
		bootOnboarding( { currentStep: 'step 1' } );

		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		expect( $( '.hcap-onb-panel' ).length ).toBe( 1 );
		expect( $( '.hcap-onb-modal-overlay' ).length ).toBe( 0 );
	} );

	test( 'welcome modal is built without the animated icon when iconAnimatedUrl is empty', () => {
		bootOnboarding( { currentStep: 'step 1', iconAnimatedUrl: '' } );

		expect( $( '.hcap-onb-modal .hcap-onb-icon' ).length ).toBe( 0 );
		expect( document.body.textContent ).toContain( 'Welcome to hCaptcha for WordPress' );
	} );

	// ─── step-1 tooltip: video link ───────────────────────────────────────────

	test( 'step-1 tooltip has a video link that opens the video modal on click', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		expect( $( '.hcap-onb-video-link' ).length ).toBe( 1 );

		$( '.hcap-onb-video-link' ).trigger( 'click' );

		expect( document.body.textContent ).toContain( 'Quick Setup Video' );
	} );

	test( 'video link keydown Enter opens the video modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		$( '.hcap-onb-video-link' ).trigger( $.Event( 'keydown', { key: 'Enter' } ) );

		expect( document.body.textContent ).toContain( 'Quick Setup Video' );
	} );

	test( 'video link keydown Space opens the video modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		$( '.hcap-onb-video-link' ).trigger( $.Event( 'keydown', { key: ' ' } ) );

		expect( document.body.textContent ).toContain( 'Quick Setup Video' );
	} );

	test( 'video link keydown Spacebar opens the video modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		$( '.hcap-onb-video-link' ).trigger( $.Event( 'keydown', { key: 'Spacebar' } ) );

		expect( document.body.textContent ).toContain( 'Quick Setup Video' );
	} );

	test( 'video link keydown non-trigger key does not open the video modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		$( '.hcap-onb-video-link' ).trigger( $.Event( 'keydown', { key: 'Tab' } ) );

		expect( $( '.hcap-onb-modal.video' ).length ).toBe( 0 );
	} );

	// ─── video modal ──────────────────────────────────────────────────────────

	test( 'video modal overlay click dismisses the modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();
		$( '.hcap-onb-video-link' ).trigger( 'click' );

		expect( $( '.hcap-onb-modal.video' ).length ).toBe( 1 );
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );

		expect( $( '.hcap-onb-modal.video' ).length ).toBe( 0 );
	} );

	test( 'video modal close button dismisses the modal', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();
		$( '.hcap-onb-video-link' ).trigger( 'click' );

		$( '.hcap-onb-video-close' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		expect( $( '.hcap-onb-modal.video' ).length ).toBe( 0 );
	} );

	test( 'toEmbedUrl converts watch?v= URL to embed format', () => {
		bootOnboarding( {
			currentStep: 'step 1',
			videoUrl: 'https://www.youtube.com/watch?v=abc123',
		} );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();
		$( '.hcap-onb-video-link' ).trigger( 'click' );

		const src = $( '.hcap-onb-modal.video iframe' ).attr( 'src' );

		expect( src ).toContain( 'embed/abc123' );
	} );

	test( 'toEmbedUrl returns original URL when no recognisable video ID is present', () => {
		bootOnboarding( {
			currentStep: 'step 1',
			videoUrl: 'https://example.com/some-video',
		} );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();
		$( '.hcap-onb-video-link' ).trigger( 'click' );

		const src = $( '.hcap-onb-modal.video iframe' ).attr( 'src' );

		expect( src ).toBe( 'https://example.com/some-video' );
	} );

	// ─── choice modal ─────────────────────────────────────────────────────────

	test( 'choice modal overlay click dismisses and goes to step 4', () => {
		bootOnboarding();

		$( '.hcap-onb-done' ).trigger( 'click' );
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'step 4' } )
		);
		expect( $( '.hcap-onb-modal-overlay' ).length ).toBe( 0 );
	} );

	test( 'choice modal is built without icon when iconAnimatedUrl is empty', () => {
		bootOnboarding( { iconAnimatedUrl: '' } );
		$( '.hcap-onb-done' ).trigger( 'click' );

		expect( $( '.hcap-onb-modal .hcap-onb-icon' ).length ).toBe( 0 );
		expect( document.body.textContent ).toContain( 'How would you like to continue?' );
	} );

	// ─── panel close button ───────────────────────────────────────────────────

	test( 'panel close button removes the panel and posts completed', () => {
		bootOnboarding();

		$( '.hcap-onb-close' ).trigger( 'click' );

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'completed' } )
		);
		expect( $( '.hcap-onb-panel' ).length ).toBe( 0 );
	} );

	// ─── panel built without icon ─────────────────────────────────────────────

	test( 'panel is built without the animated icon when iconAnimatedUrl is empty', () => {
		bootOnboarding( { iconAnimatedUrl: '' } );

		expect( $( '.hcap-onb-panel .hcap-onb-icon' ).length ).toBe( 0 );
		expect( $( '.hcap-onb-panel' ).length ).toBe( 1 );
	} );

	// ─── panel navigation: click ──────────────────────────────────────────────

	test( 'clicking a general-page step in the panel navigates to the general URL', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="2"]' ).trigger( 'click' );

		expect( locationHref ).toContain( 'onboarding_step=2' );
		expect( locationHref ).toContain( 'page=hcaptcha' );
	} );

	test( 'clicking an integrations-page step in the panel navigates to the integrations URL', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="7"]' ).trigger( 'click' );

		expect( locationHref ).toContain( 'onboarding_step=7' );
		expect( locationHref ).toContain( 'page=hcaptcha-integrations' );
	} );

	test( 'clicking a list item with data-step=0 does not navigate', () => {
		bootOnboarding();
		// Manually inject an invalid step item
		$( '.hcap-onb-list' ).append( '<li class="hcap-onb-step" data-step="0">Bad</li>' );
		$( '.hcap-onb-list li[data-step="0"]' ).trigger( 'click' );

		expect( locationHref ).toBe( 'http://localhost/' );
	} );

	// ─── panel navigation: keydown ────────────────────────────────────────────

	test( 'keydown Enter on panel step item navigates', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="2"]' ).trigger( $.Event( 'keydown', { key: 'Enter' } ) );

		expect( locationHref ).toContain( 'onboarding_step=2' );
	} );

	test( 'keydown Space on panel step item navigates', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="2"]' ).trigger( $.Event( 'keydown', { key: ' ' } ) );

		expect( locationHref ).toContain( 'onboarding_step=2' );
	} );

	test( 'keydown Spacebar on panel step item navigates', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="2"]' ).trigger( $.Event( 'keydown', { key: 'Spacebar' } ) );

		expect( locationHref ).toContain( 'onboarding_step=2' );
	} );

	test( 'keydown non-trigger key on panel step does not navigate', () => {
		bootOnboarding();
		$( '.hcap-onb-list li[data-step="2"]' ).trigger( $.Event( 'keydown', { key: 'Tab' } ) );

		expect( locationHref ).toBe( 'http://localhost/' );
	} );

	test( 'keydown Enter on panel step item with n=0 does not navigate', () => {
		bootOnboarding();
		$( '.hcap-onb-list' ).append( '<li class="hcap-onb-step" data-step="0">Bad</li>' );
		$( '.hcap-onb-list li[data-step="0"]' ).trigger( $.Event( 'keydown', { key: 'Enter' } ) );

		expect( locationHref ).toBe( 'http://localhost/' );
	} );

	// ─── Escape closes tooltip ────────────────────────────────────────────────

	test( 'Escape keydown removes the tooltip', () => {
		bootOnboarding();
		jest.runAllTimers();

		expect( $( '.hcap-onb-tip' ).length ).toBe( 1 );
		$( document ).trigger( $.Event( 'keydown', { key: 'Escape' } ) );

		expect( $( '.hcap-onb-tip' ).length ).toBe( 0 );
	} );

	test( 'Escape keydown when no tooltip is a no-op', () => {
		bootOnboarding( { currentStep: 'step 9' } ); // no tooltip rendered

		expect( () => {
			$( document ).trigger( $.Event( 'keydown', { key: 'Escape' } ) );
		} ).not.toThrow();
	} );

	// ─── showStep: target on wrong page ───────────────────────────────────────

	test( 'showStep returns early when target step belongs to another page', () => {
		// step 7 belongs to integrations; booting general page at step 7 should show no tooltip
		bootOnboarding( { page: 'general', currentStep: 'step 7' } );
		jest.runAllTimers();

		expect( $( '.hcap-onb-tip' ).length ).toBe( 0 );
		expect( $( '.hcap-onb-panel' ).length ).toBe( 0 );
	} );

	test( 'showStep returns early when the target element is not found in the DOM', () => {
		// selector for step 4 is #force_1; boot without that element
		bootOnboarding( {
			currentStep: 'step 4',
			selectors: {
				...defaultOnboardingObject.selectors,
				general: {
					...defaultOnboardingObject.selectors.general,
					force: '#nonexistent_element',
				},
			},
		} );
		jest.runAllTimers();

		expect( $( '.hcap-onb-tip' ).length ).toBe( 0 );
	} );

	// ─── showStep: closed section h3 ─────────────────────────────────────────

	test( 'showStep clicks a closed h3 to expand the settings section', () => {
		const triggerSpy = jest.spyOn( $.fn, 'trigger' );

		bootOnboarding( { currentStep: 'step 4' }, `
			<h3 class="closed">Section</h3>
			<table><tbody><tr><td><input id="force_1" type="checkbox" /></td></tr></tbody></table>
			<form id="hcaptcha-options"><input id="submit" type="submit" /></form>
		` );
		jest.runAllTimers();

		const clickedH3 = triggerSpy.mock.calls.some(
			( [ event ] ) => event === 'click' && triggerSpy.mock.instances.some(
				( el ) => el[ 0 ] && el[ 0 ].matches && el[ 0 ].matches( 'h3.closed' )
			)
		);

		expect( clickedH3 ).toBe( true );
		triggerSpy.mockRestore();
	} );

	// ─── showStep: tooltip positioning clamps ─────────────────────────────────

	test( 'tooltip left is clamped when it would overflow the right edge of the viewport', () => {
		// outerWidth for the tooltip returns 100; window width is 50 → left will be clamped.
		jest.spyOn( $.fn, 'width' ).mockImplementation( function() {
			if ( this[ 0 ] === window ) {
				return 50;
			}
			return 100;
		} );
		jest.spyOn( $.fn, 'offset' ).mockRestore();
		jest.spyOn( $.fn, 'offset' ).mockImplementation( () => ( { top: 100, left: 0 } ) );

		bootOnboarding( { currentStep: 'step 4' } );
		jest.runAllTimers();

		const left = parseInt( $( '.hcap-onb-tip' ).css( 'left' ), 10 );

		expect( left ).toBeLessThanOrEqual( 50 );
		$.fn.width.mockRestore();
	} );

	test( 'tooltip top is clamped to 10 when it would go above the viewport', () => {
		jest.spyOn( $.fn, 'offset' ).mockRestore();
		// target is at top:0 and tooltip height is 50 → top = 0 - 25 + 0 = -25 → clamped to 10
		jest.spyOn( $.fn, 'offset' ).mockImplementation( () => ( { top: 0, left: 0 } ) );
		jest.spyOn( $.fn, 'outerHeight' ).mockRestore();
		jest.spyOn( $.fn, 'outerHeight' ).mockImplementation( () => 50 );

		bootOnboarding( { currentStep: 'step 4' } );
		jest.runAllTimers();

		const top = parseInt( $( '.hcap-onb-tip' ).css( 'top' ), 10 );

		expect( top ).toBe( 10 );
		$.fn.outerHeight.mockRestore();
		jest.spyOn( $.fn, 'outerHeight' ).mockImplementation( () => 50 );
	} );

	test( 'positioning setTimeout is a no-op when tooltip is removed before it fires', () => {
		bootOnboarding( { currentStep: 'step 4' } );
		// Remove tooltip before the 180 ms positioning timer fires.
		$( '.hcap-onb-tip' ).remove();
		window.hCaptchaOnboarding.__tooltip = null;

		expect( () => jest.runAllTimers() ).not.toThrow();
	} );

	// ─── resize handler ───────────────────────────────────────────────────────

	test( 'resize event repositions the tooltip when one is visible', () => {
		bootOnboarding();
		jest.runAllTimers();

		const tipBefore = $( '.hcap-onb-tip' ).length;

		$( window ).trigger( 'resize.hcapOnb' );
		jest.runAllTimers();

		expect( $( '.hcap-onb-tip' ).length ).toBe( tipBefore );
	} );

	test( 'resize event is a no-op when no tooltip is visible', () => {
		bootOnboarding( { currentStep: 'step 9' } ); // step 9 has no target → no tooltip

		expect( () => {
			$( window ).trigger( 'resize.hcapOnb' );
		} ).not.toThrow();
	} );

	test( 'resize handler does nothing when tooltip step belongs to another page', () => {
		// Boot on integrations at step 8, but artificially trigger resize
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );
		jest.runAllTimers();

		// Remove the tip to simulate a case where the tip was shown for a different page step.
		$( '.hcap-onb-tip' ).remove();
		// Override currentStep so that the step is for the general page.
		window.HCaptchaOnboardingObject.currentStep = 'step 3';

		expect( () => {
			$( window ).trigger( 'resize.hcapOnb' );
		} ).not.toThrow();
	} );

	// ─── step 6 done → redirect to integrations ───────────────────────────────

	test( 'done button on step 6 (general) redirects to integrationsUrl', () => {
		bootOnboarding( { currentStep: 'step 6' } );
		jest.runAllTimers();

		$( '.hcap-onb-done' ).trigger( 'click' );

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'step 7' } )
		);
		expect( locationHref ).toContain( 'page=hcaptcha-integrations' );
	} );

	// ─── step 8 done → congrats modal ─────────────────────────────────────────

	test( 'done button on step 8 (integrations) shows congrats modal and removes panel on dismiss', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );
		jest.runAllTimers();

		$( '.hcap-onb-done' ).trigger( 'click' );

		expect( document.body.textContent ).toContain( 'Congrats — setup complete!' );

		// Dismiss via overlay → removes tooltip and panel.
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );
		jest.runAllTimers();

		expect( $( '.hcap-onb-modal-overlay' ).length ).toBe( 0 );
		expect( $( '.hcap-onb-panel' ).length ).toBe( 0 );
	} );

	test( 'congrats modal rate button click schedules a dismiss via setTimeout', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );
		jest.runAllTimers();

		$( '.hcap-onb-done' ).trigger( 'click' );

		expect( $( '.hcap-onb-modal' ).length ).toBe( 1 );

		$( '.hcap-onb-modal a.button' ).trigger( 'click' );
		jest.runAllTimers();

		expect( $( '.hcap-onb-modal' ).length ).toBe( 0 );
	} );

	// ─── congrats modal: no onDismiss callback ────────────────────────────────

	test( 'congrats overlay dismiss when no onDismiss callback (autoSetup path)', () => {
		sessionStorage.setItem( 'hcapOnbAutoSetup', '1' );
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );

		expect( document.body.textContent ).toContain( 'Congrats — setup complete!' );

		// Dismissing without a callback should not throw.
		expect( () => $( '.hcap-onb-modal-overlay' ).trigger( 'click' ) ).not.toThrow();
		expect( $( '.hcap-onb-modal-overlay' ).length ).toBe( 0 );
	} );

	// ─── form submit: step 6 general ─────────────────────────────────────────

	test( 'form submit on step 6 (general) posts step 7 and sets sessionStorage flag', () => {
		bootOnboarding( { currentStep: 'step 6' } );

		document.getElementById( 'hcaptcha-options' ).submit = jest.fn();

		$( '#hcaptcha-options' ).trigger( 'submit' );

		expect( postSpy ).toHaveBeenCalledWith(
			'https://test.test/wp-admin/admin-ajax.php',
			expect.objectContaining( { value: 'step 7' } )
		);
		expect( sessionStorage.getItem( 'hcapOnbGoIntegrations' ) ).toBe( '1' );
	} );

	test( 'form submit on other steps is a no-op for post', () => {
		bootOnboarding( { currentStep: 'step 4' } );
		postSpy.mockClear();

		document.getElementById( 'hcaptcha-options' ).submit = jest.fn();

		$( '#hcaptcha-options' ).trigger( 'submit' );

		// postSpy may have been called via goToWizardStep but not via form submit handler.
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// ─── form submit: step 8 integrations ────────────────────────────────────

	test( 'form submit on step 8 (integrations, first time) prevents default and shows congrats, then resubmits form', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );

		const form = document.getElementById( 'hcaptcha-options' );
		const submitSpy = jest.fn();

		form.submit = submitSpy;

		const e = $.Event( 'submit' );

		$( '#hcaptcha-options' ).trigger( e );

		expect( document.body.textContent ).toContain( 'Congrats — setup complete!' );

		// Dismiss the congrats modal → triggers the resubmit.
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );
		jest.runAllTimers();

		expect( submitSpy ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'form submit on step 8 (integrations, second time) allows normal submit', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );

		const form = document.getElementById( 'hcaptcha-options' );

		form.submit = jest.fn();

		// First submit: preventDefault → congrats → dismiss → resubmit (sets hcapOnbSubmitting=true).
		$( '#hcaptcha-options' ).trigger( 'submit' );
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );
		jest.runAllTimers();

		// Second submit: hcapOnbSubmitting is true → just return, no new congrats modal.
		const beforeCalls = postSpy.mock.calls.length;

		$( '#hcaptcha-options' ).trigger( 'submit' );

		expect( postSpy.mock.calls.length ).toBe( beforeCalls );
		expect( document.body.textContent ).not.toContain( 'Congrats' );
	} );

	test( 'form submit step 8: fallback trigger click when form.submit throws', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );

		const form = document.getElementById( 'hcaptcha-options' );

		form.submit = jest.fn( () => {
			throw new Error( 'submit blocked' );
		} );
		form.requestSubmit = jest.fn();

		const submitClickSpy = jest.fn();

		$( '#hcaptcha-options [type="submit"]' ).on( 'click', submitClickSpy );

		$( '#hcaptcha-options' ).trigger( 'submit' );
		$( '.hcap-onb-modal-overlay' ).trigger( 'click' );
		jest.runAllTimers();

		expect( submitClickSpy ).toHaveBeenCalledTimes( 1 );
	} );

	// ─── runAutomaticSetupOnGeneralPage: form.submit not a function ───────────

	test( 'automatic setup falls back to window.location.href when form has no submit method', () => {
		bootOnboarding();

		// Ensure form.submit is not a function (jsdom normally provides one).
		const form = document.getElementById( 'hcaptcha-options' );

		form.submit = 'not-a-function';

		$( '.hcap-onb-done' ).trigger( 'click' );
		$( '.hcap-onb-auto-setup' ).trigger( 'click' );

		expect( locationHref ).toContain( 'auto-setup=1' );
	} );

	test( 'automatic setup falls back to window.location.href when form element is absent', () => {
		// Boot with step 3 but no #hcaptcha-options form in DOM → form is null → setLocationHref is called.
		bootOnboarding( { currentStep: 'step 3' }, `
			<div class="hcaptcha-section-antispam"></div>
			<table><tbody>
				<tr><td><input id="site_key" type="text" value="site-key" /></td></tr>
				<tr><td><input id="secret_key" type="text" value="secret-key" /></td></tr>
				<tr><td><select name="hcaptcha_settings[mode]"><option value="live" selected>Live</option></select></td></tr>
				<tr><td><button id="check_config" type="button">Check Config</button></td></tr>
			</tbody></table>
		` );
		jest.runAllTimers();

		$( '.hcap-onb-done' ).trigger( 'click' );
		$( '.hcap-onb-auto-setup' ).trigger( 'click' );

		expect( locationHref ).toContain( 'auto-setup=1' );
	} );

	// ─── sessionStorage catch in runAutomaticSetupOnGeneralPage ──────────────

	test( 'automatic setup handles sessionStorage.setItem throwing', () => {
		bootOnboarding();

		sessionStorage.setItem.bind( sessionStorage );

		jest.spyOn( Storage.prototype, 'setItem' ).mockImplementationOnce( () => {
			throw new Error( 'storage full' );
		} );

		const form = document.getElementById( 'hcaptcha-options' );

		form.submit = jest.fn();

		expect( () => {
			$( '.hcap-onb-done' ).trigger( 'click' );
			$( '.hcap-onb-auto-setup' ).trigger( 'click' );
		} ).not.toThrow();

		Storage.prototype.setItem.mockRestore();
	} );

	// ─── sessionStorage catch in form submit step 6 ───────────────────────────

	test( 'form submit step 6: handles sessionStorage.setItem throwing', () => {
		bootOnboarding( { currentStep: 'step 6' } );

		document.getElementById( 'hcaptcha-options' ).submit = jest.fn();

		jest.spyOn( Storage.prototype, 'setItem' ).mockImplementationOnce( () => {
			throw new Error( 'storage full' );
		} );

		expect( () => {
			$( '#hcaptcha-options' ).trigger( 'submit' );
		} ).not.toThrow();

		Storage.prototype.setItem.mockRestore();
	} );

	// ─── init: page=general num=7 with goIntegrations flag ───────────────────

	test( 'init redirects to integrations when goIntegrations session flag is set at step 7', () => {
		sessionStorage.setItem( 'hcapOnbGoIntegrations', '1' );
		bootOnboarding( { page: 'general', currentStep: 'step 7' } );

		expect( locationHref ).toContain( 'page=hcaptcha-integrations' );
		expect( sessionStorage.getItem( 'hcapOnbGoIntegrations' ) ).toBeNull();
	} );

	test( 'init does not redirect when goIntegrations flag is absent at step 7', () => {
		bootOnboarding( { page: 'general', currentStep: 'step 7' } );

		expect( locationHref ).toBe( 'http://localhost/' );
	} );

	// ─── init: early return when step belongs to the other page ───────────────

	test( 'init returns early without panel when current step belongs to the other page', () => {
		// General page at step 7 (integrations step) → early return after goIntegrations check.
		bootOnboarding( { page: 'general', currentStep: 'step 7' } );

		expect( $( '.hcap-onb-panel' ).length ).toBe( 0 );
	} );

	// ─── stepNumber: no match returns 1 ──────────────────────────────────────

	test( 'stepNumber falls back to 1 when currentStep has no numeric part', () => {
		// 'completed' has no "step N" → stepNumber returns 1 → num=1 on general → welcome modal.
		bootOnboarding( { currentStep: 'completed' } );

		expect( document.body.textContent ).toContain( 'Welcome to hCaptcha for WordPress' );
	} );

	// ─── focus-trigger catch blocks in modal setTimeout ───────────────────────

	test( 'welcome modal setTimeout focus catch does not propagate', () => {
		const origTrigger = $.fn.trigger;

		jest.spyOn( $.fn, 'trigger' ).mockImplementation( function( event ) {
			if ( event === 'focus' ) {
				throw new Error( 'focus blocked' );
			}
			return origTrigger.apply( this, arguments );
		} );

		expect( () => bootOnboarding( { currentStep: 'step 1' } ) ).not.toThrow();
		$.fn.trigger.mockRestore();
	} );

	test( 'choice modal setTimeout focus catch does not propagate', () => {
		const origTrigger = $.fn.trigger;

		jest.spyOn( $.fn, 'trigger' ).mockImplementation( function( event ) {
			if ( event === 'focus' ) {
				throw new Error( 'focus blocked' );
			}
			return origTrigger.apply( this, arguments );
		} );

		expect( () => bootOnboarding() ).not.toThrow();

		// Open choice modal.
		$( '.hcap-onb-done' ).trigger( 'click' );
		expect( () => jest.runOnlyPendingTimers() ).not.toThrow();

		$.fn.trigger.mockRestore();
	} );

	test( 'video modal setTimeout focus catch does not propagate', () => {
		bootOnboarding( { currentStep: 'step 1' } );
		$( '.hcap-onb-go' ).trigger( 'click' );
		jest.runOnlyPendingTimers();

		const origTrigger = $.fn.trigger;

		jest.spyOn( $.fn, 'trigger' ).mockImplementation( function( event ) {
			if ( event === 'focus' ) {
				throw new Error( 'focus blocked' );
			}
			return origTrigger.apply( this, arguments );
		} );

		$( '.hcap-onb-video-link' ).trigger( 'click' );

		expect( () => jest.runOnlyPendingTimers() ).not.toThrow();
		$.fn.trigger.mockRestore();
	} );

	test( 'congrats modal setTimeout focus catch does not propagate', () => {
		bootOnboarding( { page: 'integrations', currentStep: 'step 8' }, getIntegrationsDom() );
		jest.runAllTimers();

		$( '.hcap-onb-done' ).trigger( 'click' );

		const origTrigger = $.fn.trigger;

		jest.spyOn( $.fn, 'trigger' ).mockImplementation( function( event ) {
			if ( event === 'focus' ) {
				throw new Error( 'focus blocked' );
			}
			return origTrigger.apply( this, arguments );
		} );

		expect( () => jest.runOnlyPendingTimers() ).not.toThrow();
		$.fn.trigger.mockRestore();
	} );

	// ─── scrollIntoView catch ─────────────────────────────────────────────────

	test( 'showStep handles scrollIntoView throwing without propagating', () => {
		Element.prototype.scrollIntoView = jest.fn( () => {
			throw new Error( 'scroll blocked' );
		} );

		expect( () => {
			bootOnboarding( { currentStep: 'step 4' } );
			jest.runAllTimers();
		} ).not.toThrow();
	} );

	// ─── init try/catch for goIntegrations storage ────────────────────────────

	test( 'init catch for sessionStorage access does not propagate', () => {
		sessionStorage.setItem( 'hcapOnbGoIntegrations', '1' );

		jest.spyOn( Storage.prototype, 'getItem' ).mockImplementationOnce( () => {
			throw new Error( 'storage blocked' );
		} );

		expect( () => bootOnboarding( { page: 'general', currentStep: 'step 7' } ) ).not.toThrow();

		Storage.prototype.getItem.mockRestore();
	} );
} );
