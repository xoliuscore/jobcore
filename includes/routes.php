<?php
/**
 * Clean URLs under the Jobs page: /jobs/all/, /jobs/employers/ and the jobs account
 * (/jobs/account/, /jobs/account/applications/, /jobs/account/jobs/new/, …).
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'query_vars',
	static function ( $vars ) {
		return array_merge( $vars, array( 'wpjc_view', 'wpjc_account', 'wpjc_item' ) );
	}
);

/**
 * Account sections: key => [menu label, icon].
 *
 * @return array<string, array{0:string,1:string}>
 */
function wpjc_account_sections() {
	static $cache = null;
	if ( null !== $cache && did_action( 'init' ) ) {
		return $cache;
	}
	$sections = array(
		'overview'     => array( __( 'Overview', 'jobcore' ), 'grid' ),
		'applications' => array( __( 'My applications', 'jobcore' ), 'send' ),
		'saved'        => array( __( 'Bookmarks', 'jobcore' ), 'bookmark' ),
		'alerts'       => array( __( 'Job alerts', 'jobcore' ), 'bell' ),
		'jobs'         => array( __( 'My job ads', 'jobcore' ), 'briefcase' ),
		'received'     => array( __( 'Applications received', 'jobcore' ), 'inbox' ),
		'companies'    => array( __( 'My companies', 'jobcore' ), 'building' ),
	);
	/**
	 * Account sections: add-ons add their own screens (key => [menu label, icon]).
	 * Keys are lowercase letters, digits and dashes.
	 *
	 * @param array $sections Sections.
	 */
	$sections = array_filter(
		(array) apply_filters( 'wpjc_account_sections', $sections ),
		static function ( $item, $key ) {
			return is_array( $item ) && preg_match( '/^[a-z0-9-]{1,30}$/', (string) $key );
		},
		ARRAY_FILTER_USE_BOTH
	);
	if ( did_action( 'init' ) ) {
		$cache = $sections;
	}
	return $sections;
}

/**
 * Account sections whose screens take an item ('new' or an ID): forms and detail views.
 *
 * @return string[]
 */
function wpjc_account_item_sections() {
	return array_values( array_unique( (array) apply_filters( 'wpjc_account_item_sections', array( 'alerts', 'jobs', 'companies' ) ) ) );
}

/**
 * Path of the Jobs page ("jobs", "careers/jobs"), '' when there is none.
 */
function wpjc_jobs_page_path() {
	$id = (int) get_option( 'wpjc_jobs_page_id' );
	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		return '';
	}
	return (string) get_page_uri( $id );
}

/**
 * Whether clean board URLs are available (pretty permalinks and a Jobs page).
 */
function wpjc_pretty_urls() {
	return '' !== (string) get_option( 'permalink_structure' ) && '' !== wpjc_jobs_page_path();
}

add_action(
	'init',
	static function () {
		$path = wpjc_jobs_page_path();
		if ( '' === $path ) {
			return;
		}
		$base = '^' . preg_quote( $path, '#' );
		$page = 'index.php?pagename=' . $path;
		add_rewrite_rule( $base . '/(all|employers)/?$', $page . '&wpjc_view=$matches[1]', 'top' );
		add_rewrite_rule( $base . '/account/?$', $page . '&wpjc_account=overview', 'top' );
		$keys  = array_diff( array_keys( wpjc_account_sections() ), array( 'overview' ) );
		$items = array_intersect( wpjc_account_item_sections(), $keys );
		$quote = static function ( $list ) {
			return implode( '|', array_map( static fn( $k ) => preg_quote( $k, '#' ), $list ) );
		};
		add_rewrite_rule( $base . '/account/(' . $quote( $keys ) . ')/?$', $page . '&wpjc_account=$matches[1]', 'top' );
		if ( $items ) {
			add_rewrite_rule( $base . '/account/(' . $quote( $items ) . ')/([a-z0-9]{1,20})/?$', $page . '&wpjc_account=$matches[1]&wpjc_item=$matches[2]', 'top' );
		}

		// The rules follow the Jobs page path and the sections add-ons register: refresh them once whenever these change.
		$sig = $path . '|5|' . md5( implode( ',', $keys ) . '/' . implode( ',', $items ) );
		if ( get_option( 'wpjc_routes_sig' ) !== $sig ) {
			add_action(
				'wp_loaded',
				static function () use ( $sig ) {
					flush_rewrite_rules( false );
					update_option( 'wpjc_routes_sig', $sig, false );
				}
			);
		}
	},
	20
);

