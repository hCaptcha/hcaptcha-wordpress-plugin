/* global jQuery */

export class helper {
	static addHCaptchaData( options, action, nonceName, $node ) {
		const data = options.data ?? '';

		if ( ! ( typeof data === 'string' && data.startsWith( `action=${ action }` ) ) ) {
			return;
		}

		options.data += helper.getHCaptchaData( $node, nonceName );
	}

	/**
	 * Get hCaptcha data from a node.
	 *
	 * @param {jQuery} $node     Node.
	 * @param {string} nonceName Nonce name.
	 * @return {string} Data.
	 */
	static getHCaptchaData( $node, nonceName ) {
		const hpName = $node.find( '[name^="hcap_hp_"]' ).first().attr( 'name' ) ?? '';
		const names = [ 'h-captcha-response', 'hcaptcha-widget-id', nonceName, hpName, 'hcap_hp_sig', 'hcap_fst_token' ];

		let data = '';

		for ( const name of names ) {
			if ( ! name ) {
				continue;
			}

			const val = $node.find( `[name="${ name }"]` ).first().val() ?? '';

			data += `&${ name }=${ val }`;
		}

		return data;
	}
}
