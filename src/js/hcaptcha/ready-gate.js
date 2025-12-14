/**
 * @file class ReadyGate.
 */

/**
 * Class ReadyGate.
 */
class ReadyGate {
	constructor() {
		this._domReady = document.readyState !== 'loading';
		this._hcaptchaReady = typeof hcaptcha !== 'undefined';
		this._resolve = null;
		this._readyPromise = new Promise( ( resolve ) => {
			this._resolve = resolve;
		} );

		this._onDom = this._onDom.bind( this );
		this._onHCaptcha = this._onHCaptcha.bind( this );

		document.addEventListener( 'DOMContentLoaded', this._onDom, { once: true } );
		document.addEventListener( 'hCaptchaOnLoad', this._onHCaptcha, { once: true } );

		this._tryResolve();
	}

	_onDom() {
		this._domReady = true;

		this._tryResolve();
	}

	_onHCaptcha() {
		this._hcaptchaReady = true;

		this._tryResolve();
	}

	_tryResolve() {
		if ( this._domReady && this._hcaptchaReady ) {
			// Resolves once. Further calls are ignored.
			this._resolve();
		}
	}

	ready() {
		return this._readyPromise;
	}

	runWhenReady( callback ) {
		return this.ready().then( () => callback() );
	}
}

export default ReadyGate;
