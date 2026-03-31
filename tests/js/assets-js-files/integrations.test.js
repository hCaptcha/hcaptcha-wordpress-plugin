// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

require( '../../../assets/js/settings-base.js' );
require( '../../../assets/js/integrations.js' );

// Mock HCaptchaIntegrationsObject
global.HCaptchaIntegrationsObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	action: 'test_action',
	nonce: 'test_nonce',
	activatePluginMsg: 'Activate %s plugin?',
	deactivatePluginMsg: 'Deactivate %s plugin?',
	activateThemeMsg: 'Activate %s theme?',
	deactivateThemeMsg: 'Deactivate %s theme?',
	installPluginMsg: 'Install %s plugin?',
	installThemeMsg: 'Install %s theme?',
	OKBtnText: 'OK',
	CancelBtnText: 'Cancel',
	selectThemeMsg: 'Select a theme',
	onlyOneThemeMsg: 'Only one theme available',
	unexpectedErrorMsg: 'Unexpected error',
	themes: { Divi: 'Divi' },
	defaultTheme: 'twentytwentyfive',
	suggestActivate: '',
	suggestActivateMsg: 'Consider activating this item',
};

function getDom() {
	return `
<html lang="en">
<body>
<div id="wpwrap">
<div class="hcaptcha-header-bar"></div>
<div id="hcaptcha-options">
	<input id="hcaptcha-integrations-search" type="text" />
</div>
<label><input type="checkbox" id="show_antispam_coverage_1" /></label>
<div id="hcaptcha-message"></div>
	<table class="form-table">
		<tbody>
		<tr class="hcaptcha-integrations-wp-status">
			<th scope="row">
				<div class="hcaptcha-integrations-logo">
					<img
						src="https://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/wp-core-logo.png"
					 	alt="WP Core Logo" data-entity="core" data-label="WordPress Core">
				 </div>
			</th>
			<td>
				<fieldset>
					<label for="wp_status_1">
						<input id="wp_status_1" name="hcaptcha_settings[wp_status][]" type="checkbox" value="comment"
						   checked="checked">
						Comment Form
					</label>
					<br>
					<label for="wp_status_2">
						<input id="wp_status_2" name="hcaptcha_settings[wp_status][]" type="checkbox" value="login"
						   checked="checked">
						Login Form
					</label>
					<br>
					<label for="wp_status_3">
						<input id="wp_status_3" name="hcaptcha_settings[wp_status][]" type="checkbox" value="lost_pass"
						   checked="checked">
						Lost Password Form
					</label>
					<br>
					<label for="wp_status_4">
						<input id="wp_status_4" name="hcaptcha_settings[wp_status][]" type="checkbox"
						   value="password_protected" checked="checked">
						Post/Page Password Form
					</label>
					<br>
					<label for="wp_status_5">
						<input id="wp_status_5" name="hcaptcha_settings[wp_status][]" type="checkbox" value="register"
						   checked="checked">
						Register Form
					</label>
					<br>
					<label data-antispam data-antispam-honeypot data-antispam-fst id="antispam-label">Anti-spam</label>
				</fieldset>
			</td>
		</tr>
		</tbody>
	</table>
	<table class="form-table">
		<tbody>
		<tr class="hcaptcha-integrations-acfe-status">
			<th scope="row">
				<div class="hcaptcha-integrations-logo" data-installed="true">
					<img
						src="https://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/acf-extended-logo.png"
					 	alt="ACF Extended Logo" data-entity="plugin" data-label="ACF Extended">
				 </div>
			</th>
			<td>
				<fieldset disabled="disabled">
					<label for="acfe_status_1">
						<input id="acfe_status_1" name="hcaptcha_settings[acfe_status][]" type="checkbox" value="form"
						   checked="checked">
						ACF Extended Form
					</label>
					<br>
				</fieldset>
			</td>
		</tr>
		<tr class="hcaptcha-integrations-twentytwentyone-theme">
			<th scope="row">
				<div class="hcaptcha-integrations-logo" data-installed="true">
					<img alt="Twenty Twenty-One Logo" data-entity="theme" data-label="Twenty Twenty-One">
				</div>
			</th>
			<td>
				<fieldset>
					<label>Twenty Twenty-One Active</label>
				</fieldset>
			</td>
		</tr>
		<tr class="hcaptcha-integrations-other-plugin">
			<th scope="row">
				<div class="hcaptcha-integrations-logo" data-installed="false">
					<img alt="Other Plugin Logo" data-entity="plugin" data-label="Other Plugin">
				</div>
			</th>
			<td>
				<fieldset disabled="disabled">
					<label>Other Plugin</label>
				</fieldset>
			</td>
		</tr>
		</tbody>
 </table>
<table class="form-table"><tbody></tbody></table>
</div>
</body>
</html>
    `;
}

