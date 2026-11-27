<?php
/**
 * Analytics: results per funnel and landing page for a date range, with
 * order bump and upsell acceptance, and revenue per day.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Analytics.
 */
class Cartiva_Commerce_Analytics {

	const PAGE = 'cartiva-commerce-analytics';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
	}

	/**
	 * Menu item in the Cartiva Commerce menu.
	 */
	public static function menu() {
		add_submenu_page(
			'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL,
			__( 'Funnel analytics', 'cartiva-commerce' ),
			__( 'Analytics', 'cartiva-commerce' ),
			'edit_pages',
			self::PAGE,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Counted orders of some landing pages, optionally in a time range.
	 *
	 * @param int[] $landing_ids Landing page IDs.
	 * @param int   $from        Start timestamp (0 = all time).
	 * @param int   $to          End timestamp (0 = now).
	 * @return WC_Order[]
	 */
	public static function orders_for( $landing_ids, $from = 0, $to = 0 ) {
		$landing_ids = array_values( array_filter( array_map( 'intval', (array) $landing_ids ) ) );
		if ( empty( $landing_ids ) ) {
			return array();
		}
		$args = array(
			'limit'      => -1,
			'status'     => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
			'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin reports only, cached.
				array(
					'key'     => '_cartiva_landing_id',
					'value'   => $landing_ids,
					'compare' => 'IN',
				),
			),
		);
		if ( $from > 0 ) {
			$args['date_created'] = $from . '...' . ( $to > 0 ? $to : time() );
		}
		$orders = wc_get_orders( $args );
		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Numbers for a set of orders.
	 *
	 * @param WC_Order[] $orders Orders.
	 * @return array orders, revenue, bump_orders, bump_revenue, upsell_yes,
	 *               upsell_no, upsell_revenue, days (Y-m-d => revenue).
	 */
	public static function summarize( $orders ) {
		$sum = array(
			'orders'         => 0,
			'revenue'        => 0.0,
			'bump_orders'    => 0,
			'bump_revenue'   => 0.0,
			'upsell_yes'     => 0,
			'upsell_no'      => 0,
			'upsell_revenue' => 0.0,
			'days'           => array(),
		);
		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}
			$total           = (float) $order->get_total();
			$sum['orders']  += 1;
			$sum['revenue'] += $total;
			$created         = $order->get_date_created();
			if ( $created ) {
				$day                 = $created->date_i18n( 'Y-m-d' );
				$sum['days'][ $day ] = ( isset( $sum['days'][ $day ] ) ? $sum['days'][ $day ] : 0 ) + $total;
			}
			$has_bump = false;
			foreach ( $order->get_items() as $item ) {
				if ( '' !== (string) $item->get_meta( '_cartiva_bump' ) ) {
					$has_bump             = true;
					$sum['bump_revenue'] += (float) $item->get_total();
				}
				if ( '' !== (string) $item->get_meta( '_cartiva_upsell' ) ) {
					$sum['upsell_revenue'] += (float) $item->get_total();
				}
			}
			$sum['bump_orders'] += $has_bump ? 1 : 0;
			$decisions           = $order->get_meta( '_cartiva_offers' );
			foreach ( is_array( $decisions ) ? $decisions : array() as $answer ) {
				$sum[ 'yes' === $answer ? 'upsell_yes' : 'upsell_no' ] += 1;
			}
		}
		return $sum;
	}

	/**
	 * A price as plain text (currency symbol decoded), for tiles and tooltips.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function money( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * A percentage, or a dash when there is nothing to divide by.
	 *
	 * @param float $part  Part.
	 * @param float $whole Whole.
	 * @return string
	 */
	public static function percent( $part, $whole ) {
		return $whole > 0 ? number_format_i18n( $part / $whole * 100, 1 ) . '%' : '—';
	}

	/**
	 * Date ranges offered on the page: days => label.
	 *
	 * @return array
	 */
	public static function ranges() {
		return array(
			7   => __( 'Last 7 days', 'cartiva-commerce' ),
			30  => __( 'Last 30 days', 'cartiva-commerce' ),
			90  => __( 'Last 90 days', 'cartiva-commerce' ),
			365 => __( 'Last 12 months', 'cartiva-commerce' ),
		);
	}

	/**
	 * All landing pages that belong to a funnel or stand for one of its steps
	 * (A/B variants), and its first step (+ variant) for visits.
	 *
	 * @param int $funnel_id Funnel ID.
	 * @return array{all:int[],entry:int[]}
	 */
	public static function funnel_pages( $funnel_id ) {
		$all   = array();
		$entry = array();
		foreach ( cartiva_commerce_funnel_steps( $funnel_id ) as $i => $step ) {
			$ids = array( (int) $step['id'] );
			$ab  = Cartiva_Commerce_AB_Test::test( (int) $step['id'] );
			if ( $ab['variant'] ) {
				$ids[] = (int) $ab['variant'];
			}
			$all = array_merge( $all, $ids );
			if ( 0 === $i ) {
				$entry = $ids;
			}
		}
		return array(
			'all'   => array_values( array_unique( $all ) ),
			'entry' => $entry,
		);
	}

	/**
	 * Everything the page shows for a range (cached for 10 minutes).
	 *
	 * @param int $days Days in the range.
	 * @return array
	 */
	public static function report( $days ) {
		$key    = 'cartiva_analytics_' . (int) $days;
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$offset  = (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS );
		$today   = gmdate( 'Y-m-d', time() + $offset );
		$first   = gmdate( 'Y-m-d', time() + $offset - ( $days - 1 ) * DAY_IN_SECONDS );
		$from_ts = strtotime( $first . ' 00:00:00' ) - $offset;
		$rows    = array();
		$used    = array();

		$funnels = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_FUNNEL,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
			)
		);
		foreach ( $funnels as $funnel ) {
			$pages = self::funnel_pages( $funnel->ID );
			$used  = array_merge( $used, $pages['all'] );
			$visit = 0;
			foreach ( $pages['entry'] as $id ) {
				$visit += cartiva_commerce_visits_between( $id, $first, $today );
			}
			$rows[] = array_merge(
				array(
					'title'  => get_the_title( $funnel ),
					'link'   => get_edit_post_link( $funnel->ID, 'raw' ),
					'kind'   => 'funnel',
					'visits' => $visit,
				),
				self::summarize( self::orders_for( $pages['all'], $from_ts ) )
			);
		}

		$landings = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_LANDING,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'post__not_in'   => $used ? $used : array( 0 ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Small admin list.
			)
		);
		foreach ( $landings as $landing ) {
			if ( cartiva_commerce_funnel_of( $landing->ID ) || cartiva_commerce_canonical_step( $landing->ID ) !== (int) $landing->ID ) {
				continue;
			}
			$ids = array( (int) $landing->ID );
			$ab  = Cartiva_Commerce_AB_Test::test( (int) $landing->ID );
			if ( $ab['variant'] ) {
				$ids[] = (int) $ab['variant'];
			}
			$visit = 0;
			foreach ( $ids as $id ) {
				$visit += cartiva_commerce_visits_between( $id, $first, $today );
			}
			$rows[] = array_merge(
				array(
					'title'  => get_the_title( $landing ),
					'link'   => get_edit_post_link( $landing->ID, 'raw' ),
					'kind'   => 'landing',
					'visits' => $visit,
				),
				self::summarize( self::orders_for( $ids, $from_ts ) )
			);
		}

		$totals = array(
			'visits'         => 0,
			'orders'         => 0,
			'revenue'        => 0.0,
			'bump_orders'    => 0,
			'bump_revenue'   => 0.0,
			'upsell_yes'     => 0,
			'upsell_no'      => 0,
			'upsell_revenue' => 0.0,
		);
		$daily  = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$daily[ gmdate( 'Y-m-d', time() + $offset - $i * DAY_IN_SECONDS ) ] = 0.0;
		}
		foreach ( $rows as $row ) {
			foreach ( array_keys( $totals ) as $field ) {
				$totals[ $field ] += $row[ $field ];
			}
			foreach ( $row['days'] as $day => $revenue ) {
				if ( isset( $daily[ $day ] ) ) {
					$daily[ $day ] += $revenue;
				}
			}
		}
		$report = array(
			'rows'   => $rows,
			'totals' => $totals,
			'daily'  => $daily,
		);
		set_transient( $key, $report, 10 * MINUTE_IN_SECONDS );
		return $report;
	}

	/**
	 * Revenue per day as an SVG bar chart (one series), with a tooltip on
	 * every bar and the same numbers in a table.
	 *
	 * @param array $daily Y-m-d => revenue.
	 * @return string
	 */
	public static function chart( $daily ) {
		$count = count( $daily );
		if ( ! $count ) {
			return '';
		}
		$max    = max( 1.0, max( $daily ) );
		$width  = 720;
		$height = 220;
		$left   = 8;
		$bottom = 24;
		$plot   = $height - $bottom - 8;
		$slot   = ( $width - $left * 2 ) / $count;
		$bar    = max( 2.0, $slot - 2 ); // 2px gap between bars.
		$svg    = '<svg class="cartiva-chart" viewBox="0 0 ' . $width . ' ' . $height . '" role="img" aria-labelledby="cartiva-chart-title"><title id="cartiva-chart-title">' . esc_html__( 'Revenue per day', 'cartiva-commerce' ) . '</title>';
		foreach ( array( 0.5, 1 ) as $line ) {
			$y    = round( 8 + $plot - $plot * $line, 1 );
			$svg .= '<line class="cartiva-chart__grid" x1="' . $left . '" x2="' . ( $width - $left ) . '" y1="' . $y . '" y2="' . $y . '" />';
			$svg .= '<text class="cartiva-chart__axis" x="' . ( $width - $left ) . '" y="' . ( $y - 4 ) . '" text-anchor="end">' . esc_html( self::money( ( $max * $line ) ) ) . '</text>';
		}
		$svg .= '<line class="cartiva-chart__base" x1="' . $left . '" x2="' . ( $width - $left ) . '" y1="' . ( 8 + $plot ) . '" y2="' . ( 8 + $plot ) . '" />';
		$i    = 0;
		$step = max( 1, (int) ceil( $count / 8 ) );
		foreach ( $daily as $day => $revenue ) {
			$h     = $revenue > 0 ? max( 2.0, $plot * $revenue / $max ) : 0;
			$x     = round( $left + $i * $slot + ( $slot - $bar ) / 2, 2 );
			$y     = round( 8 + $plot - $h, 2 );
			$label = date_i18n( get_option( 'date_format' ), strtotime( $day ) ) . ': ' . self::money( ( $revenue ) );
			// Hit area covers the whole slot; the bar has rounded top corners.
			$svg .= '<g class="cartiva-chart__day"><title>' . esc_html( $label ) . '</title>';
			$svg .= '<rect class="cartiva-chart__hit" x="' . round( $left + $i * $slot, 2 ) . '" y="8" width="' . round( $slot, 2 ) . '" height="' . $plot . '" />';
			if ( $h > 0 ) {
				$r    = min( 4, $bar / 2, $h );
				$svg .= '<path class="cartiva-chart__bar" d="M' . $x . ',' . ( 8 + $plot ) . ' V' . ( $y + $r ) . ' Q' . $x . ',' . $y . ' ' . ( $x + $r ) . ',' . $y . ' H' . ( $x + $bar - $r ) . ' Q' . ( $x + $bar ) . ',' . $y . ' ' . ( $x + $bar ) . ',' . ( $y + $r ) . ' V' . ( 8 + $plot ) . ' Z" />';
			}
			$svg .= '</g>';
			if ( 0 === $i % $step ) {
				$svg .= '<text class="cartiva-chart__axis" x="' . round( $left + $i * $slot + $slot / 2, 2 ) . '" y="' . ( $height - 6 ) . '" text-anchor="middle">' . esc_html( date_i18n( 'M j', strtotime( $day ) ) ) . '</text>';
			}
			++$i;
		}
		$svg .= '</svg>';

		$table = '<details class="cartiva-chart__table"><summary>' . esc_html__( 'Show as a table', 'cartiva-commerce' ) . '</summary><table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Day', 'cartiva-commerce' ) . '</th><th scope="col">' . esc_html__( 'Revenue', 'cartiva-commerce' ) . '</th></tr></thead><tbody>';
		foreach ( array_reverse( $daily, true ) as $day => $revenue ) {
			$table .= '<tr><td>' . esc_html( date_i18n( get_option( 'date_format' ), strtotime( $day ) ) ) . '</td><td>' . wp_kses_post( wc_price( $revenue ) ) . '</td></tr>';
		}
		$table .= '</tbody></table></details>';
		return $svg . $table;
	}

	/**
	 * Render the page.
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter.
		$days   = isset( $_GET['range'] ) ? absint( wp_unslash( $_GET['range'] ) ) : 30;
		$days   = isset( self::ranges()[ $days ] ) ? $days : 30;
		$report = self::report( $days );
		$t      = $report['totals'];
		$tiles  = array(
			__( 'Revenue', 'cartiva-commerce' )           => self::money( ( $t['revenue'] ) ),
			__( 'Orders', 'cartiva-commerce' )            => number_format_i18n( $t['orders'] ),
			__( 'Average order', 'cartiva-commerce' )     => $t['orders'] ? self::money( ( $t['revenue'] / $t['orders'] ) ) : '—',
			__( 'Conversion', 'cartiva-commerce' )        => self::percent( $t['orders'], $t['visits'] ),
			__( 'Order bump taken', 'cartiva-commerce' )  => self::percent( $t['bump_orders'], $t['orders'] ),
			__( 'Upsells accepted', 'cartiva-commerce' )  => self::percent( $t['upsell_yes'], $t['upsell_yes'] + $t['upsell_no'] ),
		);
		?>
		<div class="wrap cartiva-commerce-admin cartiva-analytics">
			<h1><?php esc_html_e( 'Funnel analytics', 'cartiva-commerce' ); ?></h1>
			<form method="get" class="cartiva-analytics__filter">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( CARTIVA_COMMERCE_FUNNEL ); ?>" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE ); ?>" />
				<label for="cartiva-range"><?php esc_html_e( 'Date range', 'cartiva-commerce' ); ?></label>
				<select id="cartiva-range" name="range" onchange="this.form.submit()">
					<?php foreach ( self::ranges() as $value => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $days, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<noscript><button class="button"><?php esc_html_e( 'Show', 'cartiva-commerce' ); ?></button></noscript>
			</form>

			<ul class="cartiva-tiles">
				<?php foreach ( $tiles as $label => $value ) : ?>
					<li><span><?php echo esc_html( $label ); ?></span><strong><?php echo esc_html( $value ); ?></strong></li>
				<?php endforeach; ?>
			</ul>

			<div class="cartiva-panel">
				<h2><?php esc_html_e( 'Revenue per day', 'cartiva-commerce' ); ?></h2>
				<?php echo self::chart( $report['daily'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts in chart(). ?>
			</div>

			<div class="cartiva-panel">
				<h2><?php esc_html_e( 'Funnels and landing pages', 'cartiva-commerce' ); ?></h2>
				<?php if ( empty( $report['rows'] ) ) : ?>
					<p><?php esc_html_e( 'No published funnels or landing pages yet.', 'cartiva-commerce' ); ?></p>
				<?php else : ?>
					<div class="cartiva-table-scroll">
					<table class="widefat striped">
						<thead><tr>
							<th scope="col"><?php esc_html_e( 'Name', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Visits', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Orders', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Conversion', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Revenue', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Average order', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Bump taken', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Bump revenue', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Upsells accepted', 'cartiva-commerce' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Upsell revenue', 'cartiva-commerce' ); ?></th>
						</tr></thead>
						<tbody>
							<?php foreach ( $report['rows'] as $row ) : ?>
								<tr>
									<th scope="row">
										<a href="<?php echo esc_url( (string) $row['link'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a>
										<span class="cartiva-kind"><?php echo esc_html( 'funnel' === $row['kind'] ? __( 'Funnel', 'cartiva-commerce' ) : __( 'Landing page', 'cartiva-commerce' ) ); ?></span>
									</th>
									<td><?php echo esc_html( number_format_i18n( $row['visits'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['orders'] ) ); ?></td>
									<td><?php echo esc_html( self::percent( $row['orders'], $row['visits'] ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $row['revenue'] ) ); ?></td>
									<td><?php echo $row['orders'] ? wp_kses_post( wc_price( $row['revenue'] / $row['orders'] ) ) : '—'; ?></td>
									<td><?php echo esc_html( self::percent( $row['bump_orders'], $row['orders'] ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $row['bump_revenue'] ) ); ?></td>
									<td><?php echo esc_html( self::percent( $row['upsell_yes'], $row['upsell_yes'] + $row['upsell_no'] ) ); ?></td>
									<td><?php echo wp_kses_post( wc_price( $row['upsell_revenue'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Visits count the first step of each funnel, without bots and your own visits while logged in. Orders count processing, completed and on-hold orders. Numbers refresh every 10 minutes. Daily visits are recorded from version 1.3 on.', 'cartiva-commerce' ); ?></p>
			</div>
		</div>
		<?php
	}
}
