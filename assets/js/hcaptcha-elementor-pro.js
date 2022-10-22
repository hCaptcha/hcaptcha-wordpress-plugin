/* global _, elementor, elementorPro, elementorModules */

class HCaptchaElementor extends elementorModules.editor.utils.Module {
	static getHCaptchaForm(item) {
		const config = elementorPro.config.forms[item.field_type];

		if (!config.enabled) {
			return (
				'<div class="elementor-alert elementor-alert-info">' +
				config.setup_message +
				'</div>'
			);
		}

		let hCaptchaData = 'data-sitekey="' + config.site_key + '"';
		hCaptchaData += ' data-theme="' + config.hcaptcha_theme + '"';
		hCaptchaData += ' data-size="' + config.hcaptcha_size + '"';
		hCaptchaData += ' data-auto="false"';

		return '<div class="h-captcha" ' + hCaptchaData + '></div>';
	}

	renderField(inputField, item) {
		inputField +=
			'<div class="elementor-field" id="form-field-' +
			item.custom_id +
			'">';
		inputField +=
			'<div class="elementor-hcaptcha' +
			_.escape(item.css_classes) +
			'">';
		inputField += HCaptchaElementor.getHCaptchaForm(item);
		inputField += '</div>';
		inputField += '</div>';
		return inputField;
	}

	filterItem(item) {
		if ('hcaptcha' === item.field_type) {
			item.field_label = false;
		}

		return item;
	}

	onInit() {
		elementor.hooks.addFilter(
			'elementor_pro/forms/content_template/item',
			this.filterItem
		);
		elementor.hooks.addFilter(
			'elementor_pro/forms/content_template/field/hcaptcha',
			this.renderField,
			10,
			2
		);
	}
}

new HCaptchaElementor();
