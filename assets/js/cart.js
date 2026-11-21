/**
 * Cartiva Commerce: slide-out cart.
 * Opens after add to cart (classic AJAX buttons, product blocks and normal
 * form submits), changes quantities and adds suggested products, using
 * WooCommerce cart fragments to refresh the contents.
 */
( function ( $ ) {
	'use strict';

	var settings = window.cartivaCart || {};
	var drawer = document.getElementById( 'cartiva-side-cart' );
	var lastFocus = null;

	if ( ! drawer ) {
		return;
	}

	function panel() {
		return drawer.querySelector( '.cartiva-sc__panel' );
	}

	function focusable() {
		return Array.prototype.filter.call(
			panel().querySelectorAll( 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])' ),
			function ( el ) {
				return el.offsetParent !== null;
			}
		);
	}

	function open() {
		if ( ! drawer.hidden ) {
			return;
		}
		lastFocus = document.activeElement;
		drawer.hidden = false;
		document.documentElement.classList.add( 'cartiva-sc-open' );
		window.requestAnimationFrame( function () {
			drawer.classList.add( 'is-open' );
			panel().focus();
		} );
	}

	function close() {
		if ( drawer.hidden ) {
			return;
		}
		drawer.classList.remove( 'is-open' );
		document.documentElement.classList.remove( 'cartiva-sc-open' );
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		window.setTimeout( function () {
			drawer.hidden = true;
		}, reduce ? 0 : 250 );
		if ( lastFocus && lastFocus.focus ) {
			lastFocus.focus();
		}
	}

	function apply( fragments ) {
		if ( ! fragments ) {
			return;
		}
		$.each( fragments, function ( selector, html ) {
			$( selector ).replaceWith( html );
		} );
		$( document.body ).trigger( 'wc_fragments_refreshed' );
	}

	function refresh() {
		return $.post( settings.refresh ).done( function ( res ) {
			if ( res && res.fragments ) {
				apply( res.fragments );
			}
		} );
	}

	function busy( on ) {
		$( drawer ).find( '.cartiva-sc__body' ).toggleClass( 'is-loading', on );
	}

	/* Open and close */

	$( document ).on( 'click', '[data-cartiva-close]', close );
	$( document ).on( 'click', '.cartiva-sc-toggle', open );

	document.addEventListener( 'keydown', function ( event ) {
		if ( drawer.hidden ) {
			return;
		}
		if ( 'Escape' === event.key ) {
			close();
		} else if ( 'Tab' === event.key ) {
			var items = focusable();
			if ( ! items.length ) {
				event.preventDefault();
				return;
			}
			var first = items[ 0 ];
			var last = items[ items.length - 1 ];
			if ( event.shiftKey && ( document.activeElement === first || document.activeElement === panel() ) ) {
				event.preventDefault();
				last.focus();
			} else if ( ! event.shiftKey && document.activeElement === last ) {
				event.preventDefault();
				first.focus();
			}
		}
	} );

	// Optional: links to the cart page open the drawer instead.
	if ( settings.links ) {
		document.addEventListener( 'click', function ( event ) {
			var link = event.target.closest ? event.target.closest( 'a[href], .wc-block-mini-cart__button' ) : null;
			if ( ! link || link.closest( '#cartiva-side-cart' ) ) {
				return;
			}
			var isCart = link.classList.contains( 'wc-block-mini-cart__button' ) || ( link.href && link.href.split( '#' )[ 0 ].replace( /\/$/, '' ) === String( settings.cartUrl ).replace( /\/$/, '' ) );
			if ( isCart ) {
				event.preventDefault();
				event.stopPropagation();
				open();
			}
		}, true );
	}

	/* Open after add to cart */

	$( document.body ).on( 'added_to_cart', function () {
		if ( settings.openOnAdd ) {
			open();
		}
	} );

	// Product blocks add through the Store API: fetch the new contents, then open.
	document.body.addEventListener( 'wc-blocks_added_to_cart', function () {
		refresh().done( function () {
			if ( settings.openOnAdd ) {
				open();
			}
		} );
	} );

	if ( settings.openNow ) {
		$( open );
	}

	/* Quantity and remove */

	function setQty( item, qty ) {
		busy( true );
		$.post( settings.qty, { nonce: settings.nonce, key: item.data( 'key' ), qty: qty } )
			.done( function ( res ) {
				if ( res && res.fragments ) {
					apply( res.fragments );
					$( document.body ).trigger( 'cartiva_cart_updated' );
				} else {
					window.alert( settings.error ); // eslint-disable-line no-alert
				}
			} )
			.fail( function () {
				window.alert( settings.error ); // eslint-disable-line no-alert
			} )
			.always( function () {
				busy( false );
				panel().focus();
			} );
	}

	$( document ).on( 'click', '.cartiva-sc__step', function () {
		var item = $( this ).closest( '.cartiva-sc__item' );
		var now = parseInt( item.find( '.cartiva-sc__num' ).text(), 10 ) || 1;
		var max = parseInt( $( this ).data( 'max' ), 10 ) || 999;
		var next = Math.min( max, now + parseInt( $( this ).data( 'step' ), 10 ) );
		setQty( item, Math.max( 0, next ) );
	} );

	$( document ).on( 'click', '.cartiva-sc__remove', function () {
		setQty( $( this ).closest( '.cartiva-sc__item' ), 0 );
	} );

	/* Suggested products */

	$( document ).on( 'click', 'button.cartiva-sc__add', function () {
		var button = $( this );
		busy( true );
		$.post( settings.add, { product_id: button.data( 'product' ), quantity: 1 } )
			.done( function ( res ) {
				if ( res && res.error && res.product_url ) {
					window.location = res.product_url;
				} else if ( res && res.fragments ) {
					apply( res.fragments );
				}
			} )
			.always( function () {
				busy( false );
			} );
	} );
}( jQuery ) );
