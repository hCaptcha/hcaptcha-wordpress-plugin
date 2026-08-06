/**
 * Load the hCaptcha API after interaction with a protected form.
 */
class FormInteraction {
	/**
	 * @param {string} eventName Event dispatched to load the hCaptcha API.
	 */
	constructor( eventName ) {
		this.eventName = eventName;
		this.listening = false;
		this.keyboardNavigation = false;

		this.handleFocusIn = this.handleFocusIn.bind( this );
		this.handleKeyDown = this.handleKeyDown.bind( this );
		this.handleKeyUp = this.handleKeyUp.bind( this );
		this.handlePointerDown = this.handlePointerDown.bind( this );
	}

	/**
	 * Start listening for form interaction.
	 *
	 * @return {void}
	 */
	init() {
		if ( ! this.eventName || this.listening ) {
			return;
		}

		this.listening = true;

		document.addEventListener( 'focusin', this.handleFocusIn, true );
		document.addEventListener( 'keydown', this.handleKeyDown, true );
		document.addEventListener( 'keyup', this.handleKeyUp, true );
		document.addEventListener( 'pointerdown', this.handlePointerDown, {
			capture: true,
			passive: true,
		} );
	}

	/**
	 * Stop listening for form interaction.
	 *
	 * @return {void}
	 */
	destroy() {
		if ( ! this.listening ) {
			return;
		}

		this.listening = false;

		document.removeEventListener( 'focusin', this.handleFocusIn, true );
		document.removeEventListener( 'keydown', this.handleKeyDown, true );
		document.removeEventListener( 'keyup', this.handleKeyUp, true );
		document.removeEventListener( 'pointerdown', this.handlePointerDown, true );
	}

	/**
	 * Handle pointer interaction.
	 *
	 * @param {PointerEvent} event Event.
	 *
	 * @return {void}
	 */
	handlePointerDown( event ) {
		this.loadForTarget( event.target );
	}

	/**
	 * Handle keyboard interaction and track Tab navigation.
	 *
	 * @param {KeyboardEvent} event Event.
	 *
	 * @return {void}
	 */
	handleKeyDown( event ) {
		this.keyboardNavigation = 'Tab' === event.key;
		this.loadForTarget( event.target );
	}

	/**
	 * Reset Tab navigation when focus did not enter a protected form.
	 *
	 * @param {KeyboardEvent} event Event.
	 *
	 * @return {void}
	 */
	handleKeyUp( event ) {
		if ( 'Tab' === event.key ) {
			this.keyboardNavigation = false;
		}
	}

	/**
	 * Handle focus caused by Tab navigation.
	 *
	 * Programmatic focus without a preceding Tab key is intentionally ignored.
	 *
	 * @param {FocusEvent} event Event.
	 *
	 * @return {void}
	 */
	handleFocusIn( event ) {
		if ( ! this.keyboardNavigation ) {
			return;
		}

		this.keyboardNavigation = false;
		this.loadForTarget( event.target );
	}

	/**
	 * Dispatch the API event when the target belongs to a protected form.
	 *
	 * @param {EventTarget|null} target Event target.
	 *
	 * @return {void}
	 */
	loadForTarget( target ) {
		if ( ! ( target instanceof Element ) ) {
			return;
		}

		const form = target.closest( 'form' );

		if ( ! form?.querySelector( '.h-captcha.hcaptcha-api-delayed' ) ) {
			return;
		}

		this.destroy();
		document.dispatchEvent( new CustomEvent( this.eventName ) );
	}
}

export default FormInteraction;
