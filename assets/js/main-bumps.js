/**
 * Cartiva Commerce: order bumps on the main checkout.
 */
( function ( $ ) {
	'use strict';

	var cfg = window.cartivaMainBumps || {};

	$( document.body ).on( 'change', '.cartiva-main-bump__check', function () {
		var check = $( this );
		var bump = check.closest( '.cartiva-bump' );
		bump.addClass( 'is-loading' ).toggleClass( 'is-added', this.checked );
		$.post( cfg.url, { nonce: cfg.nonce, index: check.data( 'index' ), on: this.checked ? 1 : 0 } )
			.fail( function () {
				check.prop( 'checked', ! check.prop( 'checked' ) );
				bump.toggleClass( 'is-added', check.prop( 'checked' ) );
			} )
			.always( function () {
				bump.removeClass( 'is-loading' );
				$( document.body ).trigger( 'update_checkout' );
			} );
	} );
}( jQuery ) );