/**
 * URL of a board view.
 *
 * @param string $view home|all|employers.
 */
function wpjc_view_url( $view = 'home' ) {
	if ( 'home' === $view || ! in_array( $view, array( 'all', 'employers' ), true ) ) {
		return wpjc_home_url();
	}
	if ( wpjc_pretty_urls() ) {
		return trailingslashit( wpjc_home_url() ) . $view . '/';
	}
	return add_query_arg( 'wpjc_view', $view, wpjc_home_url() );
}

/**
 * URL of a jobs account screen.
 *
 * @param string     $section overview|applications|saved|alerts|jobs|companies.
 * @param string|int $item    'new' or an ID (alerts, jobs, companies).
 */
function wpjc_account_url( $section = 'overview', $item = '' ) {
	$section = isset( wpjc_account_sections()[ $section ] ) ? $section : 'overview';
	$item    = wpjc_account_clean_item( $item );
	if ( ! in_array( $section, wpjc_account_item_sections(), true ) ) {
		$item = '';
	}
	if ( wpjc_pretty_urls() ) {
		$url = trailingslashit( wpjc_home_url() ) . 'account/';
		if ( 'overview' !== $section ) {
			$url .= $section . '/' . ( '' !== $item ? $item . '/' : '' );
		}
		return $url;
	}
	return add_query_arg(
		array_filter(
			array(
				'wpjc_account' => $section,
				'wpjc_item'    => $item,
			),
			'strlen'
		),
		wpjc_home_url()
	);
}

/**
 * Current account section ('' when this is not an account screen).
 */
function wpjc_account_section() {
	$id = (int) get_option( 'wpjc_jobs_page_id' );
	if ( ! $id || ! is_page( $id ) ) {
		return '';
	}
	$section = sanitize_key( (string) get_query_var( 'wpjc_account' ) );
	return isset( wpjc_account_sections()[ $section ] ) ? $section : '';
}

/**
 * Current account item: 'new', an ID as string, or ''.
 */
function wpjc_account_item() {
	return wpjc_account_clean_item( get_query_var( 'wpjc_item' ) );
}

/**
 * Account item: 'new', a job / company ID or an alert key (lowercase letters and digits).
 *
 * @param mixed $item Raw item.
 */
function wpjc_account_clean_item( $item ) {
	$item = strtolower( (string) $item );
	return preg_match( '/^[a-z0-9]{1,20}$/', $item ) ? $item : '';
}

/**
 * Whether this request is a jobs account screen.
 */
function wpjc_is_account() {
	return '' !== wpjc_account_section();
}

/**
 * Sign-in URL: the News. login page when available, else wp-login.php.
 *
 * @param string $redirect Where to go after signing in.
 */
function wpjc_login_url( $redirect = '' ) {
	if ( function_exists( 'wpjc_board_login' ) && wpjc_board_login() && '' !== wpjc_jobs_page_path() ) {
		return wpjc_auth_url( 'login', $redirect );
	}
	if ( function_exists( 'news_core_account_url' ) ) {
		$url = news_core_account_url( 'login', array( 'redirect_to' => $redirect ) );
		if ( $url ) {
			return $url;
		}
	}
	return wp_login_url( $redirect );
}

/**
 * Sign-up URL, '' when registration is closed.
 *
 * @param string $redirect Where to go after signing up.
 */
function wpjc_register_url( $redirect = '' ) {
	if ( function_exists( 'wpjc_board_login' ) && wpjc_board_login() && '' !== wpjc_jobs_page_path() ) {
		return wpjc_signup_open() ? wpjc_auth_url( 'signup', $redirect ) : '';
	}
	if ( function_exists( 'news_core_account_url' ) && function_exists( 'news_core_account_signup_open' ) && news_core_account_signup_open() ) {
		$url = news_core_account_url(
			'login',
			array(
				'mode'        => 'signup',
				'redirect_to' => $redirect,
			),
			'signup'
		);
		if ( $url ) {
			return $url;
		}
	}
	return get_option( 'users_can_register' ) ? wp_registration_url() : '';
}

