/* global hCaptcha, HCaptchaMailchimpObject */

/**
 * @param HCaptchaMailchimpObject.action
 * @param HCaptchaMailchimpObject.name
 * @param HCaptchaMailchimpObject.nonceField
 * @param HCaptchaMailchimpObject.widget
 */

document.addEventListener( 'DOMContentLoaded', function() {
	const fields = document.querySelector( 'div.mc4wp-form-fields' );

	if ( ! fields ) {
		return;
	}

	fields.addEventListener( 'mc4wp-refresh', function() {
		/**
		 * Parse shortcode in WordPress style.
		 *
		 * @param {string} content The content to parse.
		 *
		 * @return {Object|null} The parsed attributes or null if the content does not contain a shortcode.
		 */
		function parseShortcode( content ) {
			const scRegex = /\[hcaptcha\s+([^\]]+)]/;
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

		const parsedAtts = parseShortcode( fields.innerHTML );

		// If the shortcode is not found, return.
		if ( ! parsedAtts ) {
			return;
		}

		const args = { ...parsedAtts };

		delete args[ 0 ];

		// We cannot use non-standard nonce without making an ajax call.
		args.action = HCaptchaMailchimpObject.action;
		args.name = HCaptchaMailchimpObject.nonce;

		const hcapForm = form( args );

		fields.innerHTML = fields.innerHTML.replace( parsedAtts[ 0 ], hcapForm );
	} );
} );
