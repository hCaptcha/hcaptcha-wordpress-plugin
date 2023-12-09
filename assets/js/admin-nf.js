/* global Marionette, Backbone, HCaptchaAdminNFObject */

/**
 * @param HCaptchaAdminNFObject.onlyOneHCaptchaAllowed
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const nfRadio = Backbone.Radio;
	const fieldClass = 'hcaptcha-for-ninja-forms';
	const dataId = fieldClass;
	const fieldSelector = '.' + fieldClass;
	let hasObserver = false;

	const HCaptchaAdminFieldController = Marionette.Object.extend( {
		initialize() {
			document.getElementById( 'nf-builder' ).addEventListener( 'mousedown', this.checkAddingHCaptcha, true );

			const appChannel = nfRadio.channel( 'app' );

			this.listenTo( appChannel, 'click:edit', this.editField );
			this.listenTo( appChannel, 'click:closeDrawer', this.closeDrawer );

			const fieldsChannel = nfRadio.channel( 'fields' );

			this.listenTo( fieldsChannel, 'add:field', this.addField );
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

			this.observeField();
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

			this.observeField();
		},

		/**
		 * Check adding field and update hCaptcha.
		 */
		addField() {
			const field = document.querySelector( fieldSelector );

			if ( ! field ) {
				return;
			}

			this.observeField();
		},

		/**
		 * Observe adding of a field to the form and bind hCaptcha events.
		 */
		observeField() {
			if ( hasObserver ) {
				return;
			}

			hasObserver = true;

			const callback = ( mutationList ) => {
				for ( const mutation of mutationList ) {
					[ ...mutation.addedNodes ].map( ( node ) => {
						if (
							document.querySelector( '.h-captcha' ) &&
							! document.querySelector( '.h-captcha iframe' )
						) {
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

			observer.observe( document.getElementById( 'nf-main-body' ), config );
		},
	} );

	// Instantiate our custom field's controller, defined above.
	window.HCaptchaAdminFieldController = new HCaptchaAdminFieldController();
} );