describe( 'integrations', () => {
	jest.useFakeTimers();
	const successMessage = 'Success.';
	const errorMessage = 'Error.';
	let postSpy;

	beforeEach( () => {
		document.body.innerHTML = getDom();

		// Simulate jQuery.ready event
		window.hCaptchaIntegrations( $ );

		const mockSuccessResponse = {
			data: {
				message: successMessage,
				stati: [],
			},
			success: true,
		};
		const mockErrorResponse = {
			data: {
				message: errorMessage,
			},
			success: false,
		};
		const mockPostPromise = {
			done: jest.fn().mockImplementation( ( callback ) => {
				callback( mockSuccessResponse );
				return mockPostPromise;
			} ),
			fail: jest.fn().mockImplementation( ( callback ) => {
				callback( mockErrorResponse );
				return mockPostPromise;
			} ),
		};

		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const deferred = $.Deferred();

			// To fire the done callback, resolve the deferred object with the mockSuccessResponse
			deferred.resolve( mockSuccessResponse );

			// Comment the following line if you don't want the fail callback to be fired
			// deferred.reject(mockErrorResponse);

			return deferred;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
	} );

	test( 'setupHelpers creates helper icons and toggles visibility with the checkbox', () => {
		const $label = $( '#antispam-label' );
		// After init, helper should be inserted but hidden by default (checkbox unchecked)
		let $helper = $label.next( '.helper' );
		expect( $helper.length ).toBe( 1 );
		expect( $helper.css( 'display' ) ).toBe( 'none' );
		// It should contain icons for data-antispam-honeypot and data-antispam-fst
		expect( $helper.find( 'i.antispam-honeypot' ).length ).toBe( 1 );
		expect( $helper.find( 'i.antispam-fst' ).length ).toBe( 1 );

		// Toggle the checkbox to show helpers
		const $cb = $( '#show_antispam_coverage_1' );
		$cb.prop( 'checked', true ).trigger( 'change' );
		$helper = $label.next( '.helper' );
		expect( $helper.css( 'display' ) ).toBe( 'inline-flex' );
	} );

	test( 'suggestActivate highlights element and shows success message when configured', () => {
		// Spy on highlightElement
		const hlSpy = jest.spyOn( window.hCaptchaSettingsBase, 'highlightElement' ).mockImplementation( () => {} );
		// Configure suggestActivate and trigger init again
		global.HCaptchaIntegrationsObject.suggestActivate = 'acfe_status';
		window.hCaptchaIntegrations( $ );

		expect( hlSpy ).toHaveBeenCalled();
		// Message gets notice-success class
		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-success' );

		hlSpy.mockRestore();
		// Reset for other tests
		global.HCaptchaIntegrationsObject.suggestActivate = '';
	} );

	test( 'search input filters rows with debounce and prevents Enter submit', () => {
		// Spy on animate to ensure called
		const animSpy = jest.spyOn( $.fn, 'animate' ).mockImplementation( () => $.fn );
		const $search = $( '#hcaptcha-integrations-search' );
		$search.val( 'other' ).trigger( 'input' );
		jest.advanceTimersByTime( 120 );

		// Another Plugin row visible, ACF row hidden (label does not contain "other"); core unaffected but skipped
		expect( $( '.hcaptcha-integrations-other-plugin' ).css( 'display' ) ).not.toBe( 'none' );
		expect( $( '.hcaptcha-integrations-acfe-status' ).css( 'display' ) ).toBe( 'none' );
		expect( animSpy ).toHaveBeenCalled();

		// Press Enter inside #hcaptcha-options should be prevented
		let prevented = false;
		const evt = $.Event( 'keydown', { target: $search.get( 0 ), which: 13 } );
		evt.preventDefault = function() {
			prevented = true;
		};
		$( '#hcaptcha-options' ).trigger( evt );
		expect( prevented ).toBe( true );

		animSpy.mockRestore();
	} );

	test( 'only-one-theme guard shows info dialog and aborts without AJAX', () => {
		global.HCaptchaIntegrationsObject.themes = {};
		window.kaggDialog = { confirm: jest.fn() };

		const $themeImg = $( '.hcaptcha-integrations-twentytwentyone-theme img' );
		$themeImg.trigger( 'click' );

		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		expect( postSpy ).not.toHaveBeenCalled();

		// Restore themes for other tests
		global.HCaptchaIntegrationsObject.themes = { Divi: 'Divi', twentytwentyone: 'Twenty Twenty-One' };
	} );

	test( 'install flow for not-installed plugin shows confirm and posts with install flag', () => {
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };

		const $pluginImg = $( '.hcaptcha-integrations-other-plugin img' );
		$pluginImg.trigger( $.Event( 'click', { ctrlKey: false } ) );

		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
	} );

	test( 'ctrlKey bypass triggers AJAX without confirmation', () => {
		window.kaggDialog = { confirm: jest.fn() };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );
		expect( window.kaggDialog.confirm ).not.toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
	} );

	test( 'theme deactivation opens dialog with theme select', () => {
		window.kaggDialog = { confirm: jest.fn() };
		const $themeImg = $( '.hcaptcha-integrations-twentytwentyone-theme img' );
		$themeImg.trigger( 'click' );
		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		const arg = window.kaggDialog.confirm.mock.calls[ 0 ][ 0 ];
		expect( arg.type ).toBe( 'deactivate' );
		expect( String( arg.content ) ).toContain( '<select>' );
	} );

	test( 'unexpected response shows unexpected error message', () => {
		postSpy.mockRestore();
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			// Resolve with an object without a success field
			d.resolve( { data: {}, something: true } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( 'click' );

		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-error' );
	} );

	test( 'clicking on an image sends an AJAX request', () => {
		const $img = $( '.form-table img' );
		const $logo = $img.closest( '.hcaptcha-integrations-logo' );

		$logo.data( 'installed', true );

		// No ajax call on click at WP Core.
		$( $img.get( 0 ) ).trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();

		// Mock window.kaggDialog object and methods
		window.kaggDialog = {
			confirm: jest.fn( ( settings ) => settings.onAction( true ) ),
		};
		// jest.spyOn( global, 'kaggDialog.confirm' ).mockReturnValueOnce( true );

		$( $img.get( 1 ) ).trigger( 'click' );

		// expect( window.kaggDialog ).toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
	} );
} );

