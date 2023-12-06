/* global Marionette, Backbone, HCaptchaAdminNFObject */

/**
 * @param HCaptchaAdminNFObject.onlyOneHCaptchaAllowed
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const nfRadio = Backbone.Radio;
	const fieldClass = 'hcaptcha-for-ninja-forms';
	const dataId = fieldClass;
	const fieldSelector = '.' + fieldClass;

	const HCaptchaAdminFieldController = Marionette.Object.extend( {
		initialize() {
			document.getElementById( 'nf-builder' ).addEventListener( 'mousedown', this.checkAddingHCaptcha, true );

			const appChannel = nfRadio.channel( 'app' );

			this.listenTo( appChannel, 'click:edit', this.editField );
			this.listenTo( appChannel, 'click:closeDrawer', this.closeDrawer );
		},

		/**
		 * Check adding hCaptcha and prevent from having multiple hCaptcha fields.
		 *
		 * @param {Object} e Click event.
		 */
		checkAddingHCaptcha( e ) {
			const buttonClicked = e.target.dataset.id === dataId;
			const classList = e.target.classList;
			const duplicateClicked = classList !== undefined && classList.contains( 'nf-duplicate' );

			if ( ! ( buttonClicked || duplicateClicked ) ) {
				return;
			}

			const field = document.querySelector( fieldSelector );

			if ( field ) {
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

			if ( field.classList === undefined || ! field.classList.contains( fieldClass ) ) {
				return;
			}

			this.observeField( field );
		},

		/**
		 * On closing the drawer, update hCaptcha field in the form.
		 * Do it if the drawer was opened to edit hCaptcha.
		 */
		closeDrawer() {
			const field = document.querySelector( fieldSelector + '.active' );

			if ( ! field ) {
				return;
			}

			this.observeField( field );
		},

		/**
		 * Observe adding of the hCaptcha field in the form and bind its events.
		 *
		 * @param {Node} field Field.
		 */
		observeField( field ) {
			const callback = ( mutationList ) => {
				for ( const mutation of mutationList ) {
					[ ...mutation.addedNodes ].map( ( node ) => {
						if ( node.classList !== undefined && node.classList.contains( fieldClass ) ) {
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
