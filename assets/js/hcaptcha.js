/* global hcaptcha, hCaptcha */

window.hCaptchaSubmit = function( token ) {
	// const submitFormFunction = Object.getPrototypeOf( hCaptcha.form ).submit;
	// submitFormFunction.call( hCaptcha.form );

	hCaptcha.currentForm.formElement.requestSubmit( hCaptcha.currentForm.submitButtonElement );
};

document.addEventListener( 'DOMContentLoaded', function() {

	/**
	 * Generate random id.
	 *
	 * @returns {string}
	 */
	const generateID = () => {
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
	 * @param id
	 * @returns {*}
	 */
	const getFoundFormById = ( id ) => {
		const forms = hCaptcha.foundForms.filter( form => id === form.hCaptchaId );
		return forms[ 0 ];
	};

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 * @returns string
	 */
	const hCaptchaGetWidgetId = function( el ) {
		return el.getElementsByClassName( 'h-captcha' )[ 0 ].getElementsByTagName( 'iframe' )[ 0 ].dataset.hcaptchaWidgetId;
	};

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	const hCaptchaValidate = function( event ) {
		event.preventDefault();

		const form = getFoundFormById( event.target.dataset.hCaptchaId );
		const formElement = event.target.closest( form.formSelector );
		hCaptcha.currentForm = { formElement, submitButtonElement: event.target };
		hcaptcha.execute( hCaptchaGetWidgetId( formElement ) );
	};

	hCaptcha.foundForms = [];
	hCaptcha.forms.map( form => {
		let formSelector, submitButtonSelector;

		[ formSelector, submitButtonSelector ] = form;

		[ ...document.querySelectorAll( formSelector ) ].map( formElement => {
			const hCaptchaId = generateID();
			hCaptcha.foundForms.push( { formSelector, submitButtonSelector, hCaptchaId } );

			const submitButtonElement = formElement.querySelector( submitButtonSelector );
			submitButtonElement.dataset.hCaptchaId = hCaptchaId;
			submitButtonElement.addEventListener( 'click', hCaptchaValidate, false );
		} );
	} );
} );
