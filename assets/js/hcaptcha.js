/* global hcaptcha, hCaptchaData */

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
	hCaptchaGetWidgetId( el ) {
		return el.getElementsByClassName( 'h-captcha' )[ 0 ].getElementsByTagName( 'iframe' )[ 0 ].dataset.hcaptchaWidgetId;
	};

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	hCaptchaValidate = ( event ) => {
		const formElement = event.currentTarget;
		const form = this.getFoundFormById( formElement.dataset.hCaptchaId );
		const submitButtonElement = formElement.querySelector( form.submitButtonSelector );

		if ( event.target !== submitButtonElement ) {
			return;
		}

		event.preventDefault();

		this.currentForm = { formElement, submitButtonElement };
		hcaptcha.execute( this.hCaptchaGetWidgetId( formElement ) );
	};

	/**
	 * Bind events on forms containing hCaptcha.
	 *
	 * @param forms
	 */
	bindEvents( forms ) {
		forms.map( form => {
			let formSelector, submitButtonSelector;

			[ formSelector, submitButtonSelector ] = form;

			[ ...document.querySelectorAll( formSelector ) ].map( formElement => {
				const hCaptchaId = this.generateID();
				this.foundForms.push( { hCaptchaId, submitButtonSelector } );

				formElement.dataset.hCaptchaId = hCaptchaId;
				formElement.addEventListener( 'click', this.hCaptchaValidate, false );
			} );
		} );
	};

	/**
	 * Submit a form containing hCaptcha.
	 */
	submit = () => {
		// noinspection JSUnresolvedFunction
		this.currentForm.formElement.requestSubmit( this.currentForm.submitButtonElement );
	};
}

document.addEventListener( 'DOMContentLoaded', () => {
	const hCaptcha = new HCaptcha();
	hCaptcha.bindEvents( hCaptchaData.forms );
	window.hCaptchaSubmit = hCaptcha.submit;
} );
