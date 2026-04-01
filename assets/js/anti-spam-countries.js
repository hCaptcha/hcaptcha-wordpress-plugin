/* global jQuery, Choices, HCaptchaAntiSpamCountriesObject */

/**
 * @param HCaptchaAntiSpamCountriesObject.searchAriaLabel
 * @param HCaptchaAntiSpamCountriesObject.searchPlaceholder
 */

/**
 * Enhance countries multiple selects on the General settings page.
 *
 */
const antiSpamCountries = function() {
	if ( 'function' !== typeof Choices ) {
		return;
	}

	const searchPlaceholder =
		HCaptchaAntiSpamCountriesObject.searchPlaceholder;

	const applySearchPlaceholder = function( choicesInstance ) {
		const searchAriaLabel = HCaptchaAntiSpamCountriesObject.searchAriaLabel;
		const input = choicesInstance?.containerOuter?.element?.querySelector( '.choices__input--cloned' );

		if ( ! input ) {
			return;
		}

		input.placeholder = searchPlaceholder;
		input.setAttribute( 'aria-label', searchAriaLabel );
	};

	const selectors = [
		'[name="hcaptcha_settings[blacklisted_countries][]"]',
		'[name="hcaptcha_settings[whitelisted_countries][]"]',
	];

	selectors.forEach( ( selector ) => {
		const element = document.querySelector( selector );

		if ( ! element || element.dataset.hcaptchaChoicesInit ) {
			return;
		}

		element.dataset.hcaptchaChoicesInit = '1';
		element.hcaptchaChoices = new Choices(
			element,
			{
				allowHTML: false,
				duplicateItemsAllowed: false,
				itemSelectText: '',
				placeholder: false,
				removeItemButton: true,
				searchEnabled: true,
				searchPlaceholderValue: searchPlaceholder,
				shouldSort: false,
			}
		);

		applySearchPlaceholder( element.hcaptchaChoices );

		element.addEventListener( 'addItem', function() {
			applySearchPlaceholder( element.hcaptchaChoices );
		} );

		element.addEventListener( 'removeItem', function() {
			applySearchPlaceholder( element.hcaptchaChoices );
		} );
	} );
};

window.HCaptchaAntiSpamCountries = antiSpamCountries;

jQuery( document ).ready( antiSpamCountries );
