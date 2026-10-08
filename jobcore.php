<?php
/**
 * Plugin Name:       JobCore
 * Plugin URI:        https://xolius.com/product/jobcore/
 * Description:       A complete, good-looking job board: listings with search and filters, employer pages, applications with CV upload, job alerts and a front-end account for candidates and employers.
 * Version:           1.0.3
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Author:            Xolius by BlueAgency
 * Author URI:        https://xolius.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jobcore
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/*
 * Upgrading from "WP Job Core" (the same plugin under its old name): when it is still active,
 * stop here instead of loading twice, and switch it off when JobCore is activated.
 * Jobs, employers and settings are shared, so nothing is lost.
 */
if ( defined( 'WPJC_VERSION' ) ) {
	register_activation_hook(
		__FILE__,
		static function () {
			deactivate_plugins( 'wp-job-core/wp-job-core.php', true );
			delete_option( 'rewrite_rules' );
		}
	);
	add_action(
		'admin_notices',
		static function () {
			if ( current_user_can( 'activate_plugins' ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'JobCore replaces WP Job Core. Deactivate WP Job Core, then activate JobCore — your jobs, employers and settings stay.', 'jobcore' ) . '</p></div>';
			}
		}
	);
	return;
}

define( 'WPJC_VERSION', '1.0.3' );
define( 'WPJC_FILE', __FILE__ );
define( 'WPJC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPJC_URL', plugin_dir_url( __FILE__ ) );

require_once WPJC_DIR . 'includes/helpers.php';
require_once WPJC_DIR . 'includes/post-types.php';
require_once WPJC_DIR . 'includes/employers.php';
require_once WPJC_DIR . 'includes/meta.php';
require_once WPJC_DIR . 'includes/settings.php';
require_once WPJC_DIR . 'includes/query.php';
require_once WPJC_DIR . 'includes/search.php';
require_once WPJC_DIR . 'includes/schema.php';
require_once WPJC_DIR . 'includes/enqueue.php';
require_once WPJC_DIR . 'includes/demo-switcher.php';
require_once WPJC_DIR . 'includes/shortcodes.php';
require_once WPJC_DIR . 'includes/templates.php';
require_once WPJC_DIR . 'includes/apply.php';
require_once WPJC_DIR . 'includes/routes.php';
require_once WPJC_DIR . 'includes/applications.php';
require_once WPJC_DIR . 'includes/inbox.php';
require_once WPJC_DIR . 'includes/alerts.php';
require_once WPJC_DIR . 'includes/favorites.php';
require_once WPJC_DIR . 'includes/account.php';
require_once WPJC_DIR . 'includes/accounts.php';
require_once WPJC_DIR . 'includes/auth.php';
require_once WPJC_DIR . 'includes/scheme.php';
require_once WPJC_DIR . 'includes/feeds.php';
require_once WPJC_DIR . 'includes/indexing.php';
require_once WPJC_DIR . 'includes/admin-panel.php';
require_once WPJC_DIR . 'includes/admin-menu.php';
require_once WPJC_DIR . 'includes/admin.php';
require_once WPJC_DIR . 'includes/import-export.php';
require_once WPJC_DIR . 'includes/migrate-wpjm.php';
require_once WPJC_DIR . 'includes/licence.php';
require_once WPJC_DIR . 'includes/setup.php';
require_once WPJC_DIR . 'includes/dashboard-widget.php';

/**
 * Activation: CPT + Jobs page + rewrites.
 */
register_activation_hook(
	__FILE__,
	static function () {
		wpjc_register_post_types();
		if ( ! get_option( 'wpjc_jobs_page_id' ) ) {
			$id = wp_insert_post(
				array(
					'post_title'   => __( 'Jobs', 'jobcore' ),
					'post_name'    => 'jobs',
					'post_status'  => 'publish',
					'post_type'    => 'page',
					'post_content' => '[wpjc_jobs]',
				),
				true
			);
			if ( ! is_wp_error( $id ) && $id ) {
				update_option( 'wpjc_jobs_page_id', (int) $id );
			}
		}
		flush_rewrite_rules();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'wpjc_alerts_digest' );
		wp_clear_scheduled_hook( 'wpjc_app_files_cleanup' );
		wp_clear_scheduled_hook( 'wpjc_licence_check' );
		flush_rewrite_rules();
	}
);
