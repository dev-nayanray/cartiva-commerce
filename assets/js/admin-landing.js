/**
 * Cartiva Commerce admin: add, remove and reorder rows (product options,
 * order bumps, funnel steps).
 */
( function ( $ ) {
	'use strict';

	var counter = 0;

	function renumber( repeater ) {
		repeater.find( 'tbody > tr' ).each( function ( i ) {
			$( this ).find( '.cartiva-step-number' ).text( i + 1 );
		} );
	}

	$( document ).on( 'click', '.cartiva-add-row', function () {
		var repeater = $( this ).closest( '.cartiva-repeater' );
		var template = repeater.children( 'template' ).get( 0 );
		if ( ! template ) {
			return;
		}
		counter += 1;
		var html = template.innerHTML.replace( /__i__/g, 'n' + Date.now() + counter );
		var row = $( $.parseHTML( $.trim( html ) ) );
		repeater.find( 'tbody' ).append( row );
		$( document.body ).trigger( 'wc-enhanced-select-init' );
		renumber( repeater );
		row.find( 'select, input' ).first().trigger( 'focus' );
	} );

	$( document ).on( 'click', '.cartiva-remove-row', function () {
		var repeater = $( this ).closest( '.cartiva-repeater' );
		$( this ).closest( 'tr' ).remove();
		renumber( repeater );
	} );

	$( document ).on( 'click', '.cartiva-move-row', function () {
		var row = $( this ).closest( 'tr' );
		var repeater = row.closest( '.cartiva-repeater' );
		if ( '-1' === String( $( this ).data( 'dir' ) ) ) {
			row.prev().before( row );
		} else {
			row.next().after( row );
		}
		renumber( repeater );
		$( this ).trigger( 'focus' );
	} );

	$( function () {
		$( '.cartiva-repeater' ).each( function () {
			renumber( $( this ) );
		} );
	} );
}( jQuery ) );
