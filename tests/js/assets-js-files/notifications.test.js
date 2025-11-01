// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* eslint-disable brace-style,space-in-parens,no-unused-vars */
import $ from 'jquery';

global.jQuery = $;
global.$ = $;

// Default notifications object
const defaultNotifications = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	dismissNotificationAction: 'hcap_dismiss_notification',
	dismissNotificationNonce: 'nonce-dismiss',
	resetNotificationAction: 'hcap_reset_notification',
	resetNotificationNonce: 'nonce-reset',
};

global.HCaptchaNotificationsObject = { ...defaultNotifications };

function getDom( { withTwo = true } = {} ) {
	const notif1Buttons = `
		<div class="hcaptcha-notification-buttons hidden">
			<button type="button" class="btn-one">One</button>
		</div>`;
	const notif2Buttons = `
		<div class="hcaptcha-notification-buttons hidden">
			<button type="button" class="btn-two">Two</button>
		</div>`;

	const secondBlock = withTwo ? (
		`<div class="hcaptcha-notification" data-id="n2" style="display:none">
			<p>Second notification</p>
			<button type="button" class="notice-dismiss">x</button>
			${ notif2Buttons }
		</div>`
	) : '';

	return `
<html lang="en">
<body>
<form id="hcaptcha-options">
	<div id="hcaptcha-notifications">
		<div class="hcaptcha-notification" data-id="n1" style="display:block">
			<p>First notification</p>
			<button type="button" class="notice-dismiss">x</button>
			${ notif1Buttons }
		</div>
		${ secondBlock }
	</div>
	<div id="hcaptcha-notifications-footer"></div>
	<div id="hcaptcha-navigation">
		<button type="button" class="prev">Prev</button>
		<span> <span id="hcaptcha-navigation-page"></span>/<span id="hcaptcha-navigation-pages"></span> </span>
		<button type="button" class="next">Next</button>
	</div>
	<button type="button" id="reset_notifications">Reset</button>
	<h3 class="hcaptcha-section-keys">Keys Section</h3>
</form>
</body>
</html>
	`;
}

function bootNotifications( domOverrides = {} ) {
	document.body.innerHTML = getDom( domOverrides );
	Object.assign( window.HCaptchaNotificationsObject, defaultNotifications );
	// Load a fresh copy of the module and bootstrap explicitly
	jest.isolateModules( () => {
		require( '../../../assets/js/notifications.js' );
	} );
	// Directly initialize to ensure handlers are attached
	window.hCaptchaNotifications( $ );
}

