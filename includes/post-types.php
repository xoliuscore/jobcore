<?php
/**
 * Job post type and taxonomies.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', 'wpjc_register_post_types', 5 );

/**
 * Bump when post type / taxonomy rewrites change so existing installs refresh permalinks once.
 */
const WPJC_REWRITE_VER = '6';

add_action(
	'init',
	static function () {
		if ( WPJC_REWRITE_VER === get_option( 'wpjc_rewrite_ver' ) ) {
			return;
		}
		// 0.1 "featured" checkbox became the Premium tier.
		$featured = get_posts(
			array(
				'post_type'      => 'wpjc_job',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_wpjc_featured', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $featured as $id ) {
			if ( '' === (string) get_post_meta( $id, '_wpjc_tier', true ) ) {
				update_post_meta( $id, '_wpjc_tier', 'premium' );
			}
			delete_post_meta( $id, '_wpjc_featured' );
		}
		// 0.1 sample jobs used other city names; the old pink default accent now follows the theme.
		$cities  = array(
			'Sarajevo'   => 'Berlin',
			'Banja Luka' => 'Vienna',
			'Mostar'     => 'Munich',
			'Tuzla'      => 'Hamburg',
			'Zenica'     => 'Zurich',
		);
		$samples = get_posts(
			array(
				'post_type'      => 'wpjc_job',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_wpjc_sample', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $samples as $id ) {
			$city = (string) get_post_meta( $id, '_wpjc_location', true );
			if ( isset( $cities[ $city ] ) ) {
				update_post_meta( $id, '_wpjc_location', $cities[ $city ] );
			}
		}
		$settings = get_option( 'wpjc_settings' );
		if ( is_array( $settings ) && isset( $settings['accent'] ) && '#e6007e' === strtolower( (string) $settings['accent'] ) ) {
			$settings['accent'] = '';
			update_option( 'wpjc_settings', $settings );
		}
		flush_rewrite_rules( false );
		update_option( 'wpjc_rewrite_ver', WPJC_REWRITE_VER, false );
	},
	99
);

/**
 * Register CPT + taxonomies.
 */
function wpjc_register_post_types() {
	$labels = array(
		'name'                  => __( 'Jobs', 'jobcore' ),
		'singular_name'         => __( 'Job', 'jobcore' ),
		'add_new'               => __( 'Add job', 'jobcore' ),
		'add_new_item'          => __( 'Add new job', 'jobcore' ),
		'edit_item'             => __( 'Edit job', 'jobcore' ),
		'new_item'              => __( 'New job', 'jobcore' ),
		'view_item'             => __( 'View job', 'jobcore' ),
		'search_items'          => __( 'Search jobs', 'jobcore' ),
		'not_found'             => __( 'No jobs found', 'jobcore' ),
		'not_found_in_trash'    => __( 'No jobs found in Trash', 'jobcore' ),
		'menu_name'             => __( 'Jobs', 'jobcore' ),
		'all_items'             => __( 'All jobs', 'jobcore' ),
		'featured_image'        => __( 'Job photo or poster', 'jobcore' ),
		'set_featured_image'    => __( 'Set job photo', 'jobcore' ),
		'remove_featured_image' => __( 'Remove job photo', 'jobcore' ),
		'use_featured_image'    => __( 'Use as job photo', 'jobcore' ),
	);

	register_post_type(
		'wpjc_job',
		array(
			'labels'              => $labels,
			'public'              => true,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-businessman',
			'menu_position'       => 26,
			'has_archive'         => 'jobs-archive',
			'rewrite'             => array( 'slug' => 'job', 'with_front' => false ),
			'supports'            => array( 'title', 'editor', 'thumbnail', 'excerpt', 'revisions' ),
			'exclude_from_search' => false,
			'capability_type'     => 'post',
		)
	);

	register_taxonomy(
		'wpjc_job_type',
		'wpjc_job',
		array(
			'labels'            => array(
				'name'          => __( 'Job types', 'jobcore' ),
				'singular_name' => __( 'Job type', 'jobcore' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => false,
			'rewrite'           => array( 'slug' => 'job-type' ),
		)
	);

	register_taxonomy(
		'wpjc_employer',
		'wpjc_job',
		array(
			'labels'            => array(
				'name'          => __( 'Employers', 'jobcore' ),
				'singular_name' => __( 'Employer', 'jobcore' ),
				'add_new_item'  => __( 'Add new employer', 'jobcore' ),
				'edit_item'     => __( 'Edit employer', 'jobcore' ),
				'search_items'  => __( 'Search employers', 'jobcore' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => false,
			'rewrite'           => array( 'slug' => 'employer' ),
		)
	);

	register_taxonomy(
		'wpjc_job_category',
		'wpjc_job',
		array(
			'labels'            => array(
				'name'          => __( 'Job categories', 'jobcore' ),
				'singular_name' => __( 'Job category', 'jobcore' ),
			),
			'public'            => true,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'hierarchical'      => true,
			'rewrite'           => array( 'slug' => 'job-category' ),
		)
	);
}
