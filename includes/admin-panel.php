<?php
/**
 * Admin shell (header bar + sidebar), the Jobs dashboard and the stats shared with the
 * WordPress dashboard widget.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_DASHBOARD = 'wpjc-dashboard';
const WPJC_SETTINGS  = 'wp-job-core';

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=wpjc_job',
			__( 'Jobs dashboard', 'jobcore' ),
			__( 'Dashboard', 'jobcore' ),
			'edit_posts',
			WPJC_DASHBOARD,
			'wpjc_render_dashboard',
			0
		);
	}
);

/**
 * Admin URL of a settings tab.
 *
 * @param string $tab Tab slug.
 */
function wpjc_settings_url( $tab = 'look' ) {
	return admin_url( 'edit.php?post_type=wpjc_job&page=' . WPJC_SETTINGS . '&tab=' . rawurlencode( $tab ) );
}

/**
 * Admin URL of the Jobs dashboard.
 */
function wpjc_dashboard_url() {
	return admin_url( 'edit.php?post_type=wpjc_job&page=' . WPJC_DASHBOARD );
}

/**
 * Settings tabs: slug => [ label, dashicon, description ].
 *
 * @return array<string, array{0:string,1:string,2:string}>
 */
function wpjc_settings_tabs() {
	return array(
		'look'    => array( __( 'Look & colours', 'jobcore' ), 'art', __( 'Inside the theme or a standalone portal, header, logo and accent colour.', 'jobcore' ) ),
		'board'   => array( __( 'Board', 'jobcore' ), 'layout', __( 'Hero text, search placeholder and how many jobs and employers are shown.', 'jobcore' ) ),
		'contact' => array( __( 'Contact & links', 'jobcore' ), 'phone', __( 'Contact details under the hero and social profiles.', 'jobcore' ) ),
		'feeds'   => array( __( 'Feeds & Google', 'jobcore' ), 'rss', __( 'Job feeds for aggregators and partners, Google job search and instant indexing.', 'jobcore' ) ),
		'sample'  => array( __( 'Sample content', 'jobcore' ), 'database-add', __( 'Demo employers and jobs to preview the board.', 'jobcore' ) ),
		'status'  => array( __( 'System status', 'jobcore' ), 'heart', __( 'Plugin, WordPress and server details — include this when you contact support.', 'jobcore' ) ),
	);
}

/**
 * Sidebar navigation: group => [ slug => [ label, dashicon, description, url ] ].
 * Add-ons (licence, employer accounts, paid listings…) can add groups or items via the filter.
 *
 * @return array<string, array<string, array{0:string,1:string,2:string,3:string}>>
 */
function wpjc_admin_nav() {
	$settings = array();
	foreach ( wpjc_settings_tabs() as $slug => $tab ) {
		$settings[ $slug ] = array( $tab[0], $tab[1], $tab[2], wpjc_settings_url( $slug ) );
	}
	$tools = array(
		'sample' => $settings['sample'],
		'status' => $settings['status'],
	);
	unset( $settings['sample'], $settings['status'] );

	$nav = array(
		__( 'Overview', 'jobcore' ) => array(
			'dashboard' => array( __( 'Dashboard', 'jobcore' ), 'dashboard', __( 'Your jobs board at a glance: listings, setup checklist and shortcuts.', 'jobcore' ), wpjc_dashboard_url() ),
		),
		__( 'Board', 'jobcore' )    => $settings,
		__( 'Content', 'jobcore' )  => array(
			'jobs'       => array( __( 'All jobs', 'jobcore' ), 'list-view', '', admin_url( 'edit.php?post_type=wpjc_job' ) ),
			'new'        => array( __( 'Add job', 'jobcore' ), 'plus-alt2', '', admin_url( 'post-new.php?post_type=wpjc_job' ) ),
			'employers'  => array( __( 'Employers', 'jobcore' ), 'building', '', admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ) ),
			'categories' => array( __( 'Categories', 'jobcore' ), 'category', '', admin_url( 'edit-tags.php?taxonomy=wpjc_job_category&post_type=wpjc_job' ) ),
			'types'      => array( __( 'Job types', 'jobcore' ), 'tag', '', admin_url( 'edit-tags.php?taxonomy=wpjc_job_type&post_type=wpjc_job' ) ),
		),
		__( 'System', 'jobcore' )   => $tools,
	);
	return (array) apply_filters( 'wpjc_admin_nav', $nav );
}

