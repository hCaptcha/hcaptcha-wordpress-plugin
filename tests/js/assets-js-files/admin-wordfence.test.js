// noinspection JSUnresolvedVariable

import $ from 'jquery';

global.jQuery = $;
global.$ = $;

global.HCaptchaWordfenceObject = {
	noticeLabel: 'Test Notice Label',
	noticeDescription: 'Test Notice Description',
};

function getDom() {
	// language=HTML
	return `
		<div id="wfls-hcaptcha-settings">
			<div class="wfls-block-content">
				<ul id="wfls-option-enable-hcaptcha">
					<li id="wfls-enable-hcaptcha" role="checkbox" tabindex="0"></li>
					<li id="wfls-hcaptcha-details">
						<input id="input-hcaptchaSiteKey" type="text">
						<input id="input-hcaptchaSecret" type="text">
					</li>
				</ul>
			</div>
		</div>
	`;
}

describe( 'admin-wordfence', () => {
	beforeEach( () => {
		document.body.innerHTML = getDom();
		window.hCaptchaWordfence = undefined;
		jest.resetModules();

		require( '../../../assets/js/admin-wordfence.js' );
	} );

	test( 'replaces native hCaptcha settings with the plugin notice', () => {
		window.hCaptchaWordfence.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=WFLS#top#settings';

		window.hCaptchaWordfence.ready();

		const $notice = $( '.hcaptcha-wordfence-notice' );

		expect( $notice.find( 'strong' ).html() ).toBe( global.HCaptchaWordfenceObject.noticeLabel );
		expect( $notice.find( 'p' ).html() ).toBe( global.HCaptchaWordfenceObject.noticeDescription );
		expect( $( '#wfls-option-enable-hcaptcha' ).length ).toBe( 0 );
		expect( $( '#input-hcaptchaSiteKey' ).length ).toBe( 0 );
		expect( $( '#input-hcaptchaSecret' ).length ).toBe( 0 );
	} );

	test( 'does not modify another admin page', () => {
		window.hCaptchaWordfence.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=some-page';

		window.hCaptchaWordfence.ready();

		expect( $( '#wfls-option-enable-hcaptcha' ).length ).toBe( 1 );
		expect( $( '.hcaptcha-wordfence-notice' ).length ).toBe( 0 );
	} );

	test( 'does nothing when the Wordfence hCaptcha settings are unavailable', () => {
		document.body.innerHTML = '';
		window.hCaptchaWordfence.getLocationHref = () => 'https://test.test/wp-admin/admin.php?page=WFLS';

		window.hCaptchaWordfence.ready();

		expect( $( '.hcaptcha-wordfence-notice' ).length ).toBe( 0 );
	} );

	test( 'getLocationHref returns the current location', () => {
		expect( window.hCaptchaWordfence.getLocationHref() ).toBe( 'http://domain.tld/' );
	} );
} );
