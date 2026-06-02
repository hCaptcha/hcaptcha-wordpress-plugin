/* global jQuery, Choices, HCaptchaAntiSpamCountriesObject */

/**
 * @param HCaptchaAntiSpamCountriesObject.headersSearchAriaLabel
 * @param HCaptchaAntiSpamCountriesObject.headersSearchPlaceholder
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

	const applySearchPlaceholder = function( choicesInstance, searchAriaLabel, placeholder ) {
		const input = choicesInstance?.containerOuter?.element?.querySelector( '.choices__input--cloned' );

		if ( ! input ) {
			return;
		}

		input.placeholder = placeholder;
		input.setAttribute( 'aria-label', searchAriaLabel );
	};

	const selectors = [
		{
			searchAriaLabel: HCaptchaAntiSpamCountriesObject.searchAriaLabel,
			searchPlaceholder,
			selector: '[name="hcaptcha_settings[blacklisted_countries][]"]',
		},
		{
			searchAriaLabel: HCaptchaAntiSpamCountriesObject.searchAriaLabel,
			searchPlaceholder,
			selector: '[name="hcaptcha_settings[whitelisted_countries][]"]',
		},
		{
			searchAriaLabel: HCaptchaAntiSpamCountriesObject.headersSearchAriaLabel,
			searchPlaceholder: HCaptchaAntiSpamCountriesObject.headersSearchPlaceholder,
			selector: '[name="hcaptcha_settings[trusted_address_headers][]"]',
		},
	];

	selectors.forEach( ( { searchAriaLabel, searchPlaceholder: placeholder, selector } ) => {
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
				searchPlaceholderValue: placeholder,
				shouldSort: false,
			},
		);

		applySearchPlaceholder( element.hcaptchaChoices, searchAriaLabel, placeholder );

		element.addEventListener( 'addItem', function() {
			applySearchPlaceholder( element.hcaptchaChoices, searchAriaLabel, placeholder );
		} );

		element.addEventListener( 'removeItem', function() {
			applySearchPlaceholder( element.hcaptchaChoices, searchAriaLabel, placeholder );
		} );
	} );
};

window.HCaptchaAntiSpamCountries = antiSpamCountries;

jQuery( document ).ready( antiSpamCountries );
