<?php
/**
 * Shared helpers.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Jobs board page URL.
 */
function wpjc_home_url() {
	$id = (int) get_option( 'wpjc_jobs_page_id' );
	if ( $id && 'publish' === get_post_status( $id ) ) {
		return get_permalink( $id );
	}
	$archive = get_post_type_archive_link( 'wpjc_job' );
	return $archive ? $archive : home_url( '/' );
}

/**
 * @param string $key Setting key.
 * @return mixed
 */
function wpjc_opt( $key ) {
	$opts = wp_parse_args( (array) get_option( 'wpjc_settings', array() ), wpjc_default_settings() );
	return $opts[ $key ] ?? null;
}

/**
 * @return array<string, mixed>
 */
function wpjc_default_settings() {
	return array(
		'list_title'       => __( 'Find a job you love', 'jobcore' ),
		'list_intro'       => __( 'Open the door to new opportunities.', 'jobcore' ),
		'search_hint'      => __( 'e.g. manager, doctor, sales…', 'jobcore' ),
		'per_column'       => 8,
		'employers_n'      => 12,
		'per_page'         => 24,
		'fe_review'        => 1,
		'fe_days'          => 30,
		'app_files'        => 1,
		'app_files_days'   => 90,
		'contact_email'    => '',
		'contact_phone'    => '',
		'layout'           => 'theme',
		'header_style'     => 'board',
		'scheme'           => 'light',
		'feeds_on'         => 1,
		'country'          => '',
		'currency'         => '',
		'indexing_on'      => 0,
		'scheme_switch'    => 1,
		'board_skin'       => 'default',
		'demo_switcher'    => 'off',
		'brand_name'       => '',
		'brand_logo'       => '',
		'brand_logo_light' => '',
		'brand_tag'        => '',
		'brand_tag_color'  => '',
		'logo_height'      => 0,
		'logo_height_m'    => 0,
		'logo_width'       => 0,
		'accent'           => '',
		'hero_image'       => '',
		'social_facebook'  => '',
		'social_instagram' => '',
		'social_linkedin'  => '',
	);
}

/**
 * Board visual skins. Add-ons register extra looks via the filter.
 *
 * @return array<string, array{label:string,description:string,header?:string}>
 */
function wpjc_board_skins() {
	return (array) apply_filters(
		'wpjc_board_skins',
		array(
			'default' => array(
				'label'       => __( 'Default', 'jobcore' ),
				'description' => __( 'The board look included with JobCore.', 'jobcore' ),
				'header'      => 'light',
			),
		)
	);
}

/**
 * Whether the current board look uses a dark header (needs a light / white logo).
 */
function wpjc_header_is_dark() {
	$skins = wpjc_board_skins();
	$skin  = wpjc_board_skin();
	$mode  = isset( $skins[ $skin ]['header'] ) ? (string) $skins[ $skin ]['header'] : 'light';
	return (bool) apply_filters( 'wpjc_header_is_dark', 'dark' === $mode, $skin );
}

/**
 * Board skin saved in settings (ignores the demo switcher cookie).
 */
function wpjc_saved_board_skin() {
	$skin  = sanitize_key( (string) wpjc_opt( 'board_skin' ) );
	$skins = wpjc_board_skins();
	return isset( $skins[ $skin ] ) ? $skin : 'default';
}

/**
 * Selected board skin slug. Falls back to default when the add-on is off.
 * A demo-switcher pick (this browser only) wins over the saved setting.
 */
function wpjc_board_skin() {
	$skin = wpjc_saved_board_skin();
	$demo = function_exists( 'wpjc_demo_skin' ) ? wpjc_demo_skin() : '';
	return $demo ? $demo : $skin;
}

/**
 * Accent colour presets for the settings screen: hex => label.
 *
 * @return array<string, string>
 */
function wpjc_accent_presets() {
	return array(
		'#2563eb' => __( 'Ocean', 'jobcore' ),
		'#4f46e5' => __( 'Indigo', 'jobcore' ),
		'#0f766e' => __( 'Teal', 'jobcore' ),
		'#16a34a' => __( 'Green', 'jobcore' ),
		'#ea580c' => __( 'Orange', 'jobcore' ),
		'#dc2626' => __( 'Red', 'jobcore' ),
		'#db2777' => __( 'Pink', 'jobcore' ),
		'#111827' => __( 'Graphite', 'jobcore' ),
	);
}

