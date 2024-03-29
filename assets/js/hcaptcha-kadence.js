wp.hooks.addFilter(
	'hcaptcha.submitButtonSelector',
	'hcaptcha',
	( submitButtonSelector ) => {
		return submitButtonSelector + ', button.kb-forms-submit';
	}
);

wp.hooks.addFilter(
	'hcaptcha.ajaxSubmitButton',
	'hcaptcha',
	( isAjaxSubmitButton, submitButtonElement ) => {
		if (
			submitButtonElement.classList.contains( 'kb-forms-submit' )
		) {
			return true;
		}

		return isAjaxSubmitButton;
	}
);

let originalStateChange;

function modifyResponse() {
	if ( this.readyState === XMLHttpRequest.DONE ) {
		[ ...document.getElementsByClassName( 'h-captcha' ) ].map( function( widget ) {
			window.hCaptchaReset( widget.closest( 'form' ) );

			return widget;
		} );
	}

	if ( originalStateChange ) {
		originalStateChange.apply( this, arguments );
	}
}

const originalSend = XMLHttpRequest.prototype.send;

XMLHttpRequest.prototype.send = function() {
	originalStateChange = this.onreadystatechange;
	this.onreadystatechange = modifyResponse;
	originalSend.apply( this, arguments );
};
