<?php
/**
 * Job queries and search.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query jobs for the board.
 *
 * @param array $args {
 *     @type string $keywords Location/keyword search.
 *     @type string $location Location string.
 *     @type bool   $remote   Remote only.
 *     @type int       $paged    Page.
 *     @type int       $per_page Per page.
 *     @type string    $tier     Tier key (premium_plus|premium|basic); '' = all.
 *     @type int       $employer Employer term ID.
 *     @type int       $category Job category term ID.
 *     @type int       $type     Job type term ID.
 *     @type int[]     $exclude  Job IDs to leave out.
 *     @type int       $after    Only jobs published after this Unix time (0 = any).
 * }
 * @return WP_Query
 */
function wpjc_query_jobs( array $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'keywords' => '',
			'location' => '',
			'remote'   => false,
			'paged'    => 1,
			'per_page' => 12,
			'tier'     => '',
			'employer' => 0,
			'category' => 0,
			'type'     => 0,
			'places'   => array(),
			'sort'     => '',
			'exclude'  => array(),
			'after'    => 0,
		)
	);

	$query = array(
		'post_type'      => 'wpjc_job',
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, (int) $args['per_page'] ),
		'paged'          => max( 1, (int) $args['paged'] ),
		'orderby'        => 'new' === $args['sort']
			? array( 'date' => 'DESC' )
			: array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
	);
	$exclude = array_filter( array_map( 'absint', (array) $args['exclude'] ) );
	if ( $exclude ) {
		$query['post__not_in'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
	}
	if ( (int) $args['after'] > 0 ) {
		$query['date_query'] = array(
			array(
				'column' => 'post_date_gmt',
				'after'  => gmdate( 'Y-m-d H:i:s', (int) $args['after'] ),
			),
		);
	}

	$meta = array(
		'relation' => 'AND',
		array(
			'relation' => 'OR',
			array(
				'key'     => '_wpjc_filled',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'   => '_wpjc_filled',
				'value' => '0',
			),
			array(
				'key'   => '_wpjc_filled',
				'value' => '',
			),
		),
	);

	$location = trim( (string) $args['location'] );
	if ( '' !== $location ) {
		$meta[] = array(
			'key'     => '_wpjc_location',
			'value'   => $location,
			'compare' => 'LIKE',
		);
	}

	$places = array_values( array_filter( array_map( 'strval', (array) $args['places'] ), 'strlen' ) );
	if ( $places ) {
		$meta[] = array(
			'key'     => '_wpjc_location',
			'value'   => $places,
			'compare' => 'IN',
		);
	}

	if ( ! empty( $args['remote'] ) ) {
		$meta[] = array(
			'key'   => '_wpjc_remote',
			'value' => '1',
		);
	}

	// Hide expired when expires is set and in the past.
	$today  = wp_date( 'Y-m-d' );
	$meta[] = array(
		'relation' => 'OR',
		array(
			'key'     => '_wpjc_expires',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_wpjc_expires',
			'value'   => '',
			'compare' => '=',
		),
		array(
			'key'     => '_wpjc_expires',
			'value'   => $today,
			'compare' => '>=',
			'type'    => 'DATE',
		),
	);

	$tier = (string) $args['tier'];
	if ( 'basic' === $tier ) {
		$meta[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_wpjc_tier',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_wpjc_tier',
				'value'   => array_diff( array_keys( wpjc_tiers() ), array( 'basic' ) ),
				'compare' => 'NOT IN',
			),
		);
	} elseif ( '' !== $tier ) {
		$meta[] = array(
			'key'   => '_wpjc_tier',
			'value' => $tier,
		);
	}

	$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery

	$tax      = array();
	$inactive = wpjc_inactive_employer_ids();
	if ( $inactive ) {
		$tax[] = array(
			'taxonomy' => 'wpjc_employer',
			'terms'    => $inactive,
			'operator' => 'NOT IN',
		);
	}
	if ( (int) $args['employer'] > 0 ) {
		$tax[] = array(
			'taxonomy' => 'wpjc_employer',
			'terms'    => (int) $args['employer'],
		);
	}
	foreach ( array( 'category' => 'wpjc_job_category', 'type' => 'wpjc_job_type' ) as $key => $taxonomy ) {
		// One term ID or a list (any of them).
		$ids = array_values( array_filter( array_map( 'absint', (array) $args[ $key ] ) ) );
		if ( $ids ) {
			$tax[] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $ids,
			);
		}
	}
	if ( $tax ) {
		$query['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery
	}

	$keywords = trim( (string) $args['keywords'] );
	if ( '' !== $keywords ) {
		$query['s'] = $keywords;
	}

	return new WP_Query( $query );
}

/**
 * "Posted within" choices of the advanced search: days => label.
 *
 * @return array<int, string>
 */
