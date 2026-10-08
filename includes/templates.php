<?php
/**
 * Template routing for single/archive jobs and (standalone look) the Jobs page.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'template_include',
	static function ( $template ) {
		if ( is_singular( 'wpjc_job' ) ) {
			$custom = locate_template( array( 'wp-job-core/single-job.php', 'single-wpjc_job.php' ) );
			return $custom ? $custom : WPJC_DIR . 'templates/single-job.php';
		}
		if ( is_tax( 'wpjc_employer' ) && ! is_404() ) {
			$custom = locate_template( array( 'wp-job-core/employer-page.php' ) );
			return $custom ? $custom : WPJC_DIR . 'templates/employer-page.php';
		}
		if ( is_post_type_archive( 'wpjc_job' ) || is_tax( array( 'wpjc_job_type', 'wpjc_job_category' ) ) ) {
			$custom = locate_template( array( 'wp-job-core/archive-job.php', 'archive-wpjc_job.php' ) );
			return $custom ? $custom : WPJC_DIR . 'templates/archive-job.php';
		}
		if ( wpjc_is_account() ) {
			$custom = locate_template( array( 'wp-job-core/page-account.php' ) );
			return $custom ? $custom : WPJC_DIR . 'templates/page-account.php';
		}
		// The Jobs page uses the full width — also inside the theme, where the theme's page
		// template would add its sidebar next to the board.
		$jobs_page = is_page() && (int) get_option( 'wpjc_jobs_page_id' ) === (int) get_queried_object_id();
		if ( ( wpjc_is_standalone() && is_page() ) || ( $jobs_page && apply_filters( 'wpjc_board_full_width', true ) ) ) {
			$custom = locate_template( array( 'wp-job-core/page-board.php' ) );
			return $custom ? $custom : WPJC_DIR . 'templates/page-board.php';
		}
		return $template;
	},
	20
);
