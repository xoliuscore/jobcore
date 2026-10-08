<?php
/**
 * Setup wizard and the "nearly ready" admin notice shown until it is finished or skipped.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_SETUP = 'wpjc-setup';

/**
 * Wizard steps: slug => [ label, dashicon ].
 *
 * @return array<string, array{0:string,1:string}>
 */
function wpjc_setup_steps() {
	return array(
		'welcome' => array( __( 'Welcome', 'jobcore' ), 'flag' ),
		'pages'   => array( __( 'Pages', 'jobcore' ), 'admin-page' ),
		'look'    => array( __( 'Look', 'jobcore' ), 'art' ),
		'board'   => array( __( 'Contact & job ads', 'jobcore' ), 'phone' ),
		'sample'  => array( __( 'Sample jobs', 'jobcore' ), 'database-add' ),
		'done'    => array( __( 'Done', 'jobcore' ), 'yes-alt' ),
	);
}

/**
 * Admin URL of a wizard step.
 *
 * @param string $step Step slug.
 */
function wpjc_setup_url( $step = 'welcome' ) {
	return admin_url( 'edit.php?post_type=wpjc_job&page=' . WPJC_SETUP . '&step=' . rawurlencode( $step ) );
}

/**
 * Slug of the step after $step.
 *
 * @param string $step Step slug.
 */
function wpjc_setup_next( $step ) {
	$keys = array_keys( wpjc_setup_steps() );
	$i    = array_search( $step, $keys, true );
	return false === $i ? 'welcome' : $keys[ min( $i + 1, count( $keys ) - 1 ) ];
}

/**
 * Slug of the step before $step ('' on the first one).
 *
 * @param string $step Step slug.
 */
function wpjc_setup_prev( $step ) {
	$keys = array_keys( wpjc_setup_steps() );
	$i    = array_search( $step, $keys, true );
	return $i ? $keys[ $i - 1 ] : '';
}

/** Setup finished or skipped. */
function wpjc_setup_done() {
	return (bool) get_option( 'wpjc_setup_done' );
}

/** Mark setup as finished (or skipped) so the notice goes away. */
function wpjc_setup_finish() {
	update_option( 'wpjc_setup_done', time(), false );
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=wpjc_job',
			__( 'JobCore setup', 'jobcore' ),
			__( 'Setup wizard', 'jobcore' ),
			'manage_options',
			WPJC_SETUP,
			'wpjc_render_setup'
		);
	}
);

// Reachable by URL and from the notice and the System menu, but not listed under Jobs.
add_action(
	'admin_head',
	static function () {
		remove_submenu_page( 'edit.php?post_type=wpjc_job', WPJC_SETUP );
	}
);

add_filter(
	'wpjc_admin_nav',
	static function ( $nav ) {
		$group = __( 'System', 'jobcore' );
		if ( current_user_can( 'manage_options' ) && isset( $nav[ $group ] ) ) {
			$nav[ $group ]['setup'] = array( __( 'Setup wizard', 'jobcore' ), 'welcome-learn-more', __( 'Pages, look and contact details in a few steps.', 'jobcore' ), wpjc_setup_url() );
		}
		return $nav;
	}
);

