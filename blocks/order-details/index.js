/**
 * Order details block (editor). Rendered on the site by PHP; the editor shows a card.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'cartiva-commerce/order-details', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					'div',
					{ className: 'cartiva-order-form-placeholder__card' },
					el( 'strong', null, __( 'Order details', 'cartiva-commerce' ) ),
					el( 'p', null, __( 'On the site this shows the order the buyer just placed: number, items, total, payment method and delivery details.', 'cartiva-commerce' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
