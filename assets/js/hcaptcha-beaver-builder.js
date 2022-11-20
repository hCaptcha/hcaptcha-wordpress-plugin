/* global jQuery */

(function ($) {
	// noinspection JSCheckFunctionSignatures
	$.ajaxPrefilter(function (options) {
		const data = options.data;

		if (!data.startsWith('action=fl_builder_email')) {
			return;
		}

		const urlParams = new URLSearchParams(data);
		const nodeId = urlParams.get('node_id');
		const $node = $('[data-node=' + nodeId + ']');
		const nonceName = 'hcaptcha_beaver_builder_nonce';
		let response = $node.find('[name="h-captcha-response"]').val();
		response = response ? response : '';
		let nonce = $node.find('[name="' + nonceName + '"]').val();
		nonce = nonce ? nonce : '';
		options.data +=
			'&h-captcha-response=' + response + '&' + nonceName + '=' + nonce;
	});
})(jQuery);