/**
 * Board accent: the saved colour, else the News. theme brand colour, else blue.
 */
function wpjc_accent() {
	$accent = sanitize_hex_color( (string) wpjc_opt( 'accent' ) );
	if ( $accent ) {
		return $accent;
	}
	if ( wpjc_has_news_theme() ) {
		$brand = sanitize_hex_color( (string) get_theme_mod( 'brand_color', '#D7141B' ) );
		if ( $brand ) {
			return $brand;
		}
	}
	return '#2563eb';
}

/**
 * Listing tiers, highest first: key => label, short badge, colour.
 *
 * @return array<string, array{label:string,short:string,color:string}>
 */
function wpjc_tiers() {
	return (array) apply_filters(
		'wpjc_tiers',
		array(
			'premium_plus' => array(
				'label' => __( 'Premium Plus', 'jobcore' ),
				'short' => 'P+',
				'color' => 'var(--wpjc-a)',
			),
			'premium'      => array(
				'label' => __( 'Premium', 'jobcore' ),
				'short' => 'P',
				'color' => '#f59e0b',
			),
			'basic'        => array(
				'label' => __( 'Basic', 'jobcore' ),
				'short' => 'B',
				'color' => '#64748b',
			),
		)
	);
}

/**
 * A job's tier key (unknown or empty = basic).
 *
 * @param int|WP_Post|null $post Job.
 */
function wpjc_job_tier( $post = null ) {
	$tier = wpjc_meta( $post, 'tier' );
	return isset( wpjc_tiers()[ $tier ] ) ? $tier : 'basic';
}

/**
 * Current board view: home, all, employers.
 */
function wpjc_view() {
	$view = sanitize_key( (string) get_query_var( 'wpjc_view' ) );
	return in_array( $view, array( 'all', 'employers' ), true ) ? $view : 'home';
}

/**
 * Standalone look: job views get the plugin's own header, footer and styles (no theme chrome).
 */
function wpjc_is_standalone() {
	return 'standalone' === wpjc_opt( 'layout' ) && wpjc_is_jobs_view();
}

/**
 * Header for job templates: theme header, or the standalone board header.
 */
function wpjc_get_header() {
	if ( wpjc_is_standalone() ) {
		wpjc_template( 'standalone/header' );
		return;
	}
	get_header();
}

/**
 * Footer for job templates: theme footer, or the standalone board footer.
 */
function wpjc_get_footer() {
	if ( wpjc_is_standalone() ) {
		wpjc_template( 'standalone/footer' );
		return;
	}
	get_footer();
}

/**
 * Board brand and links for the standalone chrome.
 *
 * @return array<string, mixed>
 */
function wpjc_brand() {
	$name = trim( (string) wpjc_opt( 'brand_name' ) );
	if ( '' === $name ) {
		/* translators: %s: site name */
		$name = sprintf( __( '%s Jobs', 'jobcore' ), get_bloginfo( 'name' ) );
	}
	$email  = sanitize_email( (string) wpjc_opt( 'contact_email' ) );
	$social = array_filter(
		array(
			'facebook'  => (string) wpjc_opt( 'social_facebook' ),
			'instagram' => (string) wpjc_opt( 'social_instagram' ),
			'linkedin'  => (string) wpjc_opt( 'social_linkedin' ),
		)
	);
	return array(
		'name'         => $name,
		'logo'         => (string) wpjc_opt( 'brand_logo' ),
		'accent'       => wpjc_accent(),
		'email'        => $email,
		'phone'        => trim( (string) wpjc_opt( 'contact_phone' ) ),
		'post_url'     => wpjc_account_url( 'jobs', 'new' ),
		'employer_url' => wpjc_account_url( 'companies', 'new' ),
		// Only when an add-on (Resumes) registers the screen; wpjc_account_url() would fall back to the overview.
		'resume_url'   => isset( wpjc_account_sections()['resume'] ) ? wpjc_account_url( 'resume' ) : '',
		'social'       => $social,
	);
}

