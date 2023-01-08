function hCaptchaACFECallback(response, callback) {
	[
		...document.querySelectorAll(
			'.acfe-field-recaptcha input[type="hidden"]'
		),
	].map((el) => {
		el.value = response;
		return el;
	});

	if (callback !== undefined) {
		callback(response);
	}
}

function hCaptchaACFEOnLoad() {
	window.hCaptchaOnLoad = hCaptchaACFEOnLoadSaved;
	window.hCaptchaOnLoad();
}

const params = window.hCaptcha.getParams();
const savedCallback = params.callback;
const savedErrorCallback = params['error-callback'];
const savedExpiredCallback = params['expired-callback'];

params.callback = (response) => {
	hCaptchaACFECallback(response, savedCallback);
};
params['error-callback'] = () => {
	hCaptchaACFECallback('', savedErrorCallback);
};
params['expired-callback'] = () => {
	hCaptchaACFECallback('', savedExpiredCallback);
};

window.hCaptcha.setParams(params);

const hCaptchaACFEOnLoadSaved = window.hCaptchaOnLoad;

window.hCaptchaOnLoad = hCaptchaACFEOnLoad;
