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

	// The Checkout block names its fields by id: email, billing-phone, shipping-first_name ...
	var blockParts = [ 'first_name', 'last_name', 'phone', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ];
	var blockForm = '.wc-block-checkout';

	function collectBlock( form ) {
		var data = {};
		var email = form.find( '#email' ).val();
		if ( email ) {
			data.billing_email = String( email ).slice( 0, 200 );
		}
		blockParts.forEach( function ( part ) {
			[ 'billing', 'shipping' ].forEach( function ( group ) {
				var value = form.find( '#' + group + '-' + part ).val();
				if ( value && ! data[ 'billing_' + part ] ) {
					data[ 'billing_' + part ] = String( value ).slice( 0, 200 );
				}
			} );
		} );
		return data;
	}

	function collect( form ) {
		if ( form.is( blockForm ) ) {
			return collectBlock( form );
		}
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

	$( document ).on( 'change input', 'form.checkout input, form.checkout select, form.checkout textarea, ' + blockForm + ' input, ' + blockForm + ' select', function () {
		var form = $( this ).closest( 'form.checkout, ' + blockForm );
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			send( form );
		}, 2500 );
	} );

	// Save right away when the shopper leaves the page.
	$( window ).on( 'pagehide', function () {
		var form = $( 'form.checkout, ' + blockForm ).first();
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