/**
 * Open the admin shell: header bar, sidebar and section heading.
 *
 * @param string $active   Sidebar slug to highlight.
 * @param bool   $has_form Show the header "Save Changes" button (targets #wpjc-opt-form).
 */
function wpjc_admin_shell_open( $active, $has_form = false ) {
	$nav   = wpjc_admin_nav();
	$items = array();
	foreach ( $nav as $links ) {
		$items += $links;
	}
	$current = $items[ $active ] ?? array( __( 'JobCore', 'jobcore' ), 'businessman', '', '' );
	$title   = 'dashboard' === $active ? __( 'Dashboard', 'jobcore' ) : __( 'Settings', 'jobcore' );
	$board   = wpjc_home_url();
	?>
	<div class="wrap wpjc-opt" style="--wpjc-admin-a:<?php echo esc_attr( wpjc_accent() ); ?>">
		<h1 class="screen-reader-text"><?php echo esc_html( $title ); ?></h1>
		<header class="wpjc-opt__bar">
			<div class="wpjc-opt__brand">
				<span class="wpjc-opt__mark dashicons dashicons-businessman" aria-hidden="true"></span>
				<span class="wpjc-opt__name"><?php esc_html_e( 'JobCore', 'jobcore' ); ?></span>
				<span class="wpjc-opt__title"><?php echo esc_html( $title ); ?></span>
				<span class="wpjc-opt__ver"><?php echo esc_html( 'v' . WPJC_VERSION ); ?></span>
			</div>
			<div class="wpjc-opt__actions">
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpjc_job' ) ); ?>"><?php esc_html_e( 'Add job', 'jobcore' ); ?></a>
				<a href="<?php echo esc_url( $board ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View board', 'jobcore' ); ?> ↗</a>
				<?php if ( $has_form ) : ?>
					<button type="submit" form="wpjc-opt-form" class="button button-primary wpjc-opt__save"><?php esc_html_e( 'Save Changes', 'jobcore' ); ?></button>
				<?php endif; ?>
			</div>
		</header>

		<div class="wpjc-opt__body">
			<nav class="wpjc-opt__nav" aria-label="<?php esc_attr_e( 'JobCore sections', 'jobcore' ); ?>">
				<?php foreach ( $nav as $group => $links ) : ?>
					<p class="wpjc-opt__group"><?php echo esc_html( $group ); ?></p>
					<ul>
						<?php foreach ( $links as $slug => $item ) : ?>
							<?php $internal = false !== strpos( (string) $item[3], 'post_type=wpjc_job&page=' ); ?>
							<li>
								<a href="<?php echo esc_url( $item[3] ); ?>" class="<?php echo $slug === $active ? 'is-active' : ''; ?>"<?php echo $slug === $active ? ' aria-current="page"' : ''; ?>>
									<span class="dashicons dashicons-<?php echo esc_attr( $item[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $item[0] ); ?>
									<?php if ( ! $internal ) : ?>
										<span class="wpjc-opt__ext" aria-hidden="true">›</span>
									<?php endif; ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endforeach; ?>
			</nav>

			<main class="wpjc-opt__main">
				<div class="wpjc-opt__head">
					<h2><span class="dashicons dashicons-<?php echo esc_attr( $current[1] ); ?>" aria-hidden="true"></span><?php echo esc_html( $current[0] ); ?></h2>
					<?php if ( '' !== $current[2] ) : ?>
						<p><?php echo esc_html( $current[2] ); ?></p>
					<?php endif; ?>
				</div>
	<?php
}

/** Close the shell opened by wpjc_admin_shell_open(). */
function wpjc_admin_shell_close() {
	echo '</main></div></div>';
}

/**
 * Board counts: [ count, label, url ].
 *
 * @return array<int, array{0:int,1:string,2:string}>
 */
