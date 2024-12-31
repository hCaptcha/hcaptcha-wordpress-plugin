/* global HCaptchaFlatPickerObject, flatpickr */

/**
 * @param flatpickr.l10ns
 */
document.addEventListener( 'DOMContentLoaded', function() {
	/**
	 * @type {HTMLInputElement}
	 */
	const datepicker = document.getElementById( 'hcaptcha-datepicker' );

	if ( ! datepicker ) {
		return;
	}

	const classNames = {
		hide: 'hcaptcha-hide',
		selected: 'hcaptcha-is-selected',
	};
	const delimiter = HCaptchaFlatPickerObject.delimiter;
	const locale = HCaptchaFlatPickerObject.locale;
	let flatPickerObj;

	const wrapper = document.getElementById( 'hcaptcha-options' );
	const filterForm = document.querySelector( '.hcaptcha-filter' );
	const filterBtn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
	const defaultChoice = filterForm.querySelector( 'input[type="radio"][data-default]' );

	function bindEvents() {
		document.addEventListener( 'click', onClickOutside );
		wrapper.querySelector( '#hcaptcha-datepicker-popover-button' ).addEventListener( 'click', onToggle );
		wrapper.querySelectorAll( '[type="radio"]' ).forEach( function( input ) {
			input.addEventListener( 'change', onUpdateDatepicker );
		} );
		wrapper.querySelector( '[type="reset"]' ).addEventListener( 'click', onResetDatepicker );
		wrapper.addEventListener( 'submit', onSubmitDatepicker );
		wrapper.querySelector( '#current-page-selector' ).addEventListener( 'keydown', onPageNumberEnter );
	}

	function onSubmitDatepicker( event ) {
		event.preventDefault();

		// Exclude radio inputs from the form submission.
		this.querySelectorAll( 'input[type="radio"]' ).forEach( function( input ) {
			input.name = '';
		} );

		// Remove the popover from the view.
		// When the dropdown is closed, aria-expended="false".
		hideElement( filterBtn.nextElementSibling );

		const currentUrl = new URL( window.location.href );

		/**
		 * @type {URLSearchParams}
		 */
		const searchParams = currentUrl.searchParams;

		// Set date URL arg.
		searchParams.delete( 'date' );

		if ( datepicker.value ) {
			searchParams.append( 'date', datepicker.value );
		}
		window.location.href = currentUrl.toString();
	}

	function hideElement( el ) {
		el.setAttribute( 'aria-expanded', 'false' );
		el.style.display = 'none';
	}

	function onToggle( event ) {
		event.preventDefault();
		event.stopPropagation();

		const selectorElement = event.target.nextElementSibling;

		// Toggle the visibility of the matched element.
		if ( selectorElement.style.display === 'none' || selectorElement.style.display === '' ) {
			selectorElement.style.display = 'block';
		} else {
			selectorElement.style.display = 'none';
		}

		// When the dropdown is open, aria-expanded="true".
		selectorElement.setAttribute(
			'aria-expanded',
			selectorElement.style.display === 'block' ? 'true' : 'false'
		);
	}

	function onPageNumberEnter( event ) {
		if ( event.key !== 'Enter' ) {
			return;
		}

		event.preventDefault();

		const currentUrl = new URL( window.location.href );
		let paged = parseInt( currentUrl.searchParams.get( 'paged' ) );
		const newPaged = parseInt( event.target.value );

		if ( isNaN( paged ) || paged < 1 ) {
			paged = 1;
		}

		if ( isNaN( newPaged ) || newPaged < 1 ) {
			return;
		}

		currentUrl.searchParams.delete( 'paged' );

		if ( newPaged !== paged ) {
			currentUrl.searchParams.set( 'paged', newPaged.toString() );
			window.location.href = currentUrl.href;
		}
	}

	function onClickOutside( event ) {
		/**
		 * @type {HTMLElement}
		 */
		const selector = document.querySelector( '.hcaptcha-datepicker-popover' );

		// Check if the click is outside the target element.
		if ( ! selector.contains( event.target ) ) {
			selector.style.display = 'none'; // hide the element
		}
	}

	function selectDatepickerChoice( element ) {
		filterForm.querySelectorAll( 'label' ).forEach( function( label ) {
			label.classList.remove( classNames.selected );
		} );

		element.classList.add( classNames.selected );
	}

	function onResetDatepicker( event ) {
		event.preventDefault();

		// Return the form to its original state.
		// filterForm.reset();

		// Remove the popover from the view.
		// When the dropdown is closed, aria-expended="false".
		// hideElement( filterBtn.nextElementSibling );

		defaultChoice.checked = true;

		onUpdateDatepicker();
	}

	// eslint-disable-next-line no-unused-vars
	function onUpdateDatepicker( event = {}, isCustomDates = false ) {
		/**
		 * @type {HTMLInputElement}
		 */
		const selected = filterForm.querySelector( 'input:checked' );
		const parent = selected.parentElement;

		/**
		 * @type {HTMLInputElement}
		 */
		const target = isCustomDates ? datepicker : selected;
		const dates = target.value.split( delimiter );

		/**
		 * @type {HTMLInputElement} target
		 */
		const nextElementSibling = target.nextElementSibling;

		filterBtn.textContent = isCustomDates ? nextElementSibling.value : parent.textContent;

		selectDatepickerChoice( parent );

		if ( Array.isArray( dates ) && dates.length === 2 ) {
			// Sets the current selected date(s).
			flatPickerObj.setDate( dates );

			return;
		}

		flatPickerObj.clear(); // Reset the datepicker.
	}

	function initFlatPicker() {
		/**
		 * @type {HTMLInputElement} target
		 */
		const customInput = filterForm.querySelector( 'input[value="custom"]' );

		flatPickerObj = flatpickr( datepicker, {
			mode: 'range',
			inline: true,
			allowInput: false,
			enableTime: false,
			clickOpens: false,
			altInput: true,
			altFormat: 'M j, Y',
			dateFormat: 'Y-m-d',
			locale: {
				// Localized per-instance, if applicable.
				...flatpickr.l10ns[ locale ] || {},
				rangeSeparator: delimiter,
			},
			onChange( selectedDates, dateStr, instance ) {
				// Immediately after a user interacts with the datepicker, ensure that the "Custom" option is chosen.
				customInput.checked = true;
				selectDatepickerChoice( customInput.parentElement );

				if ( dateStr ) {
					// Update filter button label when date range specified.
					filterBtn.textContent = instance.altInput.value;
				}
			},
		} );

		// Determine if a custom date range was provided or selected.
		onUpdateDatepicker( {}, customInput.checked );
	}

	bindEvents();
	initFlatPicker();
} );
