<?php
/**
 * Front assets.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! wpjc_is_jobs_view() && ! wpjc_content_has_shortcode() ) {
			return;
		}
		$ver = static function ( $file ) {
			return WPJC_VERSION . '.' . (int) filemtime( WPJC_DIR . $file );
		};
		wp_enqueue_style( 'wp-job-core', WPJC_URL . 'assets/css/jobs.css', array(), $ver( 'assets/css/jobs.css' ) );
		if ( wpjc_is_account() ) {
			wp_enqueue_style( 'wp-job-core-account', WPJC_URL . 'assets/css/account.css', array( 'wp-job-core' ), $ver( 'assets/css/account.css' ) );
		}
		wp_enqueue_script( 'wp-job-core', WPJC_URL . 'assets/js/jobs.js', array(), $ver( 'assets/js/jobs.js' ), true );
		wp_localize_script(
			'wp-job-core',
			'wpjcL10n',
			array(
				'copied'  => __( 'Link copied', 'jobcore' ),
				'save'    => __( 'Bookmark job', 'jobcore' ),
				'saved'   => __( 'Remove bookmark', 'jobcore' ),
				'remove'  => __( 'Remove', 'jobcore' ),
				'cart'    => __( 'Cart', 'jobcore' ),
				/* translators: %d: max number of files */
				'tooMany' => __( 'You can add up to %d files.', 'jobcore' ),
				/* translators: %d: max size in MB */
				'tooBig'  => __( 'The files are too large: %d MB at most.', 'jobcore' ),
			)
		);
		wp_localize_script(
			'wp-job-core',
			'wpjcFavs',
			array(
				'loggedIn' => is_user_logged_in(),
				'url'      => rest_url( 'wpjc/v1/favs' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'items'    => is_user_logged_in() ? wpjc_favs_payload() : array(),
			)
		);
		wp_localize_script(
			'wp-job-core',
			'wpjcSearch',
			array(
				'url'      => rest_url( 'wpjc/v1/search' ),
				'countUrl' => rest_url( 'wpjc/v1/count' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'alertUrl' => wpjc_account_url( 'alerts', 'new' ),
				'i18n'     => array(
					'jobs'       => __( 'Jobs', 'jobcore' ),
					'employers'  => __( 'Employers', 'jobcore' ),
					'categories' => __( 'Categories', 'jobcore' ),
					'popular'    => __( 'Popular categories', 'jobcore' ),
					'recent'     => __( 'Recent searches', 'jobcore' ),
					'clear'      => __( 'Clear', 'jobcore' ),
					'idle'       => __( 'Type a job title, company or skill.', 'jobcore' ),
					'loading'    => __( 'Searching…', 'jobcore' ),
					/* translators: %s: search terms */
					'none'       => __( 'No jobs for “%s”', 'jobcore' ),
					'noneText'   => __( 'Try another word, or create a job alert and we e-mail you new matches.', 'jobcore' ),
					'alert'      => __( 'Create a job alert', 'jobcore' ),
					/* translators: 1: number of jobs, 2: search terms */
					'all'        => __( 'See all %1$s jobs for “%2$s”', 'jobcore' ),
					/* translators: %s: number of jobs */
					'count'      => __( '%s jobs', 'jobcore' ),
					'navigate'   => __( 'navigate', 'jobcore' ),
					'open'       => __( 'open', 'jobcore' ),
					'close'      => __( 'close', 'jobcore' ),
					/* translators: %s: number of jobs */
					'show'       => __( 'Show %s jobs', 'jobcore' ),
					'showOne'    => __( 'Show 1 job', 'jobcore' ),
					'showNone'   => __( 'No matching jobs', 'jobcore' ),
					'showAny'    => __( 'Show jobs', 'jobcore' ),
				),
			)
		);
		if ( wpjc_is_standalone() ) {
			$brand = wpjc_brand();
			$width = wpjc_site_width();
			$css   = '.wpjc-standalone{--wpjc-accent:' . $brand['accent'] . ';';
			$css  .= $width ? '--wpjc-content:' . (int) $width . 'px;' : '--wpjc-content:100vw;';
			$css  .= '}';
			wp_add_inline_style( 'wp-job-core', $css );
		} elseif ( sanitize_hex_color( (string) wpjc_opt( 'accent' ) ) ) {
			wp_add_inline_style( 'wp-job-core', 'body.wpjc{--wpjc-accent:' . wpjc_accent() . ';}' );
		}
	},
	30
);

/**
 * Standalone board header: drop the theme's own CSS/JS on job views so the board looks like its own site.
 * The News. header style keeps them (the header needs them).
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! wpjc_is_standalone() || 'news' === wpjc_header_style() || ! apply_filters( 'wpjc_standalone_dequeue_theme_assets', true ) ) {
			return;
		}
		$roots = array_unique( array( get_template_directory_uri(), get_stylesheet_directory_uri() ) );
		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			foreach ( (array) $deps->queue as $handle ) {
				$src = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';
				foreach ( $roots as $root ) {
					if ( '' !== $src && str_starts_with( $src, $root ) ) {
						if ( $deps instanceof WP_Styles ) {
							wp_dequeue_style( $handle );
						} else {
							wp_dequeue_script( $handle );
						}
						break;
					}
				}
			}
		}
	},
	999
);

/**
 * A logo set in JobCore replaces the News. logo in the News. header on job views.
 */
add_filter(
	'news_header_logo_html',
	static function ( $html, $class ) {
		$logo = (string) wpjc_opt( 'brand_logo' );
		if ( '' === $logo || ! did_action( 'wp' ) || ! wpjc_is_standalone() ) {
			return $html;
		}
		return sprintf( '<img src="%1$s" alt="%2$s" class="%3$s">', esc_url( $logo ), esc_attr( wpjc_brand()['name'] ), esc_attr( $class ) );
	},
	10,
	2
);

add_filter(
	'news_demo_switcher_on',
	static function ( $on ) {
		if ( ! $on || ! did_action( 'wp' ) ) {
			return $on;
		}
		if ( wpjc_is_standalone() ) {
			return false;
		}
		return ! wpjc_demo_switcher_on();
	}
);

add_filter(
	'body_class',
	static function ( $classes ) {
		if ( wpjc_is_jobs_view() || wpjc_content_has_shortcode() ) {
			$classes[] = 'wpjc';
		}
		if ( wpjc_is_account() ) {
			$classes[] = 'wpjc-account';
		}
		if ( wpjc_is_standalone() ) {
			$classes[] = 'wpjc-standalone';
			$classes[] = 'wpjc-head-' . wpjc_header_style();
		}
		$skin = wpjc_board_skin();
		if ( 'default' !== $skin ) {
			$classes[] = 'wpjc-skin-' . $skin;
		}
		return $classes;
	}
);

/**
 * Whether singular content includes [wpjc_jobs].
 */
function wpjc_content_has_shortcode() {
	if ( ! is_singular() ) {
		return false;
	}
	$post = get_post();
	return $post && has_shortcode( $post->post_content, 'wpjc_jobs' );
}
