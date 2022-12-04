/* global jQuery */

(function ($) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter(function (options) {
		const data = options.data;
		let nonceName = '';

		if (data.startsWith('action=fl_builder_email')) {
			nonceName = 'hcaptcha_beaver_builder_nonce';
		}

		if (data.startsWith('action=fl_builder_login_form_submit')) {
			nonceName = 'hcaptcha_login_nonce';
		}

		if (!nonceName) {
			return;
		}

		const urlParams = new URLSearchParams(data);
		const nodeId = urlParams.get('node_id');
		const $node = $('[data-node=' + nodeId + ']');
		let response = $node.find('[name="h-captcha-response"]').val();
		response = response ? response : '';
		let nonce = $node.find('[name="' + nonceName + '"]').val();
		nonce = nonce ? nonce : '';
		options.data +=
			'&h-captcha-response=' + response + '&' + nonceName + '=' + nonce;
	});
})(jQuery);
