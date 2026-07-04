// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const fieldClass = 'hcaptcha-for-ninja-forms';

function setupMarionette() {
	const channels = {
		app: {},
		fields: {},
	};

	global.Backbone = {
		Radio: {
			channel: jest.fn( ( name ) => channels[ name ] ),
		},
	};
	global.Marionette = {
		Object: {
			extend( proto ) {
				function Controller() {
					this.listenTo = jest.fn();
					proto.initialize.call( this );
				}

				Object.assign( Controller.prototype, proto );

				return Controller;
			},
		},
	};
}

describe( 'admin-nf.js', () => {
	let readyCallback;
	let originalMutationObserver;
	let observers;

	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '<div id="nf-builder"></div><div id="nf-main-body"></div>';
		window.HCaptchaAdminNFObject = {
			OKBtnText: 'OK',
			hCaptchaTemplate: '<div class="h-captcha"></div>',
			onlyOne: 'Only one hCaptcha field is allowed.',
		};
		global.HCaptchaAdminNFObject = window.HCaptchaAdminNFObject;
		global.nfDashInlineVars = {
			preloadedFormData: {
				fields: [
					{
						type: fieldClass,
						hcaptcha: '<div class="h-captcha">rendered</div>',
					},
				],
			},
		};
		global.kaggDialog = {
			confirm: jest.fn(),
		};
		window.hCaptchaBindEvents = jest.fn();
		setupMarionette();

		observers = [];
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

		readyCallback = null;
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
		require( '../../../assets/js/admin-nf.js' );
		readyCallback();

		return window.HCaptchaAdminFieldController;
	}

	test( 'initializes channels and the builder mouse handler', () => {
		const addEventListenerSpy = jest.spyOn( document.getElementById( 'nf-builder' ), 'addEventListener' );
		const controller = boot();

		expect( addEventListenerSpy ).toHaveBeenCalledWith( 'mousedown', controller.checkAddingHCaptcha, true );
		expect( global.Backbone.Radio.channel ).toHaveBeenCalledWith( 'app' );
		expect( global.Backbone.Radio.channel ).toHaveBeenCalledWith( 'fields' );
		expect( controller.listenTo ).toHaveBeenCalledTimes( 3 );
	} );

	test( 'prevents adding duplicate hCaptcha fields', () => {
		const controller = boot();
		document.body.insertAdjacentHTML( 'beforeend', `<div class="${ fieldClass }"></div>` );
		const event = {
			target: {
				dataset: { id: fieldClass },
				classList: document.createElement( 'button' ).classList,
			},
			stopImmediatePropagation: jest.fn(),
		};

		controller.checkAddingHCaptcha( event );

		expect( event.stopImmediatePropagation ).toHaveBeenCalled();
		expect( global.kaggDialog.confirm ).toHaveBeenCalledWith( {
			title: window.HCaptchaAdminNFObject.onlyOne,
			content: '',
			type: 'info',
			buttons: {
				ok: {
					text: window.HCaptchaAdminNFObject.OKBtnText,
				},
			},
		} );
	} );

	test( 'allows unrelated clicks and first hCaptcha field click', () => {
		const controller = boot();
		const event = {
			target: {
				dataset: { id: 'textbox' },
				classList: document.createElement( 'button' ).classList,
			},
			stopImmediatePropagation: jest.fn(),
		};

		controller.checkAddingHCaptcha( event );
		event.target.dataset.id = fieldClass;
		controller.checkAddingHCaptcha( event );

		expect( event.stopImmediatePropagation ).not.toHaveBeenCalled();
		expect( global.kaggDialog.confirm ).not.toHaveBeenCalled();
	} );

	test( 'duplicate button click is treated like adding hCaptcha', () => {
		const controller = boot();
		const target = document.createElement( 'button' );
		target.classList.add( 'nf-duplicate' );
		document.body.insertAdjacentHTML( 'beforeend', `<div class="${ fieldClass }"></div>` );
		const event = {
			target,
			stopImmediatePropagation: jest.fn(),
		};

		controller.checkAddingHCaptcha( event );

		expect( event.stopImmediatePropagation ).toHaveBeenCalled();
	} );

	test( 'renderHCaptcha handles missing containers, existing widgets, and new widgets', () => {
		const controller = boot();
		const missing = document.createElement( 'div' );
		const existing = document.createElement( 'div' );
		const node = document.createElement( 'div' );

		existing.innerHTML = '<div class="nf-realistic-field--element"><div><div class="h-captcha"></div></div></div>';
		node.innerHTML = '<div class="nf-realistic-field--element"><div></div></div>';

		controller.renderHCaptcha( missing );
		controller.renderHCaptcha( existing );
		controller.renderHCaptcha( node );

		expect( existing.querySelectorAll( '.h-captcha' ) ).toHaveLength( 1 );
		expect( node.querySelector( '.h-captcha' ).textContent ).toBe( 'rendered' );
	} );

	test( 'field lifecycle handlers observe only hCaptcha fields', () => {
		const controller = boot();
		const observeSpy = jest.spyOn( controller, 'observeField' );
		const target = document.createElement( 'button' );
		const parent = document.createElement( 'div' );
		parent.appendChild( target );
		document.body.appendChild( parent );

		controller.editField( { target } );
		controller.addField();
		parent.className = fieldClass;
		controller.editField( { target } );
		controller.closeDrawer();
		controller.addField();
		parent.classList.add( 'active' );
		controller.closeDrawer();
		controller.addField();

		expect( observeSpy ).toHaveBeenCalledTimes( 4 );
	} );

	test( 'observeField binds hCaptcha events and renders added hCaptcha nodes once', () => {
		const controller = boot();
		const node = document.createElement( 'div' );
		node.className = fieldClass;
		node.innerHTML = '<div class="nf-realistic-field--element"><div></div></div>';
		document.body.insertAdjacentHTML( 'beforeend', '<div class="h-captcha"></div>' );

		controller.observeField();
		controller.observeField();
		observers[ 0 ].callback( [ { addedNodes: [ document.createElement( 'span' ), node ] } ] );

		expect( observers ).toHaveLength( 1 );
		expect( window.hCaptchaBindEvents ).toHaveBeenCalledTimes( 2 );
		expect( node.querySelector( '.h-captcha' ).textContent ).toBe( 'rendered' );
		expect( observers[ 0 ].observe ).toHaveBeenCalledWith(
			document.getElementById( 'nf-main-body' ),
			{
				childList: true,
				subtree: true,
			},
		);
	} );

	test( 'observeField skips binding when hCaptcha markup already has content', () => {
		const controller = boot();
		document.body.insertAdjacentHTML( 'beforeend', '<div class="h-captcha">bound</div>' );

		controller.observeField();
		observers[ 0 ].callback( [ { addedNodes: [ document.createElement( 'span' ) ] } ] );

		expect( window.hCaptchaBindEvents ).not.toHaveBeenCalled();
	} );
} );
