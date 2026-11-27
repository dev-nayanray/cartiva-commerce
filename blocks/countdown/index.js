/**
 * Countdown block (editor). Rendered on the site by PHP; the editor shows a card.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;

	wp.blocks.registerBlockType( 'cartiva-commerce/countdown', {
		edit: function () {
			return el(
				'div',
				wp.blockEditor.useBlockProps(),
				el(
					'div',
					{ className: 'cartiva-order-form-placeholder__card' },
					el( 'strong', null, __( 'Countdown', 'cartiva-commerce' ) ),
					el( 'p', null, __( 'On the site this shows the live countdown. Set its length (for example 3, 5 or 7 days for each visitor) and what happens at the end in the Countdown tab of the landing page settings below the editor.', 'cartiva-commerce' ) )
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
