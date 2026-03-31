// noinspection JSUnresolvedFunction,JSUnresolvedVariable

const defaultSystemInfoObject = {
	successMsg: 'Copied to clipboard!',
	errorMsg: 'Failed to copy.',
	OKBtnText: 'OK',
};

const kaggDialogMock = {
	confirm: jest.fn(),
};

global.HCaptchaSystemInfoObject = { ...defaultSystemInfoObject };
global.kaggDialog = kaggDialogMock;

// jsdom does not implement navigator.clipboard — provide a manual mock.
const clipboardMock = {
	writeText: jest.fn(),
};

Object.defineProperty( navigator, 'clipboard', {
	value: clipboardMock,
	configurable: true,
} );

function getDom() {
	return `
<div id="hcaptcha-system-info-wrap">
	<span class="helper">Copy</span>
	<textarea id="hcaptcha-system-info">System info text</textarea>
</div>
	`.trim();
}

function bootSystemInfo() {
	jest.resetModules();
	document.body.innerHTML = getDom();
	global.HCaptchaSystemInfoObject = { ...defaultSystemInfoObject };
	kaggDialogMock.confirm.mockClear();
	jest.isolateModules( () => {
		require( '../../../assets/js/system-info.js' );
	} );
	document.dispatchEvent( new Event( 'DOMContentLoaded' ) );
}

describe( 'system-info.js', () => {
	beforeEach( () => {
		clipboardMock.writeText.mockClear();
	} );

	test( 'copies to clipboard and shows success dialog on success', async () => {
		clipboardMock.writeText.mockResolvedValue( undefined );
		bootSystemInfo();

		document.querySelector( '#hcaptcha-system-info-wrap .helper' ).click();

		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect( clipboardMock.writeText ).toHaveBeenCalledWith( 'System info text' );
		expect( kaggDialogMock.confirm ).toHaveBeenCalledWith( expect.objectContaining( {
			title: defaultSystemInfoObject.successMsg,
			type: 'info',
			buttons: { ok: { text: defaultSystemInfoObject.OKBtnText } },
		} ) );
	} );

	test( 'shows error dialog when clipboard write fails', async () => {
		clipboardMock.writeText.mockRejectedValue( new Error( 'denied' ) );
		bootSystemInfo();

		document.querySelector( '#hcaptcha-system-info-wrap .helper' ).click();

		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect( clipboardMock.writeText ).toHaveBeenCalledWith( 'System info text' );
		expect( kaggDialogMock.confirm ).toHaveBeenCalledWith( expect.objectContaining( {
			title: defaultSystemInfoObject.errorMsg,
			type: 'info',
			buttons: { ok: { text: defaultSystemInfoObject.OKBtnText } },
		} ) );
	} );
} );
