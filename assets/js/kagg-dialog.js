/* global jQuery */
// noinspection ES6ConvertVarToLetConst

// eslint-disable-next-line no-var
var kaggDialog = window.kaggDialog || ( function( document, window, $ ) {
	const app = {
		defaults: {
			title: 'Please confirm',
			content: 'Are you sure to continue?',
			type: 'default',
			// eslint-disable-next-line no-unused-vars
			onAction( result ) {
			},
		},

		/**
		 * Init app.
		 */
		init() {
			// Document ready.
			$( app.ready );
		},

		/**
		 * Document ready.
		 */
		ready() {
			$( document ).trigger( 'kaggDialogReady' );
		},

		/**
		 * Confirm dialog.
		 *
		 * @param {Object} settings Dialog data.
		 */
		confirm( settings ) {
			const data = Object.assign( {}, this.defaults, settings );
			const dialog = document.querySelector( '.kagg-dialog' );
			const confirm = document.querySelector( '.kagg-dialog .btn-ok' );
			const cancel = document.querySelector( '.kagg-dialog .btn-cancel' );
			const bg = document.querySelector( '.kagg-dialog-bg' );
			const promise = new Promise( ( resolve ) => {
				confirm.addEventListener( 'click', resolve );
				cancel.addEventListener( 'click', resolve );
				bg.addEventListener( 'click', resolve );
			} );

			async function waitClick() {
				return await promise
					.then( ( e ) => {
						return e.target.classList.contains( 'btn-ok' );
					} );
			}

			dialog.querySelector( '.kagg-dialog-title' ).innerHTML = data.title;
			dialog.querySelector( '.kagg-dialog-content' ).innerHTML = data.content;

			dialog.className = 'kagg-dialog';

			if ( data.type !== 'default' ) {
				dialog.classList.add( data.type );
			}

			dialog.classList.add( 'open' );

			waitClick()
				.then( ( result ) => {
					dialog.classList.remove( 'open' );

					data.onAction( result );
				} );
		},
	};

	return app;
}( document, window, jQuery ) );

// Initialize.
kaggDialog.init();
