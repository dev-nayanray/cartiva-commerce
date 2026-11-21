<?php
/**
 * Abandoned checkouts screen: list with call and WhatsApp buttons, status
 * filters, summary numbers and a details view.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recovery admin.
 */
class Cartiva_Commerce_Recovery_Admin {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_filter( 'manage_' . CARTIVA_COMMERCE_RECOVERY . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CARTIVA_COMMERCE_RECOVERY . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_filter( 'views_edit-' . CARTIVA_COMMERCE_RECOVERY, array( __CLASS__, 'views' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_status' ) );
		add_action( 'admin_notices', array( __CLASS__, 'summary' ) );
		add_action( 'add_meta_boxes_' . CARTIVA_COMMERCE_RECOVERY, array( __CLASS__, 'meta_boxes' ) );
		add_filter( 'bulk_actions-edit-' . CARTIVA_COMMERCE_RECOVERY, array( __CLASS__, 'bulk_actions' ) );
	}

	/**
	 * Status labels.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			'open'      => __( 'Filling in', 'cartiva-commerce' ),
			'abandoned' => __( 'Abandoned', 'cartiva-commerce' ),
			'recovered' => __( 'Recovered', 'cartiva-commerce' ),
			'converted' => __( 'Ordered', 'cartiva-commerce' ),
		);
	}

	/**
	 * Columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'              => isset( $columns['cb'] ) ? $columns['cb'] : '',
			'title'           => __( 'Customer', 'cartiva-commerce' ),
			'cartiva_contact' => __( 'Contact', 'cartiva-commerce' ),
			'cartiva_cart'    => __( 'Cart', 'cartiva-commerce' ),
			'cartiva_source'  => __( 'Form', 'cartiva-commerce' ),
			'cartiva_status'  => __( 'Status', 'cartiva-commerce' ),
			'cartiva_emails'  => __( 'Reminders', 'cartiva-commerce' ),
			'date'            => __( 'Started', 'cartiva-commerce' ),
		);
	}

	/**
	 * Contact buttons.
	 *
	 * @param int $id Entry ID.
	 * @return string
	 */
	public static function contact_html( $id ) {
		$details = Cartiva_Commerce_Recovery::meta_array( $id, '_details' );
		$html    = '';
		if ( ! empty( $details['billing_phone'] ) ) {
			$html .= '<a class="button button-small" href="' . esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $details['billing_phone'] ) ) . '">' . esc_html__( 'Call', 'cartiva-commerce' ) . ' ' . esc_html( $details['billing_phone'] ) . '</a> ';
			$wa = Cartiva_Commerce_Recovery::whatsapp_link( $id );
			if ( $wa ) {
				$html .= '<a class="button button-small cartiva-wa" href="' . esc_url( $wa ) . '" target="_blank" rel="noopener">' . esc_html__( 'WhatsApp', 'cartiva-commerce' ) . '</a> ';
			}
		}
		if ( ! empty( $details['billing_email'] ) ) {
			$html .= '<br><a href="' . esc_url( 'mailto:' . $details['billing_email'] ) . '">' . esc_html( $details['billing_email'] ) . '</a>';
		}
		return $html;
	}

	/**
	 * Print a column.
	 *
	 * @param string $column Column.
	 * @param int    $id     Entry ID.
	 */
	public static function column( $column, $id ) {
		$cart = Cartiva_Commerce_Recovery::meta_array( $id, '_cart' );
		switch ( $column ) {
			case 'cartiva_contact':
				echo self::contact_html( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in contact_html().
				break;
			case 'cartiva_cart':
				echo esc_html( isset( $cart['names'] ) ? $cart['names'] : '' );
				if ( isset( $cart['total'] ) ) {
					echo '<br><strong>' . wp_kses_post( wc_price( (float) $cart['total'], array( 'currency' => isset( $cart['currency'] ) ? $cart['currency'] : '' ) ) ) . '</strong>';
				}
				break;
			case 'cartiva_source':
				$landing = (int) get_post_meta( $id, '_landing', true );
				echo $landing ? '<a href="' . esc_url( (string) get_edit_post_link( $landing ) ) . '">' . esc_html( get_the_title( $landing ) ) . '</a>' : esc_html__( 'Main checkout', 'cartiva-commerce' );
				break;
			case 'cartiva_status':
				$status   = (string) get_post_meta( $id, '_status', true );
				$statuses = self::statuses();
				echo '<span class="cartiva-status cartiva-status--' . esc_attr( $status ) . '">' . esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status ) . '</span>';
				$order = (int) get_post_meta( $id, '_order', true );
				if ( $order ) {
					$order_obj = wc_get_order( $order );
					if ( $order_obj ) {
						/* translators: %s: order number. */
						echo '<br><a href="' . esc_url( $order_obj->get_edit_order_url() ) . '">' . esc_html( sprintf( __( 'Order #%s', 'cartiva-commerce' ), $order_obj->get_order_number() ) ) . '</a>';
					}
				}
				break;
			case 'cartiva_emails':
				$sent = (int) get_post_meta( $id, '_sent_count', true );
				echo esc_html( number_format_i18n( $sent ) );
				if ( get_post_meta( $id, '_stop', true ) ) {
					echo '<br><small>' . esc_html__( 'Unsubscribed', 'cartiva-commerce' ) . '</small>';
				}
				break;
		}
	}

