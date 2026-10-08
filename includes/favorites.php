<?php
/**
 * Saved / liked jobs: user meta for signed-in accounts, REST for the header hearts.
 * Guests keep a 7-day list in the browser (jobs.js); it is merged into the account on sign-in.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/** Max saved jobs per account. */
const WPJC_FAVS_MAX = 50;

/**
 * Saved job IDs for a user, newest first.
 *
 * @param int $user_id User ID (current user when 0).
 * @return int[]
 */
function wpjc_favs( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$ids = get_user_meta( $user_id, 'wpjc_favs', true );
	return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
}

/**
 * Persist saved job IDs.
 *
 * @param int   $user_id User ID.
 * @param int[] $ids     Job IDs, newest first.
 */
function wpjc_favs_set( $user_id, array $ids ) {
	$user_id = (int) $user_id;
	$ids     = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ), 0, WPJC_FAVS_MAX );
	if ( $ids ) {
		update_user_meta( $user_id, 'wpjc_favs', $ids );
	} else {
		delete_user_meta( $user_id, 'wpjc_favs' );
	}
}

/**
 * Whether this ID is a job ad (any status — closed ads stay in the list).
 *
 * @param int $job_id Job ID.
 */
function wpjc_fav_is_job( $job_id ) {
	$post = get_post( (int) $job_id );
	return $post && 'wpjc_job' === $post->post_type;
}

/**
 * Toggle one job. Returns whether it is now saved.
 *
 * @param int $job_id  Job ID.
 * @param int $user_id User ID (current user when 0).
 */
function wpjc_fav_toggle( $job_id, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$job_id  = absint( $job_id );
	if ( ! $user_id || ! $job_id || ! wpjc_fav_is_job( $job_id ) ) {
		return false;
	}
	$ids = wpjc_favs( $user_id );
	$on  = ! in_array( $job_id, $ids, true );
	$ids = array_values( array_diff( $ids, array( $job_id ) ) );
	if ( $on ) {
		array_unshift( $ids, $job_id );
	}
	wpjc_favs_set( $user_id, $ids );
	return $on;
}

/**
 * Add guest-saved IDs in front of the account list.
 *
 * @param int[] $job_ids Guest IDs (any order).
 * @param int   $user_id User ID (current user when 0).
 * @return int[]
 */
function wpjc_favs_merge( array $job_ids, $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$new = array();
	foreach ( array_slice( $job_ids, 0, WPJC_FAVS_MAX ) as $id ) {
		$id = absint( $id );
		if ( $id && wpjc_fav_is_job( $id ) ) {
			$new[] = $id;
		}
	}
	wpjc_favs_set( $user_id, array_merge( $new, wpjc_favs( $user_id ) ) );
	return wpjc_favs( $user_id );
}

/**
 * Saved jobs as posts (newest first). Missing IDs are dropped.
 *
 * @param int $user_id User ID (current user when 0).
 * @return WP_Post[]
 */
function wpjc_fav_jobs( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$posts   = array();
	$keep    = array();
	foreach ( wpjc_favs( $user_id ) as $id ) {
		$post = get_post( $id );
		if ( $post && 'wpjc_job' === $post->post_type ) {
			$posts[] = $post;
			$keep[]  = $id;
		}
	}
	if ( $keep !== wpjc_favs( $user_id ) ) {
		wpjc_favs_set( $user_id, $keep );
	}
	return $posts;
}

/**
 * Front payload for jobs.js (id, title, company, url, logo).
 *
 * @param int $user_id User ID (current user when 0).
 * @return array<int, array<string, mixed>>
 */
function wpjc_favs_payload( $user_id = 0 ) {
	$items = array();
	foreach ( wpjc_fav_jobs( $user_id ) as $post ) {
		$company = wpjc_job_company( $post );
		$items[] = array(
			'id'      => (int) $post->ID,
			'title'   => get_the_title( $post ),
			'company' => (string) $company['name'],
			'url'     => get_permalink( $post ),
			'logo'    => (string) $company['logo'],
		);
	}
	return $items;
}

add_action(
	'rest_api_init',
	static function () {
		$perm = static function () {
			return is_user_logged_in();
		};
		register_rest_route(
			'wpjc/v1',
			'/favs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => 'wpjc_rest_favs_get',
					'permission_callback' => $perm,
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => 'wpjc_rest_favs_post',
					'permission_callback' => $perm,
					'args'                => array(
						'id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'merge' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'integer',
							),
						),
					),
				),
			)
		);
	}
);

/**
 * Current user's saved jobs.
 *
 * @return WP_REST_Response
 */
function wpjc_rest_favs_get() {
	return rest_ensure_response( array( 'items' => wpjc_favs_payload() ) );
}

/**
 * Toggle one job or merge guest IDs.
 *
 * @param WP_REST_Request $req Request.
 * @return WP_REST_Response
 */
function wpjc_rest_favs_post( WP_REST_Request $req ) {
	$merge = $req->get_param( 'merge' );
	if ( is_array( $merge ) ) {
		wpjc_favs_merge( $merge );
	} else {
		$id = (int) $req->get_param( 'id' );
		if ( $id ) {
			wpjc_fav_toggle( $id );
		}
	}
	return rest_ensure_response( array( 'items' => wpjc_favs_payload() ) );
}