/**
 * Jobs menu for the standalone headers: key => [label, URL, icon]. Items without a URL are dropped.
 *
 * @return array<string, array{0:string,1:string,2:string}>
 */
function wpjc_nav_items() {
	$brand = wpjc_brand();
	$items = array(
		'home'      => array( __( 'Home', 'jobcore' ), wpjc_view_url( 'home' ), 'home' ),
		'all'       => array( __( 'All jobs', 'jobcore' ), wpjc_view_url( 'all' ), 'briefcase' ),
		'post'      => array( __( 'Post a job', 'jobcore' ), $brand['post_url'], 'plus-c' ),
		'company'   => array( __( 'Add company', 'jobcore' ), $brand['employer_url'], 'plus-c' ),
		'employers' => array( __( 'Employers', 'jobcore' ), wpjc_view_url( 'employers' ), 'building' ),
	);
	return array_filter(
		(array) apply_filters( 'wpjc_standalone_nav_items', $items ),
		static function ( $item ) {
			return ! empty( $item[1] );
		}
	);
}

/**
 * Which jobs menu item matches this request.
 */
function wpjc_nav_active() {
	$section = wpjc_account_section();
	if ( '' !== $section ) {
		$new = 'new' === wpjc_account_item();
		if ( $new && 'jobs' === $section ) {
			return 'post';
		}
		return $new && 'companies' === $section ? 'company' : '';
	}
	$view = wpjc_view();
	if ( is_tax( 'wpjc_employer' ) || 'employers' === $view ) {
		return 'employers';
	}
	if ( is_page() && 'home' === $view && ! wpjc_filters_active( wpjc_search_filters() ) ) {
		return 'home';
	}
	return 'all';
}

/**
 * Whether the News. theme (header parts + logo helpers) is active.
 */
function wpjc_has_news_theme() {
	return function_exists( 'news_part' ) && function_exists( 'news_logo_url' );
}

/**
 * Standalone header style: "board" (jobs portal header) or "news" (News. site header with the jobs menu).
 */
function wpjc_header_style() {
	return 'news' === wpjc_opt( 'header_style' ) && wpjc_has_news_theme() ? 'news' : 'board';
}

/**
 * Board logo: Job Core uploads, else the matching News. theme logo (light wordmark on a dark
 * header, dark wordmark on a light header), else the board name as text.
 *
 * @param string $class Image class.
 * @return string Safe HTML.
 */
function wpjc_board_logo_html( $class = 'wpjc-logo-img' ) {
	$brand = wpjc_brand();
	$size  = wpjc_logo_size();
	$dark  = wpjc_header_is_dark();
	$src   = wpjc_board_logo_src( $dark );
	if ( '' === $src ) {
		return '<span class="wpjc-top__name">' . esc_html( $brand['name'] ) . '</span>' . wpjc_brand_tag_html();
	}
	$style = '--wpjc-logo-h:' . $size['height'] . 'px;--wpjc-logo-hm:' . $size['mobile'] . 'px;' . ( $size['width'] ? '--wpjc-logo-w:' . $size['width'] . 'px;' : '' );
	$img   = static function ( $src, $extra ) use ( $class, $brand, $style ) {
		return sprintf(
			'<img class="%1$s" src="%2$s" alt="%3$s" style="%4$s">',
			esc_attr( trim( $class . ' ' . $extra ) ),
			esc_url( $src ),
			esc_attr( $brand['name'] ),
			esc_attr( $style )
		);
	};
	$html = $img( $src, '' );
	// A light header turns dark in dark mode: keep the logo for dark backgrounds ready.
	if ( ! $dark && function_exists( 'wpjc_scheme_on' ) && wpjc_scheme_on() ) {
		$night = wpjc_board_logo_src( true );
		if ( '' !== $night && $night !== $src ) {
			$html = $img( $src, 'wpjc-logo--day' ) . $img( $night, 'wpjc-logo--night' );
		}
	}
	return $html . wpjc_brand_tag_html();
}

/**
 * Board logo file for a light or a dark background ('' = none, show the name).
 *
 * @param bool $dark For a dark background.
 * @return string
 */
