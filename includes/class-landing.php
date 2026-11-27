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
		add_action( 'admin_notices', array( __CLASS__, 'admin_notices' ) );
		add_filter( 'post_row_actions', array( __CLASS__, 'product_row_action' ), 10, 2 );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'product_meta_box' ) );
		add_action( 'admin_post_cartiva_commerce_product_landing', array( __CLASS__, 'create_for_product' ) );
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
		$choices = self::product_choices();
		if ( null !== $choices ) {
			// Small catalog: a plain list with search. It works even if the search script does not load.
			?>
			<select class="wc-enhanced-select cartiva-product-search" style="width:100%;min-width:220px" name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-placeholder="<?php esc_attr_e( 'Choose a product', 'cartiva-commerce' ); ?>" data-allow_clear="true">
				<option value=""><?php esc_html_e( 'Choose a product', 'cartiva-commerce' ); ?></option>
				<?php foreach ( $choices as $id => $title ) : ?>
					<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( $item_id, $id ); ?>><?php echo esc_html( $title ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			return;
		}
		$item = $item_id ? wc_get_product( $item_id ) : null;
		?>
		<select class="wc-product-search cartiva-product-search" style="width:100%;min-width:220px" name="<?php echo esc_attr( $name ); ?>" aria-label="<?php echo esc_attr( $label ); ?>" data-placeholder="<?php esc_attr_e( 'Search for a product or a variation', 'cartiva-commerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
			<?php if ( $item ) : ?>
				<option value="<?php echo esc_attr( (string) $item_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $item->get_formatted_name() ) ); ?></option>
			<?php endif; ?>
		</select>
		<?php
	}

	/**
	 * Products to pick from in a small catalog (up to 300 products), with the
	 * variations of variable products. Null for bigger stores: they search.
	 *
	 * @return array|null ID => title.
	 */
	public static function product_choices() {
		static $choices = false;
		if ( false !== $choices ) {
			return $choices;
		}
		$products = wc_get_products(
			array(
				'limit'   => 301,
				'status'  => array( 'publish', 'private', 'draft', 'pending' ),
				'orderby' => 'title',
				'order'   => 'ASC',
				'type'    => array_diff( array_keys( wc_get_product_types() ), array( 'grouped', 'external' ) ),
			)
		);
		if ( count( $products ) > 300 ) {
			$choices = null;
			return $choices;
		}
		$choices = array();
		foreach ( $products as $product ) {
			$name = wp_strip_all_tags( $product->get_name() );
			$sku  = $product->get_sku() ? ' (' . $product->get_sku() . ')' : '';
			if ( $product->is_type( 'variable' ) ) {
				/* translators: %s: product name. */
				$choices[ $product->get_id() ] = sprintf( __( '%s — all variations', 'cartiva-commerce' ), $name ) . $sku;
				foreach ( array_slice( $product->get_children(), 0, 50 ) as $child_id ) {
					$child = wc_get_product( $child_id );
					if ( $child ) {
						$choices[ $child_id ] = '— ' . wp_strip_all_tags( $child->get_name() ) . ( $child->get_sku() ? ' (' . $child->get_sku() . ')' : '' );
					}
				}
			} else {
				$choices[ $product->get_id() ] = $name . $sku;
			}
		}
		return $choices;
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
			<div class="cartiva-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Landing page settings', 'cartiva-commerce' ); ?>">
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-products" aria-controls="cartiva-panel-products" data-tab="products"><?php esc_html_e( 'Products', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-countdown" aria-controls="cartiva-panel-countdown" data-tab="countdown"><?php esc_html_e( 'Countdown', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-form" aria-controls="cartiva-panel-form" data-tab="form"><?php esc_html_e( 'Order form', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-extras" aria-controls="cartiva-panel-extras" data-tab="extras"><?php esc_html_e( 'Order bumps', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-upsell" aria-controls="cartiva-panel-upsell" data-tab="upsell"><?php esc_html_e( 'Upsell offer', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-payment" aria-controls="cartiva-panel-payment" data-tab="payment"><?php esc_html_e( 'Payment & thank you', 'cartiva-commerce' ); ?></button>
				<button type="button" class="cartiva-tabs__tab" role="tab" id="cartiva-tab-abtest" aria-controls="cartiva-panel-abtest" data-tab="abtest"><?php esc_html_e( 'A/B test', 'cartiva-commerce' ); ?></button>
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-products" aria-labelledby="cartiva-tab-products" data-tab="products">
			<p class="description"><?php esc_html_e( 'Leave the products empty for a step that only informs, such as a sales page that links to a checkout step, or a thank-you step.', 'cartiva-commerce' ); ?></p>
			<h3><?php esc_html_e( 'Products', 'cartiva-commerce' ); ?></h3>
			<?php if ( empty( $offers ) && has_block( 'cartiva-commerce/order-form', $post ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This page has an order form but no product yet. Choose the product below, then update the page.', 'cartiva-commerce' ); ?></p></div>
			<?php endif; ?>
			<fieldset class="cartiva-mode-cards">
				<legend class="screen-reader-text"><?php esc_html_e( 'How shoppers choose', 'cartiva-commerce' ); ?></legend>
				<?php
				$mode_cards = array(
					'single' => array( __( 'One product', 'cartiva-commerce' ), __( 'Sell one product. Shoppers can change the quantity.', 'cartiva-commerce' ) ),
					'one'    => array( __( 'Multiple products: shopper picks one', 'cartiva-commerce' ), __( 'Packages (1, 2 or 3 pieces), sizes or colors. The shopper chooses one.', 'cartiva-commerce' ) ),
					'many'   => array( __( 'Multiple products: shopper picks several', 'cartiva-commerce' ), __( 'A shirt, a cap and socks. The shopper ticks the ones they want.', 'cartiva-commerce' ) ),
				);
				foreach ( $mode_cards as $value => $card ) :
					?>
					<label class="cartiva-mode-card">
						<input type="radio" name="cartiva_landing[offer_mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $config['offer_mode'], $value ); ?> />
						<span class="cartiva-mode-card__title"><?php echo esc_html( $card[0] ); ?></span>
						<span class="cartiva-mode-card__text"><?php echo esc_html( $card[1] ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="cartiva-mode-single-only"><label><input type="checkbox" name="cartiva_landing[qty_editable]" value="1" <?php checked( ! empty( $config['qty_editable'] ) ); ?> /> <?php esc_html_e( 'Show a quantity changer (+ / −) on the order form', 'cartiva-commerce' ); ?></label></p>
			<p class="description cartiva-mode-single-only"><?php esc_html_e( 'With "One product", only the first row below is sold. Choose a "Multiple products" option to sell more.', 'cartiva-commerce' ); ?></p>
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
						if ( empty( $offers ) ) {
							self::offer_row( '0', array( 'default' => true ) );
						}
						?>
					</tbody>
				</table>
				<template><?php self::offer_row( '__i__', array() ); ?></template>
				<p><button type="button" class="button cartiva-add-row"><?php esc_html_e( 'Add product option', 'cartiva-commerce' ); ?></button></p>
				<p class="description"><?php esc_html_e( 'Opening the page puts the options marked "Selected first" in the cart. Example packs: 1 piece; 2 pieces with 10% off and the badge "Most popular"; 3 pieces with 15% off.', 'cartiva-commerce' ); ?></p>
			</div>
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-countdown" aria-labelledby="cartiva-tab-countdown" data-tab="countdown">
			<h3><?php esc_html_e( 'Countdown', 'cartiva-commerce' ); ?></h3>
			<?php $cd = Cartiva_Commerce_Countdown::settings( $post->ID ); ?>
			<p class="description"><?php esc_html_e( 'A timer that shows how long the offer lasts. Each visitor gets their own time from their first visit, or everyone counts down to one date.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Countdown', 'cartiva-commerce' ); ?></th>
					<td><label><input type="checkbox" name="cartiva_landing[countdown][enabled]" value="1" <?php checked( ! empty( $cd['enabled'] ) ); ?> /> <?php esc_html_e( 'Show a countdown on this page', 'cartiva-commerce' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Timer type', 'cartiva-commerce' ); ?></th>
					<td>
						<label class="cartiva-block-label"><input type="radio" name="cartiva_landing[countdown][mode]" value="evergreen" <?php checked( $cd['mode'], 'evergreen' ); ?> /> <?php esc_html_e( 'For each visitor, from their first visit (recommended)', 'cartiva-commerce' ); ?></label>
						<label class="cartiva-block-label"><input type="radio" name="cartiva_landing[countdown][mode]" value="fixed" <?php checked( $cd['mode'], 'fixed' ); ?> /> <?php esc_html_e( 'To a fixed date and time for everyone', 'cartiva-commerce' ); ?></label>
					</td>
				</tr>
				<tr class="cartiva-cd-evergreen">
					<th scope="row"><?php esc_html_e( 'Length', 'cartiva-commerce' ); ?></th>
					<td>
						<span class="cartiva-cd-presets">
							<?php foreach ( array( 3, 5, 7 ) as $preset_days ) : ?>
								<button type="button" class="button cartiva-cd-preset" data-days="<?php echo esc_attr( (string) $preset_days ); ?>">
									<?php
									/* translators: %d: number of days. */
									echo esc_html( sprintf( _n( '%d day', '%d days', $preset_days, 'cartiva-commerce' ), $preset_days ) );
									?>
								</button>
							<?php endforeach; ?>
						</span>
						<label class="cartiva-inline"><input type="number" min="0" max="60" class="small-text cartiva-cd-days" name="cartiva_landing[countdown][days]" value="<?php echo esc_attr( (string) $cd['days'] ); ?>" /> <?php esc_html_e( 'days', 'cartiva-commerce' ); ?></label>
						<label class="cartiva-inline"><input type="number" min="0" max="23" class="small-text" name="cartiva_landing[countdown][hours]" value="<?php echo esc_attr( (string) $cd['hours'] ); ?>" /> <?php esc_html_e( 'hours', 'cartiva-commerce' ); ?></label>
						<p class="description"><?php esc_html_e( 'Counted from each visitor\'s first visit to this page, in their browser.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr class="cartiva-cd-fixed">
					<th scope="row"><label for="cartiva-cd-date"><?php esc_html_e( 'Ends on', 'cartiva-commerce' ); ?></label></th>
					<td>
						<input id="cartiva-cd-date" type="datetime-local" name="cartiva_landing[countdown][date]" value="<?php echo esc_attr( $cd['date'] ); ?>" />
						<p class="description">
							<?php
							/* translators: %s: time zone, for example Asia/Dhaka. */
							echo esc_html( sprintf( __( 'In your site\'s time zone (%s).', 'cartiva-commerce' ), wp_timezone_string() ) );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'When time runs out', 'cartiva-commerce' ); ?></th>
					<td>
						<?php
						$endings = array(
							'restart' => __( 'Start again for that visitor (keeps the urgency; for-each-visitor timers only)', 'cartiva-commerce' ),
							'hide'    => __( 'Hide the timer, keep the offer', 'cartiva-commerce' ),
							'price'   => __( 'End the discounts: prices go back to normal', 'cartiva-commerce' ),
							'close'   => __( 'Close the offer: the order form is replaced by a message', 'cartiva-commerce' ),
						);
						foreach ( $endings as $value => $text ) :
							?>
							<label class="cartiva-block-label"><input type="radio" name="cartiva_landing[countdown][ended]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $cd['ended'], $value ); ?> /> <?php echo esc_html( $text ); ?></label>
						<?php endforeach; ?>
						<input type="text" class="regular-text" name="cartiva_landing[countdown][message]" value="<?php echo esc_attr( $cd['message'] ); ?>" placeholder="<?php esc_attr_e( 'Sorry, this offer has ended.', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Message when the offer is closed', 'cartiva-commerce' ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Where to show it', 'cartiva-commerce' ); ?></th>
					<td>
						<label class="cartiva-block-label"><input type="checkbox" name="cartiva_landing[countdown][bar]" value="1" <?php checked( ! empty( $cd['bar'] ) ); ?> /> <?php esc_html_e( 'Sticky bar at the top of the page, with an Order now button', 'cartiva-commerce' ); ?></label>
						<label class="cartiva-block-label"><input type="checkbox" name="cartiva_landing[countdown][form]" value="1" <?php checked( ! empty( $cd['form'] ) ); ?> /> <?php esc_html_e( 'Above the order form', 'cartiva-commerce' ); ?></label>
						<p class="description"><?php esc_html_e( 'To place it anywhere else, add the Countdown block in the editor.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Texts and look', 'cartiva-commerce' ); ?></th>
					<td>
						<input type="text" class="regular-text" name="cartiva_landing[countdown][label]" value="<?php echo esc_attr( $cd['label'] ); ?>" placeholder="<?php esc_attr_e( 'Offer ends in', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Timer title', 'cartiva-commerce' ); ?>" />
						<input type="text" class="regular-text" name="cartiva_landing[countdown][bar_text]" value="<?php echo esc_attr( $cd['bar_text'] ); ?>" placeholder="<?php esc_attr_e( 'Special price for a short time', 'cartiva-commerce' ); ?>" aria-label="<?php esc_attr_e( 'Bar text', 'cartiva-commerce' ); ?>" />
						<p>
							<label class="cartiva-inline" style="margin-left:0"><input type="radio" name="cartiva_landing[countdown][style]" value="boxes" <?php checked( $cd['style'], 'boxes' ); ?> /> <?php esc_html_e( 'Big number boxes', 'cartiva-commerce' ); ?></label>
							<label class="cartiva-inline"><input type="radio" name="cartiva_landing[countdown][style]" value="inline" <?php checked( $cd['style'], 'inline' ); ?> /> <?php esc_html_e( 'Compact line', 'cartiva-commerce' ); ?></label>
						</p>
					</td>
				</tr>
			</table>
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-form" aria-labelledby="cartiva-tab-form" data-tab="form">
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

			<h3><?php esc_html_e( 'Order form fields', 'cartiva-commerce' ); ?></h3>
			<p class="description"><?php esc_html_e( 'These fields apply to this landing page only. Your main checkout keeps its own fields.', 'cartiva-commerce' ); ?></p>
			<?php Cartiva_Commerce_Settings::fields_editor( 'cartiva_landing', (string) $config['preset'], (array) $config['fields'], Cartiva_Commerce_Fields::presets() ); ?>
			<p><label><input type="checkbox" name="cartiva_landing[bd_phone]" value="1" <?php checked( ! empty( $config['bd_phone'] ) ); ?> /> <?php esc_html_e( 'Check that the phone is a valid Bangladeshi mobile number (01XXXXXXXXX)', 'cartiva-commerce' ); ?></label></p>
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-extras" aria-labelledby="cartiva-tab-extras" data-tab="extras">
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
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-upsell" aria-labelledby="cartiva-tab-upsell" data-tab="upsell">
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
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-payment" aria-labelledby="cartiva-tab-payment" data-tab="payment">
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
			</div>
			<div class="cartiva-tabs__panel" role="tabpanel" id="cartiva-panel-abtest" aria-labelledby="cartiva-tab-abtest" data-tab="abtest">
			<?php Cartiva_Commerce_AB_Test::render_section( $post ); ?>
			</div>
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
		self::keep_notices();
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
			$item_id = is_array( $row ) && isset( $row['item'] ) ? absint( $row['item'] ) : 0;
			if ( ! $item_id ) {
				continue;
			}
			$base = array(
				'qty'      => isset( $row['qty'] ) ? max( 1, min( 99, absint( $row['qty'] ) ) ) : 1,
				'label'    => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
				'discount' => isset( $row['discount'] ) ? (float) max( 0, min( 90, round( (float) $row['discount'], 1 ) ) ) : 0.0,
				'badge'    => isset( $row['badge'] ) ? sanitize_text_field( $row['badge'] ) : '',
				'default'  => ! empty( $row['default'] ),
			);
			$variations = self::variation_options( $item_id );
			if ( null !== $variations ) {
				// A variable product: each variation becomes a choice on the form.
				if ( empty( $variations ) ) {
					self::$notices[] = array( 'error', __( 'The variable product you chose has no variation that can be bought (check prices and stock), so it was not added.', 'cartiva-commerce' ) );
					continue;
				}
				foreach ( $variations as $n => $variation ) {
					$offers[] = array_merge(
						$base,
						$variation,
						array(
							'label'   => '' !== $base['label'] ? $base['label'] . ' — ' . $variation['label'] : $variation['label'],
							'default' => $base['default'] && 0 === $n,
						)
					);
				}
				if ( 'single' === $mode ) {
					$mode = 'one';
				}
				/* translators: %d: number of variations. */
				self::$notices[] = array( 'info', sprintf( _n( 'The variable product was added as %d choice. Shoppers pick it on the order form.', 'The variable product was added as %d choices (one per variation). Shoppers pick theirs on the order form; you can remove the ones you do not want to sell here.', count( $variations ), 'cartiva-commerce' ), count( $variations ) ) );
				continue;
			}
			$ids = self::resolve_item( $item_id );
			if ( $ids ) {
				$offers[] = array_merge( $ids, $base );
			}
		}
		$offers = array_slice( $offers, 0, 30 );
		$bumps = array();
		foreach ( isset( $raw['bumps'] ) && is_array( $raw['bumps'] ) ? $raw['bumps'] : array() as $row ) {
			$ids = is_array( $row ) ? self::resolve_single( isset( $row['item'] ) ? $row['item'] : 0 ) : null;
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
		$offer_ids = self::resolve_single( isset( $offer_raw['item'] ) ? $offer_raw['item'] : 0 );
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
			'countdown'    => Cartiva_Commerce_Countdown::sanitize( isset( $raw['countdown'] ) ? $raw['countdown'] : array() ),
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
	 * Messages for the person saving the page.
	 *
	 * @var array[] Each: type (info, error), text.
	 */
	public static $notices = array();

	/**
	 * The buyable variations of a variable product, or null when the item is
	 * not a variable product.
	 *
	 * @param int $item_id Product ID.
	 * @return array[]|null Each: product_id, variation_id, label.
	 */
	public static function variation_options( $item_id ) {
		$product = $item_id ? wc_get_product( (int) $item_id ) : null;
		if ( ! $product || ! $product->is_type( 'variable' ) ) {
			return null;
		}
		$list = array();
		foreach ( $product->get_children() as $child_id ) {
			$variation = wc_get_product( $child_id );
			if ( ! $variation || ! $variation->is_purchasable() || ! $variation->is_in_stock() || 'publish' !== get_post_status( $child_id ) ) {
				continue;
			}
			$label  = wc_get_formatted_variation( $variation, true, false, false );
			$list[] = array(
				'product_id'   => (int) $product->get_id(),
				'variation_id' => (int) $child_id,
				'label'        => '' !== trim( wp_strip_all_tags( $label ) ) ? wp_strip_all_tags( $label ) : wp_strip_all_tags( $variation->get_name() ),
			);
			if ( count( $list ) >= 30 ) {
				break;
			}
		}
		return $list;
	}

	/**
	 * One buyable item: a variable product becomes its first buyable variation
	 * (for order bumps and upsell offers, which add one exact item).
	 *
	 * @param mixed $item_id Product or variation ID.
	 * @return array|null product_id, variation_id.
	 */
	public static function resolve_single( $item_id ) {
		$variations = self::variation_options( absint( $item_id ) );
		if ( null === $variations ) {
			return self::resolve_item( $item_id );
		}
		if ( empty( $variations ) ) {
			self::$notices[] = array( 'error', __( 'A variable product you chose has no variation that can be bought, so it was not added.', 'cartiva-commerce' ) );
			return null;
		}
		/* translators: %s: variation, for example Size: L. */
		self::$notices[] = array( 'info', sprintf( __( 'Order bumps and upsell offers add one exact item, so the variation "%s" was used. Choose another variation in the list if you prefer.', 'cartiva-commerce' ), $variations[0]['label'] ) );
		return array(
			'product_id'   => $variations[0]['product_id'],
			'variation_id' => $variations[0]['variation_id'],
		);
	}

	/**
	 * Show save messages, and a reminder when a landing page with an order
	 * form has no product.
	 */
	public static function admin_notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || CARTIVA_COMMERCE_LANDING !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		$key     = 'cartiva_landing_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( is_array( $notices ) ) {
			delete_transient( $key );
			foreach ( $notices as $notice ) {
				printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( 'error' === $notice[0] ? 'error' : 'info' ), esc_html( $notice[1] ) );
			}
		}
	}

	/**
	 * "Create landing page" in the products list.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function product_row_action( $actions, $post ) {
		if ( 'product' === $post->post_type && current_user_can( 'edit_pages' ) ) {
			$actions['cartiva_landing'] = '<a href="' . esc_url( self::create_url( $post->ID ) ) . '">' . esc_html__( 'Create landing page', 'cartiva-commerce' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Link that creates a landing page for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function create_url( $product_id ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cartiva_commerce_product_landing&product=' . (int) $product_id ), 'cartiva_product_landing_' . (int) $product_id );
	}

	/**
	 * Box on the product screen: its landing pages and a create button.
	 */
	public static function product_meta_box() {
		add_meta_box( 'cartiva-product-landing', __( 'Landing pages', 'cartiva-commerce' ), array( __CLASS__, 'render_product_box' ), 'product', 'side', 'low' );
	}

	/**
	 * Landing pages that sell a product.
	 *
	 * @param int $product_id Product ID.
	 * @return int[]
	 */
	public static function landings_for_product( $product_id ) {
		$found = array();
		$ids   = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_LANDING,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			foreach ( cartiva_commerce_landing_offers( cartiva_commerce_landing_config( $id ) ) as $offer ) {
				if ( (int) $offer['product_id'] === (int) $product_id ) {
					$found[] = (int) $id;
					break;
				}
			}
		}
		return $found;
	}

	/**
	 * Product screen box.
	 *
	 * @param WP_Post $post Product.
	 */
	public static function render_product_box( $post ) {
		$pages = 'auto-draft' === $post->post_status ? array() : self::landings_for_product( $post->ID );
		if ( $pages ) {
			echo '<ul>';
			foreach ( $pages as $id ) {
				echo '<li><a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ) . '</a>' . ( 'publish' !== get_post_status( $id ) ? ' <em>(' . esc_html__( 'draft', 'cartiva-commerce' ) . ')</em>' : '' ) . '</li>';
			}
			echo '</ul>';
		}
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p class="description">' . esc_html__( 'Save the product first, then create a landing page for it here.', 'cartiva-commerce' ) . '</p>';
			return;
		}
		echo '<p><a class="button" href="' . esc_url( self::create_url( $post->ID ) ) . '">' . esc_html__( 'Create landing page', 'cartiva-commerce' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Creates a draft sales page with this product and its order form already set up.', 'cartiva-commerce' ) . '</p>';
	}

	/**
	 * Create a landing page for a product (admin-post action).
	 */
	public static function create_for_product() {
		$product_id = isset( $_GET['product'] ) ? absint( wp_unslash( $_GET['product'] ) ) : 0;
		check_admin_referer( 'cartiva_product_landing_' . $product_id );
		$product = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product || ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		$id = wp_insert_post(
			array(
				'post_type'    => CARTIVA_COMMERCE_LANDING,
				'post_status'  => 'draft',
				'post_title'   => wp_strip_all_tags( $product->get_name() ),
				'post_content' => self::default_content(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		update_post_meta(
			$id,
			'_cartiva_landing',
			self::sanitize(
				array(
					'offer_mode'   => 'single',
					'qty_editable' => 1,
					'offers'       => array(
						array(
							'item'    => $product_id,
							'default' => 1,
						),
					),
					'preset'       => 'bd_cod',
					'bd_phone'     => 1,
				)
			)
		);
		if ( has_post_thumbnail( $product_id ) ) {
			set_post_thumbnail( $id, get_post_thumbnail_id( $product_id ) );
		}
		self::keep_notices();
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) );
		exit;
	}

	/**
	 * Starting content of a new landing page as block markup: the Cartiva
	 * theme's landing sections when available, then the order form.
	 *
	 * @return string
	 */
	public static function default_content() {
		$parts = array();
		foreach ( self::default_blocks() as $block ) {
			if ( 'core/pattern' === $block[0] ) {
				$parts[] = '<!-- wp:pattern {"slug":"' . esc_attr( $block[1]['slug'] ) . '"} /-->';
			} elseif ( 'core/heading' === $block[0] ) {
				$parts[] = '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html( $block[1]['content'] ) . '</h1><!-- /wp:heading -->';
			} elseif ( 'core/paragraph' === $block[0] ) {
				$parts[] = '<!-- wp:paragraph --><p>' . esc_html( $block[1]['content'] ) . '</p><!-- /wp:paragraph -->';
			} else {
				$parts[] = '<!-- wp:' . substr( $block[0], 0, 64 ) . ' /-->';
			}
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Keep this request's messages for the next screen.
	 */
	public static function keep_notices() {
		if ( self::$notices ) {
			set_transient( 'cartiva_landing_notices_' . get_current_user_id(), self::$notices, MINUTE_IN_SECONDS * 5 );
			self::$notices = array();
		}
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
