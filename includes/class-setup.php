<?php
/**
 * "Start here" page: store checks, create a landing page or funnel for a
 * product in one go, and the extras worth turning on. Written for store
 * owners who are not technical.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Setup page.
 */
class Cartiva_Commerce_Setup {

	const PAGE = 'cartiva-commerce-setup';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 9 );
		add_action( 'admin_init', array( __CLASS__, 'first_run_redirect' ) );
		add_action( 'admin_post_cartiva_commerce_quick_create', array( __CLASS__, 'quick_create' ) );
		add_filter( 'parent_file', array( __CLASS__, 'parent_file' ) );
	}

	/**
	 * Page URL.
	 *
	 * @return string
	 */
	public static function url() {
		return admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL . '&page=' . self::PAGE );
	}

	/**
	 * First item of the Cartiva Commerce menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL,
			__( 'Start here', 'cartiva-commerce' ),
			__( 'Start here', 'cartiva-commerce' ),
			'edit_pages',
			self::PAGE,
			array( __CLASS__, 'render' ),
			0
		);
	}

	/**
	 * Keep the menu open on our pages.
	 *
	 * @param string $file Parent file.
	 * @return string
	 */
	public static function parent_file( $file ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Menu highlight only.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return in_array( $page, array( self::PAGE, Cartiva_Commerce_Settings::PAGE ), true ) ? 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL : $file;
	}

	/**
	 * After activation, open the Start here page once.
	 */
	public static function first_run_redirect() {
		if ( ! get_option( 'cartiva_commerce_show_setup' ) ) {
			return;
		}
		delete_option( 'cartiva_commerce_show_setup' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Bulk activation check only.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Store checks: each is status (ok, warn, info), text and an optional fix link.
	 *
	 * @return array[]
	 */
	public static function checks() {
		$checks   = array();
		$products = wp_count_posts( 'product' );
		$count    = $products && isset( $products->publish ) ? (int) $products->publish : 0;
		$checks[] = $count
			/* translators: %d: number of products. */
			? array( 'ok', sprintf( _n( 'You have %d published product.', 'You have %d published products.', $count, 'cartiva-commerce' ), $count ), '', '' )
			: array( 'warn', __( 'Add a product first. Landing pages sell your WooCommerce products.', 'cartiva-commerce' ), admin_url( 'post-new.php?post_type=product' ), __( 'Add a product', 'cartiva-commerce' ) );

		$gateways = function_exists( 'WC' ) && WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$cod      = isset( $gateways['cod'] ) && 'yes' === $gateways['cod']->enabled;
		$checks[] = $cod
			? array( 'ok', __( 'Cash on delivery is on.', 'cartiva-commerce' ), '', '' )
			: array( 'warn', __( 'Cash on delivery is off. Most shoppers in Bangladesh pay on delivery.', 'cartiva-commerce' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cod' ), __( 'Turn it on', 'cartiva-commerce' ) );

		$checks[] = '' !== (string) get_option( 'permalink_structure' )
			? array( 'ok', __( 'Page links are readable (pretty permalinks).', 'cartiva-commerce' ), '', '' )
			: array( 'warn', __( 'Page links use ?p=123. Choose "Post name" so landing page links look clean.', 'cartiva-commerce' ), admin_url( 'options-permalink.php' ), __( 'Change it', 'cartiva-commerce' ) );

		$checks[] = 'cartiva' === get_template()
			? array( 'ok', __( 'The Cartiva theme is active, so new pages get ready-made designs.', 'cartiva-commerce' ), '', '' )
			: array( 'info', __( 'Works with any theme. The free Cartiva theme adds ready-made landing page designs.', 'cartiva-commerce' ), admin_url( 'theme-install.php?search=cartiva' ), __( 'See the theme', 'cartiva-commerce' ) );

		$checks[] = Cartiva_Commerce_Settings::main_checkout_is_block()
			? array( 'info', __( 'Your main checkout uses the Checkout block. Landing pages work fully; the extra checkout fields and recovery on the main checkout need the classic checkout.', 'cartiva-commerce' ), Cartiva_Commerce_Settings::url( 'fields' ), __( 'Learn more', 'cartiva-commerce' ) )
			: array( 'ok', __( 'Your main checkout works with every feature.', 'cartiva-commerce' ), '', '' );

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$checks[] = array( 'info', __( 'WordPress scheduled tasks run from your server (DISABLE_WP_CRON). Make sure the server job runs every few minutes, or recovery emails are late.', 'cartiva-commerce' ), '', '' );
		}
		return $checks;
	}

	/**
	 * Extras worth turning on: title, text, on?, link, button.
	 *
	 * @return array[]
	 */
	public static function extras() {
		$s = cartiva_commerce_settings();
		return array(
			array( __( 'Fake order protection', 'cartiva-commerce' ), __( 'Spot repeat orders from the same phone and block numbers that refused parcels.', 'cartiva-commerce' ), ! empty( $s['protect'] ), Cartiva_Commerce_Settings::url( 'protect' ), 'dashicons-shield' ),
			array( __( 'Abandoned order recovery', 'cartiva-commerce' ), __( 'See who left the form without ordering, call them or send a reminder with a coupon.', 'cartiva-commerce' ), ! empty( $s['recovery'] ), Cartiva_Commerce_Settings::url( 'recovery' ), 'dashicons-backup' ),
			array( __( 'Facebook, Google and TikTok tracking', 'cartiva-commerce' ), __( 'Measure your ads: page views, checkouts and purchases.', 'cartiva-commerce' ), '' !== $s['fb_pixel'] || '' !== $s['ga4_id'] || '' !== $s['tiktok_pixel'], Cartiva_Commerce_Settings::url( 'tracking' ), 'dashicons-chart-line' ),
			array( __( 'Slide-out cart and free shipping bar', 'cartiva-commerce' ), __( 'A cart that opens on the side, with a bar that shows how much more to spend.', 'cartiva-commerce' ), ! empty( $s['side_cart'] ), Cartiva_Commerce_Settings::url( 'cart' ), 'dashicons-cart' ),
		);
	}

	/**
	 * Latest landing pages and funnels.
	 *
	 * @return WP_Post[]
	 */
	public static function recent() {
		return get_posts(
			array(
				'post_type'      => array( CARTIVA_COMMERCE_LANDING, CARTIVA_COMMERCE_FUNNEL ),
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 6,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		$checks  = self::checks();
		$warn    = count(
			array_filter(
				$checks,
				function ( $check ) {
					return 'warn' === $check[0];
				}
			)
		);
		$created = get_transient( 'cartiva_setup_error_' . get_current_user_id() );
		if ( $created ) {
			delete_transient( 'cartiva_setup_error_' . get_current_user_id() );
		}
		?>
		<div class="wrap cartiva-commerce-admin cartiva-setup">
			<?php Cartiva_Commerce_Settings::header( __( 'Start here', 'cartiva-commerce' ) ); ?>
			<hr class="wp-header-end" />
			<?php if ( $created ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $created ); ?></p></div>
			<?php endif; ?>

			<div class="cartiva-setup__grid">
				<section class="cartiva-card cartiva-setup__create">
					<h2><span class="cartiva-step-num">1</span> <?php esc_html_e( 'Create a page that sells', 'cartiva-commerce' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Pick a product and how you want to sell it. Everything is set up for cash on delivery; you can change the text and design afterwards.', 'cartiva-commerce' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="cartiva_commerce_quick_create" />
						<?php wp_nonce_field( 'cartiva_commerce_quick_create' ); ?>

						<p class="cartiva-field">
							<label class="cartiva-field__label" for="cartiva-quick-product"><?php esc_html_e( 'Product to sell', 'cartiva-commerce' ); ?></label>
							<?php self::product_field( 'product', 'cartiva-quick-product', __( 'Product to sell', 'cartiva-commerce' ) ); ?>
						</p>

						<fieldset class="cartiva-field">
							<legend class="cartiva-field__label"><?php esc_html_e( 'What to create', 'cartiva-commerce' ); ?></legend>
							<div class="cartiva-choice-cards">
								<label class="cartiva-choice-card">
									<input type="radio" name="kind" value="landing" checked="checked" />
									<span class="dashicons dashicons-media-document" aria-hidden="true"></span>
									<strong><?php esc_html_e( 'Landing page', 'cartiva-commerce' ); ?></strong>
									<small><?php esc_html_e( 'One page: product details and the order form together. Best for Facebook ads.', 'cartiva-commerce' ); ?></small>
								</label>
								<label class="cartiva-choice-card">
									<input type="radio" name="kind" value="funnel" />
									<span class="dashicons dashicons-filter" aria-hidden="true"></span>
									<strong><?php esc_html_e( 'Funnel with upsell', 'cartiva-commerce' ); ?></strong>
									<small><?php esc_html_e( 'Sales page, then checkout, then a one-click extra offer, then thank you.', 'cartiva-commerce' ); ?></small>
								</label>
							</div>
						</fieldset>

						<p class="cartiva-field cartiva-quick-upsell">
							<label class="cartiva-field__label" for="cartiva-quick-upsell"><?php esc_html_e( 'Extra product to offer after the order (optional)', 'cartiva-commerce' ); ?></label>
							<?php self::product_field( 'upsell', 'cartiva-quick-upsell', __( 'Extra product to offer after the order', 'cartiva-commerce' ) ); ?>
							<span class="description"><?php esc_html_e( 'Used by the funnel. Buyers add it to their order with one click.', 'cartiva-commerce' ); ?></span>
						</p>

						<fieldset class="cartiva-field">
							<legend class="cartiva-field__label"><?php esc_html_e( 'Countdown timer', 'cartiva-commerce' ); ?></legend>
							<div class="cartiva-pills">
								<?php
								$timers = array(
									'0' => __( 'None', 'cartiva-commerce' ),
									/* translators: %d: number of days. */
									'3' => sprintf( _n( '%d day', '%d days', 3, 'cartiva-commerce' ), 3 ),
									/* translators: %d: number of days. */
									'5' => sprintf( _n( '%d day', '%d days', 5, 'cartiva-commerce' ), 5 ),
									/* translators: %d: number of days. */
									'7' => sprintf( _n( '%d day', '%d days', 7, 'cartiva-commerce' ), 7 ),
								);
								foreach ( $timers as $value => $label ) :
									?>
									<label class="cartiva-pill"><input type="radio" name="countdown" value="<?php echo esc_attr( $value ); ?>" <?php checked( '3', $value ); ?> /> <span><?php echo esc_html( $label ); ?></span></label>
								<?php endforeach; ?>
							</div>
							<span class="description"><?php esc_html_e( 'Each visitor gets their own timer from their first visit.', 'cartiva-commerce' ); ?></span>
						</fieldset>

						<div class="cartiva-field">
							<span class="cartiva-field__label"><?php esc_html_e( 'Options', 'cartiva-commerce' ); ?></span>
							<label class="cartiva-switch"><input type="checkbox" name="delivery" value="1" checked="checked" /> <span><?php esc_html_e( 'Delivery charge choice: Inside Dhaka / Outside Dhaka', 'cartiva-commerce' ); ?></span></label>
							<label class="cartiva-switch"><input type="checkbox" name="cod_only" value="1" checked="checked" /> <span><?php esc_html_e( 'Cash on delivery only on this page', 'cartiva-commerce' ); ?></span></label>
							<label class="cartiva-switch"><input type="checkbox" name="publish" value="1" /> <span><?php esc_html_e( 'Publish right away (otherwise it is saved as a draft to check first)', 'cartiva-commerce' ); ?></span></label>
						</div>

						<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Create it', 'cartiva-commerce' ); ?></button></p>
					</form>
				</section>

				<aside class="cartiva-setup__side">
					<section class="cartiva-card">
						<h2><span class="cartiva-step-num">2</span> <?php esc_html_e( 'Store check', 'cartiva-commerce' ); ?></h2>
						<p class="description">
							<?php
							echo esc_html(
								$warn
									/* translators: %d: number of things to fix. */
									? sprintf( _n( '%d thing to fix before you start selling.', '%d things to fix before you start selling.', $warn, 'cartiva-commerce' ), $warn )
									: __( 'All set. Your store is ready.', 'cartiva-commerce' )
							);
							?>
						</p>
						<ul class="cartiva-checklist">
							<?php foreach ( $checks as $check ) : ?>
								<li class="is-<?php echo esc_attr( $check[0] ); ?>">
									<span><?php echo esc_html( $check[1] ); ?></span>
									<?php if ( '' !== $check[2] ) : ?>
										<a href="<?php echo esc_url( $check[2] ); ?>"><?php echo esc_html( $check[3] ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>

					<section class="cartiva-card">
						<h2><span class="cartiva-step-num">3</span> <?php esc_html_e( 'Sell more', 'cartiva-commerce' ); ?></h2>
						<ul class="cartiva-extras">
							<?php foreach ( self::extras() as $extra ) : ?>
								<li>
									<span class="dashicons <?php echo esc_attr( $extra[4] ); ?>" aria-hidden="true"></span>
									<div>
										<strong><?php echo esc_html( $extra[0] ); ?></strong>
										<span class="cartiva-badge cartiva-badge--<?php echo $extra[2] ? 'on' : 'off'; ?>"><?php echo esc_html( $extra[2] ? __( 'On', 'cartiva-commerce' ) : __( 'Off', 'cartiva-commerce' ) ); ?></span>
										<p><?php echo esc_html( $extra[1] ); ?></p>
									</div>
									<a class="button" href="<?php echo esc_url( $extra[3] ); ?>"><?php echo esc_html( $extra[2] ? __( 'Settings', 'cartiva-commerce' ) : __( 'Set up', 'cartiva-commerce' ) ); ?></a>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				</aside>
			</div>

			<?php $recent = self::recent(); ?>
			<?php if ( $recent ) : ?>
				<section class="cartiva-card">
					<h2><?php esc_html_e( 'Your pages and funnels', 'cartiva-commerce' ); ?></h2>
					<table class="widefat striped cartiva-recent">
						<thead><tr>
							<th><?php esc_html_e( 'Name', 'cartiva-commerce' ); ?></th>
							<th><?php esc_html_e( 'Type', 'cartiva-commerce' ); ?></th>
							<th><?php esc_html_e( 'Status', 'cartiva-commerce' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Links', 'cartiva-commerce' ); ?></span></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $recent as $item ) : ?>
							<?php
							$is_funnel = CARTIVA_COMMERCE_FUNNEL === $item->post_type;
							$in_funnel = $is_funnel ? 0 : cartiva_commerce_funnel_of( $item->ID );
							$live      = 'publish' === $item->post_status;
							?>
							<tr>
								<td><a href="<?php echo esc_url( (string) get_edit_post_link( $item->ID ) ); ?>"><strong><?php echo esc_html( '' !== $item->post_title ? $item->post_title : __( '(no title)', 'cartiva-commerce' ) ); ?></strong></a></td>
								<td><?php echo esc_html( $is_funnel ? __( 'Funnel', 'cartiva-commerce' ) : ( $in_funnel ? __( 'Funnel step', 'cartiva-commerce' ) : __( 'Landing page', 'cartiva-commerce' ) ) ); ?></td>
								<td><span class="cartiva-badge cartiva-badge--<?php echo $live ? 'on' : 'off'; ?>"><?php echo esc_html( $live ? __( 'Live', 'cartiva-commerce' ) : __( 'Draft', 'cartiva-commerce' ) ); ?></span></td>
								<td class="cartiva-recent__links">
									<a href="<?php echo esc_url( (string) get_edit_post_link( $item->ID ) ); ?>"><?php esc_html_e( 'Edit', 'cartiva-commerce' ); ?></a>
									<?php if ( ! $is_funnel && $live ) : ?>
										| <a href="<?php echo esc_url( (string) get_permalink( $item ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'cartiva-commerce' ); ?></a>
									<?php endif; ?>
									| <a href="<?php echo esc_url( Cartiva_Commerce_Orders::list_url( ( $is_funnel ? 'f' : 'l' ) . $item->ID ) ); ?>"><?php esc_html_e( 'Orders', 'cartiva-commerce' ); ?></a>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</section>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Product picker (the same list as on landing pages).
	 *
	 * @param string $name  Field name.
	 * @param string $id    Field ID.
	 * @param string $label Label.
	 */
	private static function product_field( $name, $id, $label ) {
		ob_start();
		Cartiva_Commerce_Landing::product_select( $name, array(), $label );
		$html = (string) ob_get_clean();
		// Give the select its ID so the visible label points at it.
		echo preg_replace( '/^(\s*<select )/', '$1id="' . esc_attr( $id ) . '" ', $html, 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in product_select().
	}

	/**
	 * Landing page settings for a quick-created page.
	 *
	 * @param int   $product_id Product or variation ID.
	 * @param array $choices    Form choices: countdown days, delivery, cod_only.
	 * @param array $extra      More raw settings.
	 * @return array
	 */
	public static function landing_settings( $product_id, $choices, $extra = array() ) {
		$days = (int) $choices['countdown'];
		$raw  = array_merge(
			array(
				'offer_mode'   => 'single',
				'qty_editable' => 1,
				'offers'       => $product_id ? array(
					array(
						'item'    => $product_id,
						'default' => 1,
					),
				) : array(),
				'preset'       => 'bd_cod',
				'bd_phone'     => 1,
				'gateways'     => ! empty( $choices['cod_only'] ) ? array( 'cod' ) : array(),
				'countdown'    => array(
					'enabled' => $days > 0 ? 1 : 0,
					'mode'    => 'evergreen',
					'days'    => $days > 0 ? $days : 3,
					'ended'   => 'restart',
					'bar'     => 1,
					'form'    => 1,
				),
				'delivery'     => array(
					'enabled' => ! empty( $choices['delivery'] ) ? 1 : 0,
					'options' => Cartiva_Commerce_COD::default_options(),
				),
			),
			$extra
		);
		return Cartiva_Commerce_Landing::sanitize( $raw );
	}

	/**
	 * Create the landing page or funnel (admin-post action).
	 */
	public static function quick_create() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		check_admin_referer( 'cartiva_commerce_quick_create' );
		$product_id = isset( $_POST['product'] ) ? absint( wp_unslash( $_POST['product'] ) ) : 0;
		$upsell_id  = isset( $_POST['upsell'] ) ? absint( wp_unslash( $_POST['upsell'] ) ) : 0;
		$kind       = isset( $_POST['kind'] ) && 'funnel' === $_POST['kind'] ? 'funnel' : 'landing';
		$countdown  = isset( $_POST['countdown'] ) ? absint( wp_unslash( $_POST['countdown'] ) ) : 0;
		$choices    = array(
			'countdown' => in_array( $countdown, array( 3, 5, 7 ), true ) ? $countdown : 0,
			'delivery'  => ! empty( $_POST['delivery'] ),
			'cod_only'  => ! empty( $_POST['cod_only'] ),
		);
		$status     = ! empty( $_POST['publish'] ) ? 'publish' : 'draft';
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			set_transient( 'cartiva_setup_error_' . get_current_user_id(), __( 'Please choose the product to sell.', 'cartiva-commerce' ), MINUTE_IN_SECONDS * 5 );
			wp_safe_redirect( self::url() );
			exit;
		}
		$name = wp_strip_all_tags( $product->get_name() );
		if ( 'landing' === $kind ) {
			$id = self::insert_page( $name, Cartiva_Commerce_Landing::default_content(), $status, self::landing_settings( $product_id, $choices ), $product );
			Cartiva_Commerce_Landing::keep_notices();
			wp_safe_redirect( admin_url( 'post.php?post=' . (int) $id . '&action=edit' ) );
			exit;
		}

		$funnel_id = wp_insert_post(
			array(
				'post_type'   => CARTIVA_COMMERCE_FUNNEL,
				'post_status' => 'publish',
				/* translators: %s: product name. */
				'post_title'  => sprintf( __( '%s funnel', 'cartiva-commerce' ), $name ),
			),
			true
		);
		if ( is_wp_error( $funnel_id ) ) {
			wp_die( esc_html( $funnel_id->get_error_message() ) );
		}
		$upsell = $upsell_id ? Cartiva_Commerce_Landing::resolve_single( $upsell_id ) : null;
		$plan   = array(
			/* translators: %s: product name. */
			'landing'  => array( sprintf( __( '%s: sales page', 'cartiva-commerce' ), $name ), self::landing_settings( 0, array_merge( $choices, array( 'delivery' => false ) ) ) ),
			/* translators: %s: product name. */
			'checkout' => array( sprintf( __( '%s: checkout', 'cartiva-commerce' ), $name ), self::landing_settings( $product_id, $choices ) ),
		);
		if ( $upsell ) {
			$plan['upsell'] = array(
				/* translators: %s: product name. */
				sprintf( __( '%s: special offer', 'cartiva-commerce' ), $name ),
				self::landing_settings(
					0,
					array_merge( $choices, array( 'countdown' => 0 ) ),
					array(
						'offer' => array(
							'item'     => $upsell_id,
							'qty'      => 1,
							'discount' => 10,
						),
					)
				),
			);
		}
		/* translators: %s: product name. */
		$plan['thankyou'] = array( sprintf( __( '%s: thank you', 'cartiva-commerce' ), $name ), self::landing_settings( 0, array_merge( $choices, array( 'countdown' => 0 ) ) ) );
		$steps            = array();
		foreach ( $plan as $type => $page ) {
			$step_id = self::insert_page( $page[0], Cartiva_Commerce_Funnels::template_content( $type ), $status, $page[1], 'landing' === $type || 'checkout' === $type ? $product : null );
			if ( $step_id ) {
				$steps[] = array(
					'id'   => $step_id,
					'type' => $type,
				);
			}
		}
		Cartiva_Commerce_Funnels::set_steps( (int) $funnel_id, $steps );
		Cartiva_Commerce_Landing::keep_notices();
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $funnel_id . '&action=edit&cartiva_created=1' ) );
		exit;
	}

	/**
	 * Insert a landing page with its settings.
	 *
	 * @param string          $title    Title.
	 * @param string          $content  Block markup.
	 * @param string          $status   Post status.
	 * @param array           $settings Clean settings.
	 * @param WC_Product|null $product  Product for the featured image.
	 * @return int Page ID (0 on failure).
	 */
	private static function insert_page( $title, $content, $status, $settings, $product = null ) {
		$id = wp_insert_post(
			array(
				'post_type'    => CARTIVA_COMMERCE_LANDING,
				'post_status'  => $status,
				'post_title'   => $title,
				'post_content' => $content,
			),
			true
		);
		if ( is_wp_error( $id ) || ! $id ) {
			return 0;
		}
		update_post_meta( $id, '_cartiva_landing', $settings );
		if ( $product && $product->get_image_id() ) {
			set_post_thumbnail( $id, $product->get_image_id() );
		}
		return (int) $id;
	}
}
