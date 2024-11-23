/* global hCaptcha, HCaptchaMailchimpObject */

/**
 * @param HCaptchaMailchimpObject.action
 * @param HCaptchaMailchimpObject.name
 * @param HCaptchaMailchimpObject.nonceField
 * @param HCaptchaMailchimpObject.widget
 */

document.addEventListener( 'DOMContentLoaded', function() {
	/**
	 * Parse shortcode in WordPress style.
	 *
	 * @param {string} content The content to parse.
	 *
	 * @return {Object|null} The parsed attributes or null if the content does not contain a shortcode.
	 */
	function parseShortcode( content ) {
		const scRegex = /\[hcaptcha\s*([^\]]*)]/;
		const match = content.match( scRegex );

		if ( ! match ) {
			return null;
		}

		const attsString = match[ 1 ];
		let atts = {};
		const attsRegex = /(\w+)="([^"]*)"/g;
		let attsMatch;

		while ( ( attsMatch = attsRegex.exec( attsString ) ) !== null ) {
			atts[ attsMatch[ 1 ] ] = attsMatch[ 2 ];
		}

		const params = hCaptcha.getParams();
		const pairs = {
			action: 'hcaptcha_action',
			name: 'hcaptcha_nonce',
			auto: false,
			force: params?.force || false,
			theme: params?.theme || 'light',
			size: params?.size || 'normal',
			id: [],
			protect: true,
		};

		// Merge the default attributes with the parsed attributes.
		atts = Object.assign( pairs, atts );

		// Add the full shortcode string to the attributes.
		atts[ 0 ] = match[ 0 ];

		return atts;
	}

	/**
	 * Get hCaptcha form.
	 *
	 * @param {Object} args The attributes.
	 */
	function form( args ) {
		const params = hCaptcha.getParams();
		const defaults = {
			action: '',
			name: '',
			auto: false,
			force: params?.force || false,
			theme: params?.theme || 'light',
			size: params?.size || 'normal',
			id: [],
			protect: true,
		};
		const allowedThemes = [ 'light', 'dark', 'auto' ];
		const allowedSizes = [ 'normal', 'compact', 'invisible' ];

		args = Object.assign( defaults, args );

		args.action = String( args.action );
		args.name = String( args.name );
		args.auto = Boolean( args.auto );
		args.force = Boolean( args.force );
		args.theme = allowedThemes.includes( args.theme ) ? String( args.theme ) : 'light';
		args.size = allowedSizes.includes( args.size ) ? String( args.size ) : 'normal';
		args.id = Array.isArray( args.id ) ? args.id : [ args.id ];
		args.protect = Boolean( args.protect );

		// Widget is always the same in the admin.
		const widget = HCaptchaMailchimpObject.widget;

		if ( ! args.protect ) {
			return '';
		}

		const hCaptchaTag = `
				<h-captcha
					class="h-captcha"
					data-sitekey="${ params?.sitekey ?? '' }"
					data-theme="${ args.theme }"
					data-size="${ args.size }"
					data-auto="${ args.auto ? 'true' : 'false' }"
					data-force="${ args.force ? 'true' : 'false' }">
				</h-captcha>
				`;

		const nonceField = HCaptchaMailchimpObject.nonceField;

		return widget + hCaptchaTag + nonceField;
	}

	/**
	 * Add hCaptcha to the form.
	 */
	function addHCaptcha() {
		if ( ! ( mc4wpRefreshFired && hCaptchaLoadedFired && timeoutFired ) ) {
			return;
		}

		const parsedAtts = parseShortcode( fields.innerHTML );
		let args;
		let search;

		if ( parsedAtts ) {
			// Replace the shortcode with the hCaptcha form.
			args = { ...parsedAtts };
			search = args[ 0 ];

			delete args[ 0 ];
		} else {
			// Add the hCaptcha form before submit button.
			args = {};

			const match = fields.innerHTML.match( /<p>\s*?<input .*?type="submit".*?>\s*<\/p>/s );

			search = match ? match[ 0 ] : '';
		}

		if ( ! search ) {
			return;
		}

		// We cannot use non-standard nonce without making an ajax call.
		args.action = HCaptchaMailchimpObject.action;
		args.name = HCaptchaMailchimpObject.name;

		const hcapForm = form( args );
		const hCaptchaButton = parent.document.querySelector( 'button[value="hcaptcha"]' );

		if ( parsedAtts ) {
			fields.innerHTML = fields.innerHTML.replace( search, hcapForm );
			hCaptchaButton.classList.add( 'in-form' );
			hCaptchaButton.classList.remove( 'not-in-form' );
		} else {
			fields.innerHTML = fields.innerHTML.replace( search, `<p>${ hcapForm }${ search }</p>` );
			hCaptchaButton.classList.remove( 'in-form' );
			hCaptchaButton.classList.add( 'not-in-form' );
		}
	}

	/**
	 * Set hCaptcha timeout.
	 */
	function setHCaptchaTimeout() {
		if ( timeoutId ) {
			clearTimeout( timeoutId );
		}

		timeoutId = setTimeout( function() {
			timeoutFired = true;
			addHCaptcha();
		}, 300 );
	}

	/**
	 * Add hCaptcha button to the form
	 */
	function addHCaptchaButton() {
		const availableFields = parent.document.querySelector( '#mc4wp-available-fields' );

		if ( ! availableFields ) {
			return;
		}

		const secondDiv = availableFields.querySelectorAll( 'div' )[ 1 ];

		if ( ! secondDiv ) {
			return;
		}

		const hCaptchaButton = document.createElement( 'button' );

		hCaptchaButton.className = 'button not-in-form';
		hCaptchaButton.type = 'button';
		hCaptchaButton.value = 'hcaptcha';
		hCaptchaButton.textContent = 'hCaptcha';

		const secondButton = secondDiv.querySelectorAll( 'button' )[ 1 ];

		if ( secondButton ) {
			secondDiv.insertBefore( hCaptchaButton, secondButton );
		} else {
			secondDiv.appendChild( hCaptchaButton );
		}

		hCaptchaButton.addEventListener( 'click', function() {
			// noinspection JSUnresolvedReference
			const editor = parent.window.mc4wp.forms.editor;
			const parsedAtts = parseShortcode( editor.getValue() );

			if ( parsedAtts ) {
				// hCaptcha already in the form.
				return;
			}

			// Add hCaptcha to the form at the current cursor position.
			editor.insert( '[hcaptcha]' );

			fields.dispatchEvent( new Event( 'mc4wp-refresh' ) );
		} );
	}

	const fields = document.querySelector( 'div.mc4wp-form-fields' );

	if ( ! fields ) {
		return;
	}

	addHCaptchaButton();

	let mc4wpRefreshFired = false;
	let hCaptchaLoadedFired = false;
	let timeoutFired = false;
	let timeoutId;

	fields.addEventListener( 'mc4wp-refresh', function() {
		mc4wpRefreshFired = true;
		setHCaptchaTimeout();
	} );

	document.addEventListener( 'hCaptchaLoaded', function() {
		hCaptchaLoadedFired = true;
		addHCaptcha();
	} );

	setHCaptchaTimeout();
} );
