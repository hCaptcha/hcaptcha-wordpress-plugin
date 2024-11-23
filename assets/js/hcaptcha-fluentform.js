/* global hcaptcha, HCaptchaFluentFormObject */

/**
 * @param HCaptchaFluentFormObject.id
 * @param HCaptchaFluentFormObject.url
 */

document.addEventListener( 'hCaptchaLoaded', function() {
	const formSelector = '.ffc_conv_form';

	const hasOwnCaptcha = () => {
		return document.getElementById( 'hcaptcha-container' ) !== null;
	};

	/**
	 * Process conversational form.
	 */
	const processForm = () => {
		// We assume there should be only one conversational form on the page.
		const form = document.querySelector( formSelector );
		const submitBtnSelector = '.ff-btn';

		const isSubmitVisible = ( qForm ) => {
			return qForm.querySelector( submitBtnSelector ) !== null;
		};

		const addCaptcha = () => {
			const hCaptchaHiddenClass = 'h-captcha-hidden';
			const hCaptchaClass = 'h-captcha';
			const hiddenCaptcha = document.getElementsByClassName( hCaptchaHiddenClass )[ 0 ];
			const submitBtn = form.querySelector( submitBtnSelector );

			/**
			 * @type {HTMLElement}
			 */
			const hCaptcha = hiddenCaptcha.cloneNode( true );
			const wrappingForm = document.createElement( 'form' );

			wrappingForm.setAttribute( 'method', 'POST' );
			submitBtn.parentNode.insertBefore( wrappingForm, submitBtn );
			wrappingForm.appendChild( submitBtn );
			submitBtn.before( hCaptcha );
			hCaptcha.classList.remove( hCaptchaHiddenClass );
			hCaptcha.classList.add( hCaptchaClass );
			hCaptcha.style.display = 'block';
			window.hCaptchaBindEvents();
		};

		const mutationObserverCallback = ( mutationList ) => {
			for ( const mutation of mutationList ) {
				if (
					! (
						mutation.type === 'attributes' &&
						mutation.attributeName === 'class' &&
						mutation.oldValue && mutation.oldValue.includes( 'q-is-inactive' )
					)
				) {
					continue;
				}

				if ( isSubmitVisible( mutation.target ) ) {
					addCaptcha();
				}
			}
		};

		if ( hasOwnCaptcha() ) {
			return;
		}

		const qFormSelector = '.q-form';
		const qForms = form.querySelectorAll( qFormSelector );
		const config = {
			attributes: true,
			attributeOldValue: true,
		};

		[ ...qForms ].map( ( qForm ) => {
			const observer = new MutationObserver( mutationObserverCallback );
			observer.observe( qForm, config );
			return qForm;
		} );
	};

	function waitForElement( selector ) {
		return new Promise( ( resolve ) => {
			if ( document.querySelector( selector ) ) {
				return resolve( document.querySelector( selector ) );
			}

			const observer = new MutationObserver( () => {
				if ( document.querySelector( selector ) ) {
					resolve( document.querySelector( selector ) );
					observer.disconnect();
				}
			} );

			observer.observe( document.body, {
				childList: true,
				subtree: true,
			} );
		} );
	}

	/**
	 * Custom render function using Fluent Forms conversational callback.
	 *
	 * @param {string} container The hCaptcha container selector.
	 * @param {Object} params    Parameters.
	 */
	const render = ( container, params ) => {
		const renderParams = window.hCaptcha.getParams();

		if ( hasOwnCaptcha() && renderParams.size === 'invisible' ) {
			// Cannot use invisible hCaptcha with conversational form.
			renderParams.size = 'normal';
		}

		renderParams.callback = params.callback;
		originalRender( container, renderParams );
	};

	// Intercept render request.
	const originalRender = hcaptcha.render;
	hcaptcha.render = render;

	// Launch Fluent Forms conversational script.
	const t = document.getElementsByTagName( 'script' )[ 0 ];
	const s = document.createElement( 'script' );

	s.type = 'text/javascript';
	s.id = HCaptchaFluentFormObject.id;
	s.src = HCaptchaFluentFormObject.url;
	t.parentNode.insertBefore( s, t );

	// Process form not having own hCaptcha.
	waitForElement( formSelector + ' .vff-footer' ).then( () => {
		// Launch our form-related code when conversational form is rendered.
		processForm();
	} );
} );

const { fetch: originalFetch } = window;

// Intercept fluent form fetch to add hCaptcha data.
window.fetch = async ( ...args ) => {
	const [ resource, config ] = args;

	// @param {FormData} body
	const body = config.body;
	const formId = body.get( 'form_id' );
	const inputName = 'h-captcha-response';
	const widgetName = 'hcaptcha-widget-id';
	let data = body.get( 'data' );

	if ( 'fluentform_submit' === body.get( 'action' ) && ! data.includes( inputName ) ) {
		/**
		 * @type {HTMLTextAreaElement}
		 */
		const hCaptchaResponse =
			document.querySelector( '.ff_conv_app_' + formId + ' [name="' + inputName + '"]' );

		/**
		 * @type {HTMLTextAreaElement}
		 */
		const id =
			document.querySelector( '.ff_conv_app_' + formId + ' [name="' + widgetName + '"]' );

		if ( hCaptchaResponse ) {
			data = data + '&' + inputName + '=' + hCaptchaResponse.value;
		}

		if ( id ) {
			data = data + '&' + widgetName + '=' + id.value;
		}

		body.set( 'data', data );
		config.body = body;
	}

	// noinspection JSCheckFunctionSignatures
	return await originalFetch( resource, config );
};
