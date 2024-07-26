/**
 * @file class HCaptcha.
 */

/* global hcaptcha */

/**
 * Class hCaptcha.
 */
class HCaptcha {
	constructor() {
		this.foundForms = [];
		this.params = null;
		this.observing = false;
		this.darkElement = null;
		this.darkClass = null;
		this.callback = this.callback.bind( this );
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
		if ( typeof el === 'undefined' ) {
			return '';
		}

		const hcaptcha = el.getElementsByClassName( 'h-captcha' )[ 0 ];

		if ( typeof hcaptcha === 'undefined' ) {
			return '';
		}

		const iframe = hcaptcha.getElementsByTagName( 'iframe' )[ 0 ];

		if ( typeof iframe === 'undefined' ) {
			return '';
		}

		return iframe.dataset.hcaptchaWidgetId ?? '';
	}

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 */
	reset( el ) {
		const widgetId = this.getWidgetId( el );

		if ( ! widgetId ) {
			return;
		}

		hcaptcha.reset( widgetId );
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
		const widgetId = this.getWidgetId( formElement );

		if ( ! widgetId ) {
			return;
		}

		const iframe = formElement.querySelector( '.h-captcha iframe' );
		const token = iframe.dataset.hcaptchaResponse;

		// Do not execute hCaptcha twice.
		if ( token === '' ) {
			hcaptcha.execute( widgetId );
		} else {
			this.callback( token );
		}
	}

