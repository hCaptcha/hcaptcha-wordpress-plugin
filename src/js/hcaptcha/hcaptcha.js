/**
 * @file class HCaptcha.
 */

/* global hcaptcha, HCaptchaMainObject */

/**
 * @param form.submitButtonElement
 */

/**
 * Class hCaptcha.
 */
class HCaptcha {
	constructor() {
		this.foundForms = [];
		this.params = null;
		this.observingDarkMode = false;
		this.observingPasswordManagers = false;
		this.darkElement = null;
		this.darkClass = null;
		this.callback = this.callback.bind( this );
		this.validate = this.validate.bind( this );
		this.addedDCLCallbacks = new Set();
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
	 *
	 * @return {Object|null} Form data.
	 */
	getFoundFormById( id ) {
		const forms = this.foundForms.filter( ( form ) => id === form.hCaptchaId );

		return forms[ 0 ] ?? null;
	}

	/**
	 * Get hCaptcha widget id.
	 *
	 * @param {HTMLDivElement} el Form element.
	 *
	 * @return {string} Widget id.
	 */
	getWidgetId( el ) {
		if ( el === undefined ) {
			return '';
		}

		const id = el.closest( this.formSelector )?.dataset?.hCaptchaId ?? '';

		if ( ! id ) {
			return '';
		}

		const form = this.getFoundFormById( id );

		return form?.widgetId ?? '';
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
	 * Check if child is same or a descendant of the parent.
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
	 * Set the current form.
	 *
	 * @param {CustomEvent} event Event.
	 * @return {Object|undefined} Currently processing form.
	 */
	getCurrentForm( event ) {
		/**
		 * @type {HTMLElement}
		 */
		const currentTarget = event.currentTarget;

		/**
		 * @type {HTMLFormElement} formElement
		 */
		const formElement = currentTarget.closest( this.formSelector );

		/**
		 * @type {{submitButtonElement: HTMLElement, widgetId: string}|null}
		 */
		const form = this.getFoundFormById( formElement?.dataset?.hCaptchaId );

		const submitButtonElement = form?.submitButtonElement;
		const widgetId = form?.widgetId;

		if (
			! widgetId ||
			! this.isSameOrDescendant( submitButtonElement, event.target )
		) {
			return undefined;
		}

		event.preventDefault();
		event.stopPropagation();

		return { formElement, submitButtonElement, widgetId };
	}

	/**
	 * Validate hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	validate( event ) {
		this.currentForm = this.getCurrentForm( event );

		if ( ! this.currentForm ) {
			return;
		}

		const { formElement, widgetId } = this.currentForm;

		/**
		 * @type {HTMLTextAreaElement}
		 */
		const response = formElement.querySelector( this.responseSelector );
		const token = response ? response.value : '';

		// Do not execute hCaptcha twice.
		if ( token === '' ) {
			hcaptcha.execute( widgetId, { async: false } );
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
			params = JSON.parse( wp.hooks.applyFilters( 'hcaptcha.params', HCaptchaMainObject?.params ?? '' ) );
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
				// The Twenty Twenty-One theme.
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
	 * Observe dark mode changes and apply the auto theme.
	 */
	observeDarkMode() {
		let scheduledRebind = false;

		if ( this.observingDarkMode ) {
			return;
		}

		this.observingDarkMode = true;

		const params = this.getParams();

		if ( params.theme !== 'auto' ) {
			return;
		}

		const observerCallback = ( mutationList ) => {
			let darkClassToggled = false;

			for ( const mutation of mutationList ) {
				let oldClasses = mutation.oldValue;
				let newClasses = this.darkElement.getAttribute( 'class' );

				oldClasses = oldClasses ? oldClasses.split( ' ' ) : [];
				newClasses = newClasses ? newClasses.split( ' ' ) : [];

				const diff = newClasses
					.filter( ( item ) => ! oldClasses.includes( item ) )
					.concat( oldClasses.filter( ( item ) => ! newClasses.includes( item ) ) );

				if ( diff.includes( this.darkClass ) ) {
					darkClassToggled = true;
				}
			}

			if ( darkClassToggled && ! scheduledRebind ) {
				scheduledRebind = true;

				requestAnimationFrame( () => {
					this.bindEvents();

					scheduledRebind = false;
				} );
			}
		};

		this.setDarkData();

		// Add an observer if there is a known dark mode provider.
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
	 * Observe password managers.
	 */
	observePasswordManagers() {
		if ( this.observingPasswordManagers ) {
			return;
		}

		this.observingPasswordManagers = true;

		let isProcessing = false;

		const observer = new MutationObserver( ( mutations ) => {
			if ( isProcessing ) {
				return;
			}

			isProcessing = true;

			requestAnimationFrame( () => {
				for ( const mutation of mutations ) {
					if ( ! ( mutation.type === 'childList' ) ) {
						continue;
					}

					const el1Pass = document.querySelector( 'com-1password-button' );
					const elLastPass = document.querySelector( 'div[data-lastpass-icon-root]' );

					if ( ! el1Pass && ! elLastPass ) {
						continue;
					}

					observer.disconnect(); // Stop observer after a password manager element was found.

					this.foundForms.map( ( form ) => {
						const { hCaptchaId, submitButtonElement } = form;

						if ( ! submitButtonElement ) {
							return form;
						}

						const formElement = document.querySelector( `[data-h-captcha-id="${ hCaptchaId }"]` );

						/**
						 * @type {HTMLElement}
						 */
						const hcaptchaElement = formElement.querySelector( '.h-captcha' );
						const dataset = hcaptchaElement.dataset;

						if ( dataset.size === 'invisible' || dataset.force === 'true' ) {
							// Do not add the event listener again.
							return form;
						}

						hcaptchaElement.dataset.force = 'true';

						submitButtonElement.addEventListener( 'click', this.validate, true );

						return form;
					} );

					break;
				}

				isProcessing = false;
			} );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	/**
	 * Get widget by token.
	 *
	 * @param {string} token Token.
	 *
	 * @return {HTMLDivElement} Widget.
	 */
	getWidgetByToken( token ) {
		const responses = document.querySelectorAll( this.responseSelector );

		const response = [ ...responses ].find( ( el ) => {
			return el.value === token;
		} );

		return response ? response.closest( '.h-captcha' ) : null;
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
		const hcaptcha = this.getWidgetByToken( token );
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
			params.theme = window?.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';

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
	 * Render hCaptcha explicitly.
	 *
	 * @param {HTMLDivElement} hcaptchaElement hCaptcha element.
	 *
	 * @return {string} Widget ID.
	 */
	render( hcaptchaElement ) {
		this.observeDarkMode();
		this.observePasswordManagers();

		let globalParams = this.getParams();

		// Do not overwrite a custom theme.
		if ( typeof globalParams.theme === 'object' ) {
			// noinspection JSUnresolvedReference
			const bg = globalParams?.theme?.component?.checkbox?.main?.fill ?? '';

			if ( bg ) {
				hcaptchaElement.dataset.theme = 'custom';
			}
		} else {
			globalParams.theme = hcaptchaElement.dataset.theme;
		}

		globalParams.size = hcaptchaElement.dataset.size;
		globalParams = this.applyAutoTheme( globalParams );

		return hcaptcha.render( hcaptchaElement, globalParams );
	}

	/**
	 * Add an event listener that syncs with the DOMContentLoaded event.
	 *
	 * @param {Function} callback
	 */
	addSyncedEventListener( callback ) {
		// Sync with DOMContentLoaded event.
		if ( document.readyState === 'loading' ) {
			if ( this.addedDCLCallbacks.has( callback ) ) {
				return;
			}

			this.addedDCLCallbacks.add( callback );

			window.addEventListener( 'DOMContentLoaded', callback );
		} else {
			callback();
		}
	}

	/**
	 * Move honeypot input to a random position among visible inputs in the form.
	 *
	 * @param {HTMLElement} formElement Form element.
	 */
	moveHP( formElement ) {
		// Guard: valid element and move only once per form lifecycle (prevent reentrancy).
		if ( ! formElement || formElement?.dataset?.hpMoved === '1' ) {
			return;
		}

		// Mark as moved early to avoid recursive re-entry via DOM observers.
		formElement.dataset.hpMoved = '1';

		const hpInput = formElement.querySelector( 'input[id^="hcap_hp_"]' );

		if ( ! hpInput ) {
			return;
		}

		const inputs = [ ...formElement.querySelectorAll( 'input,select,textarea,button' ) ]
			// Do not insert inside .h-captcha element - it may be re-rendered later.
			.filter( ( el ) => el !== hpInput && el.type !== 'hidden' && ! el.closest( '.h-captcha' ) );

		if ( ! inputs.length ) {
			return;
		}

		// Choose a random reference position.
		const idx = Math.floor( Math.random() * inputs.length );
		const ref = inputs[ idx ];

		if ( ! ( ref && ref.parentNode ) ) {
			return;
		}

		const inputId = hpInput.getAttribute( 'id' ) ?? '';
		const label = inputId ? formElement.querySelector( `label[for="${ inputId }"]` ) : null;
		const frag = document.createDocumentFragment();

		if ( label && label.isConnected ) {
			frag.appendChild( label );
		}

		frag.appendChild( hpInput );
		ref.parentNode.insertBefore( frag, ref );
	}

	addFSTToken( formElement ) {
		if ( ! formElement ) {
			return;
		}

		const name = 'hcap_fst_token';

		// Find or create input.
		let input = formElement.querySelector( `input[type="hidden"][name="${ name }"]` );

		if ( ! input ) {
			input = document.createElement( 'input' );
			input.type = 'hidden';
			input.name = name;
		}

		// Insert input.
		if ( formElement.firstChild ) {
			formElement.insertBefore( input, formElement.firstChild );
		} else {
			formElement.appendChild( input );
		}
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
		this.responseSelector = 'textarea[name="h-captcha-response"]';

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

			this.moveHP( formElement );
			this.addFSTToken( formElement );

			// Render or re-render.
			hcaptchaElement.innerHTML = '';

			const hCaptchaId = this.generateID();
			const submitButtonElement = formElement.querySelectorAll( this.submitButtonSelector )[ 0 ];
			const widgetId = this.render( hcaptchaElement );
			formElement.dataset.hCaptchaId = hCaptchaId;

			this.foundForms.push( { hCaptchaId, submitButtonElement, widgetId } );

			if ( ! submitButtonElement ) {
				return formElement;
			}

			const dataset = hcaptchaElement.dataset;

			if ( dataset.size === 'invisible' || dataset.force === 'true' ) {
				submitButtonElement.addEventListener( 'click', this.validate, true );
			}

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
		if ( ! this.currentForm ) {
			return;
		}

		const { formElement, submitButtonElement } = this.currentForm;

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

window.HCaptchaMainObject = window.HCaptchaMainObject || {};

export default HCaptcha;
