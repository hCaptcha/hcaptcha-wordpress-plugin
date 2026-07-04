// noinspection JSUnresolvedFunction,JSUnresolvedVariable

/* global HCaptchaSupportModalObject */

/**
 * hCaptcha support modal logic.
 */
const supportModal = () => {
	const modal = document.getElementById( 'hcaptcha-support-modal' );
	const openButton = document.querySelector( '.hcaptcha-help-button' );

	if ( ! modal || ! openButton || ! window.HCaptchaSupportModalObject ) {
		return;
	}

	const config = HCaptchaSupportModalObject;
	const strings = config.strings || {};
	const reportStrings = strings.report || {};
	const systemInfo = config.systemInfo || '';
	const summaryField = document.getElementById( 'hcaptcha-support-summary' );
	const detailsField = document.getElementById( 'hcaptcha-support-details' );
	const areaField = document.getElementById( 'hcaptcha-support-area' );
	const reportField = document.getElementById( 'hcaptcha-support-report' );
	const status = document.getElementById( 'hcaptcha-support-status' );
	const wordpressButton = modal.querySelector( '[data-hcaptcha-support-continue="wordpress"]' );
	const includeSystemInfoField = document.getElementById( 'hcaptcha-support-include-system-info' );
	let opener = null;

	const fieldIds = [
		'steps',
		'expected',
		'actual',
		'problem',
		'solution',
		'alternatives',
		'configure',
		'tried',
	];

	const getField = ( id ) => document.getElementById( `hcaptcha-support-${ id }` );

	const getValue = ( field ) => field ? field.value.trim() : '';

	const getType = () => {
		const checked = modal.querySelector( 'input[name="hcaptcha-support-type"]:checked' );

		return checked ? checked.value : 'bug';
	};

	const isVisible = ( element ) => {
		if ( element.hidden || element.closest( '[hidden]' ) ) {
			return false;
		}

		const style = window.getComputedStyle( element );

		return style.display !== 'none' && style.visibility !== 'hidden';
	};

	const getFocusable = () => Array.from(
		modal.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
		),
	).filter( isVisible );

	const setWordPressReady = ( ready ) => {
		if ( wordpressButton ) {
			wordpressButton.disabled = ! ready;
		}
	};

	const showStatus = ( message = '', type = '' ) => {
		if ( ! status ) {
			return;
		}

		status.textContent = message;
		status.className = [
			'hcaptcha-support-status',
			type ? `is-${ type }` : '',
		].filter( Boolean ).join( ' ' );
	};

	const makeSection = ( title, content ) => `## ${ title || '' }\n\n${ content || strings.emptyValue || '' }\n`;

	const makeOptionalSection = ( title, content ) => content ? makeSection( title, content ) : '';

	const joinSections = ( sections ) => sections.filter( Boolean ).join( '\n' ).trim();

	const makeDiagnostics = () => {
		if ( ! includeSystemInfoField || ! includeSystemInfoField.checked ) {
			return '';
		}

		return systemInfo;
	};

	const buildBugReport = () => joinSections( [
		makeSection( reportStrings.summary, getValue( summaryField ) ),
		makeSection( reportStrings.affectedArea, getValue( areaField ) ),
		makeSection( reportStrings.steps, getValue( getField( 'steps' ) ) ),
		makeSection( reportStrings.expected, getValue( getField( 'expected' ) ) ),
		makeSection( reportStrings.actual, getValue( getField( 'actual' ) ) ),
		makeSection( reportStrings.additional, getValue( detailsField ) ),
		makeOptionalSection( reportStrings.diagnostics, makeDiagnostics() ),
	] );

	const buildFeatureReport = () => joinSections( [
		makeSection( reportStrings.feature, getValue( summaryField ) ),
		makeSection( reportStrings.affectedArea, getValue( areaField ) ),
		makeSection( reportStrings.problem, getValue( getField( 'problem' ) ) ),
		makeSection( reportStrings.solution, getValue( getField( 'solution' ) ) ),
		makeSection( reportStrings.alternatives, getValue( getField( 'alternatives' ) ) ),
		makeSection( reportStrings.additional, getValue( detailsField ) ),
		makeOptionalSection( reportStrings.diagnostics, makeDiagnostics() ),
	] );

	const buildSupportReport = () => joinSections( [
		makeSection( reportStrings.question, getValue( summaryField ) ),
		makeSection( reportStrings.affectedArea, getValue( areaField ) ),
		makeSection( reportStrings.configure, getValue( getField( 'configure' ) ) ),
		makeSection( reportStrings.tried, getValue( getField( 'tried' ) ) ),
		makeSection( reportStrings.additional, getValue( detailsField ) ),
		makeOptionalSection( reportStrings.diagnostics, makeDiagnostics() ),
	] );

	const buildReport = () => {
		switch ( getType() ) {
			case 'feature':
				return buildFeatureReport();
			case 'support':
				return buildSupportReport();
			case 'bug':
			default:
				return buildBugReport();
		}
	};

	const updateTypeFields = () => {
		const type = getType();

		modal.querySelectorAll( '[data-hcaptcha-support-fields]' ).forEach( ( group ) => {
			group.hidden = group.dataset.hcaptchaSupportFields !== type;
		} );

		modal.querySelectorAll( '.hcaptcha-support-action' ).forEach( ( action ) => {
			const target = action.dataset.hcaptchaSupportAction;
			const recommended = action.querySelector( '.hcaptcha-support-recommended' );

			if ( ! recommended ) {
				return;
			}

			recommended.hidden = target === 'wordpress' ? type !== 'support' : type === 'support';
		} );
	};

	const closeActionTips = ( except = null ) => {
		modal.querySelectorAll( '.hcaptcha-support-action.is-description-open' ).forEach( ( action ) => {
			if ( action === except ) {
				return;
			}

			action.classList.remove( 'is-description-open' );

			const actionHelp = action.querySelector( '.hcaptcha-support-action-help' );

			if ( actionHelp ) {
				actionHelp.setAttribute( 'aria-expanded', 'false' );
			}
		} );
	};

	const toggleActionTip = ( button ) => {
		const action = button.closest( '.hcaptcha-support-action' );

		if ( ! action ) {
			return;
		}

		const isOpen = action.classList.contains( 'is-description-open' );

		closeActionTips( action );
		action.classList.toggle( 'is-description-open', ! isOpen );
		button.setAttribute( 'aria-expanded', String( ! isOpen ) );
	};

	const updateReport = () => {
		closeActionTips();
		updateTypeFields();
		setWordPressReady( false );
		showStatus();

		if ( reportField ) {
			reportField.value = buildReport();
		}
	};

	const validateSummary = () => {
		if ( getValue( summaryField ) ) {
			summaryField.removeAttribute( 'aria-invalid' );

			return true;
		}

		summaryField.setAttribute( 'aria-invalid', 'true' );
		showStatus( strings.summaryRequired || '', 'error' );
		summaryField.focus();

		return false;
	};

	const copyText = async ( text ) => {
		if ( ! window.navigator.clipboard || ! window.navigator.clipboard.writeText ) {
			throw new Error( 'clipboard' );
		}

		return window.navigator.clipboard.writeText( text );
	};

	const copyReport = async () => {
		updateReport();

		try {
			await copyText( reportField.value );
			setWordPressReady( true );
			showStatus( strings.copySuccess || '', 'success' );

			return true;
		} catch ( error ) {
			void error;
			setWordPressReady( true );
			showStatus( strings.copyError || '', 'error' );
			reportField.focus();
			reportField.select();

			return false;
		}
	};

	const openExternal = ( url ) => {
		const external = window.open( url, '_blank' );

		if ( ! external ) {
			showStatus( strings.openFailed || '', 'error' );

			return;
		}

		external.opener = null;
	};

	const continueGithub = () => {
		if ( ! validateSummary() ) {
			return;
		}

		updateReport();

		const url = new URL( config.githubIssueUrl );

		url.searchParams.set( 'title', getValue( summaryField ) );
		url.searchParams.set( 'body', reportField.value );
		openExternal( url.toString() );
	};

	const continueWordPress = () => {
		if ( ! validateSummary() ) {
			return;
		}

		openExternal( config.wordpressSupportUrl );
	};

	const openModal = () => {
		closeActionTips();
		opener = modal.ownerDocument.activeElement;
		modal.hidden = false;
		document.body.classList.add( 'hcaptcha-support-modal-open' );
		showStatus();
		updateReport();
		summaryField.focus();
	};

	const closeModal = () => {
		closeActionTips();
		modal.hidden = true;
		document.body.classList.remove( 'hcaptcha-support-modal-open' );

		if ( opener && opener.focus ) {
			opener.focus();
		}
	};

	const trapFocus = ( event ) => {
		if ( event.key !== 'Tab' ) {
			return;
		}

		const focusable = getFocusable();

		if ( ! focusable.length ) {
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];

		if ( event.shiftKey && modal.ownerDocument.activeElement === first ) {
			event.preventDefault();
			last.focus();
		} else if ( ! event.shiftKey && modal.ownerDocument.activeElement === last ) {
			event.preventDefault();
			first.focus();
		}
	};

	openButton.addEventListener( 'click', openModal );

	modal.addEventListener( 'click', ( event ) => {
		if ( ! event.target.closest ) {
			return;
		}

		if ( event.target.closest( '[data-hcaptcha-support-close]' ) ) {
			closeModal();

			return;
		}

		const actionHelp = event.target.closest( '.hcaptcha-support-action-help' );

		if ( actionHelp ) {
			event.preventDefault();
			toggleActionTip( actionHelp );

			return;
		}

		if ( ! event.target.closest( '.hcaptcha-support-action-description' ) ) {
			closeActionTips();
		}

		if ( event.target.closest( '[data-hcaptcha-support-copy]' ) ) {
			copyReport();
		}

		const continueButton = event.target.closest( '[data-hcaptcha-support-continue]' );

		if ( ! continueButton ) {
			return;
		}

		if ( continueButton.dataset.hcaptchaSupportContinue === 'github' ) {
			continueGithub();
		} else {
			continueWordPress();
		}
	} );

	modal.addEventListener( 'input', updateReport );
	modal.addEventListener( 'change', updateReport );

	document.addEventListener( 'keydown', ( event ) => {
		if ( modal.hidden ) {
			return;
		}

		if ( event.key === 'Escape' ) {
			closeModal();

			return;
		}

		trapFocus( event );
	} );

	fieldIds.forEach( ( id ) => {
		const field = getField( id );

		if ( field ) {
			field.addEventListener( 'input', updateReport );
		}
	} );

	updateReport();
};

window.hCaptchaSupportModal = supportModal;

document.addEventListener( 'DOMContentLoaded', supportModal );