function wpjc_board_logo_src( $dark ) {
	$src = $dark ? (string) wpjc_opt( 'brand_logo_light' ) : '';
	if ( '' === $src ) {
		$src = (string) wpjc_opt( 'brand_logo' );
		if ( $dark && '' !== $src && wpjc_has_news_theme() ) {
			$src = news_logo_url( 'light' );
		}
	}
	if ( '' === $src && wpjc_has_news_theme() ) {
		$src = news_logo_url( $dark ? 'light' : 'dark' );
	}
	return $src;
}

/**
 * Word next to the board logo (empty = hidden).
 */
function wpjc_brand_tag() {
	return trim( (string) wpjc_opt( 'brand_tag' ) );
}

/**
 * Logo-label colour: a custom hex, else the board / theme accent.
 */
function wpjc_brand_tag_color() {
	$custom = sanitize_hex_color( (string) wpjc_opt( 'brand_tag_color' ) );
	return $custom ? $custom : wpjc_accent();
}

/**
 * Logo-label markup, or empty when none is set.
 *
 * @return string Safe HTML.
 */
function wpjc_brand_tag_html() {
	$tag = wpjc_brand_tag();
	if ( '' === $tag ) {
		return '';
	}
	return sprintf(
		'<span class="wpjc-logo-tag" style="--wpjc-tag:%1$s">%2$s</span>',
		esc_attr( wpjc_brand_tag_color() ),
		esc_html( $tag )
	);
}

/**
 * Board logo size: height on computers / phones (empty = the News. theme's logo size) and max width (0 = auto).
 *
 * @return array{height:int,mobile:int,width:int}
 */
function wpjc_logo_size() {
	$height = (int) wpjc_opt( 'logo_height' );
	$mobile = (int) wpjc_opt( 'logo_height_m' );
	if ( ! $height ) {
		$height = function_exists( 'news_logo_height' ) ? (int) news_logo_height() : 32;
	}
	if ( ! $mobile ) {
		$mobile = function_exists( 'news_logo_height' ) ? (int) news_logo_height( true ) : 26;
	}
	return array(
		'height' => max( 16, min( 80, $height ) ),
		'mobile' => max( 16, min( 60, $mobile ) ),
		'width'  => max( 0, min( 400, (int) wpjc_opt( 'logo_width' ) ) ),
	);
}

/**
 * Content width for the standalone look: the theme's Site width when available, else 1280 (0 = full).
 */
function wpjc_site_width() {
	$width = function_exists( 'news_site_width' ) ? (int) news_site_width() : 1280;
	return (int) apply_filters( 'wpjc_site_width', $width );
}

/**
 * "29.09. – 29.10.2026" (posted – expires), or just the posted date.
 *
 * @param int|WP_Post|null $post Job.
 */
function wpjc_job_dates( $post = null ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	$posted  = (int) get_post_time( 'U', false, $post );
	$expires = wpjc_meta( $post, 'expires' );
	$end     = $expires ? strtotime( $expires . ' 12:00:00' ) : 0;
	if ( $end ) {
		return wp_date( 'd.m.', $posted ) . ' – ' . wp_date( 'd.m.Y', $end );
	}
	return wp_date( 'd.m.Y', $posted );
}

/**
 * Share of the listing period already passed, 0–100 (null when there is no expiry).
 *
 * @param int|WP_Post|null $post Job.
 * @return int|null
 */
function wpjc_job_progress( $post = null ) {
	$post    = get_post( $post );
	$expires = $post ? wpjc_meta( $post, 'expires' ) : '';
	if ( ! $expires ) {
		return null;
	}
	$start = (int) get_post_time( 'U', true, $post );
	$end   = (int) strtotime( $expires . ' 23:59:59' );
	if ( $end <= $start ) {
		return 100;
	}
	return (int) max( 0, min( 100, round( ( time() - $start ) / ( $end - $start ) * 100 ) ) );
}

/**
 * Meta helper.
 *
 * @param int|WP_Post|null $post Job.
 * @param string           $key  Meta key without prefix.
 */
function wpjc_meta( $post, $key ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return '';
	}
	return (string) get_post_meta( $post->ID, '_wpjc_' . $key, true );
}

/**
 * Whether this request is a JobCore front view.
 */
