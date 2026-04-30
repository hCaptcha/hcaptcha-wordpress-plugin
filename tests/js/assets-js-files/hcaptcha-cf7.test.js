// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Contact Form 7', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
		jest.resetModules();

		document.body.innerHTML = `
	      <form class="wpcf7">
	        <div class="h-captcha-widget"></div>
	      </form>
	      <form class="wpcf7">
	        <div class="h-captcha-widget"></div>
	      </form>
	    `;

		hCaptchaBindEvents = jest.fn();
		global.hCaptchaBindEvents = hCaptchaBindEvents;

		require( '../../../assets/js/hcaptcha-cf7.js' );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
	} );

	test( 'calls hCaptchaBindEvents immediately on load', () => {
		expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( 1 );
	} );

	const eventTypes = [
		'wpcf7invalid',
		'wpcf7spam',
		'wpcf7mailsent',
		'wpcf7mailfailed',
		'wpcf7submit',
	];

	eventTypes.forEach( ( eventType ) => {
		test( `hCaptchaBindEvents is called when the ${ eventType } event is triggered`, () => {
			document.dispatchEvent( new Event( 'hCaptchaLoaded' ) );
			hCaptchaBindEvents.mockClear();

			const forms = document.querySelectorAll( '.wpcf7' );
			forms.forEach( ( form ) => {
				form.dispatchEvent( new CustomEvent( eventType ) );
			} );

			expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( forms.length );
		} );
	} );
} );
