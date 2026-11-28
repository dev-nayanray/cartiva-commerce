/**
 * Cartiva Commerce: the SMS code box on order forms.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.cartivaOtp || {};

	function box() {
		return $( '.cartiva-otp' ).first();
	}

	function show() {
		var b = box();
		if ( ! b.length ) {
			return;
		}
		b.prop( 'hidden', false );
		window.setTimeout( function () {
			b.find( 'input' ).trigger( 'focus' );
		}, 50 );
	}

	$( document.body ).on( 'checkout_error', function () {
		if ( $( '[data-id="cartiva_otp"]' ).length ) {
			box().find( 'input' ).val( '' );
			show();
		}
	} );

	// A new phone number needs a new code.
	$( document ).on( 'change', 'input[name="billing_phone"]', function () {
		box().prop( 'hidden', true ).find( 'input' ).val( '' );
	} );

	$( document ).on( 'click', '.cartiva-otp__resend', function () {
		var button = $( this );
		var msg = box().find( '.cartiva-otp__msg' );
		button.prop( 'disabled', true );
		$.post( cfg.url, { nonce: cfg.nonce, phone: $( 'input[name="billing_phone"]' ).val() || '' } )
			.always( function ( res ) {
				var data = res && res.responseJSON ? res.responseJSON : res;
				msg.text( data && data.data && data.data.message ? data.data.message : '' );
				window.setTimeout( function () {
					button.prop( 'disabled', false );
				}, 30000 );
			} );
	} );
}( jQuery ) );