function wpjc_admin_stats() {
	$list    = admin_url( 'edit.php?post_type=wpjc_job' );
	$premium = 0;
	foreach ( array_keys( wpjc_tiers() ) as $tier ) {
		if ( 'basic' !== $tier ) {
			$premium += (int) wpjc_query_jobs(
				array(
					'tier'     => $tier,
					'per_page' => 1,
				)
			)->found_posts;
		}
	}
	$expiring  = new WP_Query(
		array(
			'post_type'      => 'wpjc_job',
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'key'     => '_wpjc_expires',
					'value'   => array( wp_date( 'Y-m-d' ), wp_date( 'Y-m-d', time() + 7 * DAY_IN_SECONDS ) ),
					'compare' => 'BETWEEN',
					'type'    => 'DATE',
				),
			),
		)
	);
	$employers = wp_count_terms( array( 'taxonomy' => 'wpjc_employer' ) );
	$pending   = (int) ( wp_count_posts( 'wpjc_job' )->pending ?? 0 );
	$stats     = array(
		array( (int) wpjc_query_jobs( array( 'per_page' => 1 ) )->found_posts, __( 'Open jobs', 'jobcore' ), $list ),
		array( $premium, __( 'Premium listings', 'jobcore' ), $list ),
		array( (int) $expiring->found_posts, __( 'Expire within 7 days', 'jobcore' ), $list ),
		array( is_wp_error( $employers ) ? 0 : (int) $employers, __( 'Employers', 'jobcore' ), admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ) ),
		array( (int) get_option( 'wpjc_applications_total', 0 ), __( 'Applications sent', 'jobcore' ), admin_url( 'edit.php?post_type=wpjc_application' ) ),
	);
	if ( $pending ) {
		array_splice( $stats, 1, 0, array( array( $pending, __( 'Waiting for review', 'jobcore' ), admin_url( 'edit.php?post_status=pending&post_type=wpjc_job' ) ) ) );
	}
	return $stats;
}

add_action(
	'wpjc_application_submitted',
	static function ( $job, $data, $cv, $ok ) {
		if ( $ok ) {
			update_option( 'wpjc_applications_total', (int) get_option( 'wpjc_applications_total', 0 ) + 1, false );
		}
	},
	10,
	4
);

/**
 * Setup checklist: [ label, done, url ].
 *
 * @return array<int, array{label:string,done:bool,url:string}>
 */
function wpjc_admin_checklist() {
	$page = (int) get_option( 'wpjc_jobs_page_id' );
	$jobs = wp_count_posts( 'wpjc_job' );
	$emps = wp_count_terms( array( 'taxonomy' => 'wpjc_employer' ) );
	$opts = (array) get_option( 'wpjc_settings', array() );
	$items = array(
		array(
			'label' => __( 'Jobs page with the board', 'jobcore' ),
			'done'  => $page && 'publish' === get_post_status( $page ),
			'url'   => $page ? (string) get_edit_post_link( $page, 'raw' ) : admin_url( 'post-new.php?post_type=page' ),
		),
		array(
			'label' => __( 'Choose the look: inside the theme or standalone', 'jobcore' ),
			'done'  => isset( $opts['layout'] ),
			'url'   => wpjc_settings_url( 'look' ),
		),
		array(
			'label' => __( 'Add your first employer', 'jobcore' ),
			'done'  => ! is_wp_error( $emps ) && (int) $emps > 0,
			'url'   => admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ),
		),
		array(
			'label' => __( 'Publish a job', 'jobcore' ),
			'done'  => (int) $jobs->publish > 0,
			'url'   => admin_url( 'post-new.php?post_type=wpjc_job' ),
		),
		array(
			'label' => __( 'Add a contact e-mail', 'jobcore' ),
			'done'  => '' !== (string) wpjc_opt( 'contact_email' ),
			'url'   => wpjc_settings_url( 'contact' ),
		),
	);
	return apply_filters( 'wpjc_admin_checklist', $items );
}

/**
 * Latest jobs (any status) for the dashboards.
 *
 * @param int $limit Number of jobs.
 * @return WP_Post[]
 */