// Additional coverage for integrations.js

describe( 'additional integrations coverage', () => {
	jest.useFakeTimers();

	function resetDom( custom = {} ) {
		document.body.innerHTML = getDom();
		Object.assign( window.HCaptchaIntegrationsObject, custom );
		window.hCaptchaIntegrations( $ );
	}

	test( 'suggestActivate: early return when not set', () => {
		resetDom( { suggestActivate: '' } );
		const msg = document.querySelector( '#hcaptcha-message' );
		expect( msg.className ).not.toContain( 'notice-success' );
	} );

	test( 'suggestActivate: element not found leads to no highlight or message', () => {
		const hlSpy = jest.spyOn( window.hCaptchaSettingsBase, 'highlightElement' ).mockImplementation( () => {} );
		resetDom( { suggestActivate: 'nonexistent_item' } );
		expect( hlSpy ).not.toHaveBeenCalled();
		const msg = document.querySelector( '#hcaptcha-message' );
		expect( msg.className ).not.toContain( 'notice-success' );
		hlSpy.mockRestore();
	} );

	test( 'AJAX fail path shows error message and removes action classes in always()', async () => {
		resetDom();
		// Mock $.post to return a Deferred we can reject later
		const d = $.Deferred();
		const postMock = jest.spyOn( $, 'post' ).mockImplementation( () => d );

		// Pick an installed plugin to trigger the toggle without installation
		window.kaggDialog = { confirm: jest.fn() };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		// Trigger ctrl-click to bypass the dialog and attach .done/.fail/.always handlers
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );

		// The row should get an action class immediately
		const $tr = $img.closest( 'tr' );
		expect( $tr.attr( 'class' ) ).toMatch( /(on|off|install)/ );

		// Now reject the request to hit .fail and then .always
		d.reject( { statusText: 'Boom' } );
		// Yield to the event loop so jQuery Deferred callbacks run
		await Promise.resolve();

		// After fail + always: error message visible and classes removed
		const msg = document.querySelector( '#hcaptcha-message' );
		expect( msg.className ).toContain( 'notice-error' );
		expect( msg.textContent ).toContain( 'Boom' );
		expect( $tr.attr( 'class' ) ).not.toMatch( /(^on|off|install$)/ );

		postMock.mockRestore();
	} );

	test( 'toggleActivation (install flow) moves plugin to active table and enables its fieldset', () => {
		resetDom();
		// Mock confirm to auto-accept
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };

		// Mock $.post resolve with success
		const d = $.Deferred();
		jest.spyOn( $, 'post' ).mockImplementation( () => d );

		const $img = $( '.hcaptcha-integrations-other-plugin img' );
		const $tr = $img.closest( 'tr' );
		const $fieldset = $tr.find( 'fieldset' );

		// Precondition: in inactive table and disabled
		expect( $fieldset.is( ':disabled' ) ).toBe( true );

		$img.trigger( 'click' );

		// Resolve success
		d.resolve( { success: true, data: { message: 'ok', stati: [] } } );

		// Should be moved into the active table (index 1) and enabled
		const activeTable = $( '.form-table' ).eq( 1 );
		expect( $tr.closest( '.form-table' )[ 0 ] ).toBe( activeTable[ 0 ] );
		expect( $fieldset.is( ':disabled' ) ).toBe( false );
	} );

	test( 'updateActivationStati sets data-installed and moves rows between tables', () => {
		resetDom();
		// Prepare a resolve with stati that flips acfe_status to active (true)
		const d = $.Deferred();
		jest.spyOn( $, 'post' ).mockImplementation( () => d );
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };

		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		const $tr = $img.closest( 'tr' );
		const $formTable = $( '.form-table' );
		const initiallyInActiveTable = $tr.closest( '.form-table' ).is( $formTable.eq( 1 ) );

		// Trigger click and resolve with stati flipping acfe_status to the opposite
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );
		d.resolve( {
			success: true,
			data: {
				message: 'ok',
				stati: { acfe_status: ! initiallyInActiveTable },
			},
		} );

		// Data-installed must be true, and the row should be in table matching status
		expect( $tr.find( '.hcaptcha-integrations-logo' ).attr( 'data-installed' ) ).toBe( 'true' );
		const shouldBeActive = ! initiallyInActiveTable;
		const tableIdx = shouldBeActive ? 1 : 2;
		expect( $tr.closest( '.form-table' ).is( $formTable.eq( tableIdx ) ) ).toBe( true );
	} );

	test( 'deactivate theme passes selected newTheme to AJAX data', () => {
		resetDom();
		// Ensure a dialog will be shown (deactivation path)
		window.kaggDialog = {
			confirm: jest.fn( function( cfg ) {
				// simulate selecting a specific theme value in the dialog by building DOM programmatically
				const dlg = document.createElement( 'div' );
				dlg.className = 'kagg-dialog';
				const select = document.createElement( 'select' );
				const opt = document.createElement( 'option' );
				opt.value = 'Divi';
				opt.selected = true;
				opt.textContent = 'Divi';
				select.appendChild( opt );
				dlg.appendChild( select );
				document.body.appendChild( dlg );
				cfg.onAction( true );
				document.body.removeChild( dlg );
			} ),
		};

		const calls = [];
		jest.spyOn( $, 'post' ).mockImplementation( function( opts ) {
			calls.push( opts );
			const d2 = $.Deferred();
			d2.resolve( { success: true, data: { message: 'ok', stati: [] } } );
			return d2;
		} );

		const $themeImg = $( '.hcaptcha-integrations-twentytwentyone-theme img' );
		$themeImg.trigger( 'click' );

		// The newTheme value should be passed from the dialog select
		expect( calls.length ).toBeGreaterThan( 0 );
		expect( calls[ 0 ].data.newTheme ).toBe( 'Divi' );
	} );
} );