	isValidated() {
		return this.currentForm !== undefined;
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
	 * @return {*} Params.
	 */
	getParams() {
		if ( this.params !== null ) {
			return this.params;
		}

		let params;

		try {
			params = JSON.parse( wp.hooks.applyFilters( 'hcaptcha.params', window?.HCaptchaMainObject?.params ?? '' ) );
		} catch ( e ) {
			params = {};
		}

		params.callback = this.callback;

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
	 * Set darkElement and darkClass.
	 */
	setDarkData() {
		let darkData = {
			'twenty-twenty-one': {
				// Twenty Twenty-One theme.
				darkStyleId: 'twenty-twenty-one-style-css',
				darkElement: document.body,
				darkClass: 'is-dark-theme',
			},
			'wp-dark-mode': {
				// WP Dark Mode plugin.
				darkStyleId: 'wp-dark-mode-frontend-css',
				darkElement: document.documentElement,
				darkClass: 'wp-dark-mode-active',
			},
			'droit-dark-mode': {
				// Droit Dark Mode plugin.
				darkStyleId: 'dtdr-public-inline-css',
				darkElement: document.documentElement,
				darkClass: 'drdt-dark-mode',
			},
		};

		darkData = wp.hooks.applyFilters( 'hcaptcha.darkData', darkData );

		for ( const datum of Object.values( darkData ) ) {
			if ( document.getElementById( datum.darkStyleId ) ) {
				this.darkElement = datum.darkElement;
				this.darkClass = datum.darkClass;

				return;
			}
		}
	}

	/**
	 * Observe dark mode changes and apply auto theme.
	 */
	observeDarkMode() {
		if ( this.observing ) {
			return;
		}

		this.observing = true;

		const params = this.getParams();

		if ( params.theme !== 'auto' ) {
			return;
		}

		const observerCallback = ( mutationList ) => {
			for ( const mutation of mutationList ) {
				let oldClasses = mutation.oldValue;
				let newClasses = this.darkElement.getAttribute( 'class' );

				oldClasses = oldClasses ? oldClasses.split( ' ' ) : [];
				newClasses = newClasses ? newClasses.split( ' ' ) : [];

				const diff = newClasses
					.filter( ( item ) => ! oldClasses.includes( item ) )
					.concat( oldClasses.filter( ( item ) => ! newClasses.includes( item ) ) );

				if ( diff.includes( this.darkClass ) ) {
					this.bindEvents();
				}
			}
		};

		this.setDarkData();

		// Add observer if there is a known dark mode provider.
		if ( this.darkElement && this.darkClass ) {
			const config = {
				attributes: true,
				attributeOldValue: true,
			};
			const observer = new MutationObserver( observerCallback );

			observer.observe( this.darkElement, config );
		}
	}

	/**
	 * Called when the user submits a successful response.
	 *
	 * @param {string} token The h-captcha-response token.
	 */
	callback( token ) {
		document.dispatchEvent(
			new CustomEvent( 'hCaptchaSubmitted', {
				detail: { token },
			} )
		);

		const params = this.getParams();
		const iframe = document.querySelector( 'iframe[data-hcaptcha-response="' + token + '"]' );
		const hcaptcha = iframe ? iframe.closest( '.h-captcha' ) : null;
		const force = hcaptcha ? hcaptcha.dataset.force : null;

		if (
			params.size === 'invisible' ||
			// Prevent form submit when hCaptcha widget was manually solved.
			( force === 'true' && this.isValidated() )
		) {
			this.submit();
		}
	}

	/**
	 * Apply auto theme.
	 *
	 * @param {*} params Params.
	 *
	 * @return {*} Params.
	 */
	applyAutoTheme( params ) {
		if ( params.theme !== 'auto' ) {
			return params;
		}

		params.theme = 'light';

		if ( ! this.darkElement ) {
			return params;
		}

		let targetClass = this.darkElement.getAttribute( 'class' );
		targetClass = targetClass ? targetClass : '';

		if ( targetClass.includes( this.darkClass ) ) {
			params.theme = 'dark';
		}

		return params;
	}

	/**
	 * Render hCaptcha.
	 *
	 * @param {HTMLDivElement} hcaptchaElement hCaptcha element.
	 */
	render( hcaptchaElement ) {
		this.observeDarkMode();

		const params = this.applyAutoTheme( this.getParams() );

		hcaptcha.render( hcaptchaElement, params );
	}

	/**
	 * Bind events on forms containing hCaptcha.
	 */
	bindEvents() {
		if ( 'undefined' === typeof hcaptcha ) {
			return;
		}

		this.formSelector = wp.hooks.applyFilters(
			'hcaptcha.formSelector',
			'form' +
			', section.cwginstock-subscribe-form, div.sdm_download_item' +
			', .gform_editor, #nf-builder, .wpforms-captcha-preview'
		);
		this.submitButtonSelector = wp.hooks.applyFilters(
			'hcaptcha.submitButtonSelector',
			'*[type="submit"]:not(.quform-default-submit), #check_config' +
			', button[type="button"].ff-btn, a.et_pb_newsletter_button.et_pb_button' +
			', .forminator-button-submit, .frm_button_submit, a.sdm_download' +
			', .uagb-forms-main-submit-button' // Spectra.
		);

		this.getForms().map( ( formElement ) => {
			const hcaptchaElement = formElement.querySelector( '.h-captcha' );

			// Ignore forms not having hcaptcha.
			if ( null === hcaptchaElement ) {
				return formElement;
			}

			// Do not deal with skipped hCaptcha.
			if ( hcaptchaElement.classList.contains( 'hcaptcha-widget-id' ) ) {
				return formElement;
			}

			const iframe = hcaptchaElement.querySelector( 'iframe' );

			// Re-render.
			if ( null !== iframe ) {
				iframe.remove();
			}

			this.render( hcaptchaElement );

			if (
				( 'invisible' !== hcaptchaElement.dataset.size ) &&
				( 'true' !== hcaptchaElement.dataset.force )
			) {
				return formElement;
			}

			const submitButtonElement = formElement.querySelectorAll( this.submitButtonSelector )[ 0 ];

			if ( ! submitButtonElement ) {
				return formElement;
			}

			const hCaptchaId = this.generateID();

			this.foundForms.push( { hCaptchaId, submitButtonElement } );

			formElement.dataset.hCaptchaId = hCaptchaId;

			submitButtonElement.addEventListener( 'click', this.validate, true );

			return formElement;
		}, this );
	}

	/**
	 * Whether submitButtonElement is an ajax submit button.
	 *
	 * @param {Object} submitButtonElement Element to check.
	 *
	 * @return {boolean} Ajax submit button status.
	 */
	isAjaxSubmitButton( submitButtonElement ) {
		let typeAttribute = submitButtonElement.getAttribute( 'type' );
		typeAttribute = typeAttribute ? typeAttribute.toLowerCase() : '';

		const isAjaxSubmitButton = 'submit' !== typeAttribute;
		return wp.hooks.applyFilters( 'hcaptcha.ajaxSubmitButton', isAjaxSubmitButton, submitButtonElement );
	}

	/**
	 * Submit a form containing hCaptcha.
	 */
	submit() {
		const formElement = this.currentForm.formElement;
		const submitButtonElement = this.currentForm.submitButtonElement;

		if (
			'form' !== formElement.tagName.toLowerCase() ||
			this.isAjaxSubmitButton( submitButtonElement )
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
