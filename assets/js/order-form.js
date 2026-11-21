/**
 * Cartiva Commerce: landing order form.
 * Quantity changer, product options, order bumps, coupon field, the two-step
 * layout and "Order now" links. Every change updates the cart on the server,
 * then asks WooCommerce to refresh the order totals.
 */
( function ( $ ) {
	'use strict';

	var settings = window.cartivaOrderForm || {};
	var timer = null;

	function fail() {
		window.alert( settings.error ); // eslint-disable-line no-alert
	}

	function post( url, data, box ) {
		data.nonce = settings.nonce;
		if ( box ) {
			box.addClass( 'is-loading' );
		}
		return $.post( url, data )
			.done( function ( res ) {
				if ( res && res.success ) {
					$( document.body ).trigger( 'update_checkout' );
				} else {
					fail();
				}
			} )
			.fail( fail )
			.always( function () {
				if ( box ) {
					box.removeClass( 'is-loading' );
				}
			} );
	}

	/* Quantity changer (one-product pages) */

	function clamp( box, value ) {
		var max = parseInt( box.data( 'max' ), 10 ) || 99;
		return Math.max( 1, Math.min( max, parseInt( value, 10 ) || 1 ) );
	}

	function sendQty( box, qty ) {
		post( settings.url, { landing: box.data( 'landing' ), qty: qty }, box ).done( function ( res ) {
			if ( res && res.success ) {
				box.find( '.cartiva-order-form__qty-input' ).val( res.data.qty );
			}
		} );
	}

	$( document ).on( 'click', '.cartiva-order-form__qty-btn', function () {
		var box = $( this ).closest( '.cartiva-order-form__qty' );
		var input = box.find( '.cartiva-order-form__qty-input' );
		var qty = clamp( box, ( parseInt( input.val(), 10 ) || 1 ) + parseInt( $( this ).data( 'step' ), 10 ) );
		input.val( qty );
		window.clearTimeout( timer );
		timer = window.setTimeout( function () {
			sendQty( box, qty );
		}, 350 );
	} );

	$( document ).on( 'change', '.cartiva-order-form__qty-input', function () {
		var box = $( this ).closest( '.cartiva-order-form__qty' );
		var qty = clamp( box, $( this ).val() );
		$( this ).val( qty );
		sendQty( box, qty );
	} );

	/* Product options */

	$( document ).on( 'change', '.cartiva-offer__input', function () {
		var box = $( this ).closest( '.cartiva-offers' );
		var inputs = box.find( '.cartiva-offer__input' );
		var chosen = inputs.filter( ':checked' );
		if ( ! chosen.length ) {
			// At least one product must stay in the order.
			$( this ).prop( 'checked', true );
			return;
		}
		inputs.each( function () {
			$( this ).closest( '.cartiva-offer' ).toggleClass( 'is-selected', this.checked );
		} );
		post( settings.select, {
			landing: box.data( 'landing' ),
			options: chosen.map( function () {
				return this.value;
			} ).get(),
		}, box );
	} );

	/* Order bumps */

	$( document ).on( 'change', '.cartiva-bump__check', function () {
		var check = $( this );
		var bump = check.closest( '.cartiva-bump' );
		bump.toggleClass( 'is-added', this.checked );
		post( settings.bump, {
			landing: check.data( 'landing' ),
			index: check.data( 'index' ),
			on: this.checked ? 1 : 0,
		}, bump ).fail( function () {
			check.prop( 'checked', ! check.prop( 'checked' ) );
			bump.toggleClass( 'is-added', check.prop( 'checked' ) );
		} );
	} );

	/* Coupon field (uses WooCommerce's own coupon request) */

	function applyCoupon( field ) {
		var params = window.wc_checkout_params;
		var code = $.trim( field.find( '.cartiva-coupon__code' ).val() );
		if ( ! params || ! code ) {
			return;
		}
		field.addClass( 'is-loading' );
		$.post( params.wc_ajax_url.toString().replace( '%%endpoint%%', 'apply_coupon' ), {
			security: params.apply_coupon_nonce,
			coupon_code: code,
		} )
			.done( function ( html ) {
				field.siblings( '.woocommerce-error, .woocommerce-message, .woocommerce-info' ).remove();
				field.after( html );
				field.find( '.cartiva-coupon__code' ).val( '' );
				$( document.body ).trigger( 'applied_coupon_in_checkout', [ code ] );
				$( document.body ).trigger( 'update_checkout', { update_shipping_method: false } );
			} )
			.always( function () {
				field.removeClass( 'is-loading' );
			} );
	}

	$( document ).on( 'click', '.cartiva-coupon__apply', function () {
		applyCoupon( $( this ).closest( '.cartiva-coupon' ) );
	} );

	$( document ).on( 'keydown', '.cartiva-coupon__code', function ( event ) {
		if ( 13 === event.which ) {
			// Enter would place the order: apply the coupon instead.
			event.preventDefault();
			applyCoupon( $( this ).closest( '.cartiva-coupon' ) );
		}
	} );

	/* Two-step layout */

	function setStep( form, step ) {
		form.toggleClass( 'is-step-2', 2 === step ).toggleClass( 'is-step-1', 1 === step );
		form.find( '.cartiva-steps-nav li' ).each( function ( i ) {
			$( this ).toggleClass( 'is-active', i + 1 === step );
			if ( i + 1 === step ) {
				$( this ).attr( 'aria-current', 'step' );
			} else {
				$( this ).removeAttr( 'aria-current' );
			}
		} );
		var top = form.closest( '.cartiva-order-form' ).get( 0 );
		if ( top && top.scrollIntoView ) {
			top.scrollIntoView( { block: 'start' } );
		}
	}

	function detailsValid( form ) {
		var first = null;
		form.find( '#customer_details .validate-required:visible' ).each( function () {
			var row = $( this );
			var field = row.find( 'input:not([type="hidden"]), select, textarea' ).first();
			field.trigger( 'validate' );
			var empty = field.is( ':checkbox' ) ? ! field.is( ':checked' ) : '' === $.trim( field.val() || '' );
			if ( empty ) {
				row.addClass( 'woocommerce-invalid woocommerce-invalid-required-field' );
			}
			if ( ( empty || row.hasClass( 'woocommerce-invalid' ) ) && ! first ) {
				first = field;
			}
		} );
		if ( first ) {
			first.trigger( 'focus' );
			return false;
		}
		return true;
	}

	function setupSteps() {
		$( '.cartiva-order-form--steps form.checkout' ).each( function () {
			var form = $( this );
			if ( form.data( 'cartivaSteps' ) ) {
				return;
			}
			form.data( 'cartivaSteps', true ).addClass( 'cartiva-steps is-step-1' );
			form.prepend(
				'<ol class="cartiva-steps-nav"><li class="is-active" aria-current="step"><span>1</span> ' + settings.details + '</li><li><span>2</span> ' + settings.payment + '</li></ol>'
			);
			var details = form.find( '#customer_details' );
			$( '<p class="cartiva-steps-next"><button type="button" class="button alt cartiva-steps-continue">' + settings['continue'] + '</button></p>' ).insertAfter( details.nextAll( '.cartiva-bumps' ).last().length ? details.nextAll( '.cartiva-bumps' ).last() : details );
			$( '<p class="cartiva-steps-back"><button type="button" class="button-link cartiva-steps-previous">&larr; ' + settings.back + '</button></p>' ).insertBefore( form.find( '#order_review_heading' ) );
		} );
	}

	$( document ).on( 'click', '.cartiva-steps-continue', function () {
		var form = $( this ).closest( 'form.checkout' );
		if ( detailsValid( form ) ) {
			setStep( form, 2 );
		}
	} );

	$( document ).on( 'click', '.cartiva-steps-previous', function () {
		setStep( $( this ).closest( 'form.checkout' ), 1 );
	} );

	// WooCommerce shows errors after Place order: go back if they are about the details.
	$( document.body ).on( 'checkout_error', function () {
		$( '.cartiva-steps.is-step-2' ).each( function () {
			var form = $( this );
			if ( form.find( '#customer_details .woocommerce-invalid' ).length ) {
				setStep( form, 1 );
			}
		} );
	} );

	$( setupSteps );

	/* Offer buttons: send once. */

	$( document ).on( 'submit', '.cartiva-offer-box__form', function ( event ) {
		var form = $( this );
		if ( form.hasClass( 'is-sending' ) ) {
			event.preventDefault();
			return;
		}
		form.addClass( 'is-sending' );
	} );

	/* "Order now" links on the page scroll smoothly to the form. */

	$( document ).on( 'click', 'a[href="#order"]', function ( event ) {
		var target = document.getElementById( 'order' );
		if ( ! target ) {
			return;
		}
		event.preventDefault();
		var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		target.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
		var field = target.querySelector( '.cartiva-offer__input:checked, input:not([type="hidden"]), select, textarea' );
		if ( field ) {
			window.setTimeout( function () {
				field.focus( { preventScroll: true } );
			}, reduce ? 0 : 500 );
		}
	} );
}( jQuery ) );
