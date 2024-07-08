// noinspection JSUnresolvedFunction,JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

require( '../../../assets/js/integrations.js' );

// Mock HCaptchaIntegrationsObject
global.HCaptchaIntegrationsObject = {
	ajaxUrl: 'https://test.test/wp-admin/admin-ajax.php',
	action: 'test_action',
	nonce: 'test_nonce',
	activateMsg: 'Activate %s plugin?',
	deactivateMsg: 'Deactivate %s plugin?',
};

function getDom() {
	return `
<html lang="en">
<body>
<div id="wpwrap">
<div id="hcaptcha-message"></div>
	<table class="form-table">
		<tbody>
		<tr class="hcaptcha-integrations-wp-status">
			<th scope="row">
				<div class="hcaptcha-integrations-logo">
					<img
						src="https://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/wp-core-logo.png"
					 	alt="WP Core Logo" data-entity="core">
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
				</fieldset>
			</td>
		</tr>
		</tbody>
	</table>
	<table class="form-table">
		<tbody>
		<tr class="hcaptcha-integrations-acfe-status">
			<th scope="row">
				<div class="hcaptcha-integrations-logo">
					<img
						src="https://test.test/wp-content/plugins/hcaptcha-wordpress-plugin/assets/images/acf-extended-logo.png"
					 	alt="ACF Extended Logo" data-entity="plugin">
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
		</tbody>
	</table>
</div>
</body>
</html>
    `;
}

describe( 'integrations', () => {
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
