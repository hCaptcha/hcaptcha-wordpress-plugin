/**
 * WPDiscuz script file.
 */

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if ( submitButtonElement.classList.contains( 'wc_comm_submit' ) ) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);
