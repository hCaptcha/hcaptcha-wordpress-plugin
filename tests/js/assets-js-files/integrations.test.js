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
	themes: { twentynineteen: 'Twenty Nineteen', twentytwenty: 'Twenty Twenty' },
	defaultTheme: 'twentytwenty',
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
		<tr class="hcaptcha-integrations-twentytwenty-theme">
			<th scope="row">
				<div class="hcaptcha-integrations-logo" data-installed="true">
					<img alt="Twenty Twenty Logo" data-entity="theme" data-label="Twenty Twenty">
				</div>
			</th>
			<td>
				<fieldset>
					<label>Twenty Twenty Active</label>
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

		// Other Plugin row visible, ACF row hidden (label does not contain "other"); core unaffected but skipped
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

		const $themeImg = $( '.hcaptcha-integrations-twentytwenty-theme img' );
		$themeImg.trigger( 'click' );

		expect( window.kaggDialog.confirm ).toHaveBeenCalled();
		expect( postSpy ).not.toHaveBeenCalled();

		// Restore themes for other tests
		global.HCaptchaIntegrationsObject.themes = { twentynineteen: 'Twenty Nineteen', twentytwenty: 'Twenty Twenty' };
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
		const $themeImg = $( '.hcaptcha-integrations-twentytwenty-theme img' );
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
			// Resolve with object without success field
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

		// Pick an installed plugin to trigger toggle without install
		window.kaggDialog = { confirm: jest.fn() };
		const $img = $( '.hcaptcha-integrations-acfe-status img' );
		// Trigger ctrl-click to bypass dialog and attach .done/.fail/.always handlers
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
		const initiallyInActiveTable = $tr.closest( '.form-table' ).is( $( '.form-table' ).eq( 1 ) );

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
		expect( $tr.closest( '.form-table' ).is( $( '.form-table' ).eq( tableIdx ) ) ).toBe( true );
	} );

	test( 'deactivate theme passes selected newTheme to AJAX data', () => {
		resetDom();
		// Ensure dialog will be shown (deactivation path)
		window.kaggDialog = {
			confirm: jest.fn( function( cfg ) {
				// simulate selecting a specific theme value in dialog by building DOM programmatically
				const dlg = document.createElement( 'div' );
				dlg.className = 'kagg-dialog';
				const select = document.createElement( 'select' );
				const opt = document.createElement( 'option' );
				opt.value = 'twentynineteen';
				opt.selected = true;
				opt.textContent = 'Twenty Nineteen';
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

		const $themeImg = $( '.hcaptcha-integrations-twentytwenty-theme img' );
		$themeImg.trigger( 'click' );

		// The newTheme value should be passed from the dialog select
		expect( calls.length ).toBeGreaterThan( 0 );
		expect( calls[ 0 ].data.newTheme ).toBe( 'twentynineteen' );
	} );
} );
