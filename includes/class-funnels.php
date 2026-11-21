<?php
/**
 * Funnels: an ordered list of steps (landing pages) such as a sales page, a
 * checkout and a thank-you page, with a ready-made template and results.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Funnels.
 */
class Cartiva_Commerce_Funnels {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 19 );
		add_action( 'add_meta_boxes_' . CARTIVA_COMMERCE_FUNNEL, array( __CLASS__, 'meta_boxes' ) );
		add_action( 'save_post_' . CARTIVA_COMMERCE_FUNNEL, array( __CLASS__, 'save' ), 10, 2 );
		add_action( 'admin_post_cartiva_commerce_new_funnel', array( __CLASS__, 'create_from_template' ) );
		add_action( 'admin_notices', array( __CLASS__, 'list_intro' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'next_step_links' ), 20 );
		add_filter( 'manage_' . CARTIVA_COMMERCE_FUNNEL . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CARTIVA_COMMERCE_FUNNEL . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'before_delete_post', array( __CLASS__, 'forget_steps' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'forget_steps' ) );
	}

	/**
	 * Step types.
	 *
	 * @return array
	 */
	public static function step_types() {
		return array(
			'landing'  => __( 'Sales page', 'cartiva-commerce' ),
			'checkout' => __( 'Checkout', 'cartiva-commerce' ),
			'upsell'   => __( 'Upsell', 'cartiva-commerce' ),
			'downsell' => __( 'Downsell', 'cartiva-commerce' ),
			'thankyou' => __( 'Thank you', 'cartiva-commerce' ),
		);
	}

	/**
	 * Register the post type (admin only: shoppers see the steps).
	 */
	public static function register() {
		register_post_type(
			CARTIVA_COMMERCE_FUNNEL,
			array(
				'labels'          => array(
					'name'               => __( 'Funnels', 'cartiva-commerce' ),
					'singular_name'      => __( 'Funnel', 'cartiva-commerce' ),
					'add_new'            => __( 'Add empty funnel', 'cartiva-commerce' ),
					'add_new_item'       => __( 'Add funnel', 'cartiva-commerce' ),
					'edit_item'          => __( 'Edit funnel', 'cartiva-commerce' ),
					'new_item'           => __( 'New funnel', 'cartiva-commerce' ),
					'search_items'       => __( 'Search funnels', 'cartiva-commerce' ),
					'not_found'          => __( 'No funnels yet.', 'cartiva-commerce' ),
					'not_found_in_trash' => __( 'No funnels in the trash.', 'cartiva-commerce' ),
					'all_items'          => __( 'Funnels', 'cartiva-commerce' ),
					'menu_name'          => __( 'Cartiva Funnels', 'cartiva-commerce' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_icon'       => 'dashicons-filter',
				'menu_position'   => 56,
				'capability_type' => 'page',
				'map_meta_cap'    => true,
				'supports'        => array( 'title' ),
			)
		);
	}

	/**
	 * Link that creates a funnel from the template.
	 *
	 * @return string
	 */
	public static function template_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cartiva_commerce_new_funnel' ), 'cartiva_commerce_new_funnel' );
	}

	/**
	 * Link that creates a funnel with an upsell and a downsell.
	 *
	 * @return string
	 */
	public static function template_upsell_url() {
		return add_query_arg( 'with_offers', '1', self::template_url() );
	}

	/**
	 * Admin script for the steps table.
	 *
	 * @param string $hook Screen hook.
	 */
	public static function admin_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && CARTIVA_COMMERCE_FUNNEL === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_script( 'cartiva-commerce-admin-landing', CARTIVA_COMMERCE_URL . 'assets/js/admin-landing.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, true );
		}
	}

	/**
	 * A short guide above the funnels list.
	 */
	public static function list_intro() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . CARTIVA_COMMERCE_FUNNEL !== $screen->id || ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		?>
		<div class="notice notice-info cartiva-commerce-callout">
			<p><strong><?php esc_html_e( 'Start with the ready-made funnel', 'cartiva-commerce' ); ?></strong></p>
			<p><?php esc_html_e( 'It adds three draft steps: a sales page, a checkout with the order form, and a thank-you page. Pick the product on the checkout step, check the text, then publish the steps.', 'cartiva-commerce' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( self::template_url() ); ?>"><?php esc_html_e( 'Create a funnel from the template', 'cartiva-commerce' ); ?></a>
				<a class="button" href="<?php echo esc_url( self::template_upsell_url() ); ?>"><?php esc_html_e( 'Template with upsell and downsell', 'cartiva-commerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Meta boxes.
	 */
	public static function meta_boxes() {
		add_meta_box( 'cartiva-funnel-steps', __( 'Steps', 'cartiva-commerce' ), array( __CLASS__, 'render_steps' ), CARTIVA_COMMERCE_FUNNEL, 'normal', 'high' );
		add_meta_box( 'cartiva-funnel-results', __( 'Results', 'cartiva-commerce' ), array( __CLASS__, 'render_results' ), CARTIVA_COMMERCE_FUNNEL, 'side' );
	}

	/**
	 * Landing pages that can be steps: ID => title (with status).
	 *
	 * @return array
	 */
	public static function step_choices() {
		$posts   = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_LANDING,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$choices = array();
		foreach ( $posts as $post ) {
			$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'cartiva-commerce' );
			if ( 'publish' !== $post->post_status ) {
				/* translators: 1: page title, 2: status such as Draft. */
				$title = sprintf( __( '%1$s (%2$s)', 'cartiva-commerce' ), $title, get_post_status_object( $post->post_status ) ? get_post_status_object( $post->post_status )->label : $post->post_status );
			}
			$choices[ (int) $post->ID ] = $title;
		}
		return $choices;
	}

	/**
	 * One step row.
	 *
	 * @param string $i       Row index or __i__.
	 * @param array  $step    Step (id, type).
	 * @param array  $choices Landing pages.
	 */
	public static function step_row( $i, $step, $choices ) {
		$step = wp_parse_args(
			$step,
			array(
				'id'   => 0,
				'type' => 'landing',
			)
		);
		$base = 'cartiva_funnel[steps][' . $i . ']';
		$id   = (int) $step['id'];
		?>
		<tr>
			<td class="cartiva-step-number" aria-hidden="true"></td>
			<td>
				<select name="<?php echo esc_attr( $base . '[id]' ); ?>" aria-label="<?php esc_attr_e( 'Landing page', 'cartiva-commerce' ); ?>" style="width:100%">
					<option value="0"><?php esc_html_e( 'Choose a landing page', 'cartiva-commerce' ); ?></option>
					<?php foreach ( $choices as $choice_id => $title ) : ?>
						<option value="<?php echo esc_attr( (string) $choice_id ); ?>" <?php selected( $id, $choice_id ); ?>><?php echo esc_html( $title ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select name="<?php echo esc_attr( $base . '[type]' ); ?>" aria-label="<?php esc_attr_e( 'Step type', 'cartiva-commerce' ); ?>">
					<?php foreach ( self::step_types() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $step['type'], $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td class="cartiva-step-links">
				<?php if ( $id ) : ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>"><?php esc_html_e( 'Edit', 'cartiva-commerce' ); ?></a>
					| <a href="<?php echo esc_url( (string) get_permalink( $id ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'cartiva-commerce' ); ?></a>
				<?php endif; ?>
			</td>
			<td class="cartiva-step-tools">
				<button type="button" class="button-link cartiva-move-row" data-dir="-1" aria-label="<?php esc_attr_e( 'Move up', 'cartiva-commerce' ); ?>">&uarr;</button>
				<button type="button" class="button-link cartiva-move-row" data-dir="1" aria-label="<?php esc_attr_e( 'Move down', 'cartiva-commerce' ); ?>">&darr;</button>
				<button type="button" class="button-link cartiva-remove-row" aria-label="<?php esc_attr_e( 'Remove', 'cartiva-commerce' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Steps box.
	 *
	 * @param WP_Post $post Funnel.
	 */
	public static function render_steps( $post ) {
		$choices = self::step_choices();
		$steps   = cartiva_commerce_funnel_steps( $post->ID );
		wp_nonce_field( 'cartiva_funnel_save', 'cartiva_funnel_nonce' );
		?>
		<p class="description"><?php esc_html_e( 'Shoppers go through the steps from top to bottom. On a sales page, buttons that link to #order (or #cartiva-next) open the next step. After an order, buyers see the Upsell steps that follow the checkout: "Yes" moves on to the next upsell, "No" shows the Downsell placed right after it. The flow ends on the Thank you step with the order details.', 'cartiva-commerce' ); ?></p>
		<div class="cartiva-repeater" data-cartiva-repeater="steps">
			<table class="widefat cartiva-rows cartiva-steps">
				<thead><tr>
					<th style="width:2em">#</th>
					<th><?php esc_html_e( 'Landing page', 'cartiva-commerce' ); ?></th>
					<th><?php esc_html_e( 'Step type', 'cartiva-commerce' ); ?></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Links', 'cartiva-commerce' ); ?></span></th>
					<th><span class="screen-reader-text"><?php esc_html_e( 'Order', 'cartiva-commerce' ); ?></span></th>
				</tr></thead>
				<tbody>
					<?php
					foreach ( $steps as $i => $step ) {
						self::step_row( (string) $i, $step, $choices );
					}
					?>
				</tbody>
			</table>
			<template><?php self::step_row( '__i__', array(), $choices ); ?></template>
			<p>
				<button type="button" class="button cartiva-add-row"><?php esc_html_e( 'Add step', 'cartiva-commerce' ); ?></button>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . CARTIVA_COMMERCE_LANDING ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Create a new landing page', 'cartiva-commerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Results box.
	 *
	 * @param WP_Post $post Funnel.
	 */
	public static function render_results( $post ) {
		$totals = self::totals( $post->ID );
		?>
		<ul class="cartiva-funnel-results">
			<li><span><?php esc_html_e( 'Visits', 'cartiva-commerce' ); ?></span> <strong><?php echo esc_html( number_format_i18n( $totals['visits'] ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Orders', 'cartiva-commerce' ); ?></span> <strong><?php echo esc_html( number_format_i18n( $totals['orders'] ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Revenue', 'cartiva-commerce' ); ?></span> <strong><?php echo wp_kses_post( wc_price( $totals['revenue'] ) ); ?></strong></li>
			<li><span><?php esc_html_e( 'Conversion', 'cartiva-commerce' ); ?></span> <strong><?php echo esc_html( $totals['visits'] > 0 ? number_format_i18n( $totals['orders'] / $totals['visits'] * 100, 1 ) . '%' : '—' ); ?></strong></li>
		</ul>
		<p class="description"><?php esc_html_e( 'Visits count the first step. Orders count processing, completed and on-hold orders. Numbers refresh every 10 minutes.', 'cartiva-commerce' ); ?></p>
		<?php
	}

	/**
	 * Clean a steps list.
	 *
	 * @param mixed $raw Raw steps.
	 * @return array
	 */
	public static function sanitize_steps( $raw ) {
		$types = self::step_types();
		$steps = array();
		$seen  = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			$id   = is_array( $row ) && isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$type = is_array( $row ) && isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'landing';
			if ( ! $id || isset( $seen[ $id ] ) || CARTIVA_COMMERCE_LANDING !== get_post_type( $id ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$steps[]     = array(
				'id'   => $id,
				'type' => isset( $types[ $type ] ) ? $type : 'landing',
			);
		}
		return $steps;
	}

	/**
	 * Store steps and link each step back to its funnel.
	 *
	 * @param int   $funnel_id Funnel ID.
	 * @param array $steps     Clean steps.
	 */
	public static function set_steps( $funnel_id, $steps ) {
		foreach ( cartiva_commerce_funnel_steps( $funnel_id ) as $old ) {
			if ( cartiva_commerce_funnel_of( (int) $old['id'] ) === (int) $funnel_id ) {
				delete_post_meta( (int) $old['id'], '_cartiva_funnel' );
			}
		}
		foreach ( $steps as $step ) {
			$previous = cartiva_commerce_funnel_of( $step['id'] );
			if ( $previous && $previous !== (int) $funnel_id ) {
				// A page is a step of one funnel only: take it out of the other one.
				$others = array_values(
					array_filter(
						cartiva_commerce_funnel_steps( $previous ),
						function ( $other ) use ( $step ) {
							return (int) $other['id'] !== (int) $step['id'];
						}
					)
				);
				update_post_meta( $previous, '_cartiva_funnel_steps', $others );
			}
			update_post_meta( $step['id'], '_cartiva_funnel', (int) $funnel_id );
		}
		update_post_meta( $funnel_id, '_cartiva_funnel_steps', $steps );
		delete_transient( 'cartiva_funnel_stats_' . (int) $funnel_id );
	}

	/**
	 * Save the steps box.
	 *
	 * @param int     $post_id Funnel ID.
	 * @param WP_Post $post    Funnel.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['cartiva_funnel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartiva_funnel_nonce'] ) ), 'cartiva_funnel_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw = isset( $_POST['cartiva_funnel']['steps'] ) ? wp_unslash( $_POST['cartiva_funnel']['steps'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in sanitize_steps().
		$steps = array();
		foreach ( self::sanitize_steps( $raw ) as $step ) {
			if ( current_user_can( 'edit_post', $step['id'] ) ) {
				$steps[] = $step;
			}
		}
		self::set_steps( $post_id, $steps );
	}

	/**
	 * When a funnel is deleted or trashed, its pages stop being steps.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function forget_steps( $post_id ) {
		if ( CARTIVA_COMMERCE_FUNNEL !== get_post_type( $post_id ) ) {
			return;
		}
		foreach ( cartiva_commerce_funnel_steps( $post_id ) as $step ) {
			if ( cartiva_commerce_funnel_of( (int) $step['id'] ) === (int) $post_id ) {
				delete_post_meta( (int) $step['id'], '_cartiva_funnel' );
			}
		}
	}

	/**
	 * Block markup for a button.
	 *
	 * @param string $text Button text.
	 * @param string $href Link.
	 * @return string
	 */
	private static function button_markup( $text, $href ) {
		return '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_attr( $href ) . '">' . esc_html( $text ) . '</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
	}

	/**
	 * Starting content of the template steps.
	 *
	 * @param string $type Step type.
	 * @return string Block markup.
	 */
	public static function template_content( $type ) {
		$registry = class_exists( 'WP_Block_Patterns_Registry' ) ? WP_Block_Patterns_Registry::get_instance() : null;
		$has      = function ( $slug ) use ( $registry ) {
			return $registry && $registry->is_registered( 'cartiva/' . $slug );
		};
		$heading  = function ( $text, $level = 2 ) {
			return '<!-- wp:heading {"textAlign":"center","level":' . (int) $level . '} --><h' . (int) $level . ' class="wp-block-heading has-text-align-center">' . esc_html( $text ) . '</h' . (int) $level . '><!-- /wp:heading -->';
		};
		$para     = function ( $text ) {
			return '<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">' . esc_html( $text ) . '</p><!-- /wp:paragraph -->';
		};
		$parts    = array();
		if ( 'landing' === $type ) {
			if ( $has( 'landing-hero' ) ) {
				foreach ( array( 'landing-offer', 'landing-hero', 'landing-benefits', 'landing-steps', 'landing-comparison', 'landing-guarantee', 'landing-faq', 'landing-cta' ) as $slug ) {
					if ( $has( $slug ) ) {
						$parts[] = '<!-- wp:pattern {"slug":"cartiva/' . $slug . '"} /-->';
					}
				}
			} else {
				$parts[] = $heading( __( 'Your product headline', 'cartiva-commerce' ), 1 );
				$parts[] = $para( __( 'Say in one or two sentences what the product does and why it is worth buying.', 'cartiva-commerce' ) );
				$parts[] = self::button_markup( __( 'Order now', 'cartiva-commerce' ), '#cartiva-next' );
			}
		} elseif ( 'checkout' === $type ) {
			$parts[] = $heading( __( 'Complete your order', 'cartiva-commerce' ), 1 );
			$parts[] = $para( __( 'Fill in your details. Pay when the parcel arrives.', 'cartiva-commerce' ) );
			$parts[] = '<!-- wp:cartiva-commerce/order-form /-->';
		} elseif ( 'upsell' === $type ) {
			$parts[] = $heading( __( 'Wait! Your order is not complete yet', 'cartiva-commerce' ), 1 );
			$parts[] = $para( __( 'Add this to your order now at a special price. This offer is only on this page.', 'cartiva-commerce' ) );
			$parts[] = '<!-- wp:cartiva-commerce/offer /-->';
		} elseif ( 'downsell' === $type ) {
			$parts[] = $heading( __( 'How about this instead?', 'cartiva-commerce' ), 1 );
			$parts[] = $para( __( 'A smaller offer at an even better price, added to the same order.', 'cartiva-commerce' ) );
			$parts[] = '<!-- wp:cartiva-commerce/offer /-->';
		} else {
			$parts[] = $heading( __( 'Thank you for your order!', 'cartiva-commerce' ), 1 );
			$parts[] = $para( __( 'We will call you soon to confirm your order.', 'cartiva-commerce' ) );
			$parts[] = '<!-- wp:cartiva-commerce/order-details /-->';
		}
		return implode( "\n\n", $parts );
	}

	/**
	 * Create a funnel with three draft steps (admin-post action).
	 */
	public static function create_from_template() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		check_admin_referer( 'cartiva_commerce_new_funnel' );
		$funnel_id = wp_insert_post(
			array(
				'post_type'   => CARTIVA_COMMERCE_FUNNEL,
				'post_status' => 'publish',
				'post_title'  => __( 'New funnel', 'cartiva-commerce' ),
			),
			true
		);
		if ( is_wp_error( $funnel_id ) ) {
			wp_die( esc_html( $funnel_id->get_error_message() ) );
		}
		$titles = array(
			'landing'  => __( 'Sales page', 'cartiva-commerce' ),
			'checkout' => __( 'Checkout', 'cartiva-commerce' ),
		);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checked by check_admin_referer() above.
		if ( ! empty( $_GET['with_offers'] ) ) {
			$titles['upsell']   = __( 'Upsell', 'cartiva-commerce' );
			$titles['downsell'] = __( 'Downsell', 'cartiva-commerce' );
		}
		$titles['thankyou'] = __( 'Thank you', 'cartiva-commerce' );
		$steps  = array();
		foreach ( $titles as $type => $title ) {
			$step_id = wp_insert_post(
				array(
					'post_type'    => CARTIVA_COMMERCE_LANDING,
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_content' => self::template_content( $type ),
				)
			);
			if ( $step_id && ! is_wp_error( $step_id ) ) {
				$steps[] = array(
					'id'   => (int) $step_id,
					'type' => $type,
				);
			}
		}
		self::set_steps( (int) $funnel_id, $steps );
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $funnel_id . '&action=edit' ) );
		exit;
	}

	/**
	 * On a step, buttons that link to #cartiva-next (and #order, when the page
	 * has no order form of its own) open the next step.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	public static function next_step_links( $content ) {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) || (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return $content;
		}
		$next = cartiva_commerce_next_step( (int) get_the_ID() );
		if ( ! $next ) {
			return $content;
		}
		$url     = esc_url( (string) get_permalink( $next ) );
		$content = str_replace( array( 'href="#cartiva-next"', "href='#cartiva-next'" ), 'href="' . $url . '"', $content );
		if ( false === strpos( $content, 'cartiva-order-form' ) ) {
			$content = str_replace( array( 'href="#order"', "href='#order'" ), 'href="' . $url . '"', $content );
		}
		return $content;
	}

	/**
	 * Results of a funnel (cached for 10 minutes).
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array{visits:int,orders:int,revenue:float}
	 */
	public static function totals( $funnel_id ) {
		$key    = 'cartiva_funnel_stats_' . (int) $funnel_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$steps  = cartiva_commerce_funnel_steps( $funnel_id );
		$totals = array(
			'visits'  => $steps ? (int) get_post_meta( (int) $steps[0]['id'], '_cartiva_visits', true ) : 0,
			'orders'  => 0,
			'revenue' => 0.0,
		);
		foreach ( $steps as $step ) {
			$step_totals        = Cartiva_Commerce_Stats::totals( (int) $step['id'] );
			$totals['orders']  += $step_totals['orders'];
			$totals['revenue'] += $step_totals['revenue'];
		}
		set_transient( $key, $totals, 10 * MINUTE_IN_SECONDS );
		return $totals;
	}

	/**
	 * List columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		unset( $columns['date'] );
		$columns['cartiva_steps']      = __( 'Steps', 'cartiva-commerce' );
		$columns['cartiva_visits']     = __( 'Visits', 'cartiva-commerce' );
		$columns['cartiva_orders']     = __( 'Orders', 'cartiva-commerce' );
		$columns['cartiva_revenue']    = __( 'Revenue', 'cartiva-commerce' );
		$columns['cartiva_conversion'] = __( 'Conversion', 'cartiva-commerce' );
		return $columns;
	}

	/**
	 * Print a list column.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Funnel ID.
	 */
	public static function column( $column, $post_id ) {
		if ( 'cartiva_steps' === $column ) {
			$types = self::step_types();
			$names = array();
			foreach ( cartiva_commerce_funnel_steps( $post_id ) as $step ) {
				$label   = isset( $types[ $step['type'] ] ) ? $types[ $step['type'] ] : $step['type'];
				$names[] = 'publish' === get_post_status( (int) $step['id'] )
					? esc_html( $label )
					/* translators: %s: step type such as Checkout. */
					: esc_html( sprintf( __( '%s (draft)', 'cartiva-commerce' ), $label ) );
			}
			echo $names ? implode( ' &rarr; ', $names ) : '<span aria-hidden="true">&mdash;</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each name is escaped above.
			return;
		}
		$totals = self::totals( $post_id );
		switch ( $column ) {
			case 'cartiva_visits':
				echo esc_html( number_format_i18n( $totals['visits'] ) );
				break;
			case 'cartiva_orders':
				echo esc_html( number_format_i18n( $totals['orders'] ) );
				break;
			case 'cartiva_revenue':
				echo wp_kses_post( wc_price( $totals['revenue'] ) );
				break;
			case 'cartiva_conversion':
				echo $totals['visits'] > 0 ? esc_html( number_format_i18n( $totals['orders'] / $totals['visits'] * 100, 1 ) . '%' ) : '<span aria-hidden="true">&mdash;</span>';
				break;
		}
	}

	/**
	 * "View" opens the first step.
	 *
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( CARTIVA_COMMERCE_FUNNEL !== $post->post_type ) {
			return $actions;
		}
		$steps = cartiva_commerce_funnel_steps( $post->ID );
		if ( $steps ) {
			$actions['cartiva_view'] = '<a href="' . esc_url( (string) get_permalink( (int) $steps[0]['id'] ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View first step', 'cartiva-commerce' ) . '</a>';
		}
		return $actions;
	}
}
