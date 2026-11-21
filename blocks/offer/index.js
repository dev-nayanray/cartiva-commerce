/**
 * Upsell offer block (editor). Rendered on the site by PHP; the editor shows a card.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'cartiva-commerce/offer', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					'div',
					{ className: 'cartiva-order-form-placeholder__card' },
					el( 'strong', null, __( 'Upsell offer', 'cartiva-commerce' ) ),
					el( 'p', null, __( 'On the site this shows the offer product, its price and two buttons: "Yes, add it to my order" and "No thanks". Choose the product in the "Upsell or downsell offer" section below the editor.', 'cartiva-commerce' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
