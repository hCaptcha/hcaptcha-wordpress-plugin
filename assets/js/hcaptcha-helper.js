export class helper {
	static addHCaptchaData( options, action, nonceName, $node ) {
		const data = options.data ?? '';

		if ( ! ( typeof data === 'string' && data.startsWith( `action=${ action }` ) ) ) {
			return;
		}

		const hCaptchaData = helper.getHCaptchaData( $node, nonceName );

		options.data +=
			'&h-captcha-response=' + hCaptchaData.response +
			'&hcaptcha-widget-id=' + hCaptchaData.id +
			'&' + nonceName + '=' + hCaptchaData.nonce;
	}

	static getHCaptchaData( $node, nonceName ) {
		let response = $node.find( '[name="h-captcha-response"]' ).val();
		response = response ? response : '';
		let id = $node.find( '[name="hcaptcha-widget-id"]' ).val();
		id = id ? id : '';
		let nonce = $node.find( '[name="' + nonceName + '"]' ).val();
		nonce = nonce ? nonce : '';

		return { response, id, nonce };
	}
}