	/**
	 * Row actions: details and delete only.
	 *
	 * @param array   $actions Actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( CARTIVA_COMMERCE_RECOVERY !== $post->post_type ) {
			return $actions;
		}
		$keep = array();
		if ( isset( $actions['edit'] ) ) {
			$keep['edit'] = '<a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html__( 'Details', 'cartiva-commerce' ) . '</a>';
		}
		if ( isset( $actions['trash'] ) ) {
			$keep['trash'] = $actions['trash'];
		}
		if ( isset( $actions['delete'] ) ) {
			$keep['delete'] = $actions['delete'];
		}
		return $keep;
	}

	/**
	 * Bulk actions: delete only.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		return array_intersect_key( $actions, array_flip( array( 'trash', 'delete', 'untrash' ) ) );
	}

	/**
	 * Status filter links.
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public static function views( $views ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$current = isset( $_GET['cartiva_status'] ) ? sanitize_key( wp_unslash( $_GET['cartiva_status'] ) ) : '';
		$base    = admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_RECOVERY );
		$out     = array( 'all' => '<a href="' . esc_url( $base ) . '"' . ( '' === $current ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html__( 'All', 'cartiva-commerce' ) . '</a>' );
		foreach ( self::statuses() as $status => $label ) {
			$out[ $status ] = '<a href="' . esc_url( add_query_arg( 'cartiva_status', $status, $base ) ) . '"' . ( $current === $status ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $label ) . '</a>';
		}
		if ( isset( $views['trash'] ) ) {
			$out['trash'] = $views['trash'];
		}
		return $out;
	}

	/**
	 * Apply the status filter.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || CARTIVA_COMMERCE_RECOVERY !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$status = isset( $_GET['cartiva_status'] ) ? sanitize_key( wp_unslash( $_GET['cartiva_status'] ) ) : '';
		if ( isset( self::statuses()[ $status ] ) ) {
			$query->set(
				'meta_query',
				array(
					array(
						'key'   => '_status',
						'value' => $status,
					),
				)
			);
		}
	}

	/**
	 * Numbers for the last 30 days.
	 *
	 * @return array abandoned, recovered, revenue.
	 */
	public static function numbers() {
		$cached = get_transient( 'cartiva_recovery_numbers' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$numbers = array(
			'abandoned' => 0,
			'recovered' => 0,
			'revenue'   => 0.0,
		);
		$ids     = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 2000,
				'fields'         => 'ids',
				'date_query'     => array( array( 'after' => '30 days ago' ) ),
			)
		);
		foreach ( $ids as $id ) {
			$status = (string) get_post_meta( $id, '_status', true );
			if ( in_array( $status, array( 'abandoned', 'recovered' ), true ) ) {
				++$numbers['abandoned'];
			}
			if ( 'recovered' === $status ) {
				++$numbers['recovered'];
				$numbers['revenue'] += (float) get_post_meta( $id, '_order_total', true );
			}
		}
		set_transient( 'cartiva_recovery_numbers', $numbers, 10 * MINUTE_IN_SECONDS );
		return $numbers;
	}

	/**
	 * Summary above the list.
	 */
	public static function summary() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-' . CARTIVA_COMMERCE_RECOVERY !== $screen->id ) {
			return;
		}
		$n     = self::numbers();
		$tiles = array(
			__( 'Abandoned (30 days)', 'cartiva-commerce' ) => number_format_i18n( $n['abandoned'] ),
			__( 'Recovered', 'cartiva-commerce' )           => number_format_i18n( $n['recovered'] ),
			__( 'Recovery rate', 'cartiva-commerce' )       => Cartiva_Commerce_Analytics::percent( $n['recovered'], $n['abandoned'] ),
			__( 'Recovered revenue', 'cartiva-commerce' )   => Cartiva_Commerce_Analytics::money( $n['revenue'] ),
		);
		echo '<div class="cartiva-recovery-summary"><ul class="cartiva-tiles">';
		foreach ( $tiles as $label => $value ) {
			echo '<li><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $value ) . '</strong></li>';
		}
		echo '</ul>';
		if ( ! cartiva_commerce_setting( 'recovery' ) ) {
			echo '<p>' . esc_html__( 'Saving abandoned checkouts is switched off.', 'cartiva-commerce' ) . ' <a href="' . esc_url( Cartiva_Commerce_Settings::url( 'recovery' ) ) . '">' . esc_html__( 'Recovery settings', 'cartiva-commerce' ) . '</a></p>';
		} else {
			echo '<p class="description">' . esc_html__( 'A checkout counts as abandoned when no order follows within the time set in the recovery settings. Call or message the shopper, or let the reminder emails do it. Entries are deleted automatically after the keep period.', 'cartiva-commerce' ) . ' <a href="' . esc_url( Cartiva_Commerce_Settings::url( 'recovery' ) ) . '">' . esc_html__( 'Recovery settings', 'cartiva-commerce' ) . '</a></p>';
		}
		echo '</div>';
	}

	/**
	 * Details box (read only) instead of the editor.
	 */
	public static function meta_boxes() {
		remove_meta_box( 'submitdiv', CARTIVA_COMMERCE_RECOVERY, 'side' );
		add_meta_box( 'cartiva-recovery-details', __( 'Details', 'cartiva-commerce' ), array( __CLASS__, 'render_details' ), CARTIVA_COMMERCE_RECOVERY, 'normal', 'high' );
	}

	/**
	 * Details box.
	 *
	 * @param WP_Post $post Entry.
	 */
	public static function render_details( $post ) {
		$details  = Cartiva_Commerce_Recovery::meta_array( $post->ID, '_details' );
		$cart     = Cartiva_Commerce_Recovery::meta_array( $post->ID, '_cart' );
		$status   = (string) get_post_meta( $post->ID, '_status', true );
		$statuses = self::statuses();
		$sent     = Cartiva_Commerce_Recovery::meta_array( $post->ID, '_sent' );
		$labels   = array(
			'billing_full_name' => __( 'Name', 'cartiva-commerce' ),
			'billing_phone'     => __( 'Phone', 'cartiva-commerce' ),
			'billing_email'     => __( 'Email', 'cartiva-commerce' ),
			'billing_address_1' => __( 'Address', 'cartiva-commerce' ),
			'billing_address_2' => __( 'Address line 2', 'cartiva-commerce' ),
			'billing_city'      => __( 'City', 'cartiva-commerce' ),
			'billing_state'     => __( 'District / state', 'cartiva-commerce' ),
			'billing_postcode'  => __( 'Postcode', 'cartiva-commerce' ),
		);
		?>
		<p><?php echo self::contact_html( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in contact_html(). ?></p>
		<table class="form-table" role="presentation">
			<tr><th scope="row"><?php esc_html_e( 'Status', 'cartiva-commerce' ); ?></th><td><?php echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status ); ?></td></tr>
			<?php foreach ( $labels as $key => $label ) : ?>
				<?php if ( ! empty( $details[ $key ] ) ) : ?>
					<tr><th scope="row"><?php echo esc_html( $label ); ?></th><td><?php echo esc_html( $details[ $key ] ); ?></td></tr>
				<?php endif; ?>
			<?php endforeach; ?>
			<tr><th scope="row"><?php esc_html_e( 'Cart', 'cartiva-commerce' ); ?></th><td><?php echo esc_html( isset( $cart['names'] ) ? $cart['names'] : '' ); ?><?php echo isset( $cart['total'] ) ? ' — ' . wp_kses_post( wc_price( (float) $cart['total'], array( 'currency' => isset( $cart['currency'] ) ? $cart['currency'] : '' ) ) ) : ''; ?></td></tr>
			<tr>
				<th scope="row"><label for="cartiva-recovery-link"><?php esc_html_e( 'Recovery link', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input id="cartiva-recovery-link" type="text" class="large-text code" readonly value="<?php echo esc_attr( Cartiva_Commerce_Recovery::link( $post->ID ) ); ?>" onfocus="this.select()" />
					<p class="description"><?php esc_html_e( 'Opening this link puts the products back in the cart and opens the order form with the details filled in. Send it by SMS or chat if you like.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Reminders sent', 'cartiva-commerce' ); ?></th>
				<td>
					<?php
					if ( empty( $sent ) ) {
						esc_html_e( 'None yet.', 'cartiva-commerce' );
					} else {
						foreach ( $sent as $index => $time ) {
							/* translators: 1: reminder number, 2: date and time. */
							echo esc_html( sprintf( __( 'Email %1$d: %2$s', 'cartiva-commerce' ), (int) $index + 1, date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $time ) ) ) . '<br>';
						}
					}
					$coupon = (string) get_post_meta( $post->ID, '_coupon', true );
					if ( '' !== $coupon ) {
						/* translators: %s: coupon code. */
						echo esc_html( sprintf( __( 'Coupon: %s', 'cartiva-commerce' ), strtoupper( $coupon ) ) );
					}
					?>
				</td>
			</tr>
		</table>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . CARTIVA_COMMERCE_RECOVERY ) ); ?>">&larr; <?php esc_html_e( 'All abandoned checkouts', 'cartiva-commerce' ); ?></a></p>
		<?php
	}
}
