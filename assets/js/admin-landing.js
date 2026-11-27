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

	/* Landing page box: tabs */

	function showTab( box, key, focus ) {
		var tabs = box.find( '.cartiva-tabs__tab' );
		if ( ! tabs.filter( '[data-tab="' + key + '"]' ).length ) {
			key = tabs.first().data( 'tab' );
		}
		tabs.each( function () {
			var on = $( this ).data( 'tab' ) === key;
			$( this ).attr( { 'aria-selected': on ? 'true' : 'false', tabindex: on ? 0 : -1 } ).toggleClass( 'is-active', on );
			if ( on && focus ) {
				this.focus();
			}
		} );
		box.find( '.cartiva-tabs__panel' ).each( function () {
			this.hidden = $( this ).data( 'tab' ) !== key;
		} );
		try {
			window.sessionStorage.setItem( 'cartiva-landing-tab', key );
		} catch ( e ) {}
	}

	$( document ).on( 'click', '[data-cartiva-go-tab]', function () {
		showTab( $( this ).closest( '.cartiva-landing-box' ), $( this ).data( 'cartiva-go-tab' ), true );
	} );

	$( document ).on( 'click', '.cartiva-tabs__tab', function () {
		showTab( $( this ).closest( '.cartiva-landing-box' ), $( this ).data( 'tab' ), false );
	} );

	$( document ).on( 'keydown', '.cartiva-tabs__tab', function ( event ) {
		var tabs = $( this ).closest( '.cartiva-tabs' ).find( '.cartiva-tabs__tab' );
		var i = tabs.index( this );
		var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[ event.key ];
		if ( undefined === next ) {
			return;
		}
		event.preventDefault();
		next = ( next + tabs.length ) % tabs.length;
		showTab( $( this ).closest( '.cartiva-landing-box' ), tabs.eq( next ).data( 'tab' ), true );
	} );

	/* Products: show what fits "One product" or "Multiple products" */

	function syncMode( box ) {
		var mode = box.find( 'input[name="cartiva_landing[offer_mode]"]:checked' ).val() || 'single';
		box.find( '.cartiva-mode-single-only' ).toggle( 'single' === mode );
		box.find( '.cartiva-mode-card' ).each( function () {
			$( this ).toggleClass( 'is-selected', $( this ).find( 'input' ).is( ':checked' ) );
		} );
	}

	/* Countdown: show the fields of the chosen timer type, length presets */

	function syncCountdown( box ) {
		var fixed = 'fixed' === box.find( 'input[name="cartiva_landing[countdown][mode]"]:checked' ).val();
		box.find( '.cartiva-cd-evergreen' ).toggle( ! fixed );
		box.find( '.cartiva-cd-fixed' ).toggle( fixed );
		var restart = box.find( 'input[name="cartiva_landing[countdown][ended]"][value="restart"]' );
		restart.prop( 'disabled', fixed ).closest( 'label' ).css( 'opacity', fixed ? 0.5 : 1 );
		if ( fixed && restart.is( ':checked' ) ) {
			box.find( 'input[name="cartiva_landing[countdown][ended]"][value="hide"]' ).prop( 'checked', true );
		}
		var days = parseInt( box.find( '.cartiva-cd-days' ).val(), 10 );
		box.find( '.cartiva-cd-preset' ).each( function () {
			$( this ).toggleClass( 'button-primary', parseInt( $( this ).data( 'days' ), 10 ) === days );
		} );
	}

	$( document ).on( 'change', '.cartiva-landing-box input', function () {
		var box = $( this ).closest( '.cartiva-landing-box' );
		syncMode( box );
		syncCountdown( box );
	} );

	$( document ).on( 'click', '.cartiva-cd-preset', function () {
		var box = $( this ).closest( '.cartiva-landing-box' );
		box.find( '.cartiva-cd-days' ).val( $( this ).data( 'days' ) );
		box.find( 'input[name="cartiva_landing[countdown][hours]"]' ).val( 0 );
		syncCountdown( box );
	} );

	$( function () {
		$( '.cartiva-repeater' ).each( function () {
			renumber( $( this ) );
		} );
		$( '.cartiva-landing-box' ).each( function () {
			var box = $( this );
			var saved = '';
			try {
				saved = window.sessionStorage.getItem( 'cartiva-landing-tab' ) || '';
			} catch ( e ) {}
			// A missing product is the first thing to fix.
			showTab( box, box.find( '#cartiva-panel-products .notice-warning' ).length ? 'products' : saved, false );
			syncMode( box );
			syncCountdown( box );
		} );
	} );
}( jQuery ) );
