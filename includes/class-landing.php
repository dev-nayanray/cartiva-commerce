<?php
/**
 * Landing pages: post type, settings box, page template and preparing the
 * cart with the landing page's product.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Landing pages.
 */
class Cartiva_Commerce_Landing {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 20 );
		add_action( 'init', array( __CLASS__, 'register_template' ), 21 );
		add_action( 'add_meta_boxes_' . CARTIVA_COMMERCE_LANDING, array( __CLASS__, 'meta_box' ) );
		add_action( 'save_post_' . CARTIVA_COMMERCE_LANDING, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_action( 'template_redirect', array( __CLASS__, 'prepare' ), 5 );
		add_filter( 'woocommerce_is_checkout', array( __CLASS__, 'is_checkout' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Register the post type.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Landing pages', 'cartiva-commerce' ),
			'singular_name'      => __( 'Landing page', 'cartiva-commerce' ),
			'add_new'            => __( 'Add landing page', 'cartiva-commerce' ),
			'add_new_item'       => __( 'Add landing page', 'cartiva-commerce' ),
			'edit_item'          => __( 'Edit landing page', 'cartiva-commerce' ),
			'new_item'           => __( 'New landing page', 'cartiva-commerce' ),
			'view_item'          => __( 'View landing page', 'cartiva-commerce' ),
			'search_items'       => __( 'Search landing pages', 'cartiva-commerce' ),
			'not_found'          => __( 'No landing pages yet.', 'cartiva-commerce' ),
			'not_found_in_trash' => __( 'No landing pages in the trash.', 'cartiva-commerce' ),
			'all_items'          => __( 'Landing pages', 'cartiva-commerce' ),
			'menu_name'          => __( 'Landing pages', 'cartiva-commerce' ),
		);
		register_post_type(
			CARTIVA_COMMERCE_LANDING,
			array(
				'labels'          => $labels,
				'public'          => true,
				'show_in_rest'    => true,
				'show_in_menu'    => 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL,
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'hierarchical'    => false,
				'has_archive'     => false,
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
				/**
				 * Filters the landing page URL base, for example "offer" in example.com/offer/my-product/.
				 *
				 * @param string $slug URL base.
				 */
				'rewrite'         => array(
					'slug'       => sanitize_title( apply_filters( 'cartiva_commerce_landing_slug', 'offer' ) ),
					'with_front' => false,
				),
				'template'        => self::default_blocks(),
			)
		);
		if ( get_option( 'cartiva_commerce_flush_rewrite' ) ) {
			delete_option( 'cartiva_commerce_flush_rewrite' );
			flush_rewrite_rules();
		}
	}

	/**
	 * Starting content for a new landing page. Uses the Cartiva theme's
	 * landing patterns when they exist, plain blocks otherwise.
	 *
	 * @return array
	 */
	public static function default_blocks() {
		$registry = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance() : null;
		$blocks   = array();
		if ( $registry && $registry->is_registered( 'cartiva/landing-hero' ) ) {
			foreach ( array( 'landing-offer', 'landing-hero', 'landing-benefits', 'landing-steps', 'landing-guarantee', 'landing-faq' ) as $slug ) {
				if ( $registry->is_registered( 'cartiva/' . $slug ) ) {
					$blocks[] = array( 'core/pattern', array( 'slug' => 'cartiva/' . $slug ) );
				}
			}
		} else {
			$blocks[] = array(
				'core/heading',
				array(
					'level'   => 1,
					'content' => __( 'Your product headline', 'cartiva-commerce' ),
				),
			);
			$blocks[] = array( 'core/paragraph', array( 'content' => __( 'Say in one or two sentences what the product does and why it is worth buying.', 'cartiva-commerce' ) ) );
		}
		$blocks[] = array( 'cartiva-commerce/order-form', array() );
		return $blocks;
	}

	/**
	 * Page template for landing pages in block themes: header, the page
	 * content at full width, footer.
	 */
	public static function register_template() {
		if ( ! function_exists( 'register_block_template' ) || ! wp_is_block_theme() ) {
			return;
		}
		// Themes with a distraction-free landing header and footer (Cartiva has them) get those.
		$header = file_exists( get_theme_file_path( 'parts/landing-header.html' ) ) ? 'landing-header' : 'header';
		$footer = file_exists( get_theme_file_path( 'parts/landing-footer.html' ) ) ? 'landing-footer' : 'footer';
		register_block_template(
			'cartiva-commerce//single-' . CARTIVA_COMMERCE_LANDING,
			array(
				'title'       => __( 'Single landing page', 'cartiva-commerce' ),
				'description' => __( 'Shows a Cartiva Commerce landing page at full width between the site header and footer.', 'cartiva-commerce' ),
				'content'     => '<!-- wp:template-part {"slug":"' . $header . '","tagName":"header"} /-->' . "\n"
					. '<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->' . "\n"
					. '<main class="wp-block-group alignfull"><!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /--></main>' . "\n"
					. '<!-- /wp:group -->' . "\n"
					. '<!-- wp:template-part {"slug":"' . $footer . '","tagName":"footer"} /-->',
				'post_types'  => array( CARTIVA_COMMERCE_LANDING ),
			)
		);
	}

	/**
	 * Settings box under the editor.
	 */
	public static function meta_box() {
		add_meta_box(
			'cartiva-landing-settings',
			__( 'Landing page: product and order form', 'cartiva-commerce' ),
			array( __CLASS__, 'render_box' ),
			CARTIVA_COMMERCE_LANDING,
			'normal',
			'high'
		);
	}

	/**
	 * Admin scripts for the product search field.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || CARTIVA_COMMERCE_LANDING !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'cartiva-commerce-admin-landing', CARTIVA_COMMERCE_URL . 'assets/js/admin-landing.js', array( 'jquery', 'wc-enhanced-select' ), CARTIVA_COMMERCE_VERSION, true );
	}

	/**
	 * Enabled payment methods: ID => title.
	 *
	 * @return array
	 */
	public static function enabled_gateways() {
		$list = array();
		if ( ! WC()->payment_gateways() ) {
			return $list;
		}
		foreach ( WC()->payment_gateways()->payment_gateways() as $id => $gateway ) {
			if ( isset( $gateway->enabled ) && 'yes' === $gateway->enabled ) {
				$list[ (string) $id ] = wp_strip_all_tags( (string) $gateway->get_title() );
			}
		}
		return $list;
	}

	/**
	 * Product search field.
	 *
	 * @param string $name    Field name.
	 * @param array  $row     Row with product_id and variation_id.
	 * @param string $label   Accessible label.
	 */
	public static function product_select( $name, $row, $label ) {
		$item_id = ! empty( $row['variation_id'] ) ? (int) $row['variation_id'] : ( isset( $row['product_id'] ) ? (int) $row['product_id'] : 0 );
		$item    = $item_id ? wc_get_product( $item_id ) : null;
		?>
		<select class="wc-product-search cartiva-product-search" style="width:100%;min-width:220px" name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product or a variation', 'cartiva-commerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
			<?php if ( $item ) : ?>
				<option value="<?php echo esc_attr( (string) $item_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $item->get_formatted_name() ) ); ?></option>
			<?php endif; ?>
		</select>
		<?php
	}

	/**
	 * One product option row.
	 *
	 * @param string $i   Row index (or __i__ in the template).
	 * @param array  $row Option.
	 */
	public static function offer_row( $i, $row ) {
		$row  = wp_parse_args(
			$row,
			array(
				'qty'      => 1,
				'label'    => '',
				'discount' => 0,
				'badge'    => '',
				'default'  => false,
			)
		);
		$base = 'cartiva_landing[offers][' . $i . ']';
		?>
		<tr>
			<td><?php self::product_select( $base . '[item]', $row, __( 'Product or variation', 'cartiva-commerce' ) ); ?></td>
			<td><input type="number" min="1" max="99" class="small-text" name="<?php echo esc_attr( $base . '[qty]' ); ?>" value="<?php echo esc_attr( (string) $row['qty'] ); ?>" aria-label="<?php esc_attr_e( 'Quantity', 'cartiva-commerce' ); ?>" /></td>
			<td><input type="text" class="regular-text" style="width:100%" name="<?php echo esc_attr( $base . '[label]' ); ?>" value="<?php echo esc_attr( $row['label'] ); ?>" placeholder="<?php esc_attr_e( 'Product name', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Label', 'cartiva-commerce' ); ?>" /></td>
			<td><input type="number" min="0" max="90" step="0.5" class="small-text" name="<?php echo esc_attr( $base . '[discount]' ); ?>" value="<?php echo esc_attr( (string) $row['discount'] ); ?>" aria-label="<?php esc_attr_e( 'Discount %', 'cartiva-commerce' ); ?>" /></td>
			<td><input type="text" class="regular-text" style="width:100%" name="<?php echo esc_attr( $base . '[badge]' ); ?>" value="<?php echo esc_attr( $row['badge'] ); ?>" placeholder="<?php esc_attr_e( 'Best value', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Badge', 'cartiva-commerce' ); ?>" /></td>
			<td><input type="checkbox" name="<?php echo esc_attr( $base . '[default]' ); ?>" value="1" <?php checked( ! empty( $row['default'] ) ); ?> aria-label="<?php esc_attr_e( 'Selected first', 'cartiva-commerce' ); ?>" /></td>
			<td><button type="button" class="button-link cartiva-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'cartiva-commerce' ); ?>">&times;</button></td>
		</tr>
		<?php
	}

	/**
	 * One order bump row.
	 *
	 * @param string $i   Row index (or __i__ in the template).
	 * @param array  $row Bump.
	 */
	public static function bump_row( $i, $row ) {
		$row  = wp_parse_args(
			$row,
			array(
				'title'     => '',
				'text'      => '',
				'discount'  => 0,
				'min_total' => 0,
				'position'  => 'before_payment',
			)
		);
		$base = 'cartiva_landing[bumps][' . $i . ']';
		?>
		<tr>
			<td><?php self::product_select( $base . '[item]', $row, __( 'Product', 'cartiva-commerce' ) ); ?></td>
			<td>
				<input type="text" style="width:100%" name="<?php echo esc_attr( $base . '[title]' ); ?>" value="<?php echo esc_attr( $row['title'] ); ?>" placeholder="<?php esc_attr_e( 'Yes, add it to my order', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Tick box text', 'cartiva-commerce' ); ?>" />
				<textarea rows="2" style="width:100%;margin-top:4px" name="<?php echo esc_attr( $base . '[text]' ); ?>" placeholder="<?php esc_attr_e( 'One line on why it is worth adding', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Description', 'cartiva-commerce' ); ?>"><?php echo esc_textarea( $row['text'] ); ?></textarea>
			</td>
			<td><input type="number" min="0" max="90" step="0.5" class="small-text" name="<?php echo esc_attr( $base . '[discount]' ); ?>" value="<?php echo esc_attr( (string) $row['discount'] ); ?>" aria-label="<?php esc_attr_e( 'Discount %', 'cartiva-commerce' ); ?>" /></td>
			<td><input type="number" min="0" step="any" class="small-text" name="<?php echo esc_attr( $base . '[min_total]' ); ?>" value="<?php echo esc_attr( (string) $row['min_total'] ); ?>" aria-label="<?php esc_attr_e( 'Show when the order is at least', 'cartiva-commerce' ); ?>" /></td>
			<td>
				<select name="<?php echo esc_attr( $base . '[position]' ); ?>" aria-label="<?php esc_attr_e( 'Position', 'cartiva-commerce' ); ?>">
					<option value="before_payment" <?php selected( $row['position'], 'before_payment' ); ?>><?php esc_html_e( 'Above payment', 'cartiva-commerce' ); ?></option>
					<option value="after_customer" <?php selected( $row['position'], 'after_customer' ); ?>><?php esc_html_e( 'Below the address', 'cartiva-commerce' ); ?></option>
				</select>
			</td>
			<td><button type="button" class="button-link cartiva-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'cartiva-commerce' ); ?>">&times;</button></td>
		</tr>
		<?php
	}

	/**
	 * Render the settings box.
	 *
	 * @param WP_Post $post Landing page.
	 */
	public static function render_box( $post ) {
		$config = cartiva_commerce_landing_config( $post->ID );
		$offers = cartiva_commerce_landing_offers( $config );
		wp_nonce_field( 'cartiva_landing_save', 'cartiva_landing_nonce' );
		$modes = array(
			'single' => __( 'One product (shoppers can change the quantity)', 'cartiva-commerce' ),
			'one'    => __( 'Shoppers pick one option, for example 1, 2 or 3 pieces', 'cartiva-commerce' ),
			'many'   => __( 'Shoppers pick one or more products', 'cartiva-commerce' ),
		);
		$layouts = array(
			'one'   => __( 'One column', 'cartiva-commerce' ),
			'two'   => __( 'Two columns (details left, order summary right)', 'cartiva-commerce' ),
			'steps' => __( 'Two steps (details, then payment)', 'cartiva-commerce' ),
		);
		?>
		<div class="cartiva-landing-box">
			<p class="description"><?php esc_html_e( 'Leave the products empty for a step that only informs, such as a sales page that links to a checkout step, or a thank-you step.', 'cartiva-commerce' ); ?></p>
			<h3><?php esc_html_e( 'Products', 'cartiva-commerce' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cartiva-landing-mode"><?php esc_html_e( 'How shoppers choose', 'cartiva-commerce' ); ?></label></th>
					<td>
						<select id="cartiva-landing-mode" name="cartiva_landing[offer_mode]">
							<?php foreach ( $modes as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $config['offer_mode'], $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<label class="cartiva-inline"><input type="checkbox" name="cartiva_landing[qty_editable]" value="1" <?php checked( ! empty( $config['qty_editable'] ) ); ?> /> <?php esc_html_e( 'Show a quantity changer (one product only)', 'cartiva-commerce' ); ?></label>
					</td>
				</tr>
			</table>
			<div class="cartiva-repeater" data-cartiva-repeater="offers">
				<table class="widefat cartiva-rows">
					<thead><tr>
						<th><?php esc_html_e( 'Product or variation', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Qty', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Label', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Discount %', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Badge', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Selected first', 'cartiva-commerce' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'cartiva-commerce' ); ?></span></th>
					</tr></thead>
					<tbody>
						<?php
						foreach ( $offers as $i => $row ) {
							self::offer_row( (string) $i, $row );
						}
						?>
					</tbody>
				</table>
				<template><?php self::offer_row( '__i__', array() ); ?></template>
				<p><button type="button" class="button cartiva-add-row"><?php esc_html_e( 'Add product option', 'cartiva-commerce' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Opening the page puts the options marked "Selected first" in the cart. Example packs: 1 piece; 2 pieces with 10% off and the badge "Most popular"; 3 pieces with 15% off.', 'cartiva-commerce' ); ?></p>
			</div>

			<h3><?php esc_html_e( 'Order bumps', 'cartiva-commerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'A tick box on the order form that adds an extra product, often at a special price.', 'cartiva-commerce' ); ?></p>
			<div class="cartiva-repeater" data-cartiva-repeater="bumps">
				<table class="widefat cartiva-rows">
					<thead><tr>
						<th><?php esc_html_e( 'Product', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Tick box text and description', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Discount %', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Show when the order is at least', 'cartiva-commerce' ); ?></th>
						<th><?php esc_html_e( 'Position', 'cartiva-commerce' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'cartiva-commerce' ); ?></span></th>
					</tr></thead>
					<tbody>
						<?php
						foreach ( (array) $config['bumps'] as $i => $row ) {
							self::bump_row( (string) $i, $row );
						}
						?>
					</tbody>
				</table>
				<template><?php self::bump_row( '__i__', array() ); ?></template>
				<p><button type="button" class="button cartiva-add-row"><?php esc_html_e( 'Add order bump', 'cartiva-commerce' ); ?></button></p>
			</div>

			<h3><?php esc_html_e( 'Order form layout', 'cartiva-commerce' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Layout', 'cartiva-commerce' ); ?></th>
					<td>
						<?php foreach ( $layouts as $value => $label ) : ?>
							<label class="cartiva-block-label"><input type="radio" name="cartiva_landing[layout]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $config['layout'], $value ); ?> /> <?php echo esc_html( $label ); ?></label>
						<?php endforeach; ?>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Upsell or downsell offer', 'cartiva-commerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Only for pages used as an Upsell or Downsell step in a funnel. Buyers see the offer right after their order, and one click adds it to the same order. Works with Cash on delivery, bank transfer and check payments; orders paid another way skip the offer.', 'cartiva-commerce' ); ?></p>
			<?php $offer = Cartiva_Commerce_Upsells::offer( $post->ID ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Offer product', 'cartiva-commerce' ); ?></th>
					<td><?php self::product_select( 'cartiva_landing[offer][item]', $offer, __( 'Offer product', 'cartiva-commerce' ) ); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-offer-qty"><?php esc_html_e( 'Quantity and discount', 'cartiva-commerce' ); ?></label></th>
					<td>
						<input id="cartiva-offer-qty" type="number" min="1" max="99" class="small-text" name="cartiva_landing[offer][qty]" value="<?php echo esc_attr( (string) $offer['qty'] ); ?>" />
						<label class="cartiva-inline"><?php esc_html_e( 'Discount %', 'cartiva-commerce' ); ?> <input type="number" min="0" max="90" step="0.5" class="small-text" name="cartiva_landing[offer][discount]" value="<?php echo esc_attr( (string) $offer['discount'] ); ?>" /></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-offer-yes"><?php esc_html_e( 'Button texts', 'cartiva-commerce' ); ?></label></th>
					<td>
						<input id="cartiva-offer-yes" type="text" class="regular-text" name="cartiva_landing[offer][yes_label]" value="<?php echo esc_attr( $offer['yes_label'] ); ?>" placeholder="<?php esc_attr_e( 'Yes, add it to my order', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Yes button', 'cartiva-commerce' ); ?>" />
						<input type="text" class="regular-text" name="cartiva_landing[offer][no_label]" value="<?php echo esc_attr( $offer['no_label'] ); ?>" placeholder="<?php esc_attr_e( 'No thanks, I don\'t want this offer', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'No button', 'cartiva-commerce' ); ?>" />
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Payment', 'cartiva-commerce' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Payment methods', 'cartiva-commerce' ); ?></th>
					<td class="cartiva-gateways">
						<?php
						$gateways = self::enabled_gateways();
						if ( empty( $gateways ) ) :
							?>
							<p class="description"><?php esc_html_e( 'No payment methods are enabled yet. Enable them in WooCommerce > Settings > Payments.', 'cartiva-commerce' ); ?></p>
							<?php
						else :
							foreach ( $gateways as $id => $title ) :
								?>
								<label><input type="checkbox" name="cartiva_landing[gateways][]" value="<?php echo esc_attr( $id ); ?>" <?php checked( in_array( $id, (array) $config['gateways'], true ) ); ?> /> <?php echo esc_html( $title ); ?></label>
								<?php
							endforeach;
							?>
							<p class="description"><?php esc_html_e( 'Tick the methods to offer on this page, for example only Cash on delivery. Leave all unticked to offer every enabled method.', 'cartiva-commerce' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Coupons', 'cartiva-commerce' ); ?></th>
					<td><label><input type="checkbox" name="cartiva_landing[show_coupon]" value="1" <?php checked( ! empty( $config['show_coupon'] ) ); ?> /> <?php esc_html_e( 'Show the coupon field', 'cartiva-commerce' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-landing-thankyou"><?php esc_html_e( 'Thank-you page', 'cartiva-commerce' ); ?></label></th>
					<td>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'cartiva_landing[thankyou]',
								'id'                => 'cartiva-landing-thankyou',
								'selected'          => (int) $config['thankyou'],
								'show_option_none'  => esc_html__( 'WooCommerce order received page', 'cartiva-commerce' ),
								'option_none_value' => '0',
							)
						);
						?>
						<p class="description"><?php esc_html_e( 'Optional. The order summary is shown at the top of the page you pick.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Order form fields', 'cartiva-commerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'These fields apply to this landing page only. Your main checkout keeps its own fields.', 'cartiva-commerce' ); ?></p>
			<?php Cartiva_Commerce_Settings::fields_editor( 'cartiva_landing', (string) $config['preset'], (array) $config['fields'], Cartiva_Commerce_Fields::presets() ); ?>
			<p><label><input type="checkbox" name="cartiva_landing[bd_phone]" value="1" <?php checked( ! empty( $config['bd_phone'] ) ); ?> /> <?php esc_html_e( 'Check that the phone is a valid Bangladeshi mobile number (01XXXXXXXXX)', 'cartiva-commerce' ); ?></label></p>
			<?php Cartiva_Commerce_AB_Test::render_section( $post ); ?>
		</div>
		<?php
	}

	/**
	 * Save the settings box.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['cartiva_landing_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartiva_landing_nonce'] ) ), 'cartiva_landing_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['cartiva_landing'] ) && is_array( $_POST['cartiva_landing'] ) ? wp_unslash( $_POST['cartiva_landing'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		update_post_meta( $post_id, '_cartiva_landing', self::sanitize( $raw ) );
		delete_transient( 'cartiva_landing_stats_' . $post_id );
	}

	/**
	 * Clean landing page settings.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$presets  = Cartiva_Commerce_Fields::presets();
		$preset   = isset( $raw['preset'] ) ? sanitize_key( $raw['preset'] ) : 'bd_cod';
		$enabled  = array_keys( self::enabled_gateways() );
		$gateways = isset( $raw['gateways'] ) && is_array( $raw['gateways'] ) ? array_map( 'sanitize_key', $raw['gateways'] ) : array();
		$thankyou = isset( $raw['thankyou'] ) ? absint( $raw['thankyou'] ) : 0;
		$mode     = isset( $raw['offer_mode'] ) ? sanitize_key( $raw['offer_mode'] ) : 'single';
		$layout   = isset( $raw['layout'] ) ? sanitize_key( $raw['layout'] ) : 'one';

		$offers = array();
		foreach ( isset( $raw['offers'] ) && is_array( $raw['offers'] ) ? $raw['offers'] : array() as $row ) {
			$ids = is_array( $row ) ? self::resolve_item( isset( $row['item'] ) ? $row['item'] : 0 ) : null;
			if ( ! $ids ) {
				continue;
			}
			$offers[] = array_merge(
				$ids,
				array(
					'qty'      => isset( $row['qty'] ) ? max( 1, min( 99, absint( $row['qty'] ) ) ) : 1,
					'label'    => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
					'discount' => isset( $row['discount'] ) ? (float) max( 0, min( 90, round( (float) $row['discount'], 1 ) ) ) : 0.0,
					'badge'    => isset( $row['badge'] ) ? sanitize_text_field( $row['badge'] ) : '',
					'default'  => ! empty( $row['default'] ),
				)
			);
		}
		$bumps = array();
		foreach ( isset( $raw['bumps'] ) && is_array( $raw['bumps'] ) ? $raw['bumps'] : array() as $row ) {
			$ids = is_array( $row ) ? self::resolve_item( isset( $row['item'] ) ? $row['item'] : 0 ) : null;
			if ( ! $ids || count( $bumps ) >= 5 ) {
				continue;
			}
			$bumps[] = array_merge(
				$ids,
				array(
					'qty'       => 1,
					'title'     => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
					'text'      => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
					'discount' => isset( $row['discount'] ) ? (float) max( 0, min( 90, round( (float) $row['discount'], 1 ) ) ) : 0.0,
					'min_total' => isset( $row['min_total'] ) ? max( 0, (float) $row['min_total'] ) : 0,
					'position'  => ( isset( $row['position'] ) && 'after_customer' === $row['position'] ) ? 'after_customer' : 'before_payment',
				)
			);
		}
		$offer_raw = isset( $raw['offer'] ) && is_array( $raw['offer'] ) ? $raw['offer'] : array();
		$offer_ids = self::resolve_item( isset( $offer_raw['item'] ) ? $offer_raw['item'] : 0 );
		$offer     = $offer_ids ? array_merge(
			$offer_ids,
			array(
				'qty'       => isset( $offer_raw['qty'] ) ? max( 1, min( 99, absint( $offer_raw['qty'] ) ) ) : 1,
				'discount'  => isset( $offer_raw['discount'] ) ? (float) max( 0, min( 90, round( (float) $offer_raw['discount'], 1 ) ) ) : 0.0,
				'yes_label' => isset( $offer_raw['yes_label'] ) ? sanitize_text_field( $offer_raw['yes_label'] ) : '',
				'no_label'  => isset( $offer_raw['no_label'] ) ? sanitize_text_field( $offer_raw['no_label'] ) : '',
			)
		) : array();
		$first     = $offers ? $offers[0] : array(
			'product_id'   => 0,
			'variation_id' => 0,
			'qty'          => 1,
		);
		return array(
			// The first option is also kept in the 1.0 keys, for code that reads them.
			'product_id'   => (int) $first['product_id'],
			'variation_id' => (int) $first['variation_id'],
			'quantity'     => (int) $first['qty'],
			'qty_editable' => ! empty( $raw['qty_editable'] ),
			'offer_mode'   => in_array( $mode, array( 'single', 'one', 'many' ), true ) ? $mode : 'single',
			'offers'       => $offers,
			'bumps'        => $bumps,
			'layout'       => in_array( $layout, array( 'one', 'two', 'steps' ), true ) ? $layout : 'one',
			'offer'        => $offer,
			'gateways'     => array_values( array_intersect( $gateways, $enabled ) ),
			'preset'       => isset( $presets[ $preset ] ) ? $preset : 'bd_cod',
			'fields'       => Cartiva_Commerce_Fields::sanitize_config( isset( $raw['fields'] ) ? $raw['fields'] : array() ),
			'thankyou'     => ( $thankyou && in_array( get_post_type( $thankyou ), array( 'page', CARTIVA_COMMERCE_LANDING ), true ) ) ? $thankyou : 0,
			'show_coupon'  => ! empty( $raw['show_coupon'] ),
			'bd_phone'     => ! empty( $raw['bd_phone'] ),
		);
	}

	/**
	 * Product and variation IDs of a picked product.
	 *
	 * @param mixed $item_id Product or variation ID.
	 * @return array|null product_id, variation_id.
	 */
	public static function resolve_item( $item_id ) {
		$item = absint( $item_id ) ? wc_get_product( absint( $item_id ) ) : null;
		if ( ! $item ) {
			return null;
		}
		if ( $item->is_type( 'variation' ) ) {
			return array(
				'product_id'   => (int) $item->get_parent_id(),
				'variation_id' => (int) $item->get_id(),
			);
		}
		if ( $item->is_type( 'variable' ) ) {
			return null; // A specific variation is needed.
		}
		return array(
			'product_id'   => (int) $item->get_id(),
			'variation_id' => 0,
		);
	}

	/**
	 * The cart line of the first product option, if present (used by the
	 * quantity changer of one-product pages).
	 *
	 * @param array $config     Landing settings.
	 * @param int   $landing_id Landing page ID.
	 * @return string Cart item key or ''.
	 */
	public static function cart_item_key( $config, $landing_id = 0 ) {
		if ( ! WC()->cart ) {
			return '';
		}
		$landing_id = $landing_id ? $landing_id : cartiva_commerce_current_landing_id();
		$lines      = Cartiva_Commerce_Offers::landing_lines( $landing_id );
		return isset( $lines['offers'][0] ) ? $lines['offers'][0] : '';
	}

	/**
	 * Does a landing page sell something (and so show an order form)?
	 *
	 * @param int $landing_id Landing page ID.
	 * @return bool
	 */
	public static function sells( $landing_id ) {
		return ! empty( cartiva_commerce_landing_offers( cartiva_commerce_landing_config( $landing_id ) ) );
	}

	/**
	 * On a landing page: make the cart hold this page's products, keep the
	 * page out of caches and count the visit.
	 */
	public static function prepare() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$landing_id = (int) get_queried_object_id();

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		if ( ! current_user_can( 'edit_post', $landing_id ) && ! cartiva_commerce_is_bot() && ! isset( $_GET['cartiva_order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check: offer and thank-you views of an order are not new visits.
			cartiva_commerce_count_visit( $landing_id );
		}

		if ( ! self::sells( $landing_id ) || ! WC()->cart ) {
			return;
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		Cartiva_Commerce_Offers::setup_cart( $landing_id, cartiva_commerce_landing_config( $landing_id ) );
	}

	/**
	 * Landing pages count as a checkout page for WooCommerce, so it loads its
	 * checkout scripts for the order form.
	 *
	 * @param bool $is_checkout Is checkout.
	 * @return bool
	 */
	public static function is_checkout( $is_checkout ) {
		if ( ! is_admin() && did_action( 'wp' ) && is_singular( CARTIVA_COMMERCE_LANDING ) && self::sells( (int) get_queried_object_id() ) ) {
			return true;
		}
		return $is_checkout;
	}

	/**
	 * Body class for styling.
	 *
	 * @param array $classes Classes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			$classes[] = 'cartiva-landing-page';
			if ( self::sells( (int) get_queried_object_id() ) ) {
				$classes[] = 'woocommerce-checkout';
				$classes[] = 'woocommerce-page';
			}
		}
		return $classes;
	}
}
