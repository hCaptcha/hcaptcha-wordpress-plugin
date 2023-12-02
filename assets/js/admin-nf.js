/* global Marionette, Backbone */

document.addEventListener( 'DOMContentLoaded', function() {
	const HCaptchaAdminFieldController = Marionette.Object.extend( {
		initialize() {
			const nfRadio = Backbone.Radio;
			// On the Close Field Edit Drawer.
			const appChannel = nfRadio.channel( 'app' );
			this.listenTo( appChannel, 'click:edit', this.editField );
			this.listenTo( appChannel, 'click:closeDrawer', this.closeDrawer );
		},

		editField( e ) {
			const field = e.target.parentNode;

			if ( field.classList === undefined || ! field.classList.contains( 'hcaptcha-for-ninja-forms' ) ) {
				return;
			}

			this.observeHCaptcha( field );
		},

		closeDrawer() {
			const field = document.querySelector( '.hcaptcha-for-ninja-forms.active' );

			if ( ! field ) {
				return;
			}

			this.observeHCaptcha( field );
		},

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

			// window.hCaptchaBindEvents();
			// observer.observe( document.getElementById( 'nf-builder' ), config );
			observer.observe( field, config );
		},
	} );

	// Instantiate our custom field's controller, defined above.
	window.HCaptchaAdminFieldController = new HCaptchaAdminFieldController();
} );
