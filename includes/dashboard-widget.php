<?php
/**
 * "Jobs board" widget on the WordPress Dashboard: status, counts, latest jobs and quick links.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_dashboard_setup',
	static function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		wp_add_dashboard_widget( 'wpjc_dashboard', __( 'Jobs board', 'jobcore' ), 'wpjc_dashboard_widget' );
	}
);

/**
 * Widget body.
 */
function wpjc_dashboard_widget() {
	$jobs  = wpjc_admin_latest_jobs( 4 );
	$look  = 'standalone' === wpjc_opt( 'layout' ) ? __( 'Standalone portal', 'jobcore' ) : __( 'Inside the theme', 'jobcore' );
	$stats = array_slice( wpjc_admin_stats(), 0, 4 );
	$links = array(
		array( 'dashboard', __( 'Jobs dashboard', 'jobcore' ), wpjc_dashboard_url(), 'edit_posts' ),
		array( 'plus-alt2', __( 'Add job', 'jobcore' ), admin_url( 'post-new.php?post_type=wpjc_job' ), 'edit_posts' ),
		array( 'building', __( 'Employers', 'jobcore' ), admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ), 'manage_categories' ),
		array( 'admin-generic', __( 'Settings', 'jobcore' ), wpjc_settings_url(), 'manage_options' ),
	);
	?>
	<div class="wpjc-wdg" style="--wpjc-admin-a:<?php echo esc_attr( wpjc_accent() ); ?>">
		<div class="wpjc-wdg__head">
			<span class="wpjc-opt__mark dashicons dashicons-businessman" aria-hidden="true"></span>
			<div class="wpjc-wdg__name">
				<strong><?php echo esc_html( wpjc_brand()['name'] ); ?></strong>
				<span><?php /* translators: 1: version, 2: board look */ printf( esc_html__( 'JobCore %1$s · %2$s', 'jobcore' ), esc_html( WPJC_VERSION ), esc_html( $look ) ); ?></span>
			</div>
			<a class="wpjc-wdg__view" href="<?php echo esc_url( wpjc_home_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View board', 'jobcore' ); ?> ↗</a>
		</div>

		<ul class="wpjc-wdg__stats">
			<?php foreach ( $stats as $st ) : ?>
				<li><a href="<?php echo esc_url( $st[2] ); ?>"><b><?php echo esc_html( number_format_i18n( $st[0] ) ); ?></b><span><?php echo esc_html( $st[1] ); ?></span></a></li>
			<?php endforeach; ?>
		</ul>

		<?php if ( $jobs ) : ?>
			<ul class="wpjc-adm-jobs wpjc-wdg__jobs">
				<?php
				foreach ( $jobs as $job ) {
					wpjc_admin_job_row( $job );
				}
				?>
			</ul>
		<?php endif; ?>

		<ul class="wpjc-wdg__links">
			<?php foreach ( $links as $l ) : ?>
				<?php if ( current_user_can( $l[3] ) ) : ?>
					<li><a href="<?php echo esc_url( $l[2] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $l[0] ); ?>" aria-hidden="true"></span><?php echo esc_html( $l[1] ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php
}