function wpjc_posted_options() {
	return array(
		1  => __( 'Last 24 hours', 'jobcore' ),
		3  => __( 'Last 3 days', 'jobcore' ),
		7  => __( 'Last 7 days', 'jobcore' ),
		30 => __( 'Last 30 days', 'jobcore' ),
	);
}

/**
 * Board search filters from a request (slashed, like $_GET).
 *
 * @param array|null $src Request values; null = $_GET.
 * @return array{keywords:string,category:int,type:int,location:string,remote:bool,days:int,cats:int[],types:int[],places:string[],sort:string}
 */
function wpjc_search_filters( $src = null ) {
	$src  = null === $src ? $_GET : (array) $src; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only search filters.
	$text = static function ( $key ) use ( $src ) {
		return isset( $src[ $key ] ) && is_scalar( $src[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $src[ $key ] ) ) ) : '';
	};
	// "3", "3,5" or wpjc_cat[]=3&wpjc_cat[]=5.
	$ids  = static function ( $key ) use ( $src ) {
		$raw = $src[ $key ] ?? '';
		$raw = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		return array_slice( array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) ), 0, 20 );
	};
	$days   = absint( $text( 'wpjc_days' ) );
	$cats   = $ids( 'wpjc_cat' );
	$types  = $ids( 'wpjc_type' );
	$places = array();
	foreach ( isset( $src['wpjc_place'] ) ? (array) $src['wpjc_place'] : array() as $place ) {
		$place = is_scalar( $place ) ? mb_substr( trim( sanitize_text_field( wp_unslash( (string) $place ) ) ), 0, 80 ) : '';
		if ( '' !== $place && count( $places ) < 20 ) {
			$places[] = $place;
		}
	}
	return array(
		'keywords' => $text( 'wpjc_q' ),
		'category' => $cats ? $cats[0] : 0,
		'type'     => $types ? $types[0] : 0,
		'location' => mb_substr( $text( 'wpjc_loc' ), 0, 80 ),
		'remote'   => '' !== $text( 'wpjc_remote' ),
		'days'     => isset( wpjc_posted_options()[ $days ] ) ? $days : 0,
		'cats'     => $cats,
		'types'    => $types,
		'places'   => array_values( array_unique( $places ) ),
		'sort'     => 'new' === $text( 'wpjc_sort' ) ? 'new' : '',
	);
}

/**
 * Whether any search filter is set.
 *
 * @param array $filters From wpjc_search_filters().
 */
function wpjc_filters_active( array $filters ) {
	unset( $filters['sort'] );
	return (bool) array_filter( $filters );
}

/**
 * Number of advanced filters set (everything except the keywords).
 *
 * @param array $filters From wpjc_search_filters().
 */
function wpjc_filters_advanced_count( array $filters ) {
	$count = count( (array) ( $filters['cats'] ?? array() ) ) + count( (array) ( $filters['types'] ?? array() ) ) + count( (array) ( $filters['places'] ?? array() ) );
	if ( ! isset( $filters['cats'] ) ) {
		$count += ( empty( $filters['category'] ) ? 0 : 1 ) + ( empty( $filters['type'] ) ? 0 : 1 );
	}
	unset( $filters['keywords'], $filters['category'], $filters['type'], $filters['cats'], $filters['types'], $filters['places'], $filters['sort'] );
	return $count + count( array_filter( $filters ) );
}

/**
 * wpjc_query_jobs() arguments for search filters.
 *
 * @param array $filters From wpjc_search_filters().
 */
function wpjc_filters_query_args( array $filters ) {
	return array(
		'keywords' => $filters['keywords'],
		'category' => ! empty( $filters['cats'] ) ? $filters['cats'] : $filters['category'],
		'type'     => ! empty( $filters['types'] ) ? $filters['types'] : $filters['type'],
		'location' => $filters['location'],
		'remote'   => $filters['remote'],
		'places'   => (array) ( $filters['places'] ?? array() ),
		'sort'     => (string) ( $filters['sort'] ?? '' ),
		'after'    => $filters['days'] ? time() - $filters['days'] * DAY_IN_SECONDS : 0,
	);
}

/**
 * URL parameters for search filters (empty ones left out).
 *
 * @param array $filters From wpjc_search_filters().
 * @return array<string, string|int>
 */
function wpjc_filters_params( array $filters ) {
	return array_filter(
		array(
			'wpjc_q'      => $filters['keywords'],
			'wpjc_cat'    => ! empty( $filters['cats'] ) ? implode( ',', $filters['cats'] ) : $filters['category'],
			'wpjc_type'   => ! empty( $filters['types'] ) ? implode( ',', $filters['types'] ) : $filters['type'],
			'wpjc_loc'    => $filters['location'],
			'wpjc_remote' => $filters['remote'] ? 1 : 0,
			'wpjc_days'   => $filters['days'],
			'wpjc_place'  => (array) ( $filters['places'] ?? array() ),
			'wpjc_sort'   => (string) ( $filters['sort'] ?? '' ),
		)
	);
}

