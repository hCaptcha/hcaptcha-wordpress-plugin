/* global jQuery */

jQuery(document).ready(function ($) {
	$('.wpforo-section .add_wpftopic:not(.not_reg_user)').click(function () {
		window.hCaptchaBindEvents();
	});
});
