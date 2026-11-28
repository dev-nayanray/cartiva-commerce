<?php
/**
 * Settings page (Cartiva Commerce > Settings) and the shared checkout
 * field editor used here and on each landing page.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings.
 */
class Cartiva_Commerce_Settings {

	const PAGE = 'cartiva-commerce';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 60 );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_cartiva_commerce_classic_checkout', array( __CLASS__, 'switch_to_classic_checkout' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CARTIVA_COMMERCE_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'cartiva-commerce' ) . '</a>' );
		return $links;
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $tab Tab.
	 * @return string
	 */
	public static function url( $tab = 'general' ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Menu item in the Cartiva Commerce menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL,
			__( 'Cartiva Commerce settings', 'cartiva-commerce' ),
			__( 'Settings', 'cartiva-commerce' ),
			'manage_woocommerce',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Register the option.
	 */
	public static function register() {
		register_setting(
			'cartiva_commerce',
			'cartiva_commerce_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Clean submitted settings. Each tab only sends its own values, so the
	 * other tab's saved values are kept.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input  = is_array( $input ) ? $input : array();
		$output = cartiva_commerce_settings();
		$tab    = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : 'general';
		if ( 'fields' === $tab ) {
			$presets               = Cartiva_Commerce_Fields::presets();
			$preset                = isset( $input['main']['preset'] ) ? sanitize_key( $input['main']['preset'] ) : 'default';
			$output['main_preset'] = isset( $presets[ $preset ] ) ? $preset : 'default';
			$output['main_fields'] = Cartiva_Commerce_Fields::sanitize_config( isset( $input['main']['fields'] ) ? $input['main']['fields'] : array() );
			$output['main_bd_phone'] = ! empty( $input['main_bd_phone'] );
			$output['main_coupon']   = ! empty( $input['main_coupon'] );
		} elseif ( 'cart' === $tab ) {
			$output['side_cart']      = ! empty( $input['side_cart'] );
			$output['side_cart_open'] = ! empty( $input['side_cart_open'] );
			$output['side_cart_btn']  = ! empty( $input['side_cart_btn'] );
			$output['side_cart_link'] = ! empty( $input['side_cart_link'] );
			$output['ship_bar']       = ! empty( $input['ship_bar'] );
			$output['ship_min']       = isset( $input['ship_min'] ) ? max( 0, round( (float) $input['ship_min'], 2 ) ) : 0;
			$output['cart_recs']      = ! empty( $input['cart_recs'] );
			$deals                    = array();
			foreach ( isset( $input['deals'] ) && is_array( $input['deals'] ) ? $input['deals'] : array() as $row ) {
				$buy  = is_array( $row ) ? Cartiva_Commerce_Landing::resolve_item( isset( $row['item'] ) ? $row['item'] : 0 ) : null;
				$gift = is_array( $row ) ? Cartiva_Commerce_Landing::resolve_item( isset( $row['gift'] ) ? $row['gift'] : 0 ) : null;
				if ( ! $buy || ! $gift || count( $deals ) >= 20 ) {
					continue;
				}
				$deals[] = array(
					'active'            => ! empty( $row['active'] ),
					'product_id'        => $buy['product_id'],
					'variation_id'      => $buy['variation_id'],
					'buy_qty'           => isset( $row['buy_qty'] ) ? max( 1, min( 99, absint( $row['buy_qty'] ) ) ) : 1,
					'gift_product_id'   => $gift['product_id'],
					'gift_variation_id' => $gift['variation_id'],
					'gift_qty'          => isset( $row['gift_qty'] ) ? max( 1, min( 99, absint( $row['gift_qty'] ) ) ) : 1,
					'discount'          => isset( $row['discount'] ) ? (float) max( 1, min( 100, round( (float) $row['discount'], 1 ) ) ) : 100.0,
					'repeat'            => ! empty( $row['repeat'] ),
				);
			}
			$output['deals'] = $deals;
			$output['main_bumps'] = Cartiva_Commerce_Main_Bumps::sanitize( isset( $input['main_bumps'] ) ? $input['main_bumps'] : array() );
		} elseif ( 'recovery' === $tab ) {
			$output['recovery']       = ! empty( $input['recovery'] );
			$output['recovery_main']  = ! empty( $input['recovery_main'] );
			$output['recovery_after'] = isset( $input['recovery_after'] ) ? max( 10, min( 1440, absint( $input['recovery_after'] ) ) ) : 30;
			$output['recovery_keep']  = isset( $input['recovery_keep'] ) ? max( 1, min( 365, absint( $input['recovery_keep'] ) ) ) : 30;
			$output['recovery_note']  = isset( $input['recovery_note'] ) ? sanitize_text_field( $input['recovery_note'] ) : '';
			$output['coupon_percent'] = isset( $input['coupon_percent'] ) ? max( 1, min( 90, absint( $input['coupon_percent'] ) ) ) : 10;
			$output['coupon_days']    = isset( $input['coupon_days'] ) ? max( 1, min( 60, absint( $input['coupon_days'] ) ) ) : 3;
			$output['whatsapp_text']  = isset( $input['whatsapp_text'] ) ? sanitize_textarea_field( $input['whatsapp_text'] ) : '';
			$emails                   = array();
			foreach ( Cartiva_Commerce_Recovery::default_emails() as $i => $default ) {
				$row          = isset( $input['emails'][ $i ] ) && is_array( $input['emails'][ $i ] ) ? $input['emails'][ $i ] : array();
				$emails[ $i ] = array(
					'enabled' => ! empty( $row['enabled'] ),
					'hours'   => isset( $row['hours'] ) ? max( 0.5, min( 720, round( (float) $row['hours'], 1 ) ) ) : $default['hours'],
					'subject' => isset( $row['subject'] ) && '' !== trim( $row['subject'] ) ? sanitize_text_field( $row['subject'] ) : $default['subject'],
					'body'    => isset( $row['body'] ) && '' !== trim( $row['body'] ) ? sanitize_textarea_field( $row['body'] ) : $default['body'],
					'coupon'  => ! empty( $row['coupon'] ),
				);
			}
			$output['emails'] = $emails;
		} elseif ( 'courier' === $tab ) {
			$text = function ( $key ) use ( $input ) {
				return isset( $input[ $key ] ) ? trim( sanitize_text_field( $input[ $key ] ) ) : '';
			};
			$default                    = isset( $input['courier_default'] ) ? sanitize_key( $input['courier_default'] ) : '';
			$output['courier_default']  = isset( Cartiva_Commerce_Courier::providers()[ $default ] ) ? $default : '';
			$output['courier_weight']   = isset( $input['courier_weight'] ) ? max( 0.1, min( 30, round( (float) $input['courier_weight'], 2 ) ) ) : 0.5;
			$output['courier_note']     = $text( 'courier_note' );
			$output['courier_complete'] = ! empty( $input['courier_complete'] );
			$output['courier_cancel']   = ! empty( $input['courier_cancel'] );
			foreach ( array( 'sf_key', 'sf_secret', 'pa_client_id', 'pa_client_secret', 'pa_username', 'pa_password', 'rx_token' ) as $key ) {
				$output[ $key ] = $text( $key );
			}
			$output['pa_store_id']  = isset( $input['pa_store_id'] ) ? absint( $input['pa_store_id'] ) : 0;
			$output['pa_sandbox']   = ! empty( $input['pa_sandbox'] );
			$output['rx_sandbox']   = ! empty( $input['rx_sandbox'] );
			$output['rx_store_id']  = isset( $input['rx_store_id'] ) ? absint( $input['rx_store_id'] ) : 0;
			$output['rx_area_id']   = isset( $input['rx_area_id'] ) ? absint( $input['rx_area_id'] ) : 0;
			$output['rx_area_name'] = $text( 'rx_area_name' );
			if ( '' === (string) $output['sf_webhook_token'] ) {
				$output['sf_webhook_token'] = wp_generate_password( 32, false );
			}
		} elseif ( 'protect' === $tab ) {
			$mode                      = isset( $input['protect_mode'] ) ? sanitize_key( $input['protect_mode'] ) : 'flag';
			$output['protect']         = ! empty( $input['protect'] );
			$output['protect_main']    = ! empty( $input['protect_main'] );
			$output['protect_mode']    = 'block' === $mode ? 'block' : 'flag';
			$output['protect_hours']   = isset( $input['protect_hours'] ) ? min( 720, absint( $input['protect_hours'] ) ) : 24;
			$output['protect_max']     = isset( $input['protect_max'] ) ? min( 50, absint( $input['protect_max'] ) ) : 0;
			$output['protect_block']   = isset( $input['protect_block'] ) ? sanitize_textarea_field( $input['protect_block'] ) : '';
			$output['protect_message'] = isset( $input['protect_message'] ) ? sanitize_text_field( $input['protect_message'] ) : '';
		} elseif ( 'tracking' === $tab ) {
			$fb                     = isset( $input['fb_pixel'] ) ? preg_replace( '/\D/', '', (string) $input['fb_pixel'] ) : '';
			$ga                     = isset( $input['ga4_id'] ) ? strtoupper( trim( (string) $input['ga4_id'] ) ) : '';
			$tt                     = isset( $input['tiktok_pixel'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['tiktok_pixel'] ) ) : '';
			$output['fb_pixel']     = strlen( $fb ) >= 10 && strlen( $fb ) <= 20 ? $fb : '';
			$output['ga4_id']       = preg_match( '/^G-[A-Z0-9]{4,15}$/', $ga ) ? $ga : '';
			$output['tiktok_pixel'] = strlen( $tt ) >= 10 && strlen( $tt ) <= 30 ? $tt : '';
			$output['track_store']  = ! empty( $input['track_store'] );
			foreach ( array( 'fb_pixel' => 'Facebook Pixel ID', 'ga4_id' => 'GA4 Measurement ID', 'tiktok_pixel' => 'TikTok Pixel ID' ) as $field => $name ) {
				if ( ! empty( $input[ $field ] ) && '' === $output[ $field ] ) {
					/* translators: %s: field name such as Facebook Pixel ID. */
					add_settings_error( 'cartiva_commerce_settings', 'cartiva_' . $field, sprintf( __( 'The %s does not look right, so it was not saved.', 'cartiva-commerce' ), $name ) );
				}
			}
		} else {
			$output['buy_now']       = ! empty( $input['buy_now'] );
			$output['buy_now_label'] = isset( $input['buy_now_label'] ) ? sanitize_text_field( $input['buy_now_label'] ) : '';
		}
		return $output;
	}

	/**
	 * Admin assets on our screens.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function assets( $hook ) {
		$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$ours    = false !== strpos( (string) $hook, self::PAGE );
		$landing = $screen && in_array( $screen->post_type, array( CARTIVA_COMMERCE_LANDING, CARTIVA_COMMERCE_FUNNEL ), true );
		if ( $screen && in_array( $screen->id, array( 'woocommerce_page_wc-orders', 'edit-shop_order', 'shop_order' ), true ) ) {
			// Orders screens: only the Source badges.
			wp_enqueue_style( 'cartiva-commerce-admin', CARTIVA_COMMERCE_URL . 'assets/css/admin.css', array(), CARTIVA_COMMERCE_VERSION );
			return;
		}
		if ( ! $ours && ! $landing ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-admin', CARTIVA_COMMERCE_URL . 'assets/css/admin.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-fields', CARTIVA_COMMERCE_URL . 'assets/js/admin-fields.js', array(), CARTIVA_COMMERCE_VERSION, true );
		if ( $ours ) {
			wp_enqueue_script( 'wc-enhanced-select' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_script( 'cartiva-commerce-admin-landing', CARTIVA_COMMERCE_URL . 'assets/js/admin-landing.js', array( 'jquery', 'wc-enhanced-select' ), CARTIVA_COMMERCE_VERSION, true );
		}
	}

	/**
	 * Is the main checkout page using WooCommerce's Checkout block?
	 *
	 * @return bool
	 */
	public static function main_checkout_is_block() {
		$page_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'checkout' ) : 0;
		return $page_id > 0 && has_block( 'woocommerce/checkout', $page_id );
	}

	/**
	 * Switch the main checkout page to the classic checkout (button on the
	 * Main checkout tab). WordPress keeps the old content as a revision.
	 */
	public static function switch_to_classic_checkout() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		check_admin_referer( 'cartiva_commerce_classic_checkout' );
		$page_id = (int) wc_get_page_id( 'checkout' );
		if ( $page_id > 0 ) {
			wp_update_post(
				array(
					'ID'           => $page_id,
					'post_content' => '<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->',
				)
			);
		}
		wp_safe_redirect( add_query_arg( 'cartiva_switched', '1', self::url( 'fields' ) ) );
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab switch only.
		$tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$tabs     = self::tabs();
		$tab      = isset( $tabs[ $tab ] ) ? $tab : 'general';
		$settings = cartiva_commerce_settings();
		?>
		<div class="wrap cartiva-commerce-admin">
			<?php self::header( __( 'Settings', 'cartiva-commerce' ) ); ?>
			<nav class="cartiva-settings-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'cartiva-commerce' ); ?>">
				<?php foreach ( $tabs as $key => $info ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="cartiva-settings-nav__item<?php echo $key === $tab ? ' is-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>>
						<span class="dashicons <?php echo esc_attr( $info[2] ); ?>" aria-hidden="true"></span>
						<strong><?php echo esc_html( $info[0] ); ?></strong>
						<small><?php echo esc_html( $info[1] ); ?></small>
					</a>
				<?php endforeach; ?>
			</nav>
			<hr class="wp-header-end" />
			<?php settings_errors(); ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			if ( isset( $_GET['cartiva_switched'] ) ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Your checkout page now uses the classic checkout. The previous version is saved as a page revision.', 'cartiva-commerce' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php" class="cartiva-settings-form">
				<?php settings_fields( 'cartiva_commerce' ); ?>
				<input type="hidden" name="cartiva_commerce_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
				<?php
				if ( 'fields' === $tab ) {
					self::render_fields_tab( $settings );
				} elseif ( 'protect' === $tab ) {
					self::render_protect_tab( $settings );
				} elseif ( 'courier' === $tab ) {
					self::render_courier_tab( $settings );
				} elseif ( 'cart' === $tab ) {
					self::render_cart_tab( $settings );
				} elseif ( 'recovery' === $tab ) {
					self::render_recovery_tab( $settings );
				} elseif ( 'tracking' === $tab ) {
					self::render_tracking_tab( $settings );
				} else {
					self::render_general_tab( $settings );
				}
				?>
				<div class="cartiva-save-bar"><?php submit_button( __( 'Save changes', 'cartiva-commerce' ), 'primary large', 'submit', false ); ?></div>
			</form>
		</div>
		<?php
	}

	/**
	 * Settings sections: key => label, short description, icon.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'general'  => array( __( 'General', 'cartiva-commerce' ), __( 'Buy now button', 'cartiva-commerce' ), 'dashicons-admin-generic' ),
			'fields'   => array( __( 'Checkout form', 'cartiva-commerce' ), __( 'Fields of the main checkout', 'cartiva-commerce' ), 'dashicons-feedback' ),
			'protect'  => array( __( 'Fake order protection', 'cartiva-commerce' ), __( 'Repeat orders, blocked numbers', 'cartiva-commerce' ), 'dashicons-shield' ),
			'courier'  => array( __( 'Courier', 'cartiva-commerce' ), __( 'Steadfast, Pathao, RedX', 'cartiva-commerce' ), 'dashicons-car' ),
			'cart'     => array( __( 'Cart & deals', 'cartiva-commerce' ), __( 'Slide-out cart, free shipping, gifts', 'cartiva-commerce' ), 'dashicons-cart' ),
			'recovery' => array( __( 'Recovery', 'cartiva-commerce' ), __( 'Win back unfinished orders', 'cartiva-commerce' ), 'dashicons-backup' ),
			'tracking' => array( __( 'Tracking', 'cartiva-commerce' ), __( 'Facebook, Google, TikTok', 'cartiva-commerce' ), 'dashicons-chart-line' ),
		);
	}

	/**
	 * Page header shared by the plugin screens.
	 *
	 * @param string $title Screen title.
	 */
	public static function header( $title ) {
		?>
		<div class="cartiva-admin-header">
			<div class="cartiva-admin-header__brand">
				<span class="cartiva-admin-header__logo dashicons dashicons-filter" aria-hidden="true"></span>
				<div>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p><?php /* translators: %s: version number. */ echo esc_html( sprintf( __( 'Cartiva Commerce %s', 'cartiva-commerce' ), CARTIVA_COMMERCE_VERSION ) ); ?></p>
				</div>
			</div>
			<nav class="cartiva-admin-header__links" aria-label="<?php esc_attr_e( 'Cartiva Commerce', 'cartiva-commerce' ); ?>">
				<a href="<?php echo esc_url( Cartiva_Commerce_Setup::url() ); ?>"><?php esc_html_e( 'Start here', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_LANDING ) ); ?>"><?php esc_html_e( 'Landing pages', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL ) ); ?>"><?php esc_html_e( 'Funnels', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( Cartiva_Commerce_Orders::list_url( 'cartiva' ) ); ?>"><?php esc_html_e( 'Orders', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( self::url() ); ?>"><?php esc_html_e( 'Settings', 'cartiva-commerce' ); ?></a>
			</nav>
		</div>
		<?php
	}

	/**
	 * A text field row of the courier tab.
	 *
	 * @param array  $settings Settings.
	 * @param string $key      Setting.
	 * @param string $label    Label.
	 * @param string $type     Input type.
	 * @param string $help     Help text.
	 */
	private static function courier_field( $settings, $key, $label, $type = 'text', $help = '' ) {
		$id = 'cartiva-' . str_replace( '_', '-', $key );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
		echo '<input id="' . esc_attr( $id ) . '" type="' . esc_attr( $type ) . '" class="regular-text" autocomplete="off" name="cartiva_commerce_settings[' . esc_attr( $key ) . ']" value="' . esc_attr( 'number' === $type && ! $settings[ $key ] ? '' : (string) $settings[ $key ] ) . '" />';
		if ( '' !== $help ) {
			echo '<p class="description">' . esc_html( $help ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Courier tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_courier_tab( $settings ) {
		$ready = Cartiva_Commerce_Courier::ready();
		?>
		<div class="cartiva-card">
			<h2><?php esc_html_e( 'Send orders to your courier', 'cartiva-commerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Connect the courier you use. Then send an order from its order screen, or tick many orders in WooCommerce > Orders and choose "Send to ..." from Bulk actions. The parcel number, status and a tracking link are saved on the order and shown to the customer.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cartiva-courier-default"><?php esc_html_e( 'Main courier', 'cartiva-commerce' ); ?></label></th>
					<td>
						<select id="cartiva-courier-default" name="cartiva_commerce_settings[courier_default]">
							<?php foreach ( Cartiva_Commerce_Courier::providers() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['courier_default'], $key ); ?>><?php echo esc_html( $label . ( isset( $ready[ $key ] ) ? '' : ' - ' . __( 'not connected', 'cartiva-commerce' ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Chosen first on the order screen.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-courier-weight"><?php esc_html_e( 'Usual parcel weight (kg)', 'cartiva-commerce' ); ?></label></th>
					<td><input id="cartiva-courier-weight" type="number" min="0.1" max="30" step="0.1" class="small-text" name="cartiva_commerce_settings[courier_weight]" value="<?php echo esc_attr( (string) $settings['courier_weight'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-courier-note"><?php esc_html_e( 'Note for every parcel', 'cartiva-commerce' ); ?></label></th>
					<td><input id="cartiva-courier-note" type="text" class="regular-text" name="cartiva_commerce_settings[courier_note]" value="<?php echo esc_attr( (string) $settings['courier_note'] ); ?>" placeholder="<?php esc_attr_e( 'For example: Call before delivery', 'cartiva-commerce' ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Order status', 'cartiva-commerce' ); ?></th>
					<td>
						<label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[courier_complete]" value="1" <?php checked( ! empty( $settings['courier_complete'] ) ); ?> /> <span><?php esc_html_e( 'Mark the order Completed when the courier delivers it', 'cartiva-commerce' ); ?></span></label>
						<label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[courier_cancel]" value="1" <?php checked( ! empty( $settings['courier_cancel'] ) ); ?> /> <span><?php esc_html_e( 'Mark the order Cancelled when the parcel is returned', 'cartiva-commerce' ); ?></span></label>
						<p class="description"><?php esc_html_e( 'Parcel status is checked every hour.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
			</table>
		</div>

		<div class="cartiva-card cartiva-courier-card">
			<h2>Steadfast <?php self::courier_badge( isset( $ready['steadfast'] ) ); ?></h2>
			<p class="description"><?php esc_html_e( 'Find the API key and secret in the Steadfast merchant panel under API. Steadfast also powers the customer delivery history check (received and refused parcels) on the order screen.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				self::courier_field( $settings, 'sf_key', __( 'API key', 'cartiva-commerce' ) );
				self::courier_field( $settings, 'sf_secret', __( 'Secret key', 'cartiva-commerce' ), 'password' );
				?>
				<?php if ( '' !== (string) $settings['sf_webhook_token'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Instant status updates (optional)', 'cartiva-commerce' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'In the Steadfast panel, add this webhook so status changes arrive at once instead of hourly:', 'cartiva-commerce' ); ?></p>
							<p><?php esc_html_e( 'Callback URL', 'cartiva-commerce' ); ?>: <code><?php echo esc_html( Cartiva_Commerce_Courier::webhook_url() ); ?></code></p>
							<p><?php esc_html_e( 'Auth token (Bearer)', 'cartiva-commerce' ); ?>: <code><?php echo esc_html( (string) $settings['sf_webhook_token'] ); ?></code></p>
						</td>
					</tr>
				<?php endif; ?>
			</table>
			<p><button type="button" class="button" data-cartiva-courier-test="steadfast"><?php esc_html_e( 'Test connection', 'cartiva-commerce' ); ?></button> <span class="cartiva-courier-test-result" role="status"><?php esc_html_e( 'Save your changes first; the test uses the saved details.', 'cartiva-commerce' ); ?></span></p>
		</div>

		<div class="cartiva-card cartiva-courier-card">
			<h2>Pathao <?php self::courier_badge( isset( $ready['pathao'] ) ); ?></h2>
			<p class="description"><?php esc_html_e( 'In the Pathao merchant panel open Developer API to get the client ID and secret. Use the email and password you log in with. Save, click Test connection, and copy your store ID from the answer.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				self::courier_field( $settings, 'pa_client_id', __( 'Client ID', 'cartiva-commerce' ) );
				self::courier_field( $settings, 'pa_client_secret', __( 'Client secret', 'cartiva-commerce' ), 'password' );
				self::courier_field( $settings, 'pa_username', __( 'Login email', 'cartiva-commerce' ), 'email' );
				self::courier_field( $settings, 'pa_password', __( 'Login password', 'cartiva-commerce' ), 'password' );
				self::courier_field( $settings, 'pa_store_id', __( 'Store ID', 'cartiva-commerce' ), 'number', __( 'The pickup store parcels are collected from.', 'cartiva-commerce' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test mode', 'cartiva-commerce' ); ?></th>
					<td><label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[pa_sandbox]" value="1" <?php checked( ! empty( $settings['pa_sandbox'] ) ); ?> /> <span><?php esc_html_e( 'Use the Pathao sandbox (no real parcels)', 'cartiva-commerce' ); ?></span></label></td>
				</tr>
			</table>
			<p><button type="button" class="button" data-cartiva-courier-test="pathao"><?php esc_html_e( 'Test connection', 'cartiva-commerce' ); ?></button> <span class="cartiva-courier-test-result" role="status"><?php esc_html_e( 'Save your changes first; the test uses the saved details.', 'cartiva-commerce' ); ?></span></p>
		</div>

		<div class="cartiva-card cartiva-courier-card">
			<h2>RedX <?php self::courier_badge( isset( $ready['redx'] ) ); ?></h2>
			<p class="description"><?php esc_html_e( 'Get the API access token from the RedX merchant panel (Developer API). RedX needs a delivery area for every parcel; it is guessed from the address and can be changed on the order screen.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<?php
				self::courier_field( $settings, 'rx_token', __( 'API access token', 'cartiva-commerce' ), 'password' );
				self::courier_field( $settings, 'rx_store_id', __( 'Pickup store ID (optional)', 'cartiva-commerce' ), 'number' );
				self::courier_field( $settings, 'rx_area_id', __( 'Default area ID (optional)', 'cartiva-commerce' ), 'number', __( 'Used when the area cannot be guessed.', 'cartiva-commerce' ) );
				self::courier_field( $settings, 'rx_area_name', __( 'Default area name (optional)', 'cartiva-commerce' ) );
				?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Test mode', 'cartiva-commerce' ); ?></th>
					<td><label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[rx_sandbox]" value="1" <?php checked( ! empty( $settings['rx_sandbox'] ) ); ?> /> <span><?php esc_html_e( 'Use the RedX sandbox (no real parcels)', 'cartiva-commerce' ); ?></span></label></td>
				</tr>
			</table>
			<p><button type="button" class="button" data-cartiva-courier-test="redx"><?php esc_html_e( 'Test connection', 'cartiva-commerce' ); ?></button> <span class="cartiva-courier-test-result" role="status"><?php esc_html_e( 'Save your changes first; the test uses the saved details.', 'cartiva-commerce' ); ?></span></p>
		</div>
		<?php
	}

	/**
	 * Connected / not connected badge.
	 *
	 * @param bool $on Connected.
	 */
	private static function courier_badge( $on ) {
		echo '<span class="cartiva-badge cartiva-badge--' . ( $on ? 'on' : 'off' ) . '">' . esc_html( $on ? __( 'Connected', 'cartiva-commerce' ) : __( 'Not connected', 'cartiva-commerce' ) ) . '</span>';
	}

	/**
	 * Fake order protection tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_protect_tab( $settings ) {
		?>
		<div class="cartiva-card">
			<h2><?php esc_html_e( 'Stop fake and repeat cash on delivery orders', 'cartiva-commerce' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Checks the phone number of each new order. Orders that look like repeats are either marked "Check before shipping" for you to call, or refused with a message.', 'cartiva-commerce' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Protection', 'cartiva-commerce' ); ?></th>
					<td>
						<label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[protect]" value="1" <?php checked( ! empty( $settings['protect'] ) ); ?> /> <span><?php esc_html_e( 'Check orders from landing pages and funnels', 'cartiva-commerce' ); ?></span></label>
						<label class="cartiva-switch"><input type="checkbox" name="cartiva_commerce_settings[protect_main]" value="1" <?php checked( ! empty( $settings['protect_main'] ) ); ?> /> <span><?php esc_html_e( 'Also check orders from the main checkout', 'cartiva-commerce' ); ?></span></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-protect-hours"><?php esc_html_e( 'Repeat orders', 'cartiva-commerce' ); ?></label></th>
					<td>
						<?php
						printf(
							/* translators: %s: number field. */
							esc_html__( 'Same phone number ordered in the last %s hours', 'cartiva-commerce' ),
							'<input id="cartiva-protect-hours" type="number" min="0" max="720" class="small-text" name="cartiva_commerce_settings[protect_hours]" value="' . esc_attr( (string) (int) $settings['protect_hours'] ) . '" />'
						);
						?>
						<p class="description"><?php esc_html_e( '0 turns this check off.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-protect-max"><?php esc_html_e( 'Daily limit', 'cartiva-commerce' ); ?></label></th>
					<td>
						<?php
						printf(
							/* translators: %s: number field. */
							esc_html__( 'At most %s orders per phone number in 24 hours', 'cartiva-commerce' ),
							'<input id="cartiva-protect-max" type="number" min="0" max="50" class="small-text" name="cartiva_commerce_settings[protect_max]" value="' . esc_attr( (string) (int) $settings['protect_max'] ) . '" />'
						);
						?>
						<p class="description"><?php esc_html_e( '0 means no limit.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'When an order looks like a repeat', 'cartiva-commerce' ); ?></th>
					<td>
						<label class="cartiva-block-label"><input type="radio" name="cartiva_commerce_settings[protect_mode]" value="flag" <?php checked( $settings['protect_mode'], 'flag' ); ?> /> <?php esc_html_e( 'Accept it, but mark it "Check before shipping" (recommended)', 'cartiva-commerce' ); ?></label>
						<label class="cartiva-block-label"><input type="radio" name="cartiva_commerce_settings[protect_mode]" value="block" <?php checked( $settings['protect_mode'], 'block' ); ?> /> <?php esc_html_e( 'Refuse it and show the message below', 'cartiva-commerce' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-protect-block"><?php esc_html_e( 'Blocked numbers', 'cartiva-commerce' ); ?></label></th>
					<td>
						<textarea id="cartiva-protect-block" class="large-text code" rows="4" name="cartiva_commerce_settings[protect_block]" placeholder="01XXXXXXXXX"><?php echo esc_textarea( (string) $settings['protect_block'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'One phone number per line. Orders from these numbers are always refused, for example customers who refused parcels before.', 'cartiva-commerce' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cartiva-protect-message"><?php esc_html_e( 'Message to the shopper', 'cartiva-commerce' ); ?></label></th>
					<td>
						<input id="cartiva-protect-message" type="text" class="large-text" name="cartiva_commerce_settings[protect_message]" value="<?php echo esc_attr( (string) $settings['protect_message'] ); ?>" placeholder="<?php esc_attr_e( 'We already have an order from this phone number. We will call you to confirm it. To change your order, please call us.', 'cartiva-commerce' ); ?>" />
					</td>
				</tr>
			</table>
			<p class="description"><?php esc_html_e( 'Flagged orders show "Check" in the Source column of the Orders list and a note on the order.', 'cartiva-commerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * General tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_general_tab( $settings ) {
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Buy now button', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[buy_now]" value="1" <?php checked( ! empty( $settings['buy_now'] ) ); ?> /> <?php esc_html_e( 'Show a Buy now button under Add to cart that goes straight to checkout', 'cartiva-commerce' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-buy-now-label"><?php esc_html_e( 'Buy now label', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input type="text" class="regular-text" id="cartiva-buy-now-label" name="cartiva_commerce_settings[buy_now_label]" value="<?php echo esc_attr( (string) $settings['buy_now_label'] ); ?>" placeholder="<?php esc_attr_e( 'Buy now', 'cartiva-commerce' ); ?>" />
					<p class="description"><?php esc_html_e( 'For example: Order now. Leave empty for "Buy now".', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>
		<div class="cartiva-card cartiva-card--hint">
			<h2><?php esc_html_e( 'Funnels and landing pages', 'cartiva-commerce' ); ?></h2>
			<p><?php esc_html_e( 'A landing page sells one product with the order form on the same page. A funnel leads shoppers through steps: a sales page, a checkout, an optional upsell and a thank-you page.', 'cartiva-commerce' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( Cartiva_Commerce_Setup::url() ); ?>"><?php esc_html_e( 'Create one in the Start here page', 'cartiva-commerce' ); ?></a></p>
		</div>
		<?php
	}

	/**
	 * One deal row.
	 *
	 * @param string $i    Row index or __i__.
	 * @param array  $deal Deal.
	 */
	public static function deal_row( $i, $deal ) {
		$deal = wp_parse_args(
			$deal,
			array(
				'active'            => true,
				'product_id'        => 0,
				'variation_id'      => 0,
				'buy_qty'           => 1,
				'gift_product_id'   => 0,
				'gift_variation_id' => 0,
				'gift_qty'          => 1,
				'discount'          => 100,
				'repeat'            => true,
			)
		);
		$base = 'cartiva_commerce_settings[deals][' . $i . ']';
		?>
		<tr>
			<td><input type="checkbox" name="<?php echo esc_attr( $base . '[active]' ); ?>" value="1" <?php checked( ! empty( $deal['active'] ) ); ?> aria-label="<?php esc_attr_e( 'Active', 'cartiva-commerce' ); ?>" /></td>
			<td>
				<input type="number" min="1" max="99" class="small-text" name="<?php echo esc_attr( $base . '[buy_qty]' ); ?>" value="<?php echo esc_attr( (string) $deal['buy_qty'] ); ?>" aria-label="<?php esc_attr_e( 'Buy quantity', 'cartiva-commerce' ); ?>" /> &times;
				<?php Cartiva_Commerce_Landing::product_select( $base . '[item]', $deal, __( 'Product to buy', 'cartiva-commerce' ) ); ?>
			</td>
			<td>
				<input type="number" min="1" max="99" class="small-text" name="<?php echo esc_attr( $base . '[gift_qty]' ); ?>" value="<?php echo esc_attr( (string) $deal['gift_qty'] ); ?>" aria-label="<?php esc_attr_e( 'Gift quantity', 'cartiva-commerce' ); ?>" /> &times;
				<?php
				Cartiva_Commerce_Landing::product_select(
					$base . '[gift]',
					array(
						'product_id'   => $deal['gift_product_id'],
						'variation_id' => $deal['gift_variation_id'],
					),
					__( 'Gift product', 'cartiva-commerce' )
				);
				?>
			</td>
			<td><input type="number" min="1" max="100" step="0.5" class="small-text" name="<?php echo esc_attr( $base . '[discount]' ); ?>" value="<?php echo esc_attr( (string) $deal['discount'] ); ?>" aria-label="<?php esc_attr_e( 'Gift discount %', 'cartiva-commerce' ); ?>" /> %</td>
			<td><input type="checkbox" name="<?php echo esc_attr( $base . '[repeat]' ); ?>" value="1" <?php checked( ! empty( $deal['repeat'] ) ); ?> aria-label="<?php esc_attr_e( 'Repeat for every set', 'cartiva-commerce' ); ?>" /></td>
			<td><button type="button" class="button-link cartiva-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'cartiva-commerce' ); ?>">&times;</button></td>
		</tr>
		<?php
	}

	/**
	 * Cart & deals tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_cart_tab( $settings ) {
		?>
		<h2><?php esc_html_e( 'Slide-out cart', 'cartiva-commerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Cart drawer', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[side_cart]" value="1" <?php checked( ! empty( $settings['side_cart'] ) ); ?> /> <?php esc_html_e( 'Use the slide-out cart', 'cartiva-commerce' ); ?></label><br>
					<label><input type="checkbox" name="cartiva_commerce_settings[side_cart_open]" value="1" <?php checked( ! empty( $settings['side_cart_open'] ) ); ?> /> <?php esc_html_e( 'Open it when a product is added to the cart', 'cartiva-commerce' ); ?></label><br>
					<label><input type="checkbox" name="cartiva_commerce_settings[side_cart_btn]" value="1" <?php checked( ! empty( $settings['side_cart_btn'] ) ); ?> /> <?php esc_html_e( 'Show a floating cart button in the corner', 'cartiva-commerce' ); ?></label><br>
					<label><input type="checkbox" name="cartiva_commerce_settings[side_cart_link]" value="1" <?php checked( ! empty( $settings['side_cart_link'] ) ); ?> /> <?php esc_html_e( 'Open it from the cart links and mini cart in your header', 'cartiva-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Shoppers change quantities, remove products and see suggestions without leaving the page. The drawer is not shown on the cart, checkout and landing pages.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Suggestions', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[cart_recs]" value="1" <?php checked( ! empty( $settings['cart_recs'] ) ); ?> /> <?php esc_html_e( 'Show "You may also like" in the drawer', 'cartiva-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Uses the Cross-sells you set on each product (Product data > Linked Products).', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Free shipping bar', 'cartiva-commerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Progress bar', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[ship_bar]" value="1" <?php checked( ! empty( $settings['ship_bar'] ) ); ?> /> <?php esc_html_e( 'Show "Add X more for free shipping" in the drawer and on the cart and checkout pages', 'cartiva-commerce' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-ship-min"><?php esc_html_e( 'Free shipping from', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input id="cartiva-ship-min" type="number" min="0" step="any" class="small-text" name="cartiva_commerce_settings[ship_min]" value="<?php echo esc_attr( (string) $settings['ship_min'] ); ?>" /> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
					<p class="description"><?php esc_html_e( 'Leave 0 to use the minimum order amount of the Free shipping method in the shopper\'s shipping zone (WooCommerce > Settings > Shipping). If you enter an amount, make sure a Free shipping method with the same minimum exists, so the promise matches the checkout.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Buy X, get Y deals', 'cartiva-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'When the cart has enough of a product, the gift product is added by itself at the discount you set (100% = free), and removed again if the shopper buys less. For "Buy 1, get 1 free" pick the same product on both sides. With "Repeat", every full set earns a gift (buy 2 get 1: 4 bought = 2 gifts).', 'cartiva-commerce' ); ?></p>
		<div class="cartiva-repeater" data-cartiva-repeater="deals">
			<table class="widefat cartiva-rows">
				<thead><tr>
					<th><?php esc_html_e( 'On', 'cartiva-commerce' ); ?></th>
					<th><?php esc_html_e( 'Buy', 'cartiva-commerce' ); ?></th>
					<th><?php esc_html_e( 'Get', 'cartiva-commerce' ); ?></th>
					<th><?php esc_html_e( 'Gift discount', 'cartiva-commerce' ); ?></th>
					<th><?php esc_html_e( 'Repeat', 'cartiva-commerce' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'cartiva-commerce' ); ?></span></th>
				</tr></thead>
				<tbody>
					<?php
					foreach ( (array) $settings['deals'] as $i => $deal ) {
						self::deal_row( (string) $i, (array) $deal );
					}
					?>
				</tbody>
			</table>
			<template><?php self::deal_row( '__i__', array() ); ?></template>
			<p><button type="button" class="button cartiva-add-row"><?php esc_html_e( 'Add deal', 'cartiva-commerce' ); ?></button></p>
		</div>

		<h2><?php esc_html_e( 'Order bumps on the main checkout', 'cartiva-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Up to three "Yes, add this to my order" boxes above the payment methods of your normal checkout page. Landing pages have their own bumps in the Extras tab.', 'cartiva-commerce' ); ?></p>
		<?php if ( self::main_checkout_is_block() ) : ?>
			<p class="cartiva-callout"><?php esc_html_e( 'Your checkout page uses the Checkout block, which does not show these boxes. Switch to the classic checkout in the Checkout form tab to use them.', 'cartiva-commerce' ); ?></p>
		<?php endif; ?>
		<?php
		$bumps = Cartiva_Commerce_Main_Bumps::rows();
		for ( $i = 0; $i < Cartiva_Commerce_Main_Bumps::MAX; $i++ ) :
			$bump = wp_parse_args(
				isset( $bumps[ $i ] ) ? $bumps[ $i ] : array(),
				array(
					'active'       => false,
					'product_id'   => 0,
					'variation_id' => 0,
					'discount'     => 10,
					'title'        => '',
					'text'         => '',
					'min_total'    => 0,
					'when_id'      => 0,
				)
			);
			$base = 'cartiva_commerce_settings[main_bumps][' . $i . ']';
			/* translators: %d: bump number. */
			$label = sprintf( __( 'Bump %d', 'cartiva-commerce' ), $i + 1 );
			?>
			<fieldset class="cartiva-main-bump-admin">
				<legend><?php echo esc_html( $label ); ?></legend>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Show it', 'cartiva-commerce' ); ?></th>
						<td><label class="cartiva-switch"><input type="checkbox" name="<?php echo esc_attr( $base . '[active]' ); ?>" value="1" <?php checked( ! empty( $bump['active'] ) ); ?> /> <span><?php esc_html_e( 'On', 'cartiva-commerce' ); ?></span></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Product to offer', 'cartiva-commerce' ); ?></th>
						<td><?php Cartiva_Commerce_Landing::product_select( $base . '[item]', $bump, __( 'Product to offer', 'cartiva-commerce' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( 'cartiva-mb-discount-' . $i ); ?>"><?php esc_html_e( 'Discount', 'cartiva-commerce' ); ?></label></th>
						<td><input id="<?php echo esc_attr( 'cartiva-mb-discount-' . $i ); ?>" type="number" min="0" max="90" step="0.5" class="small-text" name="<?php echo esc_attr( $base . '[discount]' ); ?>" value="<?php echo esc_attr( (string) $bump['discount'] ); ?>" /> %</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( 'cartiva-mb-title-' . $i ); ?>"><?php esc_html_e( 'Headline', 'cartiva-commerce' ); ?></label></th>
						<td><input id="<?php echo esc_attr( 'cartiva-mb-title-' . $i ); ?>" type="text" class="regular-text" name="<?php echo esc_attr( $base . '[title]' ); ?>" value="<?php echo esc_attr( $bump['title'] ); ?>" placeholder="<?php esc_attr_e( 'Yes, add this to my order', 'cartiva-commerce' ); ?>" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( 'cartiva-mb-text-' . $i ); ?>"><?php esc_html_e( 'Short text', 'cartiva-commerce' ); ?></label></th>
						<td><textarea id="<?php echo esc_attr( 'cartiva-mb-text-' . $i ); ?>" class="large-text" rows="2" name="<?php echo esc_attr( $base . '[text]' ); ?>"><?php echo esc_textarea( $bump['text'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Only when the cart has', 'cartiva-commerce' ); ?></th>
						<td>
							<?php Cartiva_Commerce_Landing::product_select( $base . '[when]', array( 'product_id' => $bump['when_id'] ), __( 'Only when the cart has', 'cartiva-commerce' ) ); ?>
							<p class="description"><?php esc_html_e( 'Leave empty to show it with any cart.', 'cartiva-commerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="<?php echo esc_attr( 'cartiva-mb-min-' . $i ); ?>"><?php esc_html_e( 'Minimum cart total', 'cartiva-commerce' ); ?></label></th>
						<td><input id="<?php echo esc_attr( 'cartiva-mb-min-' . $i ); ?>" type="number" min="0" step="any" class="small-text" name="<?php echo esc_attr( $base . '[min_total]' ); ?>" value="<?php echo esc_attr( $bump['min_total'] ? (string) $bump['min_total'] : '' ); ?>" /> <?php echo esc_html( get_woocommerce_currency_symbol() ); ?></td>
					</tr>
				</table>
			</fieldset>
		<?php endfor; ?>

		<h2><?php esc_html_e( 'Coupons that apply themselves', 'cartiva-commerce' ); ?></h2>
		<p><?php esc_html_e( 'Open a coupon under Marketing > Coupons and tick "Apply automatically". It is then added to the cart as soon as the cart meets the coupon\'s usage restrictions, for example "10% off orders over 2000" with a minimum spend. Shoppers who remove it do not get it again in that visit.', 'cartiva-commerce' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_coupon' ) ); ?>"><?php esc_html_e( 'Go to coupons', 'cartiva-commerce' ); ?></a></p>
		<?php
	}

	/**
	 * Recovery tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_recovery_tab( $settings ) {
		$emails = Cartiva_Commerce_Recovery::emails();
		?>
		<p class="description"><?php esc_html_e( 'When shoppers enter their phone or email on an order form but do not order, you see them under Cartiva Commerce > Abandoned checkouts, with Call and WhatsApp buttons, and reminder emails go out with a link that puts their cart back.', 'cartiva-commerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Save abandoned checkouts', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[recovery]" value="1" <?php checked( ! empty( $settings['recovery'] ) ); ?> /> <?php esc_html_e( 'On landing page order forms', 'cartiva-commerce' ); ?></label><br>
					<label><input type="checkbox" name="cartiva_commerce_settings[recovery_main]" value="1" <?php checked( ! empty( $settings['recovery_main'] ) ); ?> /> <?php esc_html_e( 'Also on the main checkout', 'cartiva-commerce' ); ?></label>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-recovery-after"><?php esc_html_e( 'Abandoned after', 'cartiva-commerce' ); ?></label></th>
				<td><input id="cartiva-recovery-after" type="number" min="10" max="1440" class="small-text" name="cartiva_commerce_settings[recovery_after]" value="<?php echo esc_attr( (string) $settings['recovery_after'] ); ?>" /> <?php esc_html_e( 'minutes without an order', 'cartiva-commerce' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-recovery-keep"><?php esc_html_e( 'Delete entries after', 'cartiva-commerce' ); ?></label></th>
				<td><input id="cartiva-recovery-keep" type="number" min="1" max="365" class="small-text" name="cartiva_commerce_settings[recovery_keep]" value="<?php echo esc_attr( (string) $settings['recovery_keep'] ); ?>" /> <?php esc_html_e( 'days', 'cartiva-commerce' ); ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-recovery-note"><?php esc_html_e( 'Notice on the form', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input id="cartiva-recovery-note" type="text" class="large-text" name="cartiva_commerce_settings[recovery_note]" value="<?php echo esc_attr( (string) $settings['recovery_note'] ); ?>" placeholder="<?php esc_attr_e( 'We save the details you enter here so we can help you if your order is not finished.', 'cartiva-commerce' ); ?>" />
					<p class="description"><?php esc_html_e( 'Shown under the details, so shoppers know their details are saved. Mention it in your privacy policy too.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Reminder emails', 'cartiva-commerce' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Sent to shoppers who entered an email, in this order, counted from when the checkout became abandoned. They stop as soon as the shopper orders or clicks "Stop these reminders". Placeholders: {name} {products} {total} {button} {link} {coupon} {discount} {expires} {site}', 'cartiva-commerce' ); ?></p>
		<?php foreach ( $emails as $i => $email ) : ?>
			<?php $base = 'cartiva_commerce_settings[emails][' . $i . ']'; ?>
			<fieldset class="cartiva-email-box">
				<legend>
					<label><input type="checkbox" name="<?php echo esc_attr( $base . '[enabled]' ); ?>" value="1" <?php checked( ! empty( $email['enabled'] ) ); ?> />
					<?php
					/* translators: %d: email number. */
					echo esc_html( sprintf( __( 'Email %d', 'cartiva-commerce' ), $i + 1 ) );
					?>
					</label>
				</legend>
				<p>
					<label><?php esc_html_e( 'Send after', 'cartiva-commerce' ); ?> <input type="number" min="0.5" max="720" step="0.5" class="small-text" name="<?php echo esc_attr( $base . '[hours]' ); ?>" value="<?php echo esc_attr( (string) $email['hours'] ); ?>" /> <?php esc_html_e( 'hours', 'cartiva-commerce' ); ?></label>
					<label class="cartiva-inline"><input type="checkbox" name="<?php echo esc_attr( $base . '[coupon]' ); ?>" value="1" <?php checked( ! empty( $email['coupon'] ) ); ?> /> <?php esc_html_e( 'Include a one-time coupon', 'cartiva-commerce' ); ?></label>
				</p>
				<p><label class="cartiva-block-label"><?php esc_html_e( 'Subject', 'cartiva-commerce' ); ?><br><input type="text" class="large-text" name="<?php echo esc_attr( $base . '[subject]' ); ?>" value="<?php echo esc_attr( $email['subject'] ); ?>" /></label></p>
				<p><label class="cartiva-block-label"><?php esc_html_e( 'Message', 'cartiva-commerce' ); ?><br><textarea class="large-text" rows="6" name="<?php echo esc_attr( $base . '[body]' ); ?>"><?php echo esc_textarea( $email['body'] ); ?></textarea></label></p>
			</fieldset>
		<?php endforeach; ?>

		<h2><?php esc_html_e( 'Coupon', 'cartiva-commerce' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cartiva-coupon-percent"><?php esc_html_e( 'Discount', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input id="cartiva-coupon-percent" type="number" min="1" max="90" class="small-text" name="cartiva_commerce_settings[coupon_percent]" value="<?php echo esc_attr( (string) $settings['coupon_percent'] ); ?>" /> %
					<label class="cartiva-inline"><?php esc_html_e( 'valid for', 'cartiva-commerce' ); ?> <input type="number" min="1" max="60" class="small-text" name="cartiva_commerce_settings[coupon_days]" value="<?php echo esc_attr( (string) $settings['coupon_days'] ); ?>" /> <?php esc_html_e( 'days', 'cartiva-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Each shopper gets their own code, usable once, only with their email address. It is also applied automatically when they open the reminder link.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'WhatsApp message', 'cartiva-commerce' ); ?></h2>
		<p><textarea class="large-text" rows="3" name="cartiva_commerce_settings[whatsapp_text]" aria-label="<?php esc_attr_e( 'WhatsApp message', 'cartiva-commerce' ); ?>" placeholder="<?php echo esc_attr( Cartiva_Commerce_Recovery::default_whatsapp() ); ?>"><?php echo esc_textarea( (string) $settings['whatsapp_text'] ); ?></textarea></p>
		<p class="description"><?php esc_html_e( 'Filled in when you click WhatsApp in the list, ready to send from your phone or WhatsApp Web. Placeholders: {name} {products} {total} {link} {site}', 'cartiva-commerce' ); ?></p>
		<?php
	}

	/**
	 * Tracking tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_tracking_tab( $settings ) {
		$fields = array(
			'fb_pixel'     => array( __( 'Facebook (Meta) Pixel ID', 'cartiva-commerce' ), '123456789012345', __( 'Events Manager > Data sources > your pixel. Numbers only.', 'cartiva-commerce' ) ),
			'ga4_id'       => array( __( 'Google Analytics 4 Measurement ID', 'cartiva-commerce' ), 'G-XXXXXXXXXX', __( 'Admin > Data streams > your web stream.', 'cartiva-commerce' ) ),
			'tiktok_pixel' => array( __( 'TikTok Pixel ID', 'cartiva-commerce' ), 'C1A2B3C4D5E6F7G8H9I0', __( 'TikTok Ads Manager > Assets > Events > Web events.', 'cartiva-commerce' ) ),
		);
		?>
		<p class="description"><?php esc_html_e( 'Send funnel events to your ad and analytics accounts: page view, product view (ViewContent / view_item), checkout start, order bump added (AddToCart) and Purchase with the final order value after upsells. Purchase is sent once per order. Leave a field empty to turn that service off.', 'cartiva-commerce' ); ?></p>
		<table class="form-table" role="presentation">
			<?php foreach ( $fields as $key => $field ) : ?>
				<tr>
					<th scope="row"><label for="cartiva-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="cartiva-<?php echo esc_attr( $key ); ?>" name="cartiva_commerce_settings[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( (string) $settings[ $key ] ); ?>" placeholder="<?php echo esc_attr( $field[1] ); ?>" autocomplete="off" />
						<p class="description"><?php echo esc_html( $field[2] ); ?></p>
					</td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Where', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[track_store]" value="1" <?php checked( ! empty( $settings['track_store'] ) ); ?> /> <?php esc_html_e( 'Also track the rest of the store (product pages, main checkout and order received page)', 'cartiva-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'Leave this off if another plugin already adds these pixels to your store, so events are not counted twice. Funnel and landing pages are always tracked.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>
		<p class="description"><?php esc_html_e( 'Privacy: the pixels load services from Meta, Google and TikTok in your visitors\' browsers. Mention them in your privacy policy. With a consent plugin that supports the WP Consent API, pixels load only after visitors accept marketing cookies.', 'cartiva-commerce' ); ?></p>
		<?php
	}

	/**
	 * Main checkout fields tab.
	 *
	 * @param array $settings Settings.
	 */
	private static function render_fields_tab( $settings ) {
		if ( self::main_checkout_is_block() ) :
			?>
			<div class="notice notice-warning inline cartiva-commerce-callout">
				<p><strong><?php esc_html_e( 'Your checkout page uses the WooCommerce Checkout block.', 'cartiva-commerce' ); ?></strong></p>
				<p><?php esc_html_e( 'The Checkout block does not allow changing or removing its built-in fields, so these settings only apply to the classic checkout. Landing page forms always support them. You can switch your main checkout page to the classic checkout below; the current version is kept as a page revision.', 'cartiva-commerce' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cartiva_commerce_classic_checkout' ), 'cartiva_commerce_classic_checkout' ) ); ?>"><?php esc_html_e( 'Use the classic checkout', 'cartiva-commerce' ); ?></a></p>
			</div>
			<?php
		endif;
		?>
		<p class="description"><?php esc_html_e( 'These fields apply to your main checkout page only. Landing pages have their own fields, so changes here never affect them, and the other way around.', 'cartiva-commerce' ); ?></p>
		<?php self::fields_editor( 'cartiva_commerce_settings[main]', (string) $settings['main_preset'], (array) $settings['main_fields'], Cartiva_Commerce_Fields::presets() ); ?>
		<p>
			<label><input type="checkbox" name="cartiva_commerce_settings[main_bd_phone]" value="1" <?php checked( ! empty( $settings['main_bd_phone'] ) ); ?> /> <?php esc_html_e( 'Check that the phone is a valid Bangladeshi mobile number (01XXXXXXXXX)', 'cartiva-commerce' ); ?></label>
		</p>
		<p>
			<input type="hidden" name="cartiva_commerce_settings[main_coupon]" value="0" />
			<label><input type="checkbox" name="cartiva_commerce_settings[main_coupon]" value="1" <?php checked( ! empty( $settings['main_coupon'] ) ); ?> /> <?php esc_html_e( 'Show the "Have a coupon?" link on the main checkout', 'cartiva-commerce' ); ?></label>
		</p>
		<?php
	}

	/**
	 * Checkout field editor: preset picker and a row per field.
	 *
	 * @param string $name    Input name prefix.
	 * @param string $preset  Selected preset.
	 * @param array  $custom  Saved custom configuration.
	 * @param array  $presets Presets to offer.
	 */
	public static function fields_editor( $name, $preset, $custom, $presets ) {
		$defs = Cartiva_Commerce_Fields::definitions();
		if ( ! isset( $presets[ $preset ] ) ) {
			$preset = (string) key( $presets );
		}
		$preset_data = array();
		foreach ( array_keys( $presets ) as $key ) {
			if ( 'custom' === $key ) {
				continue;
			}
			$config = Cartiva_Commerce_Fields::config_for( $key );
			if ( null === $config ) {
				$config = array();
				foreach ( $defs as $field_key => $def ) {
					$config[ $field_key ] = array(
						'enabled'     => $def['enabled'],
						'required'    => $def['required'],
						'label'       => $def['label'],
						'placeholder' => '',
						'width'       => 'half' === $def['width'] ? ( 'billing_last_name' === $field_key ? 'last' : 'first' ) : 'full',
						'priority'    => $def['priority'],
					);
				}
			}
			$preset_data[ $key ] = $config;
		}
		$current = 'custom' === $preset ? Cartiva_Commerce_Fields::sanitize_config( $custom ) : $preset_data[ $preset ];
		$widths  = array(
			'full'  => __( 'Full width', 'cartiva-commerce' ),
			'first' => __( 'Left half', 'cartiva-commerce' ),
			'last'  => __( 'Right half', 'cartiva-commerce' ),
		);
		?>
		<div class="cartiva-fields" data-presets="<?php echo esc_attr( (string) wp_json_encode( $preset_data ) ); ?>">
			<p>
				<label><strong><?php esc_html_e( 'Form fields', 'cartiva-commerce' ); ?></strong><br />
					<select name="<?php echo esc_attr( $name ); ?>[preset]" class="cartiva-fields__preset">
						<?php foreach ( $presets as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $preset, $key ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p class="description cartiva-fields__hint"><?php esc_html_e( 'Pick a preset, or change any field below to make it your own. Full name replaces First and Last name. Hidden fields are filled in automatically where needed (for example, the country is your store country).', 'cartiva-commerce' ); ?></p>
			<table class="widefat striped cartiva-fields__table">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Field', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Show', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Required', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Label', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Placeholder', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Width', 'cartiva-commerce' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Order', 'cartiva-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $defs as $key => $def ) :
						$row  = isset( $current[ $key ] ) ? $current[ $key ] : array();
						$base = $name . '[fields][' . $key . ']';
						?>
						<tr data-field="<?php echo esc_attr( $key ); ?>">
							<th scope="row"><?php echo esc_html( $def['label'] ); ?><br /><code><?php echo esc_html( $key ); ?></code></th>
							<td><input type="checkbox" data-prop="enabled" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field name. */ __( 'Show %s', 'cartiva-commerce' ), $def['label'] ) ); ?>" /></td>
							<td><input type="checkbox" data-prop="required" name="<?php echo esc_attr( $base ); ?>[required]" value="1" <?php checked( ! empty( $row['required'] ) ); ?> aria-label="<?php echo esc_attr( sprintf( /* translators: %s: field name. */ __( '%s is required', 'cartiva-commerce' ), $def['label'] ) ); ?>" /></td>
							<td><input type="text" data-prop="label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( isset( $row['label'] ) ? $row['label'] : $def['label'] ); ?>" /></td>
							<td><input type="text" data-prop="placeholder" name="<?php echo esc_attr( $base ); ?>[placeholder]" value="<?php echo esc_attr( isset( $row['placeholder'] ) ? $row['placeholder'] : '' ); ?>" /></td>
							<td>
								<select data-prop="width" name="<?php echo esc_attr( $base ); ?>[width]">
									<?php foreach ( $widths as $width => $label ) : ?>
										<option value="<?php echo esc_attr( $width ); ?>" <?php selected( isset( $row['width'] ) ? $row['width'] : 'full', $width ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
							<td><input type="number" min="1" max="999" step="1" class="small-text" data-prop="priority" name="<?php echo esc_attr( $base ); ?>[priority]" value="<?php echo esc_attr( (string) ( isset( $row['priority'] ) ? $row['priority'] : $def['priority'] ) ); ?>" /></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
