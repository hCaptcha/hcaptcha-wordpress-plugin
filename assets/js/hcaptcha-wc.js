/* global jQuery, hCaptchaReset */

jQuery(document).ready(function ($) {
	$(document.body).on('checkout_error', function () {
		hCaptchaReset(document.querySelector('form.woocommerce-checkout'));
	});
});
