/* global _, elementor, elementorPro, elementorModules */

/**
 * @param config.setup_message
 * @param config.site_key
 * @param config.hcaptcha_theme
 * @param config.hcaptcha_size
 * @param item.field_type
 * @param item.custom_id
 * @param item.css_classes
 */

/**
 * Class HCaptchaAdminElementorPro.
 */
class HCaptchaAdminElementorPro extends elementorModules.editor.utils.Module {
	/**
	 * Get hCaptcha form.
	 *
	 * @param {Object} item
	 *
	 * @return {string} hCaptcha form.
	 */
	static getHCaptchaForm( item ) {
		const config = elementorPro.config.forms[ item.field_type ];

		if ( ! config.enabled ) {
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

		return '<h-captcha class="h-captcha" ' + hCaptchaData + '></h-captcha>';
	}

	renderField( inputField, item ) {
		item.field_label = false;

		inputField +=
			'<div class="elementor-field" id="form-field-' +
			item.custom_id +
			'">';
		inputField +=
			'<div class="elementor-hcaptcha' +
			_.escape( item.css_classes ) +
			'">';
		inputField += HCaptchaAdminElementorPro.getHCaptchaForm( item );
		inputField += '</div>';
		inputField += '</div>';

		return inputField;
	}

	onInit() {
		elementor.hooks.addFilter(
			'elementor_pro/forms/content_template/field/hcaptcha',
			this.renderField,
			10,
			2
		);
	}
}

window.hCaptchaAdminElementorPro = new HCaptchaAdminElementorPro();
