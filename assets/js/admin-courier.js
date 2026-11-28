/**
 * Cartiva Commerce: courier buttons on the order screen and the settings.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.cartivaCourier || {};

	function message( box, text, ok ) {
		box.find( '.cartiva-courier-msg' ).first()
			.text( text || '' )
			.toggleClass( 'is-error', ok === false )
			.toggleClass( 'is-ok', ok === true );
	}

	function post( data ) {
		data.nonce = cfg.nonce;
		return $.post( cfg.ajax, data );
	}

	function toggleOnly( box ) {
		var provider = box.find( '.cartiva-courier-provider' ).val();
		box.find( '.cartiva-courier-only' ).each( function () {
			$( this ).toggle( $( this ).data( 'provider' ) === provider );
		} );
	}

	$( function () {
		$( '.cartiva-courier-box' ).each( function () {
			toggleOnly( $( this ) );
		} );
	} );

	$( document ).on( 'change', '.cartiva-courier-provider', function () {
		toggleOnly( $( this ).closest( '.cartiva-courier-box' ) );
	} );

	$( document ).on( 'click', '[data-cartiva-courier]', function ( event ) {
		event.preventDefault();
		var button = $( this );
		var box = button.closest( '.cartiva-courier-box' );
		var what = button.data( 'cartiva-courier' );
		var area = box.find( '.cartiva-courier-area option:selected' );
		var data = { action: 'cartiva_courier_' + what, order: box.data( 'order' ) };
		if ( what === 'send' ) {
			data.provider = box.find( '.cartiva-courier-provider' ).val();
			data.cod = box.find( '.cartiva-courier-cod' ).val();
			data.weight = box.find( '.cartiva-courier-weight' ).val();
			data.note = box.find( '.cartiva-courier-note' ).val();
			data.area_id = area.val() || '';
			data.area_name = area.data( 'name' ) || '';
		}
		button.prop( 'disabled', true );
		message( box, cfg.working );
		post( data )
			.done( function ( res ) {
				var msg = res && res.data && res.data.message ? res.data.message : cfg.failed;
				if ( what === 'history' && res && res.success ) {
					box.find( '.cartiva-history' ).html( $( '<p/>', {
						'class': 'cartiva-history__verdict cartiva-history__verdict--' + res.data.tone,
						text: msg
					} ) );
					message( box, '' );
					return;
				}
				message( box, msg, !! ( res && res.success ) );
				if ( res && res.success && what !== 'history' ) {
					window.setTimeout( function () { window.location.reload(); }, 900 );
				}
			} )
			.fail( function ( xhr ) {
				var res = xhr && xhr.responseJSON;
				message( box, res && res.data && res.data.message ? res.data.message : cfg.failed, false );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	} );

	/* Settings: test connection */
	$( document ).on( 'click', '[data-cartiva-courier-test]', function ( event ) {
		event.preventDefault();
		var button = $( this );
		var out = button.siblings( '.cartiva-courier-test-result' );
		button.prop( 'disabled', true );
		out.text( cfg.working ).removeClass( 'is-ok is-error' );
		post( { action: 'cartiva_courier_test', provider: button.data( 'cartiva-courier-test' ) } )
			.done( function ( res ) {
				out.text( res && res.data && res.data.message ? res.data.message : cfg.failed )
					.addClass( res && res.success ? 'is-ok' : 'is-error' );
			} )
			.fail( function ( xhr ) {
				var res = xhr && xhr.responseJSON;
				out.text( res && res.data && res.data.message ? res.data.message : cfg.failed ).addClass( 'is-error' );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	} );

	/* Settings: test SMS */
	$( document ).on( 'click', '[data-cartiva-sms-test]', function ( event ) {
		event.preventDefault();
		var button = $( this );
		var out = button.siblings( '.cartiva-courier-test-result' );
		button.prop( 'disabled', true );
		out.text( cfg.working ).removeClass( 'is-ok is-error' );
		post( { action: 'cartiva_sms_test', phone: $( '#cartiva-sms-test' ).val() || '' } )
			.done( function ( res ) {
				out.text( res && res.data && res.data.message ? res.data.message : cfg.failed )
					.addClass( res && res.success ? 'is-ok' : 'is-error' );
			} )
			.fail( function ( xhr ) {
				var res = xhr && xhr.responseJSON;
				out.text( res && res.data && res.data.message ? res.data.message : cfg.failed ).addClass( 'is-error' );
			} )
			.always( function () {
				button.prop( 'disabled', false );
			} );
	} );
}( jQuery ) );
