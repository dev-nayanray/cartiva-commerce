/**
 * Landing order form block (editor). The form itself is rendered on the site
 * by PHP, so the editor shows a preview card and a heading setting.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var RichText = wp.blockEditor.RichText;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;

	wp.blocks.registerBlockType( 'cartiva-commerce/order-form', {
		edit: function ( props ) {
			var blockProps = useBlockProps( { className: 'cartiva-order-form-placeholder' } );
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Order form', 'cartiva-commerce' ) },
						el( 'p', null, __( 'Choose the product, payment methods and form fields in the "Landing page: product and order form" box below the editor.', 'cartiva-commerce' ) )
					)
				),
				el( RichText, {
					tagName: 'h2',
					className: 'cartiva-order-form__heading',
					value: props.attributes.heading,
					placeholder: __( 'Order now (optional heading)', 'cartiva-commerce' ),
					onChange: function ( value ) {
						props.setAttributes( { heading: value } );
					},
				} ),
				el(
					'div',
					{ className: 'cartiva-order-form-placeholder__card' },
					el( 'strong', null, __( 'Landing order form', 'cartiva-commerce' ) ),
					el( 'p', null, __( 'On the site this shows the product, quantity, your chosen fields, payment methods and the Place order button.', 'cartiva-commerce' ) ),
					el(
						'div',
						{ className: 'cartiva-order-form-placeholder__rows', 'aria-hidden': 'true' },
						el( 'span', null ),
						el( 'span', null ),
						el( 'span', null ),
						el( 'span', { className: 'is-button' } )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
}( window.wp ) );
