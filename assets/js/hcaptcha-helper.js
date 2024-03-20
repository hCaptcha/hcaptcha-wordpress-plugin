export class helper {
	static addHCaptchaData( options, action, nonceName, $node ) {
		const data = options.data ?? '';

		if ( ! ( typeof data === 'string' && data.startsWith( `action=${ action }` ) ) ) {
			return;
		}

		let response = $node.find( '[name="h-captcha-response"]' ).val();
		response = response ? response : '';
		let id = $node.find( '[name="hcaptcha-widget-id"]' ).val();
		id = id ? id : '';
		let nonce = $node.find( '[name="' + nonceName + '"]' ).val();
		nonce = nonce ? nonce : '';
		options.data +=
			'&h-captcha-response=' + response + '&hcaptcha-widget-id=' + id + '&' + nonceName + '=' + nonce;
	}
}