function wpjc_is_jobs_view() {
	if ( is_singular( 'wpjc_job' ) || is_post_type_archive( 'wpjc_job' ) || is_tax( array( 'wpjc_job_type', 'wpjc_job_category', 'wpjc_employer' ) ) ) {
		return true;
	}
	$id = (int) get_option( 'wpjc_jobs_page_id' );
	return (bool) ( $id && is_page( $id ) );
}

/**
 * Load a plugin template (theme override: theme/wp-job-core/{slug}.php).
 *
 * @param string               $slug Template slug.
 * @param array<string, mixed> $args Vars.
 */
function wpjc_template( $slug, array $args = array() ) {
	$slug = trim( (string) preg_replace( '#[^a-z0-9\-_/]#', '', strtolower( $slug ) ), '/' );
	if ( ! $slug ) {
		return;
	}
	$file = locate_template( array( 'wp-job-core/' . $slug . '.php' ) );
	if ( ! $file ) {
		$file = WPJC_DIR . 'templates/' . $slug . '.php';
	}
	$file = (string) apply_filters( 'wpjc_template_file', $file, $slug );
	if ( ! $file || ! is_readable( $file ) ) {
		return;
	}
	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract
	extract( $args, EXTR_SKIP );
	include $file;
}

/**
 * Display name of a social network key.
 *
 * @param string $net facebook|instagram|linkedin.
 */
function wpjc_social_label( $net ) {
	$labels = array(
		'facebook'  => 'Facebook',
		'instagram' => 'Instagram',
		'linkedin'  => 'LinkedIn',
	);
	return $labels[ $net ] ?? ucfirst( (string) $net );
}

/**
 * Profile photo when the user has uploaded one (news-core), otherwise the name initial.
 *
 * @param WP_User|int $user  User.
 * @param int         $size  Pixel size for get_avatar().
 * @param string      $class Wrapper class.
 * @return string Safe HTML.
 */
function wpjc_avatar_html( $user, $size = 44, $class = 'wpjc-avatar' ) {
	if ( ! $user instanceof WP_User ) {
		$user = get_userdata( (int) $user );
	}
	if ( ! $user || ! $user->exists() ) {
		return '';
	}

	$photo = '';
	if ( function_exists( 'news_core_avatar_id' ) && news_core_avatar_id( $user->ID ) ) {
		$photo = get_avatar(
			$user->ID,
			(int) $size,
			'',
			'',
			array(
				'class' => 'avatar',
			)
		);
	}

	if ( $photo ) {
		return sprintf(
			'<span class="%1$s" aria-hidden="true">%2$s</span>',
			esc_attr( $class ),
			$photo
		);
	}

	return sprintf(
		'<span class="%1$s" aria-hidden="true">%2$s</span>',
		esc_attr( $class ),
		esc_html( mb_strtoupper( mb_substr( $user->display_name, 0, 1 ) ) )
	);
}

/**
 * Small inline SVG icons (stroke, currentColor; social brands are filled).
 *
 * @param string $name Icon key.
 * @param int    $size Pixel size.
 * @return string Static SVG markup.
 */
