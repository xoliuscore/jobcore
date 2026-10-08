<?php
/**
 * Shortcodes.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * [wpjc_jobs] — jobs board.
 *
 * Views: home (employers row + one column per tier), all (every open job, paged),
 * employers (all employers). A keyword search or a term filter shows results.
 *
 * @param array|string $atts Atts: per_page, employer, category, type (term IDs), title.
 * @return string
 */
function wpjc_shortcode_jobs( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'per_page' => (int) wpjc_opt( 'per_page' ),
			'employer' => 0,
			'category' => 0,
			'type'     => 0,
			'title'    => '',
		),
		$atts,
		'wpjc_jobs'
	);

	$paged    = isset( $_GET['wpjc_page'] ) ? max( 1, absint( $_GET['wpjc_page'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public, read-only.
	$search   = wpjc_search_filters();
	$keywords = $search['keywords'];

	// The page's own term (category / type archive) applies unless the visitor picked another one.
	$applied             = $search;
	$applied['category'] = $search['category'] ? $search['category'] : absint( $atts['category'] );
	$applied['type']     = $search['type'] ? $search['type'] : absint( $atts['type'] );
	$applied['cats']     = $search['cats'] ? $search['cats'] : array_filter( array( absint( $atts['category'] ) ) );
	$applied['types']    = $search['types'] ? $search['types'] : array_filter( array( absint( $atts['type'] ) ) );

	$employer = absint( $atts['employer'] );
	$per_page = max( 1, (int) $atts['per_page'] );
	$filtered = $employer || wpjc_filters_active( $applied );
	$view     = $filtered ? 'results' : wpjc_view();

	$data = array(
		'view'      => $view,
		'keywords'  => $keywords,
		'paged'     => $paged,
		'title'     => '' !== $atts['title'] ? (string) $atts['title'] : (string) wpjc_opt( 'list_title' ),
		'intro'     => (string) wpjc_opt( 'list_intro' ),
		'heading'   => '',
		'employers' => array(),
		'tiers'     => array(),
		'query'     => null,
		'filters'   => wpjc_filters_params( $search ),
		'search'    => $search,
		'applied'   => $applied,
	);

	switch ( $view ) {
		case 'results':
		case 'all':
			$data['query'] = wpjc_query_jobs(
				array_merge(
					wpjc_filters_query_args( $applied ),
					array(
						'employer' => $employer,
						'paged'    => $paged,
						'per_page' => $per_page,
					)
				)
			);
			if ( 'all' === wpjc_view() && ! $employer ) {
				// Filtered "All jobs" stays on its own address.
				$data['base_url'] = wpjc_view_url( 'all' );
			}
			if ( 'all' === $view ) {
				$data['heading']  = __( 'All jobs', 'jobcore' );
				$data['base_url'] = wpjc_view_url( 'all' );
			} elseif ( '' !== $keywords ) {
				/* translators: %s: search keywords */
				$data['heading'] = sprintf( __( 'Jobs for “%s”', 'jobcore' ), $keywords );
			} elseif ( '' !== $atts['title'] && ! wpjc_filters_advanced_count( $search ) ) {
				$data['heading'] = (string) $atts['title'];
			} else {
				$data['heading'] = wpjc_filters_advanced_count( $search ) ? __( 'Search results', 'jobcore' ) : __( 'Jobs', 'jobcore' );
			}
			break;

		case 'employers':
			$data['employers'] = wpjc_featured_employers( 200 );
			$data['heading']   = __( 'Employers', 'jobcore' );
			break;

		default:
			$employers_n = (int) wpjc_opt( 'employers_n' );
			if ( $employers_n > 0 ) {
				$data['employers'] = wpjc_featured_employers( $employers_n );
			}
			$per_column = max( 1, (int) wpjc_opt( 'per_column' ) );
			foreach ( array_keys( wpjc_tiers() ) as $tier ) {
				$data['tiers'][ $tier ] = wpjc_query_jobs(
					array(
						'tier'     => $tier,
						'per_page' => $per_column,
					)
				);
			}
	}

	ob_start();
	wpjc_template( 'board', $data );
	return (string) ob_get_clean();
}
add_shortcode( 'wpjc_jobs', 'wpjc_shortcode_jobs' );