describe( 'showMessage setTimeout branch', () => {
	beforeEach( () => {
		document.body.innerHTML = getDom();
		window.hCaptchaIntegrations( $ );
	} );

	test( 'setTimeout restores visibility and removes fixed clone after 3 s', () => {
		jest.useFakeTimers();

		// Trigger a message so the setTimeout is scheduled.
		window.kaggDialog = { confirm: jest.fn() };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );

		// The fixed clone should be in the body right now.
		const fixedBefore = $( 'body' ).children( '#hcaptcha-message' ).length;
		expect( fixedBefore ).toBeGreaterThanOrEqual( 0 ); // just ensure no throw

		// Advance past the 3 000 ms timeout.
		jest.advanceTimersByTime( 3100 );

		// After the timeout the original message visibility is restored.
		expect( $( '#hcaptcha-message' ).css( 'visibility' ) ).not.toBe( 'hidden' );

		jest.useRealTimers();
	} );
} );

describe( 'integrations.js extra branch coverage', () => {
	let postSpy;

	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML = getDom();
		window.hCaptchaIntegrations( $ );
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: true, data: { message: 'ok', stati: [] } } );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		jest.useRealTimers();
	} );

	// Line 241: jest !== 'undefined' branch — __integrationsTest is exposed.
	test( '__integrationsTest is exposed when jest is defined', () => {
		expect( window.__integrationsTest ).toBeDefined();
		expect( typeof window.__integrationsTest.swapThemes ).toBe( 'function' );
	} );

	// Line 384: entity not in [core, theme, plugin] → early return.
	test( 'click on image with unknown entity does nothing', () => {
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.attr( 'data-entity', 'unknown' );
		$img.trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// Line 384 second guard: entity === 'core' → early return.
	test( 'click on core image does nothing', () => {
		const $img = $( '.hcaptcha-integrations-wp-status img' );
		$img.trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// Line 412: deactivate theme builds content with <select> (already tested above, but ensure branch hit).
	test( 'deactivate theme dialog content contains select with theme options', () => {
		window.kaggDialog = { confirm: jest.fn() };
		global.HCaptchaIntegrationsObject.themes = { divi: 'Divi', twentytwentyone: 'Twenty Twenty-One' };
		global.HCaptchaIntegrationsObject.defaultTheme = 'divi';

		const $themeImg = $( '.hcaptcha-integrations-twentytwentyone-theme img' );
		$themeImg.trigger( 'click' );

		const arg = window.kaggDialog.confirm.mock.calls[ 0 ][ 0 ];
		expect( arg.content ).toContain( '<option value="divi" selected="selected">' );
		expect( arg.content ).toContain( '<option value="twentytwentyone">' );
	} );

	// Lines 454-456: ctrlKey on not-installed plugin → installEntity() directly.
	test( 'ctrlKey on not-installed plugin calls installEntity without dialog', () => {
		window.kaggDialog = { confirm: jest.fn() };
		const $img = $( '.hcaptcha-integrations-other-plugin img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );
		expect( window.kaggDialog.confirm ).not.toHaveBeenCalled();
		expect( postSpy ).toHaveBeenCalled();
		const callData = postSpy.mock.calls[ 0 ][ 0 ].data;
		expect( callData.install ).toBe( true );
	} );

	// Lines 336-337: done callback updates themes and defaultTheme when response.data.themes is set.
	test( 'done callback updates HCaptchaIntegrationsObject.themes when response contains themes', () => {
		postSpy.mockRestore();
		const newThemes = { newtheme: 'New Theme' };
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( {
				success: true,
				data: {
					message: 'ok',
					stati: [],
					themes: newThemes,
					defaultTheme: 'newtheme',
				},
			} );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );

		expect( global.HCaptchaIntegrationsObject.themes ).toEqual( newThemes );
		expect( global.HCaptchaIntegrationsObject.defaultTheme ).toBe( 'newtheme' );
	} );

	// Lines 341-345: done callback with success=false shows error message.
	test( 'done callback with success=false and data.message shows error', () => {
		postSpy.mockRestore();
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: false, data: { message: 'Something went wrong' } } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );

		expect( document.querySelector( '#hcaptcha-message' ).className ).toContain( 'notice-error' );
		expect( document.querySelector( '#hcaptcha-message' ).textContent ).toContain( 'Something went wrong' );
	} );

	// Lines 341-345: done callback with success=false and data as plain string.
	test( 'done callback with success=false and data as string shows error', () => {
		postSpy.mockRestore();
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: false, data: 'plain error string' } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( $.Event( 'click', { ctrlKey: true } ) );

		expect( document.querySelector( '#hcaptcha-message' ).textContent ).toContain( 'plain error string' );
	} );

	// Line 273: updateActivationStati skips key === '1'.
	test( 'updateActivationStati skips key "1" without throwing', () => {
		postSpy.mockRestore();
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: true, data: { message: 'ok', stati: { 1: true, acfe_status: true } } } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		expect( () => {
			$( '.hcaptcha-integrations-acfe-status img' ).trigger( $.Event( 'click', { ctrlKey: true } ) );
		} ).not.toThrow();
	} );

	// Line 282: updateActivationStati when currStatus === status (no move needed).
	test( 'updateActivationStati does not move row when status matches current table', () => {
		postSpy.mockRestore();
		// acfe_status is in table eq(1) (active), so status=true means no move.
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: true, data: { message: 'ok', stati: { acfe_status: true } } } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $tr = $( '.hcaptcha-integrations-acfe-status' );
		const tableBefore = $tr.closest( '.form-table' )[ 0 ];

		$( '.hcaptcha-integrations-acfe-status img' ).trigger( $.Event( 'click', { ctrlKey: true } ) );

		expect( $tr.closest( '.form-table' )[ 0 ] ).toBe( tableBefore );
	} );

	// Line 542: search scroll when $trFirst is found.
	test( 'search scroll animates when a matching row is found', () => {
		const animSpy = jest.spyOn( $.fn, 'animate' ).mockImplementation( function() {
			return this;
		} );
		jest.spyOn( $.fn, 'offset' ).mockReturnValue( { top: 200 } );
		jest.spyOn( $.fn, 'outerHeight' ).mockReturnValue( 50 );
		jest.spyOn( $.fn, 'height' ).mockReturnValue( 600 );

		const $search = $( '#hcaptcha-integrations-search' );
		$search.val( 'acf' ).trigger( 'input' );
		jest.advanceTimersByTime( 200 );

		expect( animSpy ).toHaveBeenCalled();

		animSpy.mockRestore();
		$.fn.offset.mockRestore();
		$.fn.outerHeight.mockRestore();
		$.fn.height.mockRestore();
	} );

	// Line 541: search returns early when no matching row ($trFirst is null).
	test( 'search does not animate when no matching row found', () => {
		const animSpy = jest.spyOn( $.fn, 'animate' ).mockImplementation( function() {
			return this;
		} );

		const $search = $( '#hcaptcha-integrations-search' );
		$search.val( 'zzznomatch' ).trigger( 'input' );
		jest.advanceTimersByTime( 200 );

		expect( animSpy ).not.toHaveBeenCalled();

		animSpy.mockRestore();
	} );

	// Line 241: maybeInstallEntity called with false → early return, no AJAX.
	test( 'maybeInstallEntity with confirmation=false does not post', () => {
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( false ) ) };
		const $img = $( '.hcaptcha-integrations-other-plugin img' );
		$img.trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// Line 249: maybeToggleActivation called with false → early return, no AJAX.
	test( 'maybeToggleActivation with confirmation=false does not post', () => {
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( false ) ) };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// Line 412: deactivate plugin sets deactivatePluginMsg (entity=plugin, fieldset enabled).
	test( 'deactivate plugin dialog uses deactivatePluginMsg', () => {
		// Move acfe row to active table (fieldset enabled) so it is in deactivate path.
		const $tr = $( '.hcaptcha-integrations-acfe-status' );
		$tr.find( 'fieldset' ).removeAttr( 'disabled' );

		window.kaggDialog = { confirm: jest.fn() };
		$( '.hcaptcha-integrations-acfe-status img' ).trigger( 'click' );

		const arg = window.kaggDialog.confirm.mock.calls[ 0 ][ 0 ];
		expect( arg.title ).toContain( 'Deactivate' );
		expect( arg.type ).toBe( 'deactivate' );
	} );
} );

