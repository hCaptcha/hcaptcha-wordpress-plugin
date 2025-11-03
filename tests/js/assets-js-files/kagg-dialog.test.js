// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/**
 * Tests for assets/js/kagg-dialog.js
 */

describe( 'kagg-dialog.js', () => {
	beforeEach( () => {
		jest.resetModules();
		document.body.innerHTML = '<div id="root"></div>';
	} );

	test( 'init registers DOMContentLoaded listener on load', () => {
		const aelSpy = jest.spyOn( window, 'addEventListener' );

		// Load module in an isolated context
		jest.isolateModules( () => {
			require( '../../../assets/js/kagg-dialog.js' );
		} );

		expect( aelSpy ).toHaveBeenCalledWith( 'DOMContentLoaded', expect.any( Function ) );
		aelSpy.mockRestore();
	} );

	test( 'confirm builds dialog with title/content and default buttons; background click resolves false and removes dialog', async () => {
		// Load module
		jest.isolateModules( () => {
			require( '../../../assets/js/kagg-dialog.js' );
		} );

		const onAction = jest.fn();
		window.kaggDialog.confirm( {
			title: 'My Title',
			content: '<p>Body</p>',
			type: 'info',
			onAction,
		} );

		// Dialog should be in DOM with proper content and type class
		const dialog = document.querySelector( '.kagg-dialog' );
		expect( dialog ).toBeTruthy();
		expect( dialog.classList.contains( 'open' ) ).toBe( true );
		expect( dialog.classList.contains( 'info' ) ).toBe( true );
		expect( dialog.querySelector( '.kagg-dialog-title' ).innerHTML ).toBe( 'My Title' );
		expect( dialog.querySelector( '.kagg-dialog-content' ).innerHTML ).toBe( '<p>Body</p>' );

		// Default buttons exist with classes from defaults
		const okBtn = dialog.querySelector( 'button.btn-ok' );
		const cancelBtn = dialog.querySelector( 'button.btn-cancel' );
		expect( okBtn ).toBeTruthy();
		expect( cancelBtn ).toBeTruthy();

		// Click on the backdrop should resolve with false (cancel) and remove the dialog
		dialog.querySelector( '.kagg-dialog-bg' ).dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
		await Promise.resolve();
		await Promise.resolve();
		await new Promise( ( r ) => setTimeout( r, 0 ) );

		expect( onAction ).toHaveBeenCalledWith( false );
		expect( document.querySelector( '.kagg-dialog' ) ).toBeNull();
	} );

	test( 'OK button click resolves true; Cancel resolves false', async () => {
		jest.isolateModules( () => {
			require( '../../../assets/js/kagg-dialog.js' );
		} );

		const onAction = jest.fn();
		window.kaggDialog.confirm( { title: 'T', content: 'C', onAction } );
		let dialog = document.querySelector( '.kagg-dialog' );
		const okBtn = dialog.querySelector( 'button.btn-ok' );
		okBtn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
		await Promise.resolve();
		await Promise.resolve();
		await new Promise( ( r ) => setTimeout( r, 0 ) );
		expect( onAction ).toHaveBeenCalledWith( true );
		expect( document.querySelector( '.kagg-dialog' ) ).toBeNull();

		// Open again and press cancel
		window.kaggDialog.confirm( { title: 'T2', content: 'C2', onAction } );
		dialog = document.querySelector( '.kagg-dialog' );
		dialog.querySelector( 'button.btn-cancel' ).click();
		await Promise.resolve();
		await Promise.resolve();
		await new Promise( ( r ) => setTimeout( r, 0 ) );
		expect( onAction ).toHaveBeenCalledWith( false );
	} );

	test( 'custom buttons override default text/classes', () => {
		jest.isolateModules( () => {
			require( '../../../assets/js/kagg-dialog.js' );
		} );

		window.kaggDialog.confirm( {
			title: 'Custom',
			content: 'X',
			buttons: {
				ok: { text: 'Proceed', class: 'go' },
				cancel: { text: 'Back', class: 'stop' },
			},
			onAction: () => {
			},
		} );

		const dialog = document.querySelector( '.kagg-dialog' );
		const okBtn = dialog.querySelector( 'button.go' );
		const cancelBtn = dialog.querySelector( 'button.stop' );

		expect( okBtn ).toBeTruthy();
		expect( cancelBtn ).toBeTruthy();
		expect( okBtn.textContent ).toBe( 'Proceed' );
		expect( cancelBtn.textContent ).toBe( 'Back' );
	} );

	test( 'when onAction not provided, defaults.onAction is used', async () => {
		jest.isolateModules( () => {
			require( '../../../assets/js/kagg-dialog.js' );
		} );

		// Spy on defaults.onAction
		const spy = jest.spyOn( window.kaggDialog.defaults, 'onAction' );
		window.kaggDialog.confirm( { title: 'T', content: 'C' } );
		const dialog = document.querySelector( '.kagg-dialog' );
		dialog.querySelector( '.kagg-dialog-bg' ).click();
		await Promise.resolve();
		await Promise.resolve();
		await new Promise( ( r ) => setTimeout( r, 0 ) );
		expect( spy ).toHaveBeenCalledWith( false );
		spy.mockRestore();
	} );
} );
