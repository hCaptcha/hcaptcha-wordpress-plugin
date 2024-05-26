/* global HCaptchaFlatPickerObject, flatpickr */

/**
 * @param flatpickr.l10ns
 */
document.addEventListener( 'DOMContentLoaded', function() {
	const classNames = {
		hide: 'hcaptcha-hide',
		selected: 'hcaptcha-is-selected',
	};
	const delimiter = HCaptchaFlatPickerObject.delimiter;
	const locale = HCaptchaFlatPickerObject.locale;
	let flatPickerObj;

	const wrapper = document.getElementById( 'hcaptcha-options' );
	const datepicker = document.getElementById( 'hcaptcha-datepicker' );
	const filterForm = document.querySelector( '.hcaptcha-filter' );
	const filterBtn = document.getElementById( 'hcaptcha-datepicker-popover-button' );
	const defaultChoice = filterForm.querySelector( 'input[type="radio"][data-default]' );

	function bindEvents() {
		wrapper.addEventListener( 'submit', onSubmitDatepicker );
		wrapper.querySelector( '[type="reset"]' ).addEventListener( 'click', onResetDatepicker );
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

	function selectDatepickerChoice( $this ) {
		filterForm.querySelector( 'label' ).classList.remove( classNames.selected );
		$this.classList.add( classNames.selected );
	}

	function onResetDatepicker( event ) {
		event.preventDefault();

		// Return the form to its original state.
		// filterForm.reset();

		// Remove the popover from the view.
		// When the dropdown is closed, aria-expended="false".
		// hideElement( filterBtn.nextElementSibling );

		defaultChoice.checked = true;

		updateDatepicker();
	}

	function updateDatepicker( isCustomDates = false ) {
		const selected = filterForm.querySelector( 'input:checked' );
		const parent = selected.parentElement;
		const target = isCustomDates ? datepicker : selected;
		const dates = target.value.split( delimiter );

		filterBtn.textContent = isCustomDates ? target.nextElementSibling.value : parent.textContent;

		selectDatepickerChoice( parent );

		if ( Array.isArray( dates ) && dates.length === 2 ) {
			// Sets the current selected date(s).
			flatPickerObj.setDate( dates );

			return;
		}

		flatPickerObj.clear(); // Reset the datepicker.
	}

	function initFlatPicker() {
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
				const custom = filterForm.querySelector( 'input[value="custom"]' );

				custom.checked = true;
				selectDatepickerChoice( custom.parentElement );

				if ( dateStr ) {
					// Update filter button label when date range specified.
					filterBtn.textContent = instance.altInput.value;
				}
			},
		} );

		// Determine if a custom date range was provided or selected.
		updateDatepicker( filterForm.querySelector( 'input[value="custom"]' ).checked );
	}

	bindEvents();
	initFlatPicker();
} );
