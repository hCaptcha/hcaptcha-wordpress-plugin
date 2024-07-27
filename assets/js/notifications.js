/* global jQuery, HCaptchaNotificationsObject */

/**
 * @param HCaptchaNotificationsObject.ajaxUrl
 * @param HCaptchaNotificationsObject.dismissNotificationAction
 * @param HCaptchaNotificationsObject.dismissNotificationNonce
 * @param HCaptchaNotificationsObject.resetNotificationAction
 * @param HCaptchaNotificationsObject.resetNotificationNonce
 */

/**
 * Notification logic.
 *
 * @param {Object} $ jQuery instance.
 */
const notifications = ( $ ) => {
	const optionsSelector = 'form#hcaptcha-options';
	const sectionKeysSelector = 'h3.hcaptcha-section-keys';
	const notificationsSelector = 'div#hcaptcha-notifications';
	const notificationSelector = 'div.hcaptcha-notification';
	const dismissSelector = notificationsSelector + ' button.notice-dismiss';
	const navSpanSelector = '#hcaptcha-navigation span';
	const navPageSelector = '#hcaptcha-navigation-page';
	const navPagesSelector = '#hcaptcha-navigation-pages';
	const navPrevSelector = '#hcaptcha-navigation .prev';
	const navNextSelector = '#hcaptcha-navigation .next';
	const navSelectors = navPrevSelector + ', ' + navNextSelector;
	const buttonsSelector = '.hcaptcha-notification-buttons';
	const resetBtnSelector = 'button#reset_notifications';
	const footerSelector = '#hcaptcha-notifications-footer';
	let $notifications;

	const getVisibleNotificationIndex = function() {
		$notifications = $( notificationSelector );

		if ( ! $notifications.length ) {
			return false;
		}

		let index = 0;

		$notifications.each( function( i ) {
			if ( $( this ).is( ':visible' ) ) {
				index = i;
				return false;
			}
		} );

		return index;
	};

	const setNavStatus = function() {
		const index = getVisibleNotificationIndex();

		if ( index >= 0 ) {
			$( navPageSelector ).text( index + 1 );
			$( navPagesSelector ).text( $notifications.length );
			$( navSpanSelector ).show();
			$( navSelectors ).removeClass( 'disabled' );
		} else {
			$( navSpanSelector ).hide();
			$( navSelectors ).addClass( 'disabled' );
			return;
		}

		if ( index === 0 ) {
			$( navPrevSelector ).addClass( 'disabled' );
		}

		if ( index === $notifications.length - 1 ) {
			$( navNextSelector ).addClass( 'disabled' );
		}
	};

	const setButtons = function() {
		const index = getVisibleNotificationIndex();

		$( footerSelector ).find( buttonsSelector ).remove();

		if ( index < 0 ) {
			return;
		}

		$( $notifications[ index ] ).find( buttonsSelector ).clone().removeClass( 'hidden' ).prependTo( footerSelector );
	};

	$( optionsSelector ).on( 'click', dismissSelector, function( event ) {
		const $notification = $( event.target ).closest( notificationSelector );

		const data = {
			action: HCaptchaNotificationsObject.dismissNotificationAction,
			nonce: HCaptchaNotificationsObject.dismissNotificationNonce,
			id: $notification.data( 'id' ),
		};

		let next = $( notificationSelector ).index( $notification ) + 1;
		next = next < $( notificationSelector ).length ? next : 0;
		const $next = $( notificationSelector ).eq( next );

		$notification.remove();
		$next.show();

		setNavStatus();
		setButtons();

		if ( $( notificationSelector ).length === 0 ) {
			$( notificationsSelector ).remove();
		}

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		$.post( {
			url: HCaptchaNotificationsObject.ajaxUrl,
			data,
		} );

		return false;
	} );

	$( optionsSelector ).on( 'click', navSelectors, function( event ) {
		let direction = 1;

		if ( $( event.target ).hasClass( 'prev' ) ) {
			direction = -1;
		}

		const index = getVisibleNotificationIndex();

		const newIndex = index + direction;

		if ( index >= 0 && newIndex !== index && newIndex >= 0 && newIndex < $notifications.length ) {
			$( $notifications[ index ] ).hide();
			$( $notifications[ newIndex ] ).show();
			setNavStatus();
			setButtons();
		}
	} );

	$( resetBtnSelector ).on( 'click', function() {
		const data = {
			action: HCaptchaNotificationsObject.resetNotificationAction,
			nonce: HCaptchaNotificationsObject.resetNotificationNonce,
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		$.post( {
			url: HCaptchaNotificationsObject.ajaxUrl,
			data,
		} ).success( function( response ) {
			if ( ! response.success ) {
				return;
			}

			$( notificationsSelector ).remove();
			$( response.data ).insertBefore( sectionKeysSelector );

			setButtons();
			$( document ).trigger( 'wp-updates-notice-added' );
		} );
	} );

	setButtons();
};

jQuery( document ).ready( notifications );