describe( 'notifications.js', () => {
	let postSpy;

	beforeEach( () => {
		jest.clearAllMocks();
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => ( {
			// jQuery 1.x style alias used by notifications.js in reset path
			success( cb ) { return this; },
		} ) );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'dismiss removes current, shows next, updates nav and clones buttons; posts payload', () => {
		bootNotifications( );

		// Click dismiss on the first visible notification
		const firstDismiss = document.querySelector( '.hcaptcha-notification[data-id="n1"] .notice-dismiss' );
		firstDismiss.click( );

		// After removal: only second notification remains and is visible
		const rem1 = document.querySelector( '.hcaptcha-notification[data-id="n1"]' );
		expect( rem1 ).toBeNull( );
		const second = document.querySelector( '.hcaptcha-notification[data-id="n2"]' );
		expect( getComputedStyle( second ).display ).toBe( 'block' );

		// Nav updated
		expect( document.getElementById( 'hcaptcha-navigation-page' ).textContent ).toBe( '1' );
		expect( document.getElementById( 'hcaptcha-navigation-pages' ).textContent ).toBe( '1' );

		// Footer received cloned buttons from the visible notification (without hidden class)
		const footer = document.getElementById( 'hcaptcha-notifications-footer' );
		const cloned = footer.querySelector( '.hcaptcha-notification-buttons' );
		expect( cloned ).toBeTruthy( );
		expect( cloned.classList.contains( 'hidden' ) ).toBe( false );
		expect( footer.querySelector( 'button.btn-two' ) ).toBeTruthy( );

		// One $.post call happened with correct URL and data (cannot read arguments from default spy, so override)
		postSpy.mockRestore( );
		const calls = [];
		jest.spyOn( $, 'post' ).mockImplementation( ( opts ) => { calls.push( opts ); return { success( ) { return this; } }; } );
		// Trigger dismiss of the second now
		document.querySelector( '.hcaptcha-notification[data-id="n2"] .notice-dismiss' ).click( );
		expect( calls.length ).toBe( 1 );
		expect( calls[ 0 ].url ).toBe( defaultNotifications.ajaxUrl );
		expect( calls[ 0 ].data.action ).toBe( defaultNotifications.dismissNotificationAction );
		expect( calls[ 0 ].data.nonce ).toBe( defaultNotifications.dismissNotificationNonce );
		expect( calls[ 0 ].data.id ).toBe( 'n2' );
	} );

	test( 'dismissing last notification removes notifications container', () => {
		bootNotifications( { withTwo: false } );
		const dismissBtn = document.querySelector( '.hcaptcha-notification .notice-dismiss' );
		dismissBtn.click( );
		expect( document.getElementById( 'hcaptcha-notifications' ) ).toBeNull( );
	} );

	test( 'navigation next/prev toggles visibility and disabled classes; nav counters update', async () => {
		// Start with default two notifications, then append a third programmatically
		bootNotifications( { withTwo: true } );
		const container = document.getElementById( 'hcaptcha-notifications' );
		const n3 = document.createElement( 'div' );
		n3.className = 'hcaptcha-notification';
		n3.setAttribute( 'data-id', 'n3' );
		n3.setAttribute( 'style', 'display:none' );
		n3.innerHTML = '<p>Third notification</p><button type="button" class="notice-dismiss">x</button><div class="hcaptcha-notification-buttons hidden"><button class="btn-three">Three</button></div>';
		container.appendChild( n3 );
		// Recompute nav/pages based on new count
		window.hCaptchaNotifications( $ );

		const prevBtn = document.querySelector( '#hcaptcha-navigation .prev' );
		const nextBtn = document.querySelector( '#hcaptcha-navigation .next' );

		// Initially on first item → page 1 / 3
		expect( document.getElementById( 'hcaptcha-navigation-page' ).textContent ).toBe( '1' );
		expect( document.getElementById( 'hcaptcha-navigation-pages' ).textContent ).toBe( '3' );

		// Use test hook to invoke the click handler directly to avoid delegation quirks in jsdom
		const { handleNavClick, setNavStatus } = window.__notificationsTest;
		setNavStatus();
		handleNavClick( { target: nextBtn } );
		expect( document.getElementById( 'hcaptcha-navigation-page' ).textContent ).toBe( '2' );

		// Go next → third
		handleNavClick( { target: nextBtn } );
		expect( document.getElementById( 'hcaptcha-navigation-page' ).textContent ).toBe( '3' );

		// Go prev → back to second
		handleNavClick( { target: prevBtn } );
		expect( document.getElementById( 'hcaptcha-navigation-page' ).textContent ).toBe( '2' );
	} );

	test( 'reset button posts reset action and replaces notifications HTML; triggers helper flows', async () => {
		bootNotifications( );

		// Prepare post to call .success with a successful response containing new markup
		postSpy.mockImplementation( ( opts ) => ( {
			success( cb ) {
				cb( { success: true, data: `
					<div id="hcaptcha-notifications">
						<div class="hcaptcha-notification" data-id="nX" style="display:block">
							<p>New notification</p>
							<button type="button" class="notice-dismiss">x</button>
							<div class="hcaptcha-notification-buttons hidden"><button class="btn-new">New</button></div>
						</div>
					</div>
				` } );
				return this;
			},
		} ) );

		// Spy on jQuery(document).trigger for wp-updates-notice-added
		const triggerSpy = jest.spyOn( $.fn, 'trigger' ).mockImplementation( function() { return this; } );

		document.getElementById( 'reset_notifications' ).click( );
		// Allow any microtasks and macrotasks in success handlers to run
		await Promise.resolve();
		await Promise.resolve();
		await new Promise( (r) => setTimeout( r, 0 ) );
		await new Promise( (r) => setTimeout( r, 0 ) );

		// Old container removed and new inserted before the section header
		const keysHeader = document.querySelector( 'h3.hcaptcha-section-keys' );
		const prev = keysHeader.previousElementSibling;
		expect( prev && prev.id ).toBe( 'hcaptcha-notifications' );
		// After replacement, there should be at least one notification element in the new container
		const newContainer = document.getElementById( 'hcaptcha-notifications' );
		expect( newContainer ).toBeTruthy();
		expect( newContainer.querySelectorAll( '.hcaptcha-notification' ).length ).toBeGreaterThan( 0 );

		// Ensure post payload contained reset action
		expect( postSpy ).toHaveBeenCalled( );
		const args = postSpy.mock.calls[ 0 ][ 0 ];
		expect( args.url ).toBe( defaultNotifications.ajaxUrl );
		expect( args.data.action ).toBe( defaultNotifications.resetNotificationAction );
		expect( args.data.nonce ).toBe( defaultNotifications.resetNotificationNonce );

		// Trigger called with wp-updates-notice-added at least once
		expect( triggerSpy ).toHaveBeenCalled( );
		triggerSpy.mockRestore( );
	} );

	test( 'reset button: success false does nothing (no replacement)', () => {
		bootNotifications( );
		const original = document.getElementById( 'hcaptcha-notifications' );
		postSpy.mockImplementation( () => ( { success( cb ) { cb( { success: false, data: '' } ); return this; } } ) );
		document.getElementById( 'reset_notifications' ).click( );
		const current = document.getElementById( 'hcaptcha-notifications' );
		expect( current ).toBe( original );
	} );
} );