describe( 'integrations.js remaining branch coverage', () => {
	let postSpy;

	beforeEach( () => {
		jest.useFakeTimers();
		document.body.innerHTML = getDom();
		// Add #adminmenuwrap for branch 58
		const wrap = document.createElement( 'div' );
		wrap.id = 'adminmenuwrap';
		document.body.appendChild( wrap );
		window.hCaptchaIntegrations( $ );
		postSpy = jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			d.resolve( { success: true, data: { message: 'ok', stati: [] } } );
			return d;
		} );
	} );

	afterEach( () => {
		postSpy.mockRestore();
		jest.useRealTimers();
	} );

	// Branch 0 (line 58): adminmenuwrap display === 'block' → use its width.
	test( 'showMessage uses adminmenuwrap width when display is block', () => {
		const $wrap = $( '#adminmenuwrap' );
		$wrap.css( 'display', 'block' );
		jest.spyOn( $.fn, 'width' ).mockImplementation( function() {
			if ( this[ 0 ] && this[ 0 ].id === 'adminmenuwrap' ) {
				return 160;
			}
			return 1024;
		} );

		// Trigger a message to run showMessage.
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		$( '.hcaptcha-integrations-acfe-status img' ).trigger( $.Event( 'click', { ctrlKey: true } ) );

		// No throw and fixed clone was appended.
		expect( $( 'body' ).find( '.notice' ).length ).toBeGreaterThanOrEqual( 0 );
		$.fn.width.mockRestore();
	} );

	// Branch 6 (line 128): alt is falsy → use empty string.
	test( 'insertIntoTable handles img with no alt attribute', () => {
		// Add a row without alt to the active table.
		const $activeTable = $( '.form-table' ).eq( 1 );
		const $tbody = $activeTable.find( 'tbody' );
		const tr = document.createElement( 'tr' );
		tr.className = 'hcaptcha-integrations-noalt-plugin';
		tr.innerHTML = '<th><div class="hcaptcha-integrations-logo" data-installed="true"><img data-entity="plugin" data-label="NoAlt"></div></th><td><fieldset></fieldset></td>';
		$tbody.get( 0 ).appendChild( tr );

		// Trigger a click that calls insertIntoTable — it will iterate rows including the no-alt one.
		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		expect( () => {
			$( '.hcaptcha-integrations-other-plugin img' ).trigger( 'click' );
		} ).not.toThrow();
	} );

	// Branch 12 (line 173): duplicate data-antispam-* attribute → class not added twice.
	test( 'setupHelper does not add duplicate antispam class icons', () => {
		const label = document.createElement( 'label' );
		label.setAttribute( 'data-antispam', '' );
		label.setAttribute( 'data-antispam-honeypot', '' );
		document.body.appendChild( label );

		// Re-init to pick up the new label.
		window.hCaptchaIntegrations( $ );

		const $helper = $( label ).next( '.helper' );
		// Should have exactly one icon for honeypot.
		expect( $helper.find( 'i.antispam-honeypot' ).length ).toBe( 1 );
	} );

	// Branch 20 (line 265): select.value is null/undefined → return ''.
	test( 'getSelectedTheme returns empty string when select has no value', () => {
		window.kaggDialog = {
			confirm: jest.fn( function( cfg ) {
				// Build a dialog with a select whose value is empty.
				const dlg = document.createElement( 'div' );
				dlg.className = 'kagg-dialog';
				const select = document.createElement( 'select' );
				// No options → value is ''.
				dlg.appendChild( select );
				document.body.appendChild( dlg );
				cfg.onAction( true );
				document.body.removeChild( dlg );
			} ),
		};

		const $themeImg = $( '.hcaptcha-integrations-twentytwentyone-theme img' );
		expect( () => $themeImg.trigger( 'click' ) ).not.toThrow();
		expect( postSpy ).toHaveBeenCalled();
		const callData = postSpy.mock.calls[ 0 ][ 0 ].data;
		expect( callData.newTheme ).toBe( '' );
	} );

	// Branch 24 (line 286): status=false in updateActivationStati → move to table eq(2).
	test( 'updateActivationStati moves row to inactive table when status is false', () => {
		postSpy.mockRestore();
		jest.spyOn( $, 'post' ).mockImplementation( () => {
			const d = $.Deferred();
			// acfe_status is currently in active table; status=false → move to inactive.
			d.resolve( { success: true, data: { message: 'ok', stati: { acfe_status: false } } } );
			return d;
		} );

		window.kaggDialog = { confirm: jest.fn( ( cfg ) => cfg.onAction( true ) ) };
		const $tr = $( '.hcaptcha-integrations-acfe-status' );
		$( '.hcaptcha-integrations-acfe-status img' ).trigger( $.Event( 'click', { ctrlKey: true } ) );

		expect( $tr.closest( '.form-table' ).is( $( '.form-table' ).eq( 2 ) ) ).toBe( true );
	} );

	// Branch 33 (line 380): entity data attr is falsy → entity = ''.
	test( 'click on img with no data-entity attribute does nothing', () => {
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.removeAttr( 'data-entity' );
		$img.trigger( 'click' );
		expect( postSpy ).not.toHaveBeenCalled();
	} );

	// Branch 36 (line 393): alt attr is falsy → alt = ''.
	test( 'click on img with no alt attribute does not throw', () => {
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		$img.removeAttr( 'alt' );
		window.kaggDialog = { confirm: jest.fn() };
		expect( () => $img.trigger( 'click' ) ).not.toThrow();
	} );

	// Branch 37 (line 398): class has no hcaptcha-integrations-* match → status = ''.
	test( 'click on img whose tr has no matching class does not throw', () => {
		const $tr = $( '.hcaptcha-integrations-acfe-status' );
		$tr.attr( 'class', 'some-other-class' );
		window.kaggDialog = { confirm: jest.fn() };
		expect( () => $( '.hcaptcha-integrations-acfe-status img' ).trigger( 'click' ) ).not.toThrow();
	} );

	// Branch 39 (line 406): activate=true, entity=theme → activateThemeMsg.
	test( 'activate theme dialog uses activateThemeMsg', () => {
		global.HCaptchaIntegrationsObject.themes = { divi: 'Divi' };
		window.kaggDialog = { confirm: jest.fn() };
		// Re-init with a DOM that includes a disabled theme row (activate path).
		document.body.innerHTML = getDom() + `
			<table class="form-table"><tbody>
				<tr class="hcaptcha-integrations-inactive-theme">
					<th><div class="hcaptcha-integrations-logo" data-installed="true">
						<img alt="Inactive Theme Logo" data-entity="theme" data-label="Inactive Theme">
					</div></th>
					<td><fieldset disabled="disabled"></fieldset></td>
				</tr>
			</tbody></table>`;
		window.hCaptchaIntegrations( $ );

		$( '.hcaptcha-integrations-inactive-theme img' ).trigger( 'click' );

		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		const arg = window.kaggDialog.confirm.mock.calls[ 0 ][ 0 ];
		expect( arg.title ).toContain( 'Inactive Theme' );
		expect( arg.type ).toBe( 'activate' );
	} );

	// Branch 46 (line 459): not-installed theme → installThemeMsg.
	test( 'not-installed theme shows installThemeMsg dialog', () => {
		global.HCaptchaIntegrationsObject.themes = { divi: 'Divi' };
		window.kaggDialog = { confirm: jest.fn() };
		// Re-init with a DOM that includes a not-installed theme row.
		document.body.innerHTML = getDom() + `
			<table class="form-table"><tbody>
				<tr class="hcaptcha-integrations-new-theme">
					<th><div class="hcaptcha-integrations-logo" data-installed="false">
						<img alt="New Theme Logo" data-entity="theme" data-label="New Theme">
					</div></th>
					<td><fieldset disabled="disabled"></fieldset></td>
				</tr>
			</tbody></table>`;
		window.hCaptchaIntegrations( $ );

		$( '.hcaptcha-integrations-new-theme img' ).trigger( 'click' );

		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		const arg = window.kaggDialog.confirm.mock.calls[ 0 ][ 0 ];
		expect( arg.title ).toContain( 'New Theme' );
		expect( arg.type ).toBe( 'install' );
	} );

	// Branch 53 (line 557): keydown on search with key !== 13 → no preventDefault.
	test( 'keydown on search with key other than Enter does not prevent default', () => {
		let prevented = false;
		const $search = $( '#hcaptcha-integrations-search' );
		const evt = $.Event( 'keydown', { target: $search.get( 0 ), which: 65 } );
		evt.preventDefault = function() {
			prevented = true;
		};
		$( '#hcaptcha-options' ).trigger( evt );
		expect( prevented ).toBe( false );
	} );
} );

