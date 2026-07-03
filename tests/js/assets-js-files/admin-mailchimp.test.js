// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const defaultMailchimpObject = {
	action: 'hcaptcha_mailchimp',
	name: 'hcaptcha_mailchimp_nonce',
	nonceField: '<input type="hidden" name="hcaptcha_mailchimp_nonce" value="nonce">',
	widget: '<textarea name="h-captcha-response"></textarea>',
};

function getAvailableFields( buttons = '<button type="button">First</button><button type="button">Second</button>' ) {
	return `
		<div id="mc4wp-available-fields">
			<div></div>
			<div>${ buttons }</div>
		</div>
	`;
}

function getFieldsDom( content ) {
	return `${ getAvailableFields() }<div class="mc4wp-form-fields">${ content }</div>`;
}

describe( 'admin-mailchimp.js', () => {
	let readyCallback;
	let editor;

	beforeEach( () => {
		jest.resetModules();
		jest.useFakeTimers();
		readyCallback = null;
		editor = {
			getValue: jest.fn( () => '' ),
			insert: jest.fn(),
		};
		window.HCaptchaMailchimpObject = { ...defaultMailchimpObject };
		global.HCaptchaMailchimpObject = window.HCaptchaMailchimpObject;
		window.hCaptcha = {
			getParams: jest.fn( () => ( {
				sitekey: 'site-key',
				force: true,
				theme: 'dark',
				size: 'compact',
			} ) ),
		};
		global.hCaptcha = window.hCaptcha;
		window.mc4wp = {
			forms: {
				editor,
			},
		};
		const originalAddEventListener = document.addEventListener.bind( document );
		jest.spyOn( document, 'addEventListener' ).mockImplementation( ( type, callback, options ) => {
			if ( type === 'DOMContentLoaded' ) {
				readyCallback = callback;

				return;
			}

			originalAddEventListener( type, callback, options );
		} );
	} );

	afterEach( () => {
		jest.useRealTimers();
		jest.restoreAllMocks();
	} );

	function boot( dom ) {
		document.body.innerHTML = dom;
		require( '../../../assets/js/admin-mailchimp.js' );
		readyCallback();
	}

	function fireReadyEvents() {
		document.dispatchEvent( new Event( 'hCaptchaLoaded' ) );
		document.querySelector( '.mc4wp-form-fields' ).dispatchEvent( new Event( 'mc4wp-refresh' ) );
		jest.runOnlyPendingTimers();
	}

	test( 'returns when the form fields container is missing', () => {
		boot( getAvailableFields() );
		jest.runOnlyPendingTimers();

		expect( document.querySelector( 'button[value="hcaptcha"]' ) ).toBeNull();
	} );

	test( 'adds the field button before the second available button and inserts shortcode on click', () => {
		boot( getFieldsDom( '<p><input type="submit"></p>' ) );

		const button = document.querySelector( 'button[value="hcaptcha"]' );
		button.click();

		expect( button.nextElementSibling.textContent ).toBe( 'Second' );
		expect( editor.insert ).toHaveBeenCalledWith( '[hcaptcha]' );
	} );

	test( 'button click does nothing when shortcode is already in the editor', () => {
		editor.getValue.mockReturnValue( '[hcaptcha]' );
		boot( getFieldsDom( '<p><input type="submit"></p>' ) );

		document.querySelector( 'button[value="hcaptcha"]' ).click();

		expect( editor.insert ).not.toHaveBeenCalled();
	} );

	test( 'adds the field button to the end when there is no second button', () => {
		boot( `${ getAvailableFields( '<button type="button">Only</button>' ) }<div class="mc4wp-form-fields"></div>` );

		const secondDiv = document.querySelectorAll( '#mc4wp-available-fields div' )[ 1 ];

		expect( secondDiv.lastElementChild.value ).toBe( 'hcaptcha' );
	} );

	test( 'does not add the field button when prerequisites are absent or it already exists', () => {
		boot( '<div class="mc4wp-form-fields"></div>' );
		expect( document.querySelector( 'button[value="hcaptcha"]' ) ).toBeNull();

		jest.resetModules();
		boot( '<div id="mc4wp-available-fields"><div></div></div><div class="mc4wp-form-fields"></div>' );
		expect( document.querySelector( 'button[value="hcaptcha"]' ) ).toBeNull();

		jest.resetModules();
		boot(
			`${ getAvailableFields( '<button type="button" value="hcaptcha">hCaptcha</button>' ) }` +
			'<div class="mc4wp-form-fields"></div>',
		);
		expect( document.querySelectorAll( 'button[value="hcaptcha"]' ) ).toHaveLength( 1 );
	} );

	test( 'replaces shortcode with rendered hCaptcha markup and marks button in form', () => {
		boot( getFieldsDom( '<p>[hcaptcha theme="auto" size="invisible" auto="1" force="1" id="form-id"]</p>' ) );

		fireReadyEvents();

		const fields = document.querySelector( '.mc4wp-form-fields' );
		const hCaptcha = fields.querySelector( 'h-captcha' );
		const button = document.querySelector( 'button[value="hcaptcha"]' );

		expect( hCaptcha.dataset.sitekey ).toBe( 'site-key' );
		expect( hCaptcha.dataset.theme ).toBe( 'auto' );
		expect( hCaptcha.dataset.size ).toBe( 'invisible' );
		expect( hCaptcha.dataset.auto ).toBe( 'true' );
		expect( hCaptcha.dataset.force ).toBe( 'true' );
		expect( button.classList.contains( 'in-form' ) ).toBe( true );
		expect( button.classList.contains( 'not-in-form' ) ).toBe( false );
	} );

	test( 'inserts hCaptcha before submit when no shortcode is present', () => {
		window.hCaptcha.getParams.mockReturnValue( {
			sitekey: 'site-key',
			force: true,
			theme: 'invalid',
			size: 'invalid',
		} );
		boot( getFieldsDom( '<p><input type="submit" value="Subscribe"></p>' ) );

		fireReadyEvents();

		const fields = document.querySelector( '.mc4wp-form-fields' );
		const hCaptcha = fields.querySelector( 'h-captcha' );
		const button = document.querySelector( 'button[value="hcaptcha"]' );

		expect( hCaptcha.dataset.theme ).toBe( 'light' );
		expect( hCaptcha.dataset.size ).toBe( 'normal' );
		expect( hCaptcha.dataset.force ).toBe( 'true' );
		expect( button.classList.contains( 'not-in-form' ) ).toBe( true );
	} );

	test( 'protect=false shortcode is removed without rendering hCaptcha', () => {
		boot( getFieldsDom( '<p>[hcaptcha protect=""]</p>' ) );

		fireReadyEvents();

		expect( document.querySelector( '.mc4wp-form-fields' ).innerHTML ).not.toContain( '[hcaptcha' );
		expect( document.querySelector( '.mc4wp-form-fields h-captcha' ) ).toBeNull();
	} );

	test( 'does not render when refresh prerequisites or insertion target are missing', () => {
		boot( getFieldsDom( '<p>No submit button</p>' ) );
		jest.runOnlyPendingTimers();

		expect( document.querySelector( '.mc4wp-form-fields h-captcha' ) ).toBeNull();

		document.dispatchEvent( new Event( 'hCaptchaLoaded' ) );
		document.querySelector( '.mc4wp-form-fields' ).dispatchEvent( new Event( 'mc4wp-refresh' ) );
		jest.runOnlyPendingTimers();

		expect( document.querySelector( '.mc4wp-form-fields h-captcha' ) ).toBeNull();
	} );

	test( 'renders with default params when hCaptcha params are absent', () => {
		window.hCaptcha.getParams.mockReturnValue( null );
		boot( getFieldsDom( '<p><input type="submit" value="Subscribe"></p>' ) );

		fireReadyEvents();

		const hCaptcha = document.querySelector( '.mc4wp-form-fields h-captcha' );

		expect( hCaptcha.dataset.sitekey ).toBe( '' );
		expect( hCaptcha.dataset.theme ).toBe( 'light' );
		expect( hCaptcha.dataset.size ).toBe( 'normal' );
	} );
	test( 'shortcode rendering uses fallback params when global params omit display settings', () => {
		window.hCaptcha.getParams
			.mockReturnValueOnce( null )
			.mockReturnValue( { sitekey: 'site-key' } );
		boot( getFieldsDom( '<p>[hcaptcha]</p>' ) );

		fireReadyEvents();

		const hCaptcha = document.querySelector( '.mc4wp-form-fields h-captcha' );

		expect( hCaptcha.dataset.theme ).toBe( 'light' );
		expect( hCaptcha.dataset.size ).toBe( 'normal' );
		expect( hCaptcha.dataset.force ).toBe( 'false' );
	} );
} );
