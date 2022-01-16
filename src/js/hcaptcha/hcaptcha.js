/**
 * @file class HCaptcha.
 */

/* global hcaptcha */

class HCaptcha {
	constructor() {
		this.foundForms = [];
	}

	/**
	 * Generate random id.
	 *
	 * @return {string} Random id.
	 */
	generateID() {
		const s4 = () => {
			return Math.floor( ( 1 + Math.random() ) * 0x10000 )
				.toString( 16 )
				.substring( 1 );
		};

		return s4() + '-' + s4() + '-' + s4() + '-' + s4();
	}

	/**
	 * Get found form by id.
	 *
	 * @param {string} id hCaptcha id.
	 * @return {*} Form id.
	 */
	getFoundFormById( id ) {
		const forms = this.foundForms.filter(
			( form ) => id === form.hCaptchaId
		);
		return forms[ 0 ];
	}

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 * @return {string} Widget id.
	 */
	getWidgetId( el ) {
		return el
			.getElementsByClassName( 'h-captcha' )[ 0 ]
			.getElementsByTagName( 'iframe' )[ 0 ].dataset.hcaptchaWidgetId;
	}

	/**
	 * Check if child is same or a descendant of parent.
	 *
	 * @param {HTMLDivElement} parent Parent element.
	 * @param {HTMLDivElement} child  Child element.
	 * @return {boolean} Whether child is the same or a descendant of parent.
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
	}

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	validate( event ) {
		const formElement = event.currentTarget;
		const form = this.getFoundFormById( formElement.dataset.hCaptchaId );
		const submitButtonElement = formElement.querySelector(
			form.submitButtonSelector
		);

		if ( ! this.isSameOrDescendant( submitButtonElement, event.target ) ) {
			return;
		}

		event.preventDefault();

		this.currentForm = { formElement, submitButtonElement };
		hcaptcha.execute( this.getWidgetId( formElement ) );
	}

	/**
	 * Get forms.
	 *
	 * @return {*[]} Forms.
	 */
	getForms() {
		return [ ...document.querySelectorAll( 'form' ) ];
	}

	/**
	 * Bind events on forms containing hCaptcha.
	 */
	bindEvents() {
		const submitButtonSelector = '*[type="submit"]';

		this.getForms().map( ( formElement ) => {
			const hcaptchaElement = formElement.querySelector( '.h-captcha' );

			// Ignore forms not having hcaptcha.
			if ( null === hcaptchaElement ) {
				return formElement;
			}

			// Do not render second time, processing arbitrary 'form' selector.
			if ( null !== hcaptchaElement.querySelector( 'iframe' ) ) {
				return formElement;
			}

			hcaptcha.render( hcaptchaElement );

			if ( 'invisible' !== hcaptchaElement.dataset.size ) {
				return formElement;
			}

			const hCaptchaId = this.generateID();
			this.foundForms.push( { hCaptchaId, submitButtonSelector } );

			formElement.dataset.hCaptchaId = hCaptchaId;
			formElement.addEventListener(
				'click',
				( event ) => {
					this.validate( event );
				},
				false
			);

			return formElement;
		} );
	}

	/**
	 * Submit a form containing hCaptcha.
	 */
	submit() {
		// noinspection JSUnresolvedVariable
		if ( this.currentForm.formElement.requestSubmit ) {
			this.currentForm.formElement.requestSubmit();
		} else {
			this.currentForm.formElement.submit();
		}
	}
}

export default HCaptcha;
