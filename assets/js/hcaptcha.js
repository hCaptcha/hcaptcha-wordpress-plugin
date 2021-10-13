/* global hcaptcha */

class HCaptcha {
	constructor() {
		this.foundForms = [];
	}

	/**
	 * Generate random id.
	 *
	 * @returns {string}
	 */
	generateID() {
		const s4 = () => {
			return Math.floor( ( 1 + Math.random() ) * 0x10000 )
				.toString( 16 )
				.substring( 1 );
		};

		return s4() + '-' + s4() + '-' + s4() + '-' + s4();
	};

	/**
	 * Get found form by id.
	 *
	 * @param {string} id hCaptcha Id.
	 * @returns {*}
	 */
	getFoundFormById( id ) {
		const forms = this.foundForms.filter( form => id === form.hCaptchaId );
		return forms[ 0 ];
	};

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 * @returns {string}
	 */
	getWidgetId = ( el ) => {
		return el.getElementsByClassName( 'h-captcha' )[ 0 ].getElementsByTagName( 'iframe' )[ 0 ].dataset.hcaptchaWidgetId;
	};

	/**
	 * Check if child is same or a descendant of parent.
	 *
	 * @param {HTMLDivElement} parent Parent element.
	 * @param {HTMLDivElement} child Child element.
	 * @returns {boolean}
	 */
	isSameOrDescendant( parent, child ) {
		let node = child;
		while ( node ) {
			if ( node === parent ) {
				return true;
			}

			node = node.parentElement;
		}

		return false;
	};

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	validate = ( event ) => {
		const formElement = event.currentTarget;
		const form = this.getFoundFormById( formElement.dataset.hCaptchaId );
		const submitButtonElement = formElement.querySelector( form.submitButtonSelector );

		if ( ! this.isSameOrDescendant( submitButtonElement, event.target ) ) {
			return;
		}

		event.preventDefault();

		this.currentForm = { formElement, submitButtonElement };
		hcaptcha.execute( this.getWidgetId( formElement ) );
	};

	/**
	 * Bind events on forms containing hCaptcha.
	 */
	bindEvents = () => {
		const submitButtonSelector = '*[type="submit"]';

		[ ...document.querySelectorAll( 'form' ) ].map( formElement => {
			const hcaptchaElement = formElement.querySelector( '.h-captcha' );

			// Ignore forms not having hcaptcha.
			if ( null === hcaptchaElement ) {
				return;
			}

			// Do not render second time, processing arbitrary 'form' selector.
			if ( null !== hcaptchaElement.querySelector( 'iframe' ) ) {
				return;
			}

			hcaptcha.render( hcaptchaElement );

			if ( 'invisible' !== hcaptchaElement.dataset.size ) {
				return;
			}

			const hCaptchaId = this.generateID();
			this.foundForms.push( { hCaptchaId, submitButtonSelector } );

			formElement.dataset.hCaptchaId = hCaptchaId;
			formElement.addEventListener( 'click', this.validate, false );
		} );
	};

	/**
	 * Submit a form containing hCaptcha.
	 */
	submit = () => {
		// noinspection JSUnresolvedVariable
		if ( this.currentForm.formElement.requestSubmit ) {
			this.currentForm.formElement.requestSubmit();
		} else {
			this.currentForm.formElement.submit();
		}
	};
}

const hCaptcha = new HCaptcha();

window.hCaptchaGetWidgetId = hCaptcha.getWidgetId;
window.hCaptchaBindEvents = hCaptcha.bindEvents;
window.hCaptchaSubmit = hCaptcha.submit;

window.hCaptchaOnLoad = hCaptchaBindEvents;