/** Show the notice on this request. */
function wpjc_setup_show_notice() {
	if ( wpjc_setup_done() || ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	return ! $screen || 'wpjc_job_page_' . WPJC_SETUP !== $screen->id;
}

add_action(
	'admin_enqueue_scripts',
	static function () {
		if ( wpjc_setup_show_notice() ) {
			wp_enqueue_style( 'wpjc-admin-notice', WPJC_URL . 'assets/css/admin-notice.css', array(), WPJC_VERSION . '.' . filemtime( WPJC_DIR . 'assets/css/admin-notice.css' ) );
		}
	}
);

add_action(
	'admin_notices',
	static function () {
		if ( ! wpjc_setup_show_notice() ) {
			return;
		}
		$skip = wp_nonce_url( admin_url( 'admin-post.php?action=wpjc_setup_skip' ), 'wpjc_setup_skip' );
		?>
		<div class="notice wpjc-setup-notice" style="--wpjc-admin-a:<?php echo esc_attr( wpjc_accent() ); ?>">
			<span class="wpjc-setup-notice__mark dashicons dashicons-businessman" aria-hidden="true"></span>
			<div class="wpjc-setup-notice__message">
				<p class="wpjc-setup-notice__heading"><?php esc_html_e( 'You are nearly ready to start listing jobs with JobCore', 'jobcore' ); ?></p>
				<p><?php esc_html_e( 'Go through the setup to check the jobs page, choose the look and add your contact details. It takes about two minutes.', 'jobcore' ); ?></p>
				<p class="wpjc-setup-notice__small">
					<?php
					/* translators: %s: link to the settings */
					printf( esc_html__( '* Skipping keeps the defaults. You can change everything later in %s.', 'jobcore' ), '<a href="' . esc_url( wpjc_settings_url() ) . '">' . esc_html__( 'Jobs → Settings', 'jobcore' ) . '</a>' );
					?>
				</p>
			</div>
			<div class="wpjc-setup-notice__actions">
				<a class="button button-primary wpjc-setup-notice__go" href="<?php echo esc_url( wpjc_setup_url() ); ?>"><?php esc_html_e( 'Run setup wizard', 'jobcore' ); ?></a>
				<a class="button wpjc-setup-notice__skip" href="<?php echo esc_url( $skip ); ?>"><?php esc_html_e( 'Skip setup*', 'jobcore' ); ?></a>
			</div>
		</div>
		<?php
	}
);

add_action(
	'admin_post_wpjc_setup_skip',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'jobcore' ), 403 );
		}
		check_admin_referer( 'wpjc_setup_skip' );
		wpjc_setup_finish();
		$back = wp_get_referer();
		wp_safe_redirect( $back && false === strpos( $back, WPJC_SETUP ) ? $back : wpjc_dashboard_url() );
		exit;
	}
);

add_action( 'admin_post_wpjc_setup', 'wpjc_handle_setup' );

/**
 * Save one wizard step and go to the next one.
 */
function wpjc_handle_setup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'jobcore' ), 403 );
	}
	check_admin_referer( 'wpjc_setup' );
	$step = isset( $_POST['step'] ) ? sanitize_key( wp_unslash( $_POST['step'] ) ) : '';
	if ( ! isset( wpjc_setup_steps()[ $step ] ) ) {
		$step = 'welcome';
	}

	if ( 'pages' === $step ) {
		wpjc_setup_save_pages();
	} elseif ( in_array( $step, array( 'look', 'board' ), true ) && isset( $_POST['wpjc_settings'] ) && is_array( $_POST['wpjc_settings'] ) ) {
		// Unslashed and cleaned field by field in wpjc_sanitize_settings().
		update_option( 'wpjc_settings', $_POST['wpjc_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
	} elseif ( 'sample' === $step ) {
		if ( isset( $_POST['sample'] ) && 'add' === $_POST['sample'] && ! wpjc_sample_count() ) {
			wpjc_seed_create();
		}
		wpjc_setup_finish();
	}

	wp_safe_redirect( wpjc_setup_url( wpjc_setup_next( $step ) ) );
	exit;
}

/**
 * Pages step: rename the jobs page, or create it when it is missing.
 */
function wpjc_setup_save_pages() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in wpjc_handle_setup().
	$title = isset( $_POST['jobs_title'] ) ? sanitize_text_field( wp_unslash( $_POST['jobs_title'] ) ) : '';
	$slug  = isset( $_POST['jobs_slug'] ) ? sanitize_title( wp_unslash( $_POST['jobs_slug'] ) ) : '';
	$make  = ! empty( $_POST['jobs_create'] );
	// phpcs:enable
	$title = '' !== $title ? $title : __( 'Jobs', 'jobcore' );
	$id    = (int) get_option( 'wpjc_jobs_page_id' );

	if ( $id && in_array( get_post_status( $id ), array( 'publish', 'draft', 'private' ), true ) ) {
		$post = array(
			'ID'          => $id,
			'post_title'  => $title,
			'post_status' => 'publish',
		);
		if ( '' !== $slug ) {
			$post['post_name'] = $slug;
		}
		wp_update_post( wp_slash( $post ) );
		return;
	}
	if ( ! $make ) {
		return;
	}
	$new = wp_insert_post(
		wp_slash(
			array(
				'post_title'   => $title,
				'post_name'    => '' !== $slug ? $slug : 'jobs',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '[wpjc_jobs]',
			)
		),
		true
	);
	if ( ! is_wp_error( $new ) && $new ) {
		update_option( 'wpjc_jobs_page_id', (int) $new );
	}
}

