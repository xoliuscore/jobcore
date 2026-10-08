<?php
/**
 * Demo look switcher (Jobs → Look & colours): a "Looks" tab on the right edge of the
 * jobs board opens a panel with every registered board skin. A visitor's choice is kept
 * in a cookie and only changes what that visitor sees — the site's own setting stays
 * untouched. Links like ?wpjc-look=market open a look directly (?wpjc-look=site goes back).
 *
 * Off by default; turn it on for a demo site under Jobs → Settings → Look & colours.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_DEMO_COOKIE = 'wpjc_demo_look';

/**
 * Whether this visitor may use the look switcher (mode only; not page-specific).
 */
function wpjc_demo_switcher_allowed() {
	if ( is_admin() || is_customize_preview() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	$mode = sanitize_key( (string) wpjc_opt( 'demo_switcher' ) );
	return 'everyone' === $mode || ( 'admins' === $mode && did_action( 'set_current_user' ) && current_user_can( 'manage_options' ) );
}

/**
 * Whether the Looks tab should render on this request.
 */
function wpjc_demo_switcher_on() {
	if ( ! wpjc_demo_switcher_allowed() ) {
		return false;
	}
	if ( count( wpjc_board_skins() ) < 2 ) {
		return false;
	}
	$on_board = ! did_action( 'wp' ) || wpjc_is_jobs_view() || wpjc_content_has_shortcode();
	/**
	 * Filters whether the look switcher shows on this request.
	 *
	 * @param bool $on Whether the switcher is on.
	 */
	return (bool) apply_filters( 'wpjc_demo_switcher_on', $on_board );
}

/**
 * Board skin this visitor picked ('' = the site's own).
 *
 * @return string
 */
function wpjc_demo_skin() {
	static $picked = null;
	if ( null !== $picked ) {
		return $picked;
	}
	$picked = '';
	if ( ! wpjc_demo_switcher_allowed() ) {
		return $picked;
	}
	$skins = array_keys( wpjc_board_skins() );
	$asked = isset( $_GET['wpjc-look'] ) ? sanitize_key( wp_unslash( $_GET['wpjc-look'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- display preference only.
	if ( isset( $_GET['wpjc-look'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- display preference only.
		$picked = in_array( $asked, $skins, true ) ? $asked : '';
		if ( ! headers_sent() ) {
			setcookie( WPJC_DEMO_COOKIE, $picked, $picked ? time() + DAY_IN_SECONDS : time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}
		return $picked;
	}
	$cookie = isset( $_COOKIE[ WPJC_DEMO_COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ WPJC_DEMO_COOKIE ] ) ) : '';
	$picked = in_array( $cookie, $skins, true ) ? $cookie : '';
	return $picked;
}

add_action(
	'wp',
	static function () {
		if ( ! wpjc_demo_skin() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- standard caching-plugin constant.
		}
		nocache_headers();
	},
	1
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! wpjc_demo_switcher_on() ) {
			return;
		}
		$ver = static function ( $file ) {
			return WPJC_VERSION . '.' . (int) filemtime( WPJC_DIR . $file );
		};
		wp_enqueue_style( 'wp-job-core-demo-switcher', WPJC_URL . 'assets/css/demo-switcher.css', array(), $ver( 'assets/css/demo-switcher.css' ) );
		wp_enqueue_script( 'wp-job-core-demo-switcher', WPJC_URL . 'assets/js/demo-switcher.js', array(), $ver( 'assets/js/demo-switcher.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
	},
	40
);

add_action(
	'wp_footer',
	static function () {
		if ( ! wpjc_demo_switcher_on() ) {
			return;
		}
		$current = wpjc_board_skin();
		$picked  = wpjc_demo_skin();
		$here    = remove_query_arg( 'wpjc-look' );
		?>
		<button type="button" class="wpjc-demo-tab" id="wpjcDemoTab" aria-expanded="false" aria-controls="wpjcDemoPanel">
			<span class="wpjc-demo-tab__icon" aria-hidden="true"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r="1.5"/><circle cx="17.5" cy="10.5" r="1.5"/><circle cx="8.5" cy="7.5" r="1.5"/><circle cx="6.5" cy="12.5" r="1.5"/><path d="M12 2a10 10 0 0 0 0 20c1.1 0 2-.9 2-2 0-.5-.2-1-.5-1.3-.3-.4-.5-.8-.5-1.3 0-1.1.9-2 2-2h2.4A5.6 5.6 0 0 0 22 9.8C22 5.5 17.5 2 12 2z"/></svg></span>
			<span class="wpjc-demo-tab__label"><?php esc_html_e( 'Looks', 'jobcore' ); ?></span>
		</button>
		<div class="wpjc-demo-panel" id="wpjcDemoPanel" role="dialog" aria-modal="false" aria-labelledby="wpjcDemoPanelTitle" hidden>
			<div class="wpjc-demo-panel__head">
				<p class="wpjc-demo-panel__title" id="wpjcDemoPanelTitle"><?php esc_html_e( 'Try a look', 'jobcore' ); ?></p>
				<button type="button" class="wpjc-demo-panel__close" data-wpjc-demo-close aria-label="<?php esc_attr_e( 'Close', 'jobcore' ); ?>"><?php echo wpjc_icon( 'close', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</div>
			<p class="wpjc-demo-panel__intro"><?php esc_html_e( 'Same jobs, different board look. Your choice only changes what you see in this browser.', 'jobcore' ); ?></p>
			<ul class="wpjc-demo-panel__grid">
				<?php foreach ( wpjc_board_skins() as $slug => $skin ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'wpjc-look', $slug, $here ) ); ?>" class="wpjc-demo-card<?php echo $slug === $current ? ' is-current' : ''; ?>"<?php echo $slug === $current ? ' aria-current="true"' : ''; ?>>
							<span class="wpjc-demo-mock wpjc-demo-mock--<?php echo esc_attr( $slug ); ?>" aria-hidden="true"></span>
							<span class="wpjc-demo-card__name"><?php echo esc_html( $skin['label'] ); ?></span>
							<span class="wpjc-demo-card__desc"><?php echo esc_html( $skin['description'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $picked ) : ?>
				<a class="wpjc-demo-panel__reset" href="<?php echo esc_url( add_query_arg( 'wpjc-look', 'site', $here ) ); ?>"><?php esc_html_e( 'Back to the site look', 'jobcore' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	},
	30
);