/**
 * What the open jobs offer to filter on: job counts per category, type and location.
 * Cached for 10 minutes and cleared when a job changes.
 *
 * @return array{total:int,remote:int,categories:array<int,int>,types:array<int,int>,locations:array<string,int>}
 */
function wpjc_search_facets() {
	$facets = get_transient( 'wpjc_facets' );
	if ( is_array( $facets ) ) {
		return $facets;
	}
	$facets = array(
		'total'      => 0,
		'remote'     => 0,
		'categories' => array(),
		'types'      => array(),
		'locations'  => array(),
	);
	$jobs   = wpjc_query_jobs( array( 'per_page' => 500 ) );
	foreach ( $jobs->posts as $job ) {
		++$facets['total'];
		$facets['remote'] += wpjc_meta( $job, 'remote' ) ? 1 : 0;
		foreach ( array( 'wpjc_job_category' => 'categories', 'wpjc_job_type' => 'types' ) as $taxonomy => $key ) {
			$terms = get_the_terms( $job, $taxonomy );
			foreach ( is_array( $terms ) ? $terms : array() as $term ) {
				$facets[ $key ][ $term->term_id ] = ( $facets[ $key ][ $term->term_id ] ?? 0 ) + 1;
			}
		}
		$place = trim( (string) wpjc_meta( $job, 'location' ) );
		if ( '' !== $place ) {
			$facets['locations'][ $place ] = ( $facets['locations'][ $place ] ?? 0 ) + 1;
		}
	}
	uksort(
		$facets['locations'],
		static function ( $a, $b ) use ( $facets ) {
			$by_count = $facets['locations'][ $b ] <=> $facets['locations'][ $a ];
			return 0 !== $by_count ? $by_count : strnatcasecmp( $a, $b );
		}
	);
	set_transient( 'wpjc_facets', $facets, 10 * MINUTE_IN_SECONDS );
	return $facets;
}

/** Forget the cached search facets. */
function wpjc_flush_facets() {
	delete_transient( 'wpjc_facets' );
}
add_action( 'save_post_wpjc_job', 'wpjc_flush_facets' );
add_action( 'deleted_post', 'wpjc_flush_facets' );
add_action( 'edited_wpjc_job_category', 'wpjc_flush_facets' );
add_action( 'edited_wpjc_job_type', 'wpjc_flush_facets' );
add_action( 'edited_wpjc_employer', 'wpjc_flush_facets' );

/**
 * Other open jobs from the same employer, and similar jobs (same category, else same type).
 *
 * @param int|WP_Post $post  Job.
 * @param int         $limit Per list.
 * @return array{employer:WP_Post[],similar:WP_Post[]}
 */
function wpjc_related_jobs( $post, $limit = 5 ) {
	$post = get_post( $post );
	$out  = array(
		'employer' => array(),
		'similar'  => array(),
	);
	if ( ! $post ) {
		return $out;
	}
	$seen = array( $post->ID );

	$employers = wp_get_post_terms( $post->ID, 'wpjc_employer', array( 'fields' => 'ids' ) );
	if ( $employers && ! is_wp_error( $employers ) ) {
		$out['employer'] = wpjc_query_jobs(
			array(
				'employer' => (int) $employers[0],
				'exclude'  => $seen,
				'per_page' => $limit,
			)
		)->posts;
		$seen            = array_merge( $seen, wp_list_pluck( $out['employer'], 'ID' ) );
	}

	foreach ( array( 'wpjc_job_category' => 'category', 'wpjc_job_type' => 'type' ) as $taxonomy => $arg ) {
		$terms = wp_get_post_terms( $post->ID, $taxonomy, array( 'fields' => 'ids' ) );
		if ( ! $terms || is_wp_error( $terms ) ) {
			continue;
		}
		$out['similar'] = wpjc_query_jobs(
			array(
				$arg       => (int) $terms[0],
				'exclude'  => $seen,
				'per_page' => $limit,
			)
		)->posts;
		if ( $out['similar'] ) {
			break;
		}
	}
	return $out;
}

/**
 * Whether candidates can still apply.
 *
 * @param int|WP_Post|null $post Job.
 */
function wpjc_can_apply( $post = null ) {
	$post = get_post( $post );
	if ( ! $post || 'wpjc_job' !== $post->post_type ) {
		return false;
	}
	if ( wpjc_meta( $post, 'filled' ) ) {
		return false;
	}
	$expires = wpjc_meta( $post, 'expires' );
	if ( $expires && $expires < wp_date( 'Y-m-d' ) ) {
		return false;
	}
	$email = wpjc_meta( $post, 'apply_email' );
	$url   = wpjc_meta( $post, 'apply_url' );
	return ( '' !== $email || '' !== $url );
}
