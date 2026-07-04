// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const defaultKadenceAdvancedObject = {
	noticeLabel: 'hCaptcha notice',
	noticeDescription: 'hCaptcha protects this form.',
};

function makePanel( selectValue = 'hcaptcha', options = [ 'recaptcha', 'hcaptcha' ] ) {
	const panel = document.createElement( 'div' );
	const optionsHtml = options.map( ( value ) => {
		return `<option value="${ value }">${ value }</option>`;
	} ).join( '' );

	panel.className = 'components-panel__body';
	panel.innerHTML = `
		<div class="components-base-control">
			<select>${ optionsHtml }</select>
		</div>
		<input type="text" disabled>
	`;
	panel.querySelector( 'select' ).value = selectValue;

	return panel;
}

describe( 'admin-kadence-advanced.js', () => {
	let readyCallback;
	let originalMutationObserver;
	let observers;

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '<div id="editor"></div>';
		window.HCaptchaKadenceAdvancedFormObject = { ...defaultKadenceAdvancedObject };
		global.HCaptchaKadenceAdvancedFormObject = window.HCaptchaKadenceAdvancedFormObject;
		observers = [];
		readyCallback = null;
		originalMutationObserver = window.MutationObserver;

		class MockMutationObserver {
			constructor( callback ) {
				this.callback = callback;
				this.observe = jest.fn();
				observers.push( this );
			}
		}

		window.MutationObserver = MockMutationObserver;
		global.MutationObserver = MockMutationObserver;
		jest.spyOn( document, 'addEventListener' ).mockImplementation( ( type, callback ) => {
			if ( type === 'DOMContentLoaded' ) {
				readyCallback = callback;
			}
		} );
	} );

	afterEach( () => {
		window.MutationObserver = originalMutationObserver;
		global.MutationObserver = originalMutationObserver;
		jest.restoreAllMocks();
	} );

	function boot() {
		require( '../../../assets/js/admin-kadence-advanced.js' );
		readyCallback();
	}

	test( 'updates hCaptcha panels and ignores unrelated editor nodes', () => {
		boot();
		const unrelated = document.createElement( 'div' );
		const panel = makePanel();

		observers[ 0 ].callback( [ { addedNodes: [ unrelated, panel ] } ] );

		expect( panel.querySelector( '.hcaptcha-notice label' ).innerHTML ).toBe( defaultKadenceAdvancedObject.noticeLabel );
		expect( panel.querySelector( '.hcaptcha-notice p' ).innerHTML ).toBe( defaultKadenceAdvancedObject.noticeDescription );
		expect( panel.querySelector( 'input' ).disabled ).toBe( true );
		expect( observers[ 1 ].observe ).toHaveBeenCalledWith(
			panel,
			{
				childList: true,
				subtree: true,
			},
		);
	} );

	test( 'panel observer updates inputs only for added nodes containing buttons', () => {
		boot();
		const panel = makePanel( 'recaptcha' );
		panel.insertAdjacentHTML( 'beforeend', '<div class="hcaptcha-notice">Old notice</div>' );
		observers[ 0 ].callback( [ { addedNodes: [ panel ] } ] );

		const fakeNode = {
			hasOwnProperty: jest.fn( () => true ),
			querySelector: jest.fn( () => document.createElement( 'button' ) ),
			closest: jest.fn( () => panel ),
		};

		observers[ 1 ].callback( [ { addedNodes: [ document.createElement( 'span' ), fakeNode ] } ] );

		expect( panel.querySelector( '.hcaptcha-notice' ) ).toBeNull();
		expect( panel.querySelector( 'input' ).disabled ).toBe( false );
	} );

	test( 'returns when panel has no select or no hCaptcha option', () => {
		boot();
		const noSelectPanel = document.createElement( 'div' );
		noSelectPanel.className = 'components-panel__body';
		noSelectPanel.innerHTML = '<input type="text" disabled>';
		const noHCaptchaPanel = makePanel( 'recaptcha', [ 'recaptcha' ] );

		observers[ 0 ].callback( [ { addedNodes: [ noSelectPanel, noHCaptchaPanel ] } ] );

		expect( noSelectPanel.querySelector( 'input' ).disabled ).toBe( true );
		expect( noHCaptchaPanel.querySelector( 'input' ).disabled ).toBe( true );
		expect( noHCaptchaPanel.querySelector( '.hcaptcha-notice' ) ).toBeNull();
	} );
} );
