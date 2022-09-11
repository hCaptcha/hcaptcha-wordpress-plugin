/* global hCaptchaReset */

document.addEventListener('DOMContentLoaded', function () {
	/**
	 * Reset hCaptcha widget.
	 *
	 * @param {CustomEvent} event Event.
	 */
	const hCaptchaResetCF7 = function (event) {
		hCaptchaReset(event.target);
	};

	[...document.querySelectorAll('.wpcf7')].map((form) => {
		form.addEventListener('wpcf7invalid', hCaptchaResetCF7, false);
		form.addEventListener('wpcf7spam', hCaptchaResetCF7, false);
		form.addEventListener('wpcf7mailsent', hCaptchaResetCF7, false);
		form.addEventListener('wpcf7mailfailed', hCaptchaResetCF7, false);
		form.addEventListener('wpcf7submit', hCaptchaResetCF7, false);

		return form;
	});
});