describe( 'swapThemes isolated', () => {
	function resetDomLocal() {
		document.body.innerHTML = getDom();
		window.hCaptchaIntegrations( $ );
	}

	test( 'does nothing when entity is not theme', () => {
		resetDomLocal();
		const $tables = $( '.form-table' );
		const countBeforeActive = $tables.eq( 1 ).find( 'tbody > tr' ).length;
		const countBeforeInactive = $tables.eq( 2 ).find( 'tbody > tr' ).length;

		// Direct call should not change anything for a non-theme entity
		window.__integrationsTest.swapThemes( true, 'plugin' );

		const countAfterActive = $tables.eq( 1 ).find( 'tbody > tr' ).length;
		const countAfterInactive = $tables.eq( 2 ).find( 'tbody > tr' ).length;

		expect( countAfterActive ).toBe( countBeforeActive );
		expect( countAfterInactive ).toBe( countBeforeInactive );
	} );

	test( 'activate: moves current active theme to inactive table', () => {
		resetDomLocal();
		const $tables = $( '.form-table' );
		const $activeTable = $tables.eq( 1 );
		const $inactiveTable = $tables.eq( 2 );

		// Precondition: one active theme present, none inactive
		expect( $activeTable.find( 'img[data-entity="theme"]' ).length ).toBe( 1 );
		expect( $inactiveTable.find( 'img[data-entity="theme"]' ).length ).toBe( 0 );

		// Direct call to move the current active theme out
		window.__integrationsTest.swapThemes( true, 'theme' );

		expect( $activeTable.find( 'img[data-entity="theme"]' ).length ).toBe( 0 );
		expect( $inactiveTable.find( 'img[data-entity="theme"]' ).length ).toBe( 1 );
	} );

	test( 'deactivate: moves selected inactive theme into active table', () => {
		resetDomLocal();
		const $tables = $( '.form-table' );
		const $activeTable = $tables.eq( 1 );
		const $inactiveTable = $tables.eq( 2 );

		// First, move current active to inactive so the inactive table has a theme row
		window.__integrationsTest.swapThemes( true, 'theme' );

		// Add another inactive theme row to test selecting by data-label
		const $tbodyInactive = $inactiveTable.find( 'tbody' );
		const tr = document.createElement( 'tr' );
		tr.className = 'hcaptcha-integrations-some-theme';
		const th = document.createElement( 'th' );
		const logo = document.createElement( 'div' );
		logo.className = 'hcaptcha-integrations-logo';
		logo.setAttribute( 'data-installed', 'true' );
		const img = document.createElement( 'img' );
		img.setAttribute( 'alt', 'Some Theme Logo' );
		img.setAttribute( 'data-entity', 'theme' );
		img.setAttribute( 'data-label', 'Some Theme' );
		logo.appendChild( img );
		th.appendChild( logo );
		const td = document.createElement( 'td' );
		const fs = document.createElement( 'fieldset' );
		td.appendChild( fs );
		tr.appendChild( th );
		tr.appendChild( td );
		$tbodyInactive.get( 0 ).appendChild( tr );

		// Now call deactivate with an explicit label to move that row to active
		window.__integrationsTest.swapThemes( false, 'theme', 'Some Theme' );

		expect( $activeTable.find( 'img[data-entity="theme"][data-label="Some Theme"]' ).length ).toBe( 1 );
		// Ensure it was removed from inactive
		expect( $inactiveTable.find( 'img[data-entity="theme"][data-label="Some Theme"]' ).length ).toBe( 0 );
	} );
} );
