/* global Marionette, Backbone, HCaptchaAdminNFObject */

/**
 * @param HCaptchaAdminNFObject.onlyOneHCaptchaAllowed
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const HCaptchaAdminFieldController = Marionette.Object.extend( {
		initialize() {
			document.getElementById( 'nf-builder' ).addEventListener( 'click', this.checkAddingHCaptchaByClick, true );

			const nfRadio = Backbone.Radio;
			const appChannel = nfRadio.channel( 'app' );

			this.listenTo( appChannel, 'click:edit', this.editField );
			this.listenTo( appChannel, 'click:closeDrawer', this.closeDrawer );
		},

		/**
		 * Check adding hCaptcha by Click and prevent from having multiple hCaptcha fields.
		 *
		 * @param {Object} e Click event.
		 */
		checkAddingHCaptchaByClick( e ) {
			const buttonClicked = e.target.dataset.id === 'hcaptcha-for-ninja-forms';
			const classList = e.target.classList;
			const duplicateClicked = classList !== undefined && classList.contains( 'nf-duplicate' );

			if ( ! ( buttonClicked || duplicateClicked ) ) {
				return;
			}

			const hcaptchaField = document.querySelector( '.hcaptcha-for-ninja-forms' );

			if ( hcaptchaField ) {
				e.stopImmediatePropagation();

				// eslint-disable-next-line no-alert
				alert( HCaptchaAdminNFObject.onlyOneHCaptchaAllowed );
			}
		},

		/**
		 * On edit field event, update hCaptcha.
		 * Do it if the drawer was opened to edit hCaptcha.
		 *
		 * @param {Object} e Event.
		 */
		editField( e ) {
			const field = e.target.parentNode;

			if ( field.classList === undefined || ! field.classList.contains( 'hcaptcha-for-ninja-forms' ) ) {
				return;
			}

			this.observeHCaptcha( field );
		},

		/**
		 * On closing the drawer, update hCaptcha field in the form.
		 * Do it if the drawer was opened to edit hCaptcha.
		 */
		closeDrawer() {
			const field = document.querySelector( '.hcaptcha-for-ninja-forms.active' );

			if ( ! field ) {
				return;
			}

			this.observeHCaptcha( field );
		},

		/**
		 * Observe adding of the hCaptcha field in the form and bind its events.
		 *
		 * @param {Node} field Field.
		 */
		observeHCaptcha( field ) {
			const callback = ( mutationList ) => {
				for ( const mutation of mutationList ) {
					[ ...mutation.addedNodes ].map( ( node ) => {
						if ( node.classList !== undefined && node.classList.contains( 'hcaptcha-for-ninja-forms' ) ) {
							window.hCaptchaBindEvents();
						}

						return node;
					} );
				}
			};

			const config = {
				childList: true,
				subtree: true,
			};
			const observer = new MutationObserver( callback );

			observer.observe( field, config );
		},
	} );

	// Instantiate our custom field's controller, defined above.
	window.HCaptchaAdminFieldController = new HCaptchaAdminFieldController();
} );
