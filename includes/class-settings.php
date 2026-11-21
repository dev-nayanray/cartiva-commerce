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
		$tab      = isset( $_GET['tab'] ) && 'fields' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'fields' : 'general';
		$settings = cartiva_commerce_settings();
		?>
		<div class="wrap cartiva-commerce-admin">
			<h1><?php esc_html_e( 'Cartiva Commerce', 'cartiva-commerce' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( self::url( 'general' ) ); ?>" class="nav-tab<?php echo 'general' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', 'cartiva-commerce' ); ?></a>
				<a href="<?php echo esc_url( self::url( 'fields' ) ); ?>" class="nav-tab<?php echo 'fields' === $tab ? ' nav-tab-active' : ''; ?>"><?php esc_html_e( 'Main checkout fields', 'cartiva-commerce' ); ?></a>
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
