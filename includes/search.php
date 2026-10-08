<?php
/**
 * Live search: REST endpoint behind the search-as-you-type panel on the board.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_LIVE_JOBS = 6;

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'wpjc/v1',
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'wpjc_rest_search',
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
		register_rest_route(
			'wpjc/v1',
			'/count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'wpjc_rest_count',
				'permission_callback' => '__return_true',
			)
		);
	}
);

/**
 * Number of open jobs for the advanced search filters (wpjc_q, wpjc_cat, wpjc_type, wpjc_loc, wpjc_remote, wpjc_days).
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function wpjc_rest_count( WP_REST_Request $req ) {
	$filters = wpjc_search_filters( wp_slash( (array) $req->get_query_params() ) );
	$args    = wpjc_filters_query_args( $filters );
	$total   = (int) wpjc_query_jobs( array_merge( $args, array( 'per_page' => 1 ) ) )->found_posts;
	$res     = rest_ensure_response( array( 'count' => $total ) );
	if ( ! is_user_logged_in() ) {
		$res->header( 'Cache-Control', 'public, max-age=60' );
	}
	return $res;
}

/**
 * URL of the full results list for a search.
 *
 * @param string $q Keywords.
 */
function wpjc_search_url( $q ) {
	return add_query_arg( 'wpjc_q', rawurlencode( $q ), wpjc_home_url() );
}

/**
 * Plain text of a term name (names are stored with HTML entities, e.g. "&amp;").
 *
 * @param WP_Term $term Term.
 */
function wpjc_term_text( $term ) {
	return html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
}

/**
 * Matching jobs, employers and categories.
 * An empty or one-letter query returns the most used categories for the idle panel.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function wpjc_rest_search( WP_REST_Request $req ) {
	$q   = trim( (string) $req['q'] );
	$out = array(
		'q'          => $q,
		'total'      => 0,
		'all'        => '',
		'jobs'       => array(),
		'employers'  => array(),
		'categories' => array(),
	);

	$short = mb_strlen( $q ) < 2;
	$cats  = get_terms(
		array(
			'taxonomy'   => 'wpjc_job_category',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => $short ? 8 : 4,
			'name__like' => $short ? '' : $q,
		)
	);
	foreach ( is_wp_error( $cats ) ? array() : $cats as $term ) {
		$link                = get_term_link( $term );
		$out['categories'][] = array(
			'name'  => wpjc_term_text( $term ),
			'url'   => is_wp_error( $link ) ? '' : $link,
			'count' => (int) $term->count,
		);
	}
	if ( $short ) {
		return wpjc_search_response( $out );
	}

	$query = wpjc_query_jobs(
		array(
			'keywords' => $q,
			'per_page' => WPJC_LIVE_JOBS,
		)
	);
	$tiers = wpjc_tiers();
	foreach ( $query->posts as $job ) {
		$company       = wpjc_job_company( $job );
		$tier          = wpjc_job_tier( $job );
		$expires       = wpjc_meta( $job, 'expires' );
		$place         = wpjc_meta( $job, 'remote' ) ? __( 'Remote', 'jobcore' ) : wpjc_meta( $job, 'location' );
		$out['jobs'][] = array(
			'id'      => (int) $job->ID,
			't'       => html_entity_decode( get_the_title( $job ), ENT_QUOTES, 'UTF-8' ),
			'url'     => get_permalink( $job ),
			'logo'    => $company['logo'],
			'company' => html_entity_decode( $company['name'], ENT_QUOTES, 'UTF-8' ),
			'place'   => (string) $place,
			/* translators: %s: date */
			'until'   => $expires ? sprintf( __( 'until %s', 'jobcore' ), wp_date( 'd.m.Y', strtotime( $expires . ' 12:00:00' ) ) ) : '',
			'tier'    => 'basic' === $tier ? null : array(
				'short' => $tiers[ $tier ]['short'],
				'label' => $tiers[ $tier ]['label'],
				'color' => $tiers[ $tier ]['color'],
			),
		);
	}
	$out['total'] = (int) $query->found_posts;
	$out['all']   = wpjc_search_url( $q );

	$employers = get_terms(
		array(
			'taxonomy'   => 'wpjc_employer',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => 3,
			'name__like' => $q,
			'exclude'    => wpjc_inactive_employer_ids(),
		)
	);
	foreach ( is_wp_error( $employers ) ? array() : $employers as $term ) {
		$link               = get_term_link( $term );
		$out['employers'][] = array(
			'name'  => wpjc_term_text( $term ),
			'url'   => is_wp_error( $link ) ? '' : $link,
			'logo'  => (string) get_term_meta( $term->term_id, 'wpjc_logo', true ),
			'count' => (int) $term->count,
		);
	}

	return wpjc_search_response( $out );
}

/**
 * JSON response that browsers and proxies may keep for a minute.
 *
 * @param array $data Payload.
 */
function wpjc_search_response( array $data ) {
	$res = rest_ensure_response( apply_filters( 'wpjc_live_search_results', $data ) );
	if ( ! is_user_logged_in() ) {
		$res->header( 'Cache-Control', 'public, max-age=60' );
	}
	return $res;
}
