// noinspection JSUnresolvedReference

import $ from 'jquery';

beforeEach( () => {
	global.jQuery = $;
	global.$ = $;
	global.HCaptchaGeneralCountriesObject = {
		searchAriaLabel: 'Search countries',
		searchPlaceholder: 'Type to search...',
	};
} );

afterEach( () => {
	document.body.innerHTML = '';
	delete global.Choices;
	delete global.HCaptchaGeneralCountriesObject;
} );

const buildDOM = () => {
	document.body.innerHTML = `
		<select name="hcaptcha_settings[blacklisted_countries][]" multiple>
			<option value="US">United States</option>
			<option value="CA">Canada</option>
		</select>
		<select name="hcaptcha_settings[whitelisted_countries][]" multiple>
			<option value="GB">United Kingdom</option>
		</select>
	`;
};

const createMockInput = () => {
	const mockInput = document.createElement( 'input' );
	mockInput.classList.add( 'choices__input--cloned' );

	const mockContainerOuter = document.createElement( 'div' );
	mockContainerOuter.appendChild( mockInput );

	return { mockInput, mockContainerOuter };
};

const setupChoices = ( mockContainerOuter ) => {
	global.Choices = jest.fn( function() {
		// noinspection JSUnusedGlobalSymbols
		this.containerOuter = { element: mockContainerOuter };
	} );
};

const loadAndRun = () => {
	jest.resetModules();
	require( '../../../assets/js/general-countries' );

	// Call explicitly to ensure it runs with current DOM and globals.
	window.hCaptchaGeneralCountries();
};

describe( 'generalCountries', () => {
	test( 'returns early when Choices is not a function', () => {
		global.Choices = undefined;
		buildDOM();
		loadAndRun();

		const el = document.querySelector( '[name="hcaptcha_settings[blacklisted_countries][]"]' );

		expect( el.dataset.hcaptchaChoicesInit ).toBeUndefined();
	} );

	test( 'initializes Choices on both selects', () => {
		const { mockInput, mockContainerOuter } = createMockInput();

		setupChoices( mockContainerOuter );
		buildDOM();
		loadAndRun();

		expect( global.Choices ).toHaveBeenCalledTimes( 2 );

		const blacklisted = document.querySelector( '[name="hcaptcha_settings[blacklisted_countries][]"]' );
		const whitelisted = document.querySelector( '[name="hcaptcha_settings[whitelisted_countries][]"]' );

		expect( blacklisted.dataset.hcaptchaChoicesInit ).toBe( '1' );
		expect( whitelisted.dataset.hcaptchaChoicesInit ).toBe( '1' );
		expect( blacklisted.hcaptchaChoices ).toBeDefined();
		expect( whitelisted.hcaptchaChoices ).toBeDefined();

		expect( mockInput.placeholder ).toBe( 'Type to search...' );
		expect( mockInput.getAttribute( 'aria-label' ) ).toBe( 'Search countries' );
	} );

	test( 'skips element already initialized', () => {
		const { mockContainerOuter } = createMockInput();

		setupChoices( mockContainerOuter );
		buildDOM();

		const blacklisted = document.querySelector( '[name="hcaptcha_settings[blacklisted_countries][]"]' );
		blacklisted.dataset.hcaptchaChoicesInit = '1';

		loadAndRun();

		// Only whitelisted should be initialized.
		expect( global.Choices ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'skips element when not found in DOM', () => {
		setupChoices( document.createElement( 'div' ) );

		// Empty DOM — no selects.
		document.body.innerHTML = '';
		loadAndRun();

		expect( global.Choices ).not.toHaveBeenCalled();
	} );

	test( 'applySearchPlaceholder returns early when input not found', () => {
		const mockContainerOuter = document.createElement( 'div' );
		// No .choices__input--cloned inside.

		setupChoices( mockContainerOuter );
		buildDOM();
		loadAndRun();

		// Should not throw; placeholder not set on anything.
		expect( global.Choices ).toHaveBeenCalledTimes( 2 );
	} );

	test( 'addItem event re-applies search placeholder', () => {
		const { mockInput, mockContainerOuter } = createMockInput();

		setupChoices( mockContainerOuter );
		buildDOM();
		loadAndRun();

		// Reset to verify re-application.
		mockInput.placeholder = '';
		mockInput.removeAttribute( 'aria-label' );

		const blacklisted = document.querySelector( '[name="hcaptcha_settings[blacklisted_countries][]"]' );
		blacklisted.dispatchEvent( new Event( 'addItem' ) );

		expect( mockInput.placeholder ).toBe( 'Type to search...' );
		expect( mockInput.getAttribute( 'aria-label' ) ).toBe( 'Search countries' );
	} );

	test( 'removeItem event re-applies search placeholder', () => {
		const { mockInput, mockContainerOuter } = createMockInput();

		setupChoices( mockContainerOuter );
		buildDOM();
		loadAndRun();

		mockInput.placeholder = '';
		mockInput.removeAttribute( 'aria-label' );

		const blacklisted = document.querySelector( '[name="hcaptcha_settings[blacklisted_countries][]"]' );
		blacklisted.dispatchEvent( new Event( 'removeItem' ) );

		expect( mockInput.placeholder ).toBe( 'Type to search...' );
		expect( mockInput.getAttribute( 'aria-label' ) ).toBe( 'Search countries' );
	} );

	test( 'exposes generalCountries on window', () => {
		setupChoices( document.createElement( 'div' ) );
		loadAndRun();

		expect( typeof window.hCaptchaGeneralCountries ).toBe( 'function' );
	} );

	test( 'Choices constructor receives correct options', () => {
		const mockContainerOuter = document.createElement( 'div' );

		setupChoices( mockContainerOuter );
		buildDOM();
		loadAndRun();

		const callArgs = global.Choices.mock.calls[ 0 ][ 1 ];

		expect( callArgs ).toEqual( {
			allowHTML: false,
			duplicateItemsAllowed: false,
			itemSelectText: '',
			placeholder: false,
			removeItemButton: true,
			searchEnabled: true,
			searchPlaceholderValue: 'Type to search...',
			shouldSort: false,
		} );
	} );
} );
