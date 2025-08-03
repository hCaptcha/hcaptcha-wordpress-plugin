// noinspection JSUnresolvedFunction,JSUnresolvedVariable

describe( 'hCaptcha Contact Form 7', () => {
	let hCaptchaBindEvents;

	beforeEach( () => {
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
		document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
	} );

	afterEach( () => {
		global.hCaptchaBindEvents.mockRestore();
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
			const forms = document.querySelectorAll( '.wpcf7' );
			forms.forEach( ( form ) => {
				const event = new CustomEvent( eventType );
				form.dispatchEvent( event );
			} );

			expect( hCaptchaBindEvents ).toHaveBeenCalledTimes( forms.length );
		} );
	} );
} );