/**
 * Hidden fields and the opening tag of a step form.
 *
 * @param string $step Step slug.
 */
function wpjc_setup_form_open( $step ) {
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpjc-setup__form">
		<input type="hidden" name="action" value="wpjc_setup">
		<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">
		<?php wp_nonce_field( 'wpjc_setup' ); ?>
	<?php
}

/**
 * Step footer: back, skip and continue; closes the form.
 *
 * @param string $step   Step slug.
 * @param string $submit Continue button label.
 */
function wpjc_setup_form_close( $step, $submit = '' ) {
	$prev = wpjc_setup_prev( $step );
	?>
		<div class="wpjc-setup__foot">
			<?php if ( $prev ) : ?>
				<a class="wpjc-setup__back" href="<?php echo esc_url( wpjc_setup_url( $prev ) ); ?>">← <?php esc_html_e( 'Back', 'jobcore' ); ?></a>
			<?php endif; ?>
			<span class="wpjc-setup__grow"></span>
			<?php if ( 'sample' !== $step ) : ?>
				<a class="button" href="<?php echo esc_url( wpjc_setup_url( wpjc_setup_next( $step ) ) ); ?>"><?php esc_html_e( 'Skip this step', 'jobcore' ); ?></a>
			<?php endif; ?>
			<button type="submit" class="button button-primary wpjc-opt__save"><?php echo esc_html( '' !== $submit ? $submit : __( 'Save and continue', 'jobcore' ) ); ?></button>
		</div>
	</form>
	<?php
}

/**
 * Wizard screen.
 */
