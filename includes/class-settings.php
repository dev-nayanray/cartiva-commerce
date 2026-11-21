<?php
/**
 * Settings page (WooCommerce > Cartiva Commerce) and the shared checkout
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
	 * Menu item under WooCommerce.
	 */
	public static function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Cartiva Commerce', 'cartiva-commerce' ),
			__( 'Cartiva Commerce', 'cartiva-commerce' ),
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
		if ( ! $ours && ! $landing ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-admin', CARTIVA_COMMERCE_URL . 'assets/css/admin.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-fields', CARTIVA_COMMERCE_URL . 'assets/js/admin-fields.js', array(), CARTIVA_COMMERCE_VERSION, true );
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
		$tab      = in_array( $tab, array( 'general', 'fields', 'tracking', 'recovery' ), true ) ? $tab : 'general';
		$settings = cartiva_commerce_settings();
		?>
		<div class="wrap cartiva-commerce-admin">
			<h1><?php esc_html_e( 'Cartiva Commerce', 'cartiva-commerce' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url( 'general' ) ); ?>" class="nav-tab<?php echo 'general' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( self::url( 'fields' ) ); ?>" class="nav-tab<?php echo 'fields' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Main checkout fields', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( self::url( 'recovery' ) ); ?>" class="nav-tab<?php echo 'recovery' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Recovery', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( self::url( 'tracking' ) ); ?>" class="nav-tab<?php echo 'tracking' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tracking', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL ) ); ?>" class="nav-tab"><?php esc_html_e( 'Funnels', 'cartiva-commerce' ); ?></a>
			</nav>
			<?php settings_errors(); ?>
			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
			if ( isset( $_GET['cartiva_switched'] ) ) :
				?>
				<div class="notice notice-success"><p><?php esc_html_e( 'Your checkout page now uses the classic checkout. The previous version is saved as a page revision.', 'cartiva-commerce' ); ?></p></div>
			<?php endif; ?>
			<form method="post" action="options.php">
				<?php settings_fields( 'cartiva_commerce' ); ?>
				<input type="hidden" name="cartiva_commerce_settings[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
				<?php
				if ( 'fields' === $tab ) {
					self::render_fields_tab( $settings );
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
		} elseif ( 'tracking' === $tab ) {
					self::render_tracking_tab( $settings );
				} else {
					self::render_general_tab( $settings );
				}
				submit_button();
				?>
			</form>
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
		<h2><?php esc_html_e( 'Funnels and landing pages', 'cartiva-commerce' ); ?></h2>
		<p><?php esc_html_e( 'A funnel leads shoppers through steps: a sales page, a checkout and a thank-you page. A single landing page can also sell on its own, with the order form on the same page.', 'cartiva-commerce' ); ?></p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( Cartiva_Commerce_Funnels::template_url() ); ?>"><?php esc_html_e( 'Create a funnel from the template', 'cartiva-commerce' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CARTIVA_COMMERCE_LANDING ) ); ?>"><?php esc_html_e( 'Add a landing page', 'cartiva-commerce' ); ?></a>
		</p>
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
		<p class="description"><?php esc_html_e( 'When shoppers enter their phone or email on an order form but do not order, you see them under Cartiva Funnels > Abandoned checkouts, with Call and WhatsApp buttons, and reminder emails go out with a link that puts their cart back.', 'cartiva-commerce' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Save abandoned checkouts', 'cartiva-commerce' ); ?></th>
				<td>
					<label><input type="checkbox" name="cartiva_commerce_settings[recovery]" value="1" <?php checked( ! empty( $settings['recovery'] ) ); ?> /> <?php esc_html_e( 'On landing page order forms', 'cartiva-commerce' ); ?></label><br>
					<label><input type="checkbox" name="cartiva_commerce_settings[recovery_main]" value="1" <?php checked( ! empty( $settings['recovery_main'] ) ); ?> /> <?php esc_html_e( 'Also on the main checkout (classic checkout only)', 'cartiva-commerce' ); ?></label>
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
