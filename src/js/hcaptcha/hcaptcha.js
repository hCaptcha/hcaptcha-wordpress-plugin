/**
 * @file class HCaptcha.
 */

/* global hcaptcha, HCaptchaMainObject */

class HCaptcha {
	constructor() {
		this.formSelector = 'form, div.fl-login-form, section.cwginstock-subscribe-form';
		this.submitButtonSelector = '*[type="submit"], a.fl-button span, button[type="button"].ff-btn, a.et_pb_newsletter_button.et_pb_button';
		this.foundForms = [];
		this.params = null;
		this.validate = this.validate.bind( this );
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
		const forms = this.foundForms.filter( ( form ) => id === form.hCaptchaId );
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
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 */
	reset( el ) {
		hcaptcha.reset( this.getWidgetId( el ) );
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
		const formElement = event.currentTarget.closest( this.formSelector );
		const form = this.getFoundFormById( formElement.dataset.hCaptchaId );
		const submitButtonElement = form.submitButtonElement;

		if ( ! this.isSameOrDescendant( submitButtonElement, event.target ) ) {
			return;
		}

		event.preventDefault();
		event.stopPropagation();

		this.currentForm = { formElement, submitButtonElement };
		hcaptcha.execute( this.getWidgetId( formElement ) );
	}

	/**
	 * Get forms.
	 *
	 * @return {*[]} Forms.
	 */
	getForms() {
		return [ ...document.querySelectorAll( this.formSelector ) ];
	}

	/**
	 * Get params.
	 *
	 * @return {{}} Params.
	 */
	getParams() {
		if ( this.params !== null ) {
			return this.params;
		}

		let params;

		try {
			params = JSON.parse( HCaptchaMainObject.params );
		} catch ( e ) {
			params = {};
		}

		return params;
	}

	/**
	 * Set params.
	 *
	 * @param {{}} params Params.
	 */
	setParams( params ) {
		this.params = params;
	}

	/**
	 * Bind events on forms containing hCaptcha.
	 */
	bindEvents() {
		if ( 'undefined' === typeof hcaptcha ) {
			return;
		}

		const params = this.getParams();

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

			// Do not deal with skipped hCaptcha.
			if ( hcaptchaElement.classList.contains( 'hcaptcha-widget-id' ) ) {
				return formElement;
			}

			hcaptcha.render( hcaptchaElement, params );

			if ( 'invisible' !== hcaptchaElement.dataset.size ) {
				return formElement;
			}

			const hCaptchaId = this.generateID();
			const submitButtonElement = formElement.querySelectorAll( this.submitButtonSelector )[ 0 ];

			this.foundForms.push( { hCaptchaId, submitButtonElement } );

			formElement.dataset.hCaptchaId = hCaptchaId;
			submitButtonElement.addEventListener(
				'click',
				this.validate,
				true
			);

			return formElement;
		} );
	}

	/**
	 * Submit a form containing hCaptcha.
	 */
	submit() {
		const formElement = this.currentForm.formElement;
		const submitButtonElement = this.currentForm.submitButtonElement;
		let submitButtonElementTypeAttribute = submitButtonElement.getAttribute( 'type' );
		submitButtonElementTypeAttribute = submitButtonElementTypeAttribute ? submitButtonElementTypeAttribute.toLowerCase() : '';

		if (
			'form' !== formElement.tagName.toLowerCase() ||
			'submit' !== submitButtonElementTypeAttribute
		) {
			submitButtonElement.removeEventListener( 'click', this.validate, true );
			submitButtonElement.click();

			return;
		}

		if ( formElement.requestSubmit ) {
			formElement.requestSubmit( submitButtonElement );
		} else {
			formElement.submit();
		}
	}
}

export default HCaptcha;
