/**
 * Cartiva Commerce: landing page countdown.
 * Ticks every timer on the page from its deadline (worked out on the server).
 * At zero: "hide" hides the timer; the other endings reload the page once so
 * the server shows the new state (full prices, closed offer, a new period).
 */
( function () {
	'use strict';

	var timers = Array.prototype.slice.call( document.querySelectorAll( '.cartiva-countdown[data-deadline]' ) );
	if ( ! timers.length ) {
		return;
	}

	function pad( n ) {
		return n < 10 ? '0' + n : String( n );
	}

	function tick() {
		var now = Date.now();
		var reload = false;
		timers.forEach( function ( el ) {
			var left = Math.max( 0, Math.floor( ( parseInt( el.getAttribute( 'data-deadline' ), 10 ) - now ) / 1000 ) );
			var values = { d: Math.floor( left / 86400 ), h: Math.floor( ( left % 86400 ) / 3600 ), m: Math.floor( ( left % 3600 ) / 60 ), s: left % 60 };
			Object.keys( values ).forEach( function ( unit ) {
				var num = el.querySelector( '[data-unit="' + unit + '"]' );
				if ( num ) {
					num.textContent = pad( values[ unit ] );
				}
			} );
			if ( 0 === left ) {
				if ( 'hide' === el.getAttribute( 'data-ended' ) ) {
					el.hidden = true;
				} else {
					reload = el.getAttribute( 'data-deadline' );
				}
			}
		} );
		if ( reload ) {
			var key = 'cartiva-cd-' + reload;
			try {
				if ( ! window.sessionStorage.getItem( key ) ) {
					window.sessionStorage.setItem( key, '1' );
					window.location.reload();
					return;
				}
			} catch ( e ) {
				// Storage blocked: stop here rather than reload in a loop.
			}
			return;
		}
		window.setTimeout( tick, 1000 - ( Date.now() % 1000 ) );
	}

	tick();
}() );
