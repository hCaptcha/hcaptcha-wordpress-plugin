// noinspection ES6ConvertVarToLetConst

// eslint-disable-next-line no-var
const kaggDialog = window.kaggDialog || ( function( document ) {
	return {
		defaults: {
			title: 'Do you want to continue?',
			content: 'Please confirm',
			type: 'default',
			buttons: {},
			defaultButtons: {
				ok: {
					text: 'OK',
					class: 'btn-ok',
					action() {},
				},
				cancel: {
					text: 'Cancel',
					class: 'btn-cancel',
					action() {},
				},
			},
			// eslint-disable-next-line no-unused-vars
			onAction() {},
		},

		settings: {},

		/**
		 * Init app.
		 */
		init() {
			addEventListener( 'DOMContentLoaded', this.ready );
		},

		/**
		 * Document ready.
		 */
		ready() {
		},

		/**
		 * Parse settings.
		 *
		 * @param {Object} settings Settings.
		 */
		parseSettings( settings ) {
			this.settings = Object.assign( {}, this.defaults, settings );
			this.settings.buttons = Object.keys( this.settings.buttons ).length
				? this.settings.buttons
				: this.settings.defaultButtons;

			for ( const btnKey in this.settings.buttons ) {
				const defaultButton = this.settings.defaultButtons[ btnKey ];

				if ( defaultButton !== undefined ) {
					this.settings.buttons[ btnKey ] = Object.assign( defaultButton, this.settings.buttons[ btnKey ] );
				}
			}
		},

		/**
		 * Get confirm dialog.
		 * Create its HTMl if it does not exist.
		 */
		getConfirmDialog() {
			let buttonsHTML = '';

			for ( const btnKey in this.settings.buttons ) {
				const button = this.settings.buttons[ btnKey ];
				buttonsHTML += `<button type="button" class="btn ${ button.class }">${ button.text }</button>`;
			}

			// Create the confirm dialog HTML.
			const innerHTML = `
				<div class="kagg-dialog-bg">
				</div>
				<div class="kagg-dialog-container">
					<div class="kagg-dialog-box">
						<div class="kagg-dialog-title"></div>
						<div class="kagg-dialog-content"></div>
						<div class="kagg-dialog-buttons">
							${ buttonsHTML }
						</div>
					</div>
				</div>
			`;

			const dialog = document.createElement( 'div' );

			dialog.className = 'kagg-dialog';
			dialog.innerHTML = innerHTML;

			return document.body.appendChild( dialog );
		},

		/**
		 * Confirm dialog.
		 *
		 * @param {Object} settings Dialog settings.
		 */
		confirm( settings ) {
			this.parseSettings( settings );

			const dialog = this.getConfirmDialog();
			const promise = new Promise( ( resolve ) => {
				document.querySelector( '.kagg-dialog-bg' ).addEventListener( 'click', resolve );

				[ ...dialog.querySelectorAll( '.btn' ) ].map( ( btn ) => {
					btn.addEventListener( 'click', resolve );

					return btn;
				} );
			} );

			async function waitClick() {
				return await promise
					.then( ( e ) => {
						return e.target.classList.contains( 'btn-ok' );
					} );
			}

			dialog.querySelector( '.kagg-dialog-title' ).innerHTML = this.settings.title;
			dialog.querySelector( '.kagg-dialog-content' ).innerHTML = this.settings.content;

			dialog.className = 'kagg-dialog';

			if ( this.settings.type !== 'default' ) {
				dialog.classList.add( this.settings.type );
			}

			dialog.classList.add( 'open' );

			waitClick()
				.then( ( result ) => {
					this.settings.onAction( result );

					dialog.remove();
				} );
		},
	};
}( document ) );

window.kaggDialog = kaggDialog;

kaggDialog.init();
