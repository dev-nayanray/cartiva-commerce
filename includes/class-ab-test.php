<?php
/**
 * A/B tests: split visitors of a landing page (or funnel step) between it and
 * a variant page, compare results, and keep the winner.
 *
 * Each visitor is put in one group and stays there (cookie, 30 days). The
 * variant stands for the tested step in its funnel, so the next steps,
 * offers and thank-you page work the same for both groups. Orders keep the
 * page they came from, which is how the two groups are compared.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A/B tests.
 */
class Cartiva_Commerce_AB_Test {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'assign' ), 0 );
		add_action( 'save_post_' . CARTIVA_COMMERCE_LANDING, array( __CLASS__, 'save' ), 20 );
		add_action( 'admin_post_cartiva_commerce_ab_variant', array( __CLASS__, 'create_variant' ) );
		add_action( 'admin_post_cartiva_commerce_ab_winner', array( __CLASS__, 'declare_winner' ) );
	}

	/**
	 * Test settings of a page.
	 *
	 * @param int $landing_id Tested page ID.
	 * @return array variant, split (share of visitors who see the variant, %), active, started.
	 */
	public static function test( $landing_id ) {
		$test = get_post_meta( (int) $landing_id, '_cartiva_ab', true );
		return wp_parse_args(
			is_array( $test ) ? $test : array(),
			array(
				'variant' => 0,
				'split'   => 50,
				'active'  => false,
				'started' => 0,
			)
		);
	}

	/**
	 * Is a test running (with a published variant)?
	 *
	 * @param array $test Test settings.
	 * @return bool
	 */
	public static function running( $test ) {
		return ! empty( $test['active'] ) && $test['variant'] && 'publish' === get_post_status( (int) $test['variant'] );
	}

	/**
	 * Pick a group for a new visitor.
	 *
	 * @param int $split Share of visitors who see the variant (0-100).
	 * @return string a or b.
	 */
	public static function pick( $split ) {
		return wp_rand( 1, 100 ) <= max( 0, min( 100, (int) $split ) ) ? 'b' : 'a';
	}

	/**
	 * Send visitors of the variant group to the variant page.
	 */
	public static function assign() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$landing_id = (int) get_queried_object_id();
		$test       = self::test( $landing_id );
		if ( ! self::running( $test ) || cartiva_commerce_is_bot() ) {
			return;
		}
		$cookie = 'cartiva_ab_' . $landing_id;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preview switch for editors only.
		$preview = isset( $_GET['cartiva_variant'] ) && current_user_can( 'edit_post', $landing_id ) ? sanitize_key( wp_unslash( $_GET['cartiva_variant'] ) ) : '';
		if ( '' !== $preview ) {
			$group = 'b' === $preview ? 'b' : 'a';
		} elseif ( isset( $_COOKIE[ $cookie ] ) && in_array( $_COOKIE[ $cookie ], array( 'a', 'b' ), true ) ) {
			$group = sanitize_key( wp_unslash( $_COOKIE[ $cookie ] ) );
		} elseif ( current_user_can( 'edit_post', $landing_id ) ) {
			return; // Editors see the original unless they ask for the variant.
		} else {
			$group = self::pick( (int) $test['split'] );
			if ( ! headers_sent() ) {
				setcookie(
					$cookie,
					$group,
					array(
						'expires'  => time() + 30 * DAY_IN_SECONDS,
						'path'     => COOKIEPATH ? COOKIEPATH : '/',
						'domain'   => COOKIE_DOMAIN,
						'secure'   => is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					)
				);
			}
		}
		if ( 'b' !== $group ) {
			return;
		}
		nocache_headers();
		// Keep the query (for example the order key on an offer step, or ad tracking tags).
		$args = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passed through unchanged to the variant page.
		foreach ( (array) wp_unslash( $_GET ) as $name => $value ) {
			if ( is_scalar( $value ) && 'cartiva_variant' !== $name ) {
				$args[ sanitize_key( $name ) ] = rawurlencode( sanitize_text_field( (string) $value ) );
			}
		}
		wp_safe_redirect( add_query_arg( $args, get_permalink( (int) $test['variant'] ) ), 302 );
		exit;
	}

	/**
	 * Pages that can be a variant of a page: published or draft landing pages
	 * that are not funnel steps and not in another test.
	 *
	 * @param int $landing_id Tested page ID.
	 * @return array ID => title.
	 */
	public static function variant_choices( $landing_id ) {
		$current = (int) self::test( $landing_id )['variant'];
		$posts   = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_LANDING,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => 200,
				'post__not_in'   => array( (int) $landing_id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- One ID.
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$choices = array();
		foreach ( $posts as $post ) {
			$parent = (int) get_post_meta( $post->ID, '_cartiva_ab_parent', true );
			$is_own = (int) $post->ID === $current;
			if ( ! $is_own && ( $parent || ( cartiva_commerce_funnel_of( $post->ID ) && ! $parent ) || self::test( $post->ID )['variant'] ) ) {
				continue;
			}
			$choices[ (int) $post->ID ] = ( '' !== $post->post_title ? $post->post_title : __( '(no title)', 'cartiva-commerce' ) ) . ( 'publish' !== $post->post_status ? ' (' . $post->post_status . ')' : '' );
		}
		return $choices;
	}

	/**
	 * Store test settings and link the variant to the tested page.
	 *
	 * @param int   $landing_id Tested page ID.
	 * @param array $test       Settings (variant, split, active).
	 */
	public static function set( $landing_id, $test ) {
		$old     = self::test( $landing_id );
		$variant = (int) $test['variant'];
		if ( $old['variant'] && (int) $old['variant'] !== $variant ) {
			self::release( (int) $old['variant'], $landing_id );
		}
		$test = array(
			'variant' => $variant,
			'split'   => max( 1, min( 99, (int) $test['split'] ) ),
			'active'  => $variant && ! empty( $test['active'] ),
			'started' => ( $variant && ! empty( $test['active'] ) ) ? ( ! empty( $old['active'] ) && (int) $old['variant'] === $variant && $old['started'] ? (int) $old['started'] : time() ) : 0,
		);
		update_post_meta( $landing_id, '_cartiva_ab', $test );
		if ( $variant ) {
			update_post_meta( $variant, '_cartiva_ab_parent', (int) $landing_id );
			$funnel = cartiva_commerce_funnel_of( $landing_id );
			if ( $funnel ) {
				update_post_meta( $variant, '_cartiva_funnel', $funnel );
			} else {
				delete_post_meta( $variant, '_cartiva_funnel' );
			}
		}
	}

	/**
	 * A page stops being a variant.
	 *
	 * @param int $variant    Variant page ID.
	 * @param int $landing_id Tested page ID.
	 */
	public static function release( $variant, $landing_id ) {
		if ( (int) get_post_meta( $variant, '_cartiva_ab_parent', true ) !== (int) $landing_id ) {
			return;
		}
		delete_post_meta( $variant, '_cartiva_ab_parent' );
		$funnel = cartiva_commerce_funnel_of( $variant );
		$listed = $funnel && in_array( $variant, array_map( 'intval', array_column( cartiva_commerce_funnel_steps( $funnel ), 'id' ) ), true );
		if ( $funnel && ! $listed ) {
			delete_post_meta( $variant, '_cartiva_funnel' );
		}
	}

	/**
	 * Save the A/B section of the landing page box.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save( $post_id ) {
		if ( ! isset( $_POST['cartiva_landing_nonce'], $_POST['cartiva_ab'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartiva_landing_nonce'] ) ), 'cartiva_landing_save' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$raw     = is_array( $_POST['cartiva_ab'] ) ? wp_unslash( $_POST['cartiva_ab'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is cast below.
		$variant = isset( $raw['variant'] ) ? absint( $raw['variant'] ) : 0;
		if ( $variant && ! isset( self::variant_choices( $post_id )[ $variant ] ) ) {
			$variant = 0;
		}
		self::set(
			$post_id,
			array(
				'variant' => $variant,
				'split'   => isset( $raw['split'] ) ? absint( $raw['split'] ) : 50,
				'active'  => ! empty( $raw['active'] ),
			)
		);
	}

	/**
	 * Results of both groups since the test started.
	 *
	 * @param int $landing_id Tested page ID.
	 * @return array a, b: visits, orders, revenue, conversion (0-1).
	 */
	public static function results( $landing_id ) {
		$test   = self::test( $landing_id );
		$from   = $test['started'] ? (int) $test['started'] : 0;
		$day    = $from ? wp_date( 'Y-m-d', $from ) : '';
		$groups = array(
			'a' => (int) $landing_id,
			'b' => (int) $test['variant'],
		);
		$out    = array();
		foreach ( $groups as $group => $id ) {
			$sum           = $id ? Cartiva_Commerce_Analytics::summarize( Cartiva_Commerce_Analytics::orders_for( array( $id ), $from ) ) : Cartiva_Commerce_Analytics::summarize( array() );
			$visits        = $id ? cartiva_commerce_visits_between( $id, $day ) : 0;
			$out[ $group ] = array(
				'visits'     => $visits,
				'orders'     => $sum['orders'],
				'revenue'    => $sum['revenue'],
				'conversion' => $visits > 0 ? $sum['orders'] / $visits : 0.0,
			);
		}
		return $out;
	}

	/**
	 * Create a copy of a page as its variant (admin-post action).
	 */
	public static function create_variant() {
		$landing_id = isset( $_GET['landing'] ) ? absint( wp_unslash( $_GET['landing'] ) ) : 0;
		check_admin_referer( 'cartiva_ab_variant_' . $landing_id );
		$post = get_post( $landing_id );
		if ( ! $post || CARTIVA_COMMERCE_LANDING !== $post->post_type || ! current_user_can( 'edit_post', $landing_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		$copy = wp_insert_post(
			array(
				'post_type'    => CARTIVA_COMMERCE_LANDING,
				'post_status'  => 'draft',
				/* translators: %s: page title. */
				'post_title'   => sprintf( __( '%s (variant B)', 'cartiva-commerce' ), $post->post_title ),
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
			),
			true
		);
		if ( is_wp_error( $copy ) ) {
			wp_die( esc_html( $copy->get_error_message() ) );
		}
		$config = get_post_meta( $landing_id, '_cartiva_landing', true );
		if ( is_array( $config ) ) {
			update_post_meta( $copy, '_cartiva_landing', $config );
		}
		$thumb = get_post_thumbnail_id( $landing_id );
		if ( $thumb ) {
			set_post_thumbnail( $copy, $thumb );
		}
		self::set(
			$landing_id,
			array(
				'variant' => (int) $copy,
				'split'   => (int) self::test( $landing_id )['split'],
				'active'  => false,
			)
		);
		wp_safe_redirect( admin_url( 'post.php?post=' . (int) $copy . '&action=edit' ) );
		exit;
	}

	/**
	 * Finish a test and keep the winner (admin-post action).
	 *
	 * When the variant wins, its content and settings move to the tested page,
	 * so its address, funnel position and history stay. The old content is
	 * kept as a revision, and the variant page becomes a draft.
	 */
	public static function declare_winner() {
		$landing_id = isset( $_GET['landing'] ) ? absint( wp_unslash( $_GET['landing'] ) ) : 0;
		check_admin_referer( 'cartiva_ab_winner_' . $landing_id );
		if ( CARTIVA_COMMERCE_LANDING !== get_post_type( $landing_id ) || ! current_user_can( 'edit_post', $landing_id ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'cartiva-commerce' ) );
		}
		$test    = self::test( $landing_id );
		$variant = (int) $test['variant'];
		$winner  = isset( $_GET['winner'] ) && 'b' === sanitize_key( wp_unslash( $_GET['winner'] ) ) ? 'b' : 'a';
		if ( 'b' === $winner && $variant && current_user_can( 'edit_post', $variant ) ) {
			$source = get_post( $variant );
			wp_update_post(
				array(
					'ID'           => $landing_id,
					'post_content' => $source->post_content,
					'post_excerpt' => $source->post_excerpt,
				)
			);
			$config = get_post_meta( $variant, '_cartiva_landing', true );
			if ( is_array( $config ) ) {
				update_post_meta( $landing_id, '_cartiva_landing', $config );
			}
			wp_update_post(
				array(
					'ID'          => $variant,
					'post_status' => 'draft',
				)
			);
		}
		if ( $variant ) {
			self::release( $variant, $landing_id );
		}
		delete_post_meta( $landing_id, '_cartiva_ab' );
		wp_safe_redirect( add_query_arg( 'cartiva_ab_done', $winner, admin_url( 'post.php?post=' . $landing_id . '&action=edit' ) ) );
		exit;
	}

	/**
	 * The A/B section of the landing page box.
	 *
	 * @param WP_Post $post Landing page.
	 */
	public static function render_section( $post ) {
		if ( cartiva_commerce_canonical_step( $post->ID ) !== (int) $post->ID ) {
			$parent = cartiva_commerce_canonical_step( $post->ID );
			echo '<h3>' . esc_html__( 'A/B test', 'cartiva-commerce' ) . '</h3><p class="description">';
			printf(
				/* translators: %s: link to the tested page. */
				esc_html__( 'This page is variant B of %s. Its results are shown there.', 'cartiva-commerce' ),
				'<a href="' . esc_url( (string) get_edit_post_link( $parent ) ) . '">' . esc_html( get_the_title( $parent ) ) . '</a>'
			);
			echo '</p>';
			return;
		}
		$test    = self::test( $post->ID );
		$choices = self::variant_choices( $post->ID );
		?>
		<h3><?php esc_html_e( 'A/B test', 'cartiva-commerce' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Show a different version of this page to part of your visitors and keep the one that sells better. Each visitor always sees the same version.', 'cartiva-commerce' ); ?></p>
		<?php
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['cartiva_ab_done'] ) ) :
			?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Test finished. The winning version is now on this page.', 'cartiva-commerce' ); ?></p></div>
		<?php endif; ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cartiva-ab-variant"><?php esc_html_e( 'Variant B', 'cartiva-commerce' ); ?></label></th>
				<td>
					<select id="cartiva-ab-variant" name="cartiva_ab[variant]">
						<option value="0"><?php esc_html_e( 'No test', 'cartiva-commerce' ); ?></option>
						<?php foreach ( $choices as $id => $title ) : ?>
							<option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (int) $test['variant'], $id ); ?>><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
					<?php if ( 'publish' === $post->post_status || 'draft' === $post->post_status ) : ?>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cartiva_commerce_ab_variant&landing=' . $post->ID ), 'cartiva_ab_variant_' . $post->ID ) ); ?>"><?php esc_html_e( 'Create variant B from a copy of this page', 'cartiva-commerce' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="cartiva-ab-split"><?php esc_html_e( 'Visitors who see B', 'cartiva-commerce' ); ?></label></th>
				<td>
					<input id="cartiva-ab-split" type="number" min="1" max="99" class="small-text" name="cartiva_ab[split]" value="<?php echo esc_attr( (string) $test['split'] ); ?>" /> %
					<label class="cartiva-inline"><input type="checkbox" name="cartiva_ab[active]" value="1" <?php checked( ! empty( $test['active'] ) ); ?> /> <?php esc_html_e( 'Run the test', 'cartiva-commerce' ); ?></label>
					<p class="description"><?php esc_html_e( 'The test runs while variant B is published. Preview B by adding ?cartiva_variant=b to this page address.', 'cartiva-commerce' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
		if ( ! $test['variant'] ) {
			return;
		}
		$results = self::results( $post->ID );
		?>
		<table class="widefat striped cartiva-ab-results">
			<thead><tr>
				<th scope="col"><?php esc_html_e( 'Version', 'cartiva-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Visits', 'cartiva-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Orders', 'cartiva-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Conversion', 'cartiva-commerce' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Revenue', 'cartiva-commerce' ); ?></th>
				<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Action', 'cartiva-commerce' ); ?></span></th>
			</tr></thead>
			<tbody>
				<?php foreach ( array( 'a' => __( 'A (this page)', 'cartiva-commerce' ), 'b' => __( 'B (variant)', 'cartiva-commerce' ) ) as $group => $label ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( $label ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $results[ $group ]['visits'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $results[ $group ]['orders'] ) ); ?></td>
						<td><?php echo esc_html( Cartiva_Commerce_Analytics::percent( $results[ $group ]['orders'], $results[ $group ]['visits'] ) ); ?></td>
						<td><?php echo wp_kses_post( wc_price( $results[ $group ]['revenue'] ) ); ?></td>
						<td><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cartiva_commerce_ab_winner&landing=' . $post->ID . '&winner=' . $group ), 'cartiva_ab_winner_' . $post->ID ) ); ?>"><?php echo esc_html( 'a' === $group ? __( 'Keep A, end test', 'cartiva-commerce' ) : __( 'B wins: use it here', 'cartiva-commerce' ) ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Wait for enough orders before choosing: with fewer than about 30 orders per version, differences are often luck. When B wins, its content and settings move to this page, so the address, funnel position and history stay. The old version is kept as a revision.', 'cartiva-commerce' ); ?></p>
		<?php
	}
}