function wpjc_admin_latest_jobs( $limit = 6 ) {
	return get_posts(
		array(
			'post_type'      => 'wpjc_job',
			'post_status'    => array( 'publish', 'pending', 'draft', 'future' ),
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

/**
 * One row of the latest-jobs list.
 *
 * @param WP_Post $job Job.
 */
function wpjc_admin_job_row( $job ) {
	$tiers   = wpjc_tiers();
	$tier    = wpjc_job_tier( $job );
	$company = wpjc_job_company( $job );
	$expires = wpjc_meta( $job, 'expires' );
	$closed  = wpjc_meta( $job, 'filled' ) || ( $expires && $expires < wp_date( 'Y-m-d' ) );
	$status  = 'publish' === $job->post_status ? ( $closed ? __( 'Closed', 'jobcore' ) : __( 'Open', 'jobcore' ) ) : get_post_status_object( $job->post_status )->label;
	?>
	<li>
		<span class="wpjc-adm-tier" style="--t:<?php echo esc_attr( $tiers[ $tier ]['color'] ); ?>" title="<?php echo esc_attr( $tiers[ $tier ]['label'] ); ?>"><?php echo esc_html( $tiers[ $tier ]['short'] ); ?></span>
		<span class="wpjc-adm-job">
			<a href="<?php echo esc_url( (string) get_edit_post_link( $job ) ); ?>"><?php echo esc_html( get_the_title( $job ) ); ?></a>
			<small><?php echo esc_html( implode( ' · ', array_filter( array( $company['name'] ?? '', $expires ? sprintf( /* translators: %s: date */ __( 'until %s', 'jobcore' ), date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) ) : '' ) ) ) ); ?></small>
		</span>
		<span class="wpjc-adm-status is-<?php echo esc_attr( 'publish' === $job->post_status ? ( $closed ? 'closed' : 'open' ) : 'draft' ); ?>"><?php echo esc_html( $status ); ?></span>
	</li>
	<?php
}

/**
 * Jobs dashboard screen.
 */
function wpjc_render_dashboard() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$check = wpjc_admin_checklist();
	$done  = count( array_filter( wp_list_pluck( $check, 'done' ) ) );
	$jobs  = wpjc_admin_latest_jobs();
	$look  = 'standalone' === wpjc_opt( 'layout' ) ? __( 'Standalone portal', 'jobcore' ) : __( 'Inside the theme', 'jobcore' );
	$tools = array(
		array( 'plus-alt2', __( 'Add job', 'jobcore' ), admin_url( 'post-new.php?post_type=wpjc_job' ) ),
		array( 'building', __( 'Employers', 'jobcore' ), admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ) ),
		array( 'category', __( 'Categories', 'jobcore' ), admin_url( 'edit-tags.php?taxonomy=wpjc_job_category&post_type=wpjc_job' ) ),
		array( 'database-add', __( 'Sample content', 'jobcore' ), wpjc_settings_url( 'sample' ) ),
		array( 'heart', __( 'System status', 'jobcore' ), wpjc_settings_url( 'status' ) ),
	);
	$cards = array();
	foreach ( array( 'look', 'board', 'contact' ) as $slug ) {
		$tab            = wpjc_settings_tabs()[ $slug ];
		$cards[ $slug ] = array( $tab[1], $tab[0] );
	}
	wpjc_admin_shell_open( 'dashboard' );
	?>
	<section class="wpjc-dash-hero">
		<div>
			<h3><?php esc_html_e( 'Welcome to your jobs board', 'jobcore' ); ?></h3>
			<p><?php esc_html_e( 'Publish jobs with Premium Plus, Premium or Basic placement, show employers and take applications with CVs — all from WordPress.', 'jobcore' ); ?></p>
			<p class="wpjc-dash-hero__status">
				<span class="is-ok"><?php /* translators: %s: version */ printf( esc_html__( 'JobCore %s active', 'jobcore' ), esc_html( WPJC_VERSION ) ); ?></span>
				<span><?php /* translators: %s: board look */ printf( esc_html__( 'Look: %s', 'jobcore' ), esc_html( $look ) ); ?></span>
			</p>
		</div>
		<div class="wpjc-dash-hero__actions">
			<a class="button button-primary wpjc-opt__save" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpjc_job' ) ); ?>"><?php esc_html_e( 'Add job', 'jobcore' ); ?></a>
			<a class="button" href="<?php echo esc_url( wpjc_home_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View board', 'jobcore' ); ?></a>
		</div>
	</section>

	<ul class="wpjc-dash-stats">
		<?php foreach ( wpjc_admin_stats() as $st ) : ?>
			<li><a href="<?php echo esc_url( $st[2] ); ?>"><b><?php echo esc_html( number_format_i18n( $st[0] ) ); ?></b><span><?php echo esc_html( $st[1] ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>

	<div class="wpjc-dash-cols">
		<section class="wpjc-dash-box">
			<h3><?php esc_html_e( 'Latest jobs', 'jobcore' ); ?> <a class="wpjc-dash-more" href="<?php echo esc_url( admin_url( 'edit.php?post_type=wpjc_job' ) ); ?>"><?php esc_html_e( 'All jobs', 'jobcore' ); ?> →</a></h3>
			<?php if ( $jobs ) : ?>
				<ul class="wpjc-adm-jobs">
					<?php
					foreach ( $jobs as $job ) {
						wpjc_admin_job_row( $job );
					}
					?>
				</ul>
			<?php else : ?>
				<p class="wpjc-dash-empty"><?php esc_html_e( 'No jobs yet.', 'jobcore' ); ?> <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wpjc_job' ) ); ?>"><?php esc_html_e( 'Add the first one', 'jobcore' ); ?></a> · <a href="<?php echo esc_url( wpjc_settings_url( 'sample' ) ); ?>"><?php esc_html_e( 'or create sample jobs', 'jobcore' ); ?></a></p>
			<?php endif; ?>
		</section>
		<div class="wpjc-dash-side">
			<section class="wpjc-dash-box">
				<h3><?php esc_html_e( 'Setup checklist', 'jobcore' ); ?> <span class="wpjc-dash-count"><?php echo esc_html( $done . ' / ' . count( $check ) ); ?></span></h3>
				<ul class="wpjc-dash-check">
					<?php foreach ( $check as $c ) : ?>
						<li class="<?php echo $c['done'] ? 'is-done' : ''; ?>">
							<span class="dashicons dashicons-<?php echo $c['done'] ? 'yes-alt' : 'marker'; ?>" aria-hidden="true"></span>
							<a href="<?php echo esc_url( $c['url'] ); ?>"><?php echo esc_html( $c['label'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
			<section class="wpjc-dash-box">
				<h3><?php esc_html_e( 'Tools', 'jobcore' ); ?></h3>
				<ul class="wpjc-dash-tools">
					<?php foreach ( $tools as $t ) : ?>
						<li><a href="<?php echo esc_url( $t[2] ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $t[0] ); ?>" aria-hidden="true"></span><?php echo esc_html( $t[1] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</section>
		</div>
	</div>

	<h3 class="wpjc-dash-title"><?php esc_html_e( 'Settings', 'jobcore' ); ?></h3>
	<ul class="wpjc-dash-cards">
		<?php foreach ( $cards as $slug => $c ) : ?>
			<li><a href="<?php echo esc_url( wpjc_settings_url( $slug ) ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $c[0] ); ?>" aria-hidden="true"></span><?php echo esc_html( $c[1] ); ?></a></li>
		<?php endforeach; ?>
	</ul>
	<?php
	do_action( 'wpjc_dashboard_after' );
	wpjc_admin_shell_close();
}

/**
 * Our admin screens (shell + dashboard widget).
 */
function wpjc_is_admin_screen() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen ) {
		return false;
	}
	$ours = 'dashboard' === $screen->id || in_array( $screen->id, array( 'wpjc_job_page_' . WPJC_DASHBOARD, 'wpjc_job_page_' . WPJC_SETTINGS, 'wpjc_job_page_wpjc-setup' ), true );
	return (bool) apply_filters( 'wpjc_is_admin_screen', $ours, $screen );
}

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( wpjc_is_admin_screen() ) {
			wp_enqueue_style( 'wpjc-admin', WPJC_URL . 'assets/css/admin.css', array(), WPJC_VERSION . '.' . filemtime( WPJC_DIR . 'assets/css/admin.css' ) );
		}
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->id, array( 'wpjc_job_page_' . WPJC_SETTINGS, 'wpjc_job_page_wpjc-setup' ), true ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'wpjc-admin-media', WPJC_URL . 'assets/js/admin-media.js', array( 'media-editor' ), WPJC_VERSION . '.' . filemtime( WPJC_DIR . 'assets/js/admin-media.js' ), true );
			wp_localize_script(
				'wpjc-admin-media',
				'wpjcAdminMedia',
				array(
					'title'  => __( 'Choose an image', 'jobcore' ),
					'button' => __( 'Use this image', 'jobcore' ),
				)
			);
		}
	}
);

/*
 * With the News. theme: link the jobs board from the theme panel (Features group).
 */
add_filter(
	'news_panel_nav',
	static function ( $nav ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $nav;
		}
		$item  = array( __( 'Jobs board', 'jobcore' ), 'businessman', __( 'JobCore: jobs, employers, look and settings.', 'jobcore' ), wpjc_dashboard_url() );
		$group = __( 'Features', 'news' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- the theme's own group label.
		if ( isset( $nav[ $group ] ) ) {
			$nav[ $group ]['wpjc'] = $item;
		} else {
			$nav[ __( 'Jobs', 'jobcore' ) ] = array( 'wpjc' => $item );
		}
		return $nav;
	}
);