/**
 * Profile and password settings: the News. account page when available, else the WordPress profile.
 */
function wpjc_profile_url() {
	if ( function_exists( 'news_core_account_url' ) ) {
		$url = news_core_account_url( 'account' );
		if ( $url ) {
			return $url;
		}
	}
	return get_edit_profile_url();
}

/*
 * Old ?wpjc_view= / ?wpjc_account= links go to the clean URLs; account screens are private
 * (no cache, no index).
 */
add_action(
	'template_redirect',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$id = (int) get_option( 'wpjc_jobs_page_id' );
		if ( $id && is_page( $id ) && wpjc_pretty_urls() && ( isset( $_GET['wpjc_view'] ) || isset( $_GET['wpjc_account'] ) ) ) {
			$args = array_map( 'sanitize_text_field', wp_unslash( (array) $_GET ) );
			if ( isset( $_GET['wpjc_account'] ) ) {
				$url = wpjc_account_url( sanitize_key( $args['wpjc_account'] ), $args['wpjc_item'] ?? '' );
			} else {
				$url = wpjc_view_url( sanitize_key( $args['wpjc_view'] ) );
			}
			unset( $args['wpjc_view'], $args['wpjc_account'], $args['wpjc_item'], $args['pagename'], $args['page_id'] );
			wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $args ), $url ), 301 );
			exit;
		}
		// phpcs:enable
		if ( wpjc_is_account() ) {
			nocache_headers();
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		}
	},
	5
);

/**
 * Title of a board view or account screen ('' for other requests).
 */
function wpjc_route_title() {
	$section = wpjc_account_section();
	if ( '' !== $section ) {
		return wpjc_account_title( $section, wpjc_account_item() );
	}
	$id = (int) get_option( 'wpjc_jobs_page_id' );
	if ( ! $id || ! is_page( $id ) ) {
		return '';
	}
	$titles = array(
		'employers' => __( 'Employers', 'jobcore' ),
		'all'       => __( 'All jobs', 'jobcore' ),
	);
	return $titles[ wpjc_view() ] ?? '';
}

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		$title = wpjc_route_title();
		if ( '' !== $title ) {
			$parts['title'] = $title;
		}
		return $parts;
	}
);

// Yoast SEO builds its own title and canonical URL.
add_filter(
	'wpseo_title',
	static function ( $title ) {
		$route = wpjc_route_title();
		return '' !== $route ? $route . ' - ' . get_bloginfo( 'name' ) : $title;
	}
);
add_filter(
	'wpseo_canonical',
	static function ( $url ) {
		if ( wpjc_is_account() ) {
			return false;
		}
		$view = wpjc_view();
		return '' !== wpjc_route_title() && 'home' !== $view ? wpjc_view_url( $view ) : $url;
	}
);

/**
 * Screen title for an account section.
 *
 * @param string $section Section.
 * @param string $item    'new', ID or ''.
 */
function wpjc_account_title( $section, $item = '' ) {
	if ( 'jobs' === $section && '' !== $item ) {
		return 'new' === $item ? __( 'Post a job', 'jobcore' ) : __( 'Edit job ad', 'jobcore' );
	}
	if ( 'companies' === $section && '' !== $item ) {
		return 'new' === $item ? __( 'Add a company', 'jobcore' ) : __( 'Edit company', 'jobcore' );
	}
	if ( 'alerts' === $section && '' !== $item ) {
		return 'new' === $item ? __( 'New job alert', 'jobcore' ) : __( 'Edit job alert', 'jobcore' );
	}
	$sections = wpjc_account_sections();
	$title    = 'overview' === $section ? __( 'My jobs account', 'jobcore' ) : ( $sections[ $section ][0] ?? '' );
	/**
	 * Screen title of an account section (add-ons name their forms and detail views).
	 *
	 * @param string $title   Title.
	 * @param string $section Section.
	 * @param string $item    'new', ID or ''.
	 */
	return (string) apply_filters( 'wpjc_account_title', $title, $section, $item );
}
