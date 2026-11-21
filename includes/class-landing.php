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
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 56,
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
		register_block_template(
			'cartiva-commerce//single-' . CARTIVA_COMMERCE_LANDING,
			array(
				'title'       => __( 'Single landing page', 'cartiva-commerce' ),
				'description' => __( 'Shows a Cartiva Commerce landing page at full width between the site header and footer.', 'cartiva-commerce' ),
				'content'     => '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->' . "\n"
					. '<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"constrained"}} -->' . "\n"
					. '<main class="wp-block-group alignfull"><!-- wp:post-content {"align":"full","layout":{"type":"constrained"}} /--></main>' . "\n"
					. '<!-- /wp:group -->' . "\n"
					. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->',
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
	 * Render the settings box.
	 *
	 * @param WP_Post $post Landing page.
	 */
	public static function render_box( $post ) {
		$config  = cartiva_commerce_landing_config( $post->ID );
		$item_id = $config['variation_id'] ? (int) $config['variation_id'] : (int) $config['product_id'];
		$item    = $item_id ? wc_get_product( $item_id ) : null;
		wp_nonce_field( 'cartiva_landing_save', 'cartiva_landing_nonce' );
		?>
		<div class="cartiva-landing-box">
			<h3><?php esc_html_e( 'Product', 'cartiva-commerce' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cartiva-landing-product"><?php esc_html_e( 'Product to sell', 'cartiva-commerce' ); ?></label></th>
					<td>
						<select id="cartiva-landing-product" class="wc-product-search" style="width:100%;max-width:480px" name="cartiva_landing[item]" data-placeholder="<?php esc_attr_e( 'Search for a product or a variation', 'cartiva-commerce' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
							<?php if ( $item ) : ?>
								<option value="<?php echo esc_attr( (string) $item_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $item->get_formatted_name() ) ); ?></option>
							<?php endif; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Opening this landing page puts this product in the cart, so the order form is ready to fill in. For a product with sizes or colors, pick the exact variation.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-landing-qty"><?php esc_html_e( 'Quantity', 'cartiva-commerce' ); ?></label></th>
					<td>
						<input id="cartiva-landing-qty" type="number" min="1" max="99" class="small-text" name="cartiva_landing[quantity]" value="<?php echo esc_attr( (string) $config['quantity'] ); ?>" />
						<label style="margin-left:12px"><input type="checkbox" name="cartiva_landing[qty_editable]" value="1" <?php checked( ! empty( $config['qty_editable'] ) ); ?> /> <?php esc_html_e( 'Let shoppers change the quantity', 'cartiva-commerce' ); ?></label>
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
		$item_id      = isset( $raw['item'] ) ? absint( $raw['item'] ) : 0;
		$product_id   = 0;
		$variation_id = 0;
		$item         = $item_id ? wc_get_product( $item_id ) : null;
		if ( $item ) {
			if ( $item->is_type( 'variation' ) ) {
				$variation_id = $item->get_id();
				$product_id   = $item->get_parent_id();
			} else {
				$product_id = $item->get_id();
			}
		}
		$presets  = Cartiva_Commerce_Fields::presets();
		$preset   = isset( $raw['preset'] ) ? sanitize_key( $raw['preset'] ) : 'bd_cod';
		$enabled  = array_keys( self::enabled_gateways() );
		$gateways = isset( $raw['gateways'] ) && is_array( $raw['gateways'] ) ? array_map( 'sanitize_key', $raw['gateways'] ) : array();
		$thankyou = isset( $raw['thankyou'] ) ? absint( $raw['thankyou'] ) : 0;
		return array(
			'product_id'   => $product_id,
			'variation_id' => $variation_id,
			'quantity'     => isset( $raw['quantity'] ) ? max( 1, min( 99, absint( $raw['quantity'] ) ) ) : 1,
			'qty_editable' => ! empty( $raw['qty_editable'] ),
			'gateways'     => array_values( array_intersect( $gateways, $enabled ) ),
			'preset'       => isset( $presets[ $preset ] ) ? $preset : 'bd_cod',
			'fields'       => Cartiva_Commerce_Fields::sanitize_config( isset( $raw['fields'] ) ? $raw['fields'] : array() ),
			'thankyou'     => ( $thankyou && 'page' === get_post_type( $thankyou ) ) ? $thankyou : 0,
			'show_coupon'  => ! empty( $raw['show_coupon'] ),
			'bd_phone'     => ! empty( $raw['bd_phone'] ),
		);
	}

	/**
	 * The cart line that belongs to a landing page, if present.
	 *
	 * @param array $config Landing settings.
	 * @return string Cart item key or ''.
	 */
	public static function cart_item_key( $config ) {
		if ( ! WC()->cart ) {
			return '';
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( (int) $item['product_id'] === (int) $config['product_id'] && (int) $item['variation_id'] === (int) $config['variation_id'] ) {
				return (string) $key;
			}
		}
		return '';
	}

	/**
	 * On a landing page: make the cart hold only this page's product, keep the
	 * page out of caches and count the visit.
	 */
	public static function prepare() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$landing_id = (int) get_queried_object_id();
		$config     = cartiva_commerce_landing_config( $landing_id );

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();

		if ( ! current_user_can( 'edit_post', $landing_id ) ) {
			update_post_meta( $landing_id, '_cartiva_visits', (int) get_post_meta( $landing_id, '_cartiva_visits', true ) + 1 );
		}

		if ( ! $config['product_id'] || ! WC()->cart ) {
			return;
		}
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		$key   = self::cart_item_key( $config );
		$lines = count( WC()->cart->get_cart() );
		if ( '' !== $key && 1 === $lines ) {
			return; // Already set up (for example after a reload).
		}
		WC()->cart->empty_cart();
		$attributes = array();
		if ( $config['variation_id'] ) {
			$variation  = wc_get_product( $config['variation_id'] );
			$attributes = $variation ? $variation->get_variation_attributes() : array();
		}
		WC()->cart->add_to_cart( (int) $config['product_id'], (int) $config['quantity'], (int) $config['variation_id'], $attributes );
		// The landing page shows its own form; drop the generic "added to cart" notice.
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
	}

	/**
	 * Landing pages count as a checkout page for WooCommerce, so it loads its
	 * checkout scripts for the order form.
	 *
	 * @param bool $is_checkout Is checkout.
	 * @return bool
	 */
	public static function is_checkout( $is_checkout ) {
		if ( ! is_admin() && did_action( 'wp' ) && is_singular( CARTIVA_COMMERCE_LANDING ) ) {
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
			$classes[] = 'woocommerce-checkout';
			$classes[] = 'woocommerce-page';
		}
		return $classes;
	}
}
