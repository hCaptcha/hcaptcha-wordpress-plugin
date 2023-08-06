/* global jQuery, HCaptchaNotificationsObject */

/**
 * @param HCaptchaNotificationsObject.ajaxUrl
 * @param HCaptchaNotificationsObject.dismissNotificationAction
 * @param HCaptchaNotificationsObject.dismissNotificationNonce
 * @param HCaptchaNotificationsObject.resetNotificationAction
 * @param HCaptchaNotificationsObject.resetNotificationNonce
 */

/**
 * Notifications logic.
 *
 * @param {Object} $ jQuery instance.
 */
const notifications = ( $ ) => {
	const optionsSelector = 'form#hcaptcha-options';
	const notificationsSelector = 'div#hcaptcha-notifications';
	const notificationSelector = 'div.hcaptcha-notification';
	const dismissSelector = notificationsSelector + ' button.notice-dismiss';
	const navPrevSelector = '#hcaptcha-navigation .prev';
	const navNextSelector = '#hcaptcha-navigation .next';
	const navSelectors = navPrevSelector + ', ' + navNextSelector;
	const buttonsSelector = '.hcaptcha-notification-buttons';
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
			$( navSelectors ).removeClass( 'disabled' );
		} else {
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

		$notification.remove();
		$( notificationSelector ).show();

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

	$( navSelectors ).on( 'click', function( event ) {
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

	$( 'button#reset_notifications' ).on( 'click', function() {
		const data = {
			action: HCaptchaNotificationsObject.resetNotificationAction,
			nonce: HCaptchaNotificationsObject.resetNotificationNonce,
		};

		// noinspection JSVoidFunctionReturnValueUsed,JSCheckFunctionSignatures
		$.post( {
			url: HCaptchaNotificationsObject.ajaxUrl,
			data,
		} ).success( function() {
			// We can prepare notifications for display on backend only.
			location.reload();
		} );
	} );

	setButtons();
};

jQuery( document ).ready( notifications );