function wpjc_render_setup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$steps = wpjc_setup_steps();
	$step  = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : 'welcome'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
	$step  = isset( $steps[ $step ] ) ? $step : 'welcome';
	$keys  = array_keys( $steps );
	$index = 'done' === $step ? count( $keys ) : (int) array_search( $step, $keys, true );
	$opts  = wp_parse_args( (array) get_option( 'wpjc_settings', array() ), wpjc_default_settings() );
	?>
	<div class="wrap wpjc-opt wpjc-setup" style="--wpjc-admin-a:<?php echo esc_attr( wpjc_accent() ); ?>">
		<h1 class="screen-reader-text"><?php esc_html_e( 'JobCore setup', 'jobcore' ); ?></h1>
		<header class="wpjc-opt__bar">
			<div class="wpjc-opt__brand">
				<span class="wpjc-opt__mark dashicons dashicons-businessman" aria-hidden="true"></span>
				<span class="wpjc-opt__name"><?php esc_html_e( 'JobCore', 'jobcore' ); ?></span>
				<span class="wpjc-opt__title"><?php esc_html_e( 'Setup', 'jobcore' ); ?></span>
				<span class="wpjc-opt__ver"><?php echo esc_html( 'v' . WPJC_VERSION ); ?></span>
			</div>
			<div class="wpjc-opt__actions">
				<a href="<?php echo esc_url( wpjc_dashboard_url() ); ?>"><?php esc_html_e( 'Exit setup', 'jobcore' ); ?></a>
			</div>
		</header>

		<ol class="wpjc-setup__steps">
			<?php foreach ( $keys as $i => $key ) : ?>
				<li class="<?php echo esc_attr( $i < $index ? 'is-done' : ( $i === $index ? 'is-current' : '' ) ); ?>"<?php echo $i === $index ? ' aria-current="step"' : ''; ?>>
					<span class="wpjc-setup__num"><?php echo $i < $index ? '<span class="dashicons dashicons-yes" aria-hidden="true"></span>' : esc_html( (string) ( $i + 1 ) ); ?></span>
					<span class="wpjc-setup__label"><?php echo esc_html( $steps[ $key ][0] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>

		<div class="wpjc-setup__card">
			<?php
			switch ( $step ) {
				case 'pages':
					wpjc_setup_step_pages();
					break;
				case 'look':
					wpjc_setup_step_look( $opts );
					break;
				case 'board':
					wpjc_setup_step_board( $opts );
					break;
				case 'sample':
					wpjc_setup_step_sample();
					break;
				case 'done':
					wpjc_setup_finish();
					wpjc_setup_step_done();
					break;
				default:
					wpjc_setup_step_welcome();
			}
			?>
		</div>
	</div>
	<?php
}

/**
 * Step heading.
 *
 * @param string $title Title.
 * @param string $intro Intro text.
 */
function wpjc_setup_head( $title, $intro ) {
	?>
	<div class="wpjc-setup__head">
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $intro ); ?></p>
	</div>
	<?php
}

/** Welcome step. */
function wpjc_setup_step_welcome() {
	$skip  = wp_nonce_url( admin_url( 'admin-post.php?action=wpjc_setup_skip' ), 'wpjc_setup_skip' );
	$items = array(
		array( 'admin-page', __( 'Pages', 'jobcore' ), __( 'The jobs page and the addresses of the jobs account.', 'jobcore' ) ),
		array( 'art', __( 'Look', 'jobcore' ), __( 'Inside your theme or a standalone portal, and the accent colour.', 'jobcore' ) ),
		array( 'phone', __( 'Contact & job ads', 'jobcore' ), __( 'Your contact details and how job ads from users are reviewed.', 'jobcore' ) ),
		array( 'database-add', __( 'Sample jobs', 'jobcore' ), __( 'Optional demo employers and jobs to preview the board.', 'jobcore' ) ),
	);
	?>
	<div class="wpjc-setup__welcome">
		<span class="wpjc-setup__icon dashicons dashicons-businessman" aria-hidden="true"></span>
		<h2><?php esc_html_e( 'Welcome to JobCore', 'jobcore' ); ?></h2>
		<p><?php esc_html_e( 'Employers post jobs, candidates search, save and apply — all on your own site. This short setup gets the board ready. Every step can be skipped and changed later.', 'jobcore' ); ?></p>
	</div>
	<ul class="wpjc-setup__tiles">
		<?php foreach ( $items as $item ) : ?>
			<li><span class="dashicons dashicons-<?php echo esc_attr( $item[0] ); ?>" aria-hidden="true"></span><b><?php echo esc_html( $item[1] ); ?></b><small><?php echo esc_html( $item[2] ); ?></small></li>
		<?php endforeach; ?>
	</ul>
	<div class="wpjc-setup__foot">
		<a class="wpjc-setup__back" href="<?php echo esc_url( $skip ); ?>"><?php esc_html_e( 'Not now — skip setup', 'jobcore' ); ?></a>
		<span class="wpjc-setup__grow"></span>
		<a class="button button-primary wpjc-opt__save" href="<?php echo esc_url( wpjc_setup_url( 'pages' ) ); ?>"><?php esc_html_e( 'Start setup', 'jobcore' ); ?> →</a>
	</div>
	<?php
}