function wpjc_icon( $name, $size = 18 ) {
	$paths = array(
		'home'      => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h5v-6h4v6h5V10"/>',
		'wallet'    => '<path d="M4 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a1 1 0 0 1-1-1V7z"/><path d="M4 7V6a2 2 0 0 1 2-2h10v3M16 13.5h.01"/>',
		'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
		'moon'      => '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
		'monitor'   => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
		'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 13h18"/>',
		'plus'      => '<path d="M12 5v14M5 12h14"/>',
		'cart'      => '<path d="M2.5 3.5h2.6l2.4 11.3a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.4-1.1L21 7.5H6"/><circle cx="9.5" cy="20" r="1.4"/><circle cx="17.5" cy="20" r="1.4"/>',
		'plus-c'    => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
		'building'  => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2M10 21v-3h4v3"/>',
		'heart'     => '<path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/>',
		'bookmark'  => '<path d="M6 4h12v17l-6-4-6 4z"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
		'calendar'  => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
		'pin'       => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'email'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
		'phone'     => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'link'      => '<path d="M10 14a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 10a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
		'arrow'     => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'send'      => '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
		'file'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
		'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-8 9"/>',
		'cog'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
		'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
		'login'     => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/>',
		'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
		'sliders'   => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12M20 18h0"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
		'chevron'   => '<path d="m6 9 6 6 6-6"/>',
		'info'      => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
		'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
		'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
		'bell-off'  => '<path d="M8.7 3A6 6 0 0 1 18 8c0 2.7.6 4.7 1.3 6.1M17 17H3s3-2 3-9c0-.6.1-1.2.3-1.8"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0M3 3l18 18"/>',
		'check'     => '<path d="m5 12 5 5L20 7"/>',
		'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
		'trash'     => '<path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/>',
		'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
		'clock'     => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
		'lock'      => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
		'pause'     => '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
		'play'      => '<path d="M7 4v16l13-8z"/>',
		'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
		'id'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><path d="M6.5 16a2.5 2.5 0 0 1 5 0M14 10h4M14 14h3"/>',
		'receipt'   => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h3"/>',
		'map'       => '<path d="m9 4-6 2v14l6-2 6 2 6-2V4l-6 2z"/><path d="M9 4v14M15 6v14"/>',
		'star'      => '<path d="m12 3 2.7 5.6 6.1.9-4.4 4.3 1 6.1L12 17l-5.4 2.9 1-6.1L3.2 9.5l6.1-.9z"/>',
		'megaphone' => '<path d="M3 10v4a1 1 0 0 0 1 1h3l9 5V4L7 9H4a1 1 0 0 0-1 1z"/><path d="M7 15l1.5 5.5M19.5 9.5a3.5 3.5 0 0 1 0 5"/>',
		'tag'       => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="8" cy="8" r="1.5"/>',
		'inbox'     => '<path d="M3 13h5l1.5 3h5L16 13h5"/><path d="M5.5 5h13L21 13v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-6z"/>',
		'download'  => '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>',
		'external'  => '<path d="M14 4h6v6M20 4 10 14M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
	);
	$solid = array(
		'user-fill' => '<path fill-rule="evenodd" d="M14 7c0-1.103-.897-2-2-2s-2 .897-2 2 .897 2 2 2 2-.897 2-2m2 0c0 2.206-1.794 4-4 4S8 9.206 8 7s1.794-4 4-4 4 1.794 4 4M5 20c0-3.86 3.141-7 7-7s7 3.14 7 7a1 1 0 1 1-2 0c0-2.757-2.243-5-5-5s-5 2.243-5 5a1 1 0 1 1-2 0"/>',
		'facebook'  => '<path d="M9.1 23.7v-8H6.6V12h2.5v-1.6c0-4.1 1.8-6 5.9-6 .8 0 2.1.2 2.6.3V8c-.3 0-.8-.1-1.4-.1-2 0-2.8.8-2.8 2.8V12h3.9l-.7 3.7h-3.2v8.2A12 12 0 1 0 9.1 23.7z"/>',
		'instagram' => '<path fill-rule="evenodd" d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm5.5-3.75a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5z"/>',
		'linkedin'  => '<path d="M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.64-1.85 3.37-1.85 3.6 0 4.27 2.37 4.27 5.46zM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13zM7.12 20.45H3.56V9h3.56z"/>',
	);
	$size = (int) $size;
	if ( isset( $solid[ $name ] ) ) {
		return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">' . $solid[ $name ] . '</svg>';
	}
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/**
 * Social login buttons, when a social login plugin is installed (Nextend Social Login is supported
 * out of the box; other plugins can use the `wpjc_social_login_html` filter).
 *
 * @param string $redirect Where to go after signing in.
 */
function wpjc_social_login_html( $redirect = '' ) {
	$html = '';
	if ( shortcode_exists( 'nextend_social_login' ) ) {
		$html = do_shortcode( '[nextend_social_login' . ( $redirect ? ' redirect="' . esc_url( $redirect ) . '"' : '' ) . ']' );
	}
	$html = (string) apply_filters( 'wpjc_social_login_html', $html, $redirect );
	if ( '' === trim( $html ) ) {
		return '';
	}
	return '<div class="wpjc-social"><p class="wpjc-social__or"><span>' . esc_html__( 'or continue with', 'jobcore' ) . '</span></p>' . $html . '</div>';
}
