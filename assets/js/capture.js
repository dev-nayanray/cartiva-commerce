/**
 * Cartiva Commerce: save the order form details as they are typed, so an
 * unfinished order can be followed up. Sends only once a phone number or
 * email is entered, and at most every few seconds.
 */
( function ( $ ) {
	'use strict';

	var settings = window.cartivaCapture || {};
	var fields = [ 'billing_full_name', 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' ];
	var timer = null;
	var last = '';

	function collect( form ) {
		var data = {};
		fields.forEach( function ( name ) {
			var field = form.find( '[name="' + name + '"]' );
			if ( field.length && field.val() ) {
				data[ name ] = String( field.val() ).slice( 0, 200 );
			}
		} );
		return data;
	}

	function contactable( data ) {
		var phone = ( data.billing_phone || '' ).replace( /\D/g, '' );
		return phone.length >= 10 || /\S+@\S+\.\S+/.test( data.billing_email || '' );
	}

	function send( form ) {
		var data = collect( form );
		var key = JSON.stringify( data );
		if ( ! contactable( data ) || key === last ) {
			return;
		}
		last = key;
		$.post( settings.url, {
			nonce: settings.nonce,
			landing: form.find( '[name="_cartiva_landing_id"]' ).val() || 0,
			fields: data,
		} );
	}

	$( document ).on( 'change input', 'form.checkout input, form.checkout select, form.checkout textarea', function () {
		var form = $( this ).closest( 'form.checkout' );
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			send( form );
		}, 2500 );
	} );

	// Save right away when the shopper leaves the page.
	$( window ).on( 'pagehide', function () {
		var form = $( 'form.checkout' ).first();
		if ( ! form.length || ! navigator.sendBeacon ) {
			return;
		}
		var data = collect( form );
		var key = JSON.stringify( data );
		if ( ! contactable( data ) || key === last ) {
			return;
		}
		var body = new FormData();
		body.append( 'nonce', settings.nonce );
		body.append( 'landing', form.find( '[name="_cartiva_landing_id"]' ).val() || 0 );
		Object.keys( data ).forEach( function ( name ) {
			body.append( 'fields[' + name + ']', data[ name ] );
		} );
		navigator.sendBeacon( settings.url, body );
	} );
}( jQuery ) );