/** Pages step. */
function wpjc_setup_step_pages() {
	$id     = (int) get_option( 'wpjc_jobs_page_id' );
	$exists = $id && in_array( get_post_status( $id ), array( 'publish', 'draft', 'private' ), true );
	$title  = $exists ? get_the_title( $id ) : __( 'Jobs', 'jobcore' );
	$slug   = $exists ? (string) get_post_field( 'post_name', $id ) : 'jobs';
	$parent = $exists ? trailingslashit( dirname( '/' . get_page_uri( $id ) ) ) : '/';
	$pretty = '' !== (string) get_option( 'permalink_structure' );
	$routes = array(
		array( __( 'All jobs', 'jobcore' ), __( 'Every open job with search and filters.', 'jobcore' ), wpjc_view_url( 'all' ) ),
		array( __( 'Employers', 'jobcore' ), __( 'All companies with open jobs.', 'jobcore' ), wpjc_view_url( 'employers' ) ),
		array( __( 'Jobs account', 'jobcore' ), __( 'Applications, job alerts, job ads and companies of signed-in users.', 'jobcore' ), wpjc_account_url() ),
		array( __( 'Post a job', 'jobcore' ), __( 'The job ad form in the jobs account.', 'jobcore' ), wpjc_account_url( 'jobs', 'new' ) ),
		array( __( 'Add a company', 'jobcore' ), __( 'The company form in the jobs account.', 'jobcore' ), wpjc_account_url( 'companies', 'new' ) ),
	);
	wpjc_setup_head( __( 'Page setup', 'jobcore' ), __( 'The board lives on one WordPress page. Everything else — all jobs, employers and the jobs account — gets its own address under that page automatically.', 'jobcore' ) );
	wpjc_setup_form_open( 'pages' );
	if ( ! $pretty ) :
		?>
		<div class="notice notice-warning inline"><p>
			<?php
			/* translators: %s: link to the permalink settings */
			printf( esc_html__( 'Your site uses plain links, so the addresses below use ?wpjc_view= parameters. Choose any other option in %s for clean addresses.', 'jobcore' ), '<a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">' . esc_html__( 'Settings → Permalinks', 'jobcore' ) . '</a>' );
			?>
		</p></div>
	<?php endif; ?>
	<table class="widefat wpjc-setup__pages">
		<thead>
			<tr>
				<td class="check-column"></td>
				<th scope="col"><?php esc_html_e( 'Page title', 'jobcore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Description', 'jobcore' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Address', 'jobcore' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<th scope="row" class="check-column">
					<?php if ( $exists ) : ?>
						<span class="dashicons dashicons-yes-alt wpjc-setup__ok" title="<?php esc_attr_e( 'Page exists', 'jobcore' ); ?>"></span>
					<?php else : ?>
						<input type="checkbox" name="jobs_create" value="1" checked aria-label="<?php esc_attr_e( 'Create the jobs page', 'jobcore' ); ?>">
					<?php endif; ?>
				</th>
				<td><input type="text" class="regular-text" name="jobs_title" value="<?php echo esc_attr( $title ); ?>" aria-label="<?php esc_attr_e( 'Page title', 'jobcore' ); ?>"></td>
				<td>
					<?php
					echo $exists
						? esc_html__( 'The board home: hero with search, Premium Plus, Premium and Basic jobs, and featured employers. Already created — you can rename it here.', 'jobcore' )
						: esc_html__( 'Creates the page for the board home: hero with search, Premium Plus, Premium and Basic jobs, and featured employers.', 'jobcore' );
					?>
					<code>[wpjc_jobs]</code>
				</td>
				<td class="wpjc-setup__addr">
					<span><?php echo esc_html( untrailingslashit( home_url() ) . $parent ); ?></span><input type="text" name="jobs_slug" value="<?php echo esc_attr( $slug ); ?>" aria-label="<?php esc_attr_e( 'Page address', 'jobcore' ); ?>"><span>/</span>
				</td>
			</tr>
			<?php foreach ( $routes as $route ) : ?>
				<tr class="is-auto">
					<th scope="row" class="check-column"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span></th>
					<td><b><?php echo esc_html( $route[0] ); ?></b> <span class="wpjc-setup__tag"><?php esc_html_e( 'Automatic', 'jobcore' ); ?></span></td>
					<td><?php echo esc_html( $route[1] ); ?></td>
					<td class="wpjc-setup__addr"><?php if ( $exists ) : ?><a href="<?php echo esc_url( $route[2] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $route[2] ); ?></a><?php else : ?>—<?php endif; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	wpjc_setup_form_close( 'pages', $exists ? '' : __( 'Create page and continue', 'jobcore' ) );
}

/**
 * Look step.
 *
 * @param array $opts Current settings.
 */
function wpjc_setup_step_look( array $opts ) {
	wpjc_setup_head( __( 'Choose the look', 'jobcore' ), __( 'Show the board inside your theme or as a jobs portal with its own header and footer, and pick the accent colour.', 'jobcore' ) );
	wpjc_setup_form_open( 'look' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Board look', 'jobcore' ); ?></th>
			<td><?php wpjc_render_layout_choices( $opts ); ?></td>
		</tr>
		<?php wpjc_admin_field( 'brand_name', __( 'Board name', 'jobcore' ), 'text', __( 'Empty = “{Site name} Jobs”.', 'jobcore' ), $opts, array( 'placeholder' => wpjc_brand()['name'] ?? '' ) ); ?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Accent colour', 'jobcore' ); ?></th>
			<td><?php wpjc_render_accent_choices( $opts ); ?></td>
		</tr>
	</table>
	<p class="wpjc-setup__hint"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><?php esc_html_e( 'Logo, header style, logo sizes and the hero image are in Jobs → Settings → Look & colours.', 'jobcore' ); ?></p>
	<?php
	wpjc_setup_form_close( 'look' );
}

/**
 * Contact and job ads step.
 *
 * @param array $opts Current settings.
 */
function wpjc_setup_step_board( array $opts ) {
	if ( '' === (string) $opts['contact_email'] ) {
		$opts['contact_email'] = (string) get_option( 'admin_email' );
	}
	wpjc_setup_head( __( 'Contact & job ads', 'jobcore' ), __( 'Shown under the hero of the board. Signed-in users can post job ads for their companies in the jobs account.', 'jobcore' ) );
	wpjc_setup_form_open( 'board' );
	?>
	<table class="form-table" role="presentation">
		<?php
		wpjc_admin_field( 'contact_email', __( 'Contact e-mail', 'jobcore' ), 'email', __( 'Shown on the board; receives the “waiting for review” e-mails.', 'jobcore' ), $opts );
		wpjc_admin_field( 'contact_phone', __( 'Contact phone', 'jobcore' ), 'text', __( 'Optional.', 'jobcore' ), $opts );
		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'New job ads', 'jobcore' ); ?></th>
			<td>
				<label><input type="checkbox" name="wpjc_settings[fe_review]" value="1" <?php checked( (int) $opts['fe_review'], 1 ); ?>> <?php esc_html_e( 'Review job ads posted in the jobs account before they go live', 'jobcore' ); ?></label>
				<p class="description"><?php esc_html_e( 'Recommended. Untick to publish them right away.', 'jobcore' ); ?></p>
			</td>
		</tr>
		<?php wpjc_admin_field( 'fe_days', __( 'Max listing days', 'jobcore' ), 'number', __( 'Longest period a user can choose for a job ad (7–120).', 'jobcore' ), $opts, array( 'min' => 7, 'max' => 120 ) ); ?>
	</table>
	<?php
	wpjc_setup_form_close( 'board' );
}

/** Sample jobs step. */
function wpjc_setup_step_sample() {
	$count = wpjc_sample_count();
	wpjc_setup_head( __( 'Sample jobs', 'jobcore' ), __( 'Preview the board with demo content. Samples are marked and can be removed in one click from Jobs → Settings → Sample content; your own jobs are never touched.', 'jobcore' ) );
	wpjc_setup_form_open( 'sample' );
	?>
	<?php if ( $count ) : ?>
		<p class="wpjc-setup__hint is-ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<?php
			/* translators: %d: number of sample jobs */
			echo esc_html( sprintf( _n( '%d sample job is already on this site.', '%d sample jobs are already on this site.', $count, 'jobcore' ), $count ) );
			?>
		</p>
		<input type="hidden" name="sample" value="none">
	<?php else : ?>
		<fieldset class="wpjc-choices wpjc-setup__choices">
			<label class="wpjc-choice"><input type="radio" name="sample" value="add" checked><span class="dashicons dashicons-database-add" aria-hidden="true"></span><b><?php esc_html_e( 'Add sample jobs', 'jobcore' ); ?></b><small><?php esc_html_e( '5 employers and 12 jobs, some with photos.', 'jobcore' ); ?></small></label>
			<label class="wpjc-choice"><input type="radio" name="sample" value="none"><span class="dashicons dashicons-media-default" aria-hidden="true"></span><b><?php esc_html_e( 'Start empty', 'jobcore' ); ?></b><small><?php esc_html_e( 'Add your own employers and jobs.', 'jobcore' ); ?></small></label>
		</fieldset>
	<?php endif; ?>
	<?php
	wpjc_setup_form_close( 'sample', __( 'Finish setup', 'jobcore' ) );
}

/** Done step. */
function wpjc_setup_step_done() {
	$next = array(
		array( 'external', __( 'View the board', 'jobcore' ), __( 'See the jobs page as visitors do.', 'jobcore' ), wpjc_home_url(), true ),
		array( 'plus-alt2', __( 'Add a job', 'jobcore' ), __( 'Publish a job with Premium Plus, Premium or Basic placement.', 'jobcore' ), admin_url( 'post-new.php?post_type=wpjc_job' ), false ),
		array( 'building', __( 'Add employers', 'jobcore' ), __( 'Company names, logos and websites.', 'jobcore' ), admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ), false ),
		array( 'admin-users', __( 'Open the jobs account', 'jobcore' ), __( 'What signed-in users see: applications, alerts, job ads.', 'jobcore' ), wpjc_account_url(), true ),
		array( 'dashboard', __( 'Jobs dashboard', 'jobcore' ), __( 'Counts, setup checklist and the latest jobs.', 'jobcore' ), wpjc_dashboard_url(), false ),
		array( 'admin-generic', __( 'All settings', 'jobcore' ), __( 'Logo, header, board texts, links and social profiles.', 'jobcore' ), wpjc_settings_url(), false ),
	);
	?>
	<div class="wpjc-setup__welcome is-done">
		<span class="wpjc-setup__icon dashicons dashicons-yes" aria-hidden="true"></span>
		<h2><?php esc_html_e( 'Your jobs board is ready', 'jobcore' ); ?></h2>
		<p><?php esc_html_e( 'Here are the most common next steps. You can run this setup again any time from Jobs → Settings → Setup wizard.', 'jobcore' ); ?></p>
	</div>
	<ul class="wpjc-setup__tiles is-links">
		<?php foreach ( $next as $item ) : ?>
			<li>
				<a href="<?php echo esc_url( $item[3] ); ?>"<?php echo $item[4] ? ' target="_blank" rel="noopener"' : ''; ?>>
					<span class="dashicons dashicons-<?php echo esc_attr( $item[0] ); ?>" aria-hidden="true"></span><b><?php echo esc_html( $item[1] ); ?></b><small><?php echo esc_html( $item[2] ); ?></small>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php
}
