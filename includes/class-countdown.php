<?php
/**
 * Landing page countdown: a timer for each visitor (evergreen, for example
 * 3, 5 or 7 days from their first visit) or to a fixed date for everyone.
 *
 * The deadline is worked out on the server, from a signed cookie for
 * evergreen timers, so what happens at the end (discounts end, the offer
 * closes) is enforced on the order too, not only on screen.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Countdown.
 */
class Cartiva_Commerce_Countdown {

	/**
	 * Deadlines worked out in this request: landing ID => timestamp.
	 *
	 * @var array
	 */
	private static $deadlines = array();

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'start_visitor' ), 4 );
		add_filter( 'cartiva_commerce_landing_config', array( __CLASS__, 'apply_ending' ), 10, 2 );
		add_action( 'wp_body_open', array( __CLASS__, 'bar' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate_order' ), 20, 2 );
	}

	/**
	 * Countdown settings of a landing page, with defaults.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return array
	 */
	public static function settings( $landing_id ) {
		$saved = get_post_meta( (int) $landing_id, '_cartiva_landing', true );
		$saved = is_array( $saved ) && isset( $saved['countdown'] ) && is_array( $saved['countdown'] ) ? $saved['countdown'] : array();
		return wp_parse_args(
			$saved,
			array(
				'enabled'  => false,
				'mode'     => 'evergreen',
				'days'     => 3,
				'hours'    => 0,
				'date'     => '',
				'ended'    => 'restart',
				'bar'      => true,
				'form'     => true,
				'style'    => 'boxes',
				'label'    => '',
				'bar_text' => '',
				'message'  => '',
			)
		);
	}

	/**
	 * Clean countdown settings from the landing page box.
	 *
	 * @param mixed $raw Raw values.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$mode  = isset( $raw['mode'] ) && 'fixed' === $raw['mode'] ? 'fixed' : 'evergreen';
		$ended = isset( $raw['ended'] ) ? sanitize_key( $raw['ended'] ) : 'restart';
		$date  = isset( $raw['date'] ) ? sanitize_text_field( $raw['date'] ) : '';
		$days  = isset( $raw['days'] ) ? absint( $raw['days'] ) : 3;
		$hours = isset( $raw['hours'] ) ? absint( $raw['hours'] ) : 0;
		if ( 0 === $days && 0 === $hours ) {
			$days = 3;
		}
		if ( 'fixed' === $mode && 'restart' === $ended ) {
			$ended = 'hide'; // A fixed date cannot restart.
		}
		return array(
			'enabled'  => ! empty( $raw['enabled'] ),
			'mode'     => $mode,
			'days'     => min( 60, $days ),
			'hours'    => min( 23, $hours ),
			'date'     => preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $date ) ? $date : '',
			'ended'    => in_array( $ended, array( 'restart', 'hide', 'price', 'close' ), true ) ? $ended : 'restart',
			'bar'      => ! empty( $raw['bar'] ),
			'form'     => ! empty( $raw['form'] ),
			'style'    => isset( $raw['style'] ) && 'inline' === $raw['style'] ? 'inline' : 'boxes',
			'label'    => isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '',
			'bar_text' => isset( $raw['bar_text'] ) ? sanitize_text_field( $raw['bar_text'] ) : '',
			'message'  => isset( $raw['message'] ) ? sanitize_text_field( $raw['message'] ) : '',
		);
	}

	/**
	 * Length of an evergreen timer in seconds.
	 *
	 * @param array $settings Countdown settings.
	 * @return int
	 */
	public static function period( $settings ) {
		return max( 60, (int) $settings['days'] * DAY_IN_SECONDS + (int) $settings['hours'] * HOUR_IN_SECONDS );
	}

	/**
	 * Cookie name of a page's evergreen timer.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return string
	 */
	public static function cookie_name( $landing_id ) {
		return 'cartiva_cd_' . (int) $landing_id;
	}

	/**
	 * Signed cookie value for a start time.
	 *
	 * @param int $landing_id Landing page ID.
	 * @param int $start      Start timestamp.
	 * @return string
	 */
	public static function sign( $landing_id, $start ) {
		return (int) $start . '.' . substr( wp_hash( 'cartiva_cd|' . (int) $landing_id . '|' . (int) $start ), 0, 16 );
	}

	/**
	 * This visitor's start time for a page, or 0 (not started, or a cookie
	 * that was changed by hand).
	 *
	 * @param int $landing_id Landing page ID.
	 * @return int
	 */
	public static function visitor_start( $landing_id ) {
		$name = self::cookie_name( $landing_id );
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return 0;
		}
		$value = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		$start = (int) strtok( $value, '.' );
		return ( $start > 0 && $start <= time() && hash_equals( self::sign( $landing_id, $start ), $value ) ) ? $start : 0;
	}

	/**
	 * Remember a start time for this visitor (cookie, 90 days).
	 *
	 * @param int $landing_id Landing page ID.
	 * @param int $start      Start timestamp.
	 */
	public static function remember( $landing_id, $start ) {
		$value                               = self::sign( $landing_id, $start );
		$_COOKIE[ self::cookie_name( $landing_id ) ] = $value;
		if ( ! headers_sent() ) {
			setcookie(
				self::cookie_name( $landing_id ),
				$value,
				array(
					'expires'  => time() + 90 * DAY_IN_SECONDS,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}
	}

	/**
	 * The page's deadline for this visitor, or 0 (no timer, or not started).
	 *
	 * @param int $landing_id Landing page ID.
	 * @return int Timestamp.
	 */
	public static function deadline( $landing_id ) {
		$landing_id = (int) $landing_id;
		if ( isset( self::$deadlines[ $landing_id ] ) ) {
			return self::$deadlines[ $landing_id ];
		}
		$settings = self::settings( $landing_id );
		$deadline = 0;
		if ( $settings['enabled'] ) {
			if ( 'fixed' === $settings['mode'] ) {
				if ( '' !== $settings['date'] ) {
					try {
						$deadline = ( new DateTimeImmutable( $settings['date'], wp_timezone() ) )->getTimestamp();
					} catch ( Exception $e ) {
						$deadline = 0;
					}
				}
			} else {
				$start    = self::visitor_start( $landing_id );
				$deadline = $start ? $start + self::period( $settings ) : 0;
			}
		}
		self::$deadlines[ $landing_id ] = $deadline;
		return $deadline;
	}

	/**
	 * Has the offer ended for this visitor?
	 *
	 * @param int $landing_id Landing page ID.
	 * @return bool
	 */
	public static function ended( $landing_id ) {
		$deadline = self::deadline( $landing_id );
		return $deadline > 0 && time() >= $deadline;
	}

	/**
	 * What happens now for this visitor: '' (nothing ended), or the ending
	 * action once the time is up.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return string '', hide, price or close.
	 */
	public static function ending( $landing_id ) {
		$settings = self::settings( $landing_id );
		if ( ! $settings['enabled'] || ! self::ended( $landing_id ) || 'restart' === $settings['ended'] ) {
			return '';
		}
		return $settings['ended'];
	}

	/**
	 * Start (or restart) the visitor's evergreen timer on a landing page.
	 */
	public static function start_visitor() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$landing_id = (int) get_queried_object_id();
		$settings   = self::settings( $landing_id );
		if ( ! $settings['enabled'] || 'evergreen' !== $settings['mode'] || cartiva_commerce_is_bot() ) {
			return;
		}
		$start = self::visitor_start( $landing_id );
		if ( ! $start || ( 'restart' === $settings['ended'] && time() >= $start + self::period( $settings ) ) ) {
			self::remember( $landing_id, time() );
			unset( self::$deadlines[ $landing_id ] );
		}
	}

	/**
	 * When the offer has ended with "discounts end", the shop uses full prices.
	 *
	 * @param array $config     Landing settings.
	 * @param int   $landing_id Landing page ID.
	 * @return array
	 */
	public static function apply_ending( $config, $landing_id ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $config;
		}
		if ( 'price' !== self::ending( $landing_id ) ) {
			return $config;
		}
		foreach ( array( 'offers', 'bumps' ) as $list ) {
			foreach ( (array) $config[ $list ] as $i => $row ) {
				if ( is_array( $row ) ) {
					$config[ $list ][ $i ]['discount'] = 0;
				}
			}
		}
		return $config;
	}

	/**
	 * Refuse an order from a closed offer (when the form was left open).
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate_order( $data, $errors ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( $landing_id && 'close' === self::ending( $landing_id ) ) {
			$errors->add( 'cartiva_offer_ended', self::ended_message( $landing_id ) );
		}
	}

	/**
	 * The "offer ended" text.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return string
	 */
	public static function ended_message( $landing_id ) {
		$message = self::settings( $landing_id )['message'];
		return '' !== $message ? $message : __( 'Sorry, this offer has ended.', 'cartiva-commerce' );
	}

	/**
	 * Timer markup.
	 *
	 * @param int    $landing_id Landing page ID.
	 * @param string $where      form, bar or block.
	 * @return string
	 */
	public static function html( $landing_id, $where = 'block' ) {
		$settings = self::settings( $landing_id );
		$deadline = self::deadline( $landing_id );
		if ( ! $settings['enabled'] || ! $deadline ) {
			return '';
		}
		$left = max( 0, $deadline - time() );
		if ( 0 === $left && 'restart' !== $settings['ended'] ) {
			return 'close' === $settings['ended'] || 'price' === $settings['ended']
				? '<p class="cartiva-countdown cartiva-countdown--ended">' . esc_html( 'price' === $settings['ended'] ? __( 'The special price has ended.', 'cartiva-commerce' ) : self::ended_message( $landing_id ) ) . '</p>'
				: '';
		}
		$parts = array(
			'd' => array( intdiv( $left, DAY_IN_SECONDS ), __( 'Days', 'cartiva-commerce' ) ),
			'h' => array( intdiv( $left % DAY_IN_SECONDS, HOUR_IN_SECONDS ), __( 'Hours', 'cartiva-commerce' ) ),
			'm' => array( intdiv( $left % HOUR_IN_SECONDS, MINUTE_IN_SECONDS ), __( 'Minutes', 'cartiva-commerce' ) ),
			's' => array( $left % MINUTE_IN_SECONDS, __( 'Seconds', 'cartiva-commerce' ) ),
		);
		$label = '' !== $settings['label'] ? $settings['label'] : __( 'Offer ends in', 'cartiva-commerce' );
		$style = 'bar' === $where ? 'inline' : $settings['style'];
		$html  = '<div class="cartiva-countdown cartiva-countdown--' . esc_attr( $style ) . ' cartiva-countdown--' . esc_attr( $where ) . '" data-deadline="' . esc_attr( (string) ( $deadline * 1000 ) ) . '" data-ended="' . esc_attr( $settings['ended'] ) . '">';
		$html .= '<span class="cartiva-countdown__label">' . esc_html( $label ) . '</span>';
		$html .= '<span class="cartiva-countdown__units" role="timer" aria-label="' . esc_attr( $label ) . '">';
		foreach ( $parts as $key => $part ) {
			if ( 'd' === $key && 0 === $part[0] && 'inline' === $style ) {
				continue;
			}
			$html .= '<span class="cartiva-countdown__unit"><span class="cartiva-countdown__num" data-unit="' . esc_attr( $key ) . '">' . esc_html( str_pad( (string) $part[0], 2, '0', STR_PAD_LEFT ) ) . '</span><span class="cartiva-countdown__name">' . esc_html( $part[1] ) . '</span></span>';
		}
		$html .= '</span></div>';
		return $html;
	}

	/**
	 * The sticky bar at the top of the page.
	 */
	public static function bar() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$landing_id = (int) get_queried_object_id();
		$settings   = self::settings( $landing_id );
		if ( ! $settings['enabled'] || ! $settings['bar'] ) {
			return;
		}
		$timer = self::html( $landing_id, 'bar' );
		if ( '' === $timer ) {
			return;
		}
		$post = get_post( $landing_id );
		$next = cartiva_commerce_next_step( $landing_id );
		$link = ( $post && has_block( 'cartiva-commerce/order-form', $post ) ) || ! $next ? '#order' : (string) get_permalink( $next );
		$text = '' !== $settings['bar_text'] ? $settings['bar_text'] : __( 'Special price for a short time', 'cartiva-commerce' );
		echo '<div class="cartiva-countdown-bar"><div class="cartiva-countdown-bar__inner"><span class="cartiva-countdown-bar__text">' . esc_html( $text ) . '</span>';
		echo $timer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in html().
		if ( ! self::ending( $landing_id ) ) {
			echo '<a class="cartiva-countdown-bar__button" href="' . esc_url( $link ) . '">' . esc_html__( 'Order now', 'cartiva-commerce' ) . '</a>';
		}
		echo '</div></div>';
	}

	/**
	 * Script that ticks the timers.
	 */
	public static function assets() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) || ! self::settings( (int) get_queried_object_id() )['enabled'] ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-order-form', CARTIVA_COMMERCE_URL . 'assets/css/order-form.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-countdown', CARTIVA_COMMERCE_URL . 'assets/js/countdown.js', array(), CARTIVA_COMMERCE_VERSION, array( 'in_footer' => true ) );
	}

	/**
	 * The Countdown block (place the timer anywhere on the page).
	 */
	public static function register_block() {
		register_block_type( CARTIVA_COMMERCE_DIR . 'blocks/countdown', array( 'render_callback' => array( __CLASS__, 'render_block' ) ) );
	}

	/**
	 * Render the Countdown block.
	 *
	 * @return string
	 */
	public static function render_block() {
		$landing_id = (int) get_the_ID();
		if ( CARTIVA_COMMERCE_LANDING !== get_post_type( $landing_id ) ) {
			return '';
		}
		$html = self::html( $landing_id, 'block' );
		if ( '' === $html && current_user_can( 'edit_post', $landing_id ) && ! self::settings( $landing_id )['enabled'] ) {
			return '<p class="cartiva-order-form__notice">' . esc_html__( 'Turn the countdown on in the Countdown tab of the landing page settings.', 'cartiva-commerce' ) . '</p>';
		}
		return '' !== $html ? '<div ' . get_block_wrapper_attributes( array( 'class' => 'cartiva-countdown-block' ) ) . '>' . $html . '</div>' : '';
	}
}
