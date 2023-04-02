/* global jQuery, hCaptchaBindEvents, elementorFrontend */

jQuery(document).ready(function () {
	if ('undefined' === typeof elementorFrontend) {
		return;
	}

	elementorFrontend.hooks.addAction(
		'frontend/element_ready/widget',
		function ($scope) {
			if ($scope[0].classList.contains('elementor-widget-form')) {
				// Elementor reinserts element during editing, so we need to bind events again.
				hCaptchaBindEvents();
			}
		}
	);
});
