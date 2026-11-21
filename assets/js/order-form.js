/**
 * Cartiva Commerce: landing order form quantity changer.
 * Updates the cart, then asks WooCommerce to refresh the order totals.
 */
( function ( $ ) {
	'use strict';

	var settings = window.cartivaOrderForm || {};
	var timer = null;

	function send( box, qty ) {
		var input = box.find( '.cartiva-order-form__qty-input' );
		box.addClass( 'is-loading' );
		$.post( settings.url, {
			nonce: settings.nonce,
			landing: box.data( 'landing' ),
			qty: qty,
		} )
			.done( function ( res ) {
				if ( res && res.success ) {
					input.val( res.data.qty );
					$( document.body ).trigger( 'update_checkout' );
				} else {
					window.alert( settings.error ); // eslint-disable-line no-alert
				}
			} )
			.fail( function () {
				window.alert( settings.error ); // eslint-disable-line no-alert
			} )
			.always( function () {
				box.removeClass( 'is-loading' );
			} );
	}

	function clamp( box, value ) {
		var max = parseInt( box.data( 'max' ), 10 ) || 99;
		return Math.max( 1, Math.min( max, parseInt( value, 10 ) || 1 ) );
	}

	$( document ).on( 'click', '.cartiva-order-form__qty-btn', function () {
		var box = $( this ).closest( '.cartiva-order-form__qty' );
		var input = box.find( '.cartiva-order-form__qty-input' );
		var qty = clamp( box, ( parseInt( input.val(), 10 ) || 1 ) + parseInt( $( this ).data( 'step' ), 10 ) );
		input.val( qty );
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			send( box, qty );
		}, 350 );
	} );

	$( document ).on( 'change', '.cartiva-order-form__qty-input', function () {
		var box = $( this ).closest( '.cartiva-order-form__qty' );
		var qty = clamp( box, $( this ).val() );
		$( this ).val( qty );
		send( box, qty );
	} );

	// "Order now" links on the page scroll smoothly to the form.
	$( document ).on( 'click', 'a[href="#order"]', function ( event ) {
		var target = document.getElementById( 'order' );
		if ( ! target ) {
			return;
		}
		event.preventDefault();
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		target.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
		var field = target.querySelector( 'input:not([type="hidden"]), select, textarea' );
		if ( field ) {
			window.setTimeout( function () {
				field.focus( { preventScroll: true } );
			}, reduce ? 0 : 500 );
		}
	} );
}( jQuery ) );
