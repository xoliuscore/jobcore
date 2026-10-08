<?php
/**
 * Jobs account: overview.
 *
 * @package JobCore
 * @var WP_User    $user         Current user.
 * @var WP_Post[]  $applications Applications.
 * @var array      $alerts       Alerts.
 * @var WP_Post[]  $saved        Saved jobs.
 * @var WP_Post[]  $jobs         Job ads.
 * @var WP_Term[]  $companies    Companies.
 * @var array|null $notice       Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_active_alerts = count(
	array_filter(
		$alerts,
		static function ( $alert ) {
			return $alert['active'];
		}
	)
);
$wpjc_live_jobs     = count(
	array_filter(
		$jobs,
		static function ( $job ) {
			return 'live' === wpjc_job_owner_status( $job )[0];
		}
	)
);
$wpjc_hour          = (int) wp_date( 'G' );
$wpjc_greet         = $wpjc_hour < 12 ? __( 'Good morning', 'jobcore' ) : ( $wpjc_hour < 18 ? __( 'Good afternoon', 'jobcore' ) : __( 'Good evening', 'jobcore' ) );
$wpjc_first         = trim( (string) $user->first_name );

// Account type decides what the overview shows ('' = everything: board team, or one account for all).
$wpjc_type = ( 'off' === wpjc_accounts_mode() || user_can( $user, 'manage_options' ) ) ? '' : wpjc_user_type( $user->ID );
$wpjc_new  = $jobs ? wpjc_received_new_count( $user->ID ) : 0;

$wpjc_stats = array();
$wpjc_tiles = array();
if ( 'employer' !== $wpjc_type ) {
	$wpjc_stats[] = array( 'send', count( $applications ), __( 'Applications', 'jobcore' ), __( 'Jobs you applied for', 'jobcore' ), wpjc_account_url( 'applications' ) );
	$wpjc_stats[] = array( 'bell', $wpjc_active_alerts, __( 'Active alerts', 'jobcore' ), __( 'New jobs by e-mail', 'jobcore' ), wpjc_account_url( 'alerts' ) );
}
if ( 'candidate' !== $wpjc_type ) {
	$wpjc_stats[] = array( 'briefcase', $wpjc_live_jobs, __( 'Live job ads', 'jobcore' ), __( 'Your ads on the board', 'jobcore' ), wpjc_account_url( 'jobs' ) );
}
if ( 'employer' === $wpjc_type ) {
	$wpjc_stats[] = array( 'inbox', $wpjc_new, __( 'New applications', 'jobcore' ), __( 'Waiting for your answer', 'jobcore' ), wpjc_account_url( 'received' ) );
	$wpjc_stats[] = array( 'building', count( $companies ), __( 'Companies', 'jobcore' ), __( 'Your company profiles', 'jobcore' ), wpjc_account_url( 'companies' ) );
} else {
	$wpjc_stats[] = array( 'bookmark', count( $saved ), __( 'Bookmarks', 'jobcore' ), __( 'Saved in your account', 'jobcore' ), wpjc_account_url( 'saved' ) );
}
if ( 'employer' !== $wpjc_type ) {
	$wpjc_tiles[] = array( 'search', __( 'Find a job', 'jobcore' ), __( 'Search all open jobs and apply in a minute.', 'jobcore' ), wpjc_view_url( 'all' ) );
	$wpjc_tiles[] = array( 'bell', __( 'Create a job alert', 'jobcore' ), __( 'Tell us what you are looking for — we e-mail you new jobs.', 'jobcore' ), wpjc_account_url( 'alerts', 'new' ) );
}
if ( 'candidate' !== $wpjc_type ) {
	$wpjc_tiles[] = $companies
		? array( 'plus-c', __( 'Post a job', 'jobcore' ), __( 'Publish a job ad for one of your companies.', 'jobcore' ), wpjc_account_url( 'jobs', 'new' ) )
		: array( 'building', __( 'Add your company', 'jobcore' ), __( 'Hiring? Add a company profile, then post job ads.', 'jobcore' ), wpjc_account_url( 'companies', 'new' ) );
}
if ( 'employer' === $wpjc_type ) {
	$wpjc_tiles[] = array( 'inbox', __( 'Applications received', 'jobcore' ), __( 'Read applications, shortlist candidates and download CVs.', 'jobcore' ), wpjc_account_url( 'received' ) );
}
/**
 * Overview number cards ([icon, number, label, hint, URL]) and shortcut tiles ([icon, title, text, URL]).
 *
 * @param array   $items Items.
 * @param WP_User $user  User.
 */
$wpjc_stats = (array) apply_filters( 'wpjc_account_overview_stats', $wpjc_stats, $user );
$wpjc_tiles = (array) apply_filters( 'wpjc_account_overview_tiles', $wpjc_tiles, $user );

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'My jobs account', 'jobcore' ),
		'dek'    => 'employer' === $wpjc_type
			? __( 'Your companies, job ads and the applications you receive in one place.', 'jobcore' )
			: ( 'candidate' === $wpjc_type ? __( 'Your applications, saved jobs and job alerts in one place.', 'jobcore' ) : __( 'Your applications, job alerts and job ads in one place.', 'jobcore' ) ),
		'notice' => $notice,
	)
);
?>
<div class="wpjc-acc-card wpjc-acc-welcome">
	<div class="wpjc-acc-welcome__who">
		<p class="wpjc-acc-welcome__greet">
			<?php
			/* translators: 1: greeting, 2: name */
			echo esc_html( sprintf( __( '%1$s, %2$s.', 'jobcore' ), $wpjc_greet, '' !== $wpjc_first ? $wpjc_first : $user->display_name ) );
			?>
		</p>
		<p class="wpjc-acc-welcome__mail"><?php echo esc_html( $user->user_email ); ?></p>
		<?php if ( '' !== $wpjc_type ) : ?>
			<div class="wpjc-acc-welcome__type">
				<?php echo wpjc_icon( wpjc_account_types()[ $wpjc_type ][2], 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span><?php echo 'employer' === $wpjc_type ? esc_html__( 'Account for hiring', 'jobcore' ) : esc_html__( 'Account for job seeking', 'jobcore' ); ?></span>
				<?php if ( 'soft' === wpjc_accounts_mode() ) : ?>
					<?php $wpjc_other = 'employer' === $wpjc_type ? 'candidate' : 'employer'; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wpjc_account_form_fields( 'account_type' ); ?>
						<button class="wpjc-acc-welcome__switch" type="submit" name="type" value="<?php echo esc_attr( $wpjc_other ); ?>"><?php echo 'employer' === $wpjc_other ? esc_html__( 'Switch to hiring', 'jobcore' ) : esc_html__( 'Switch to job seeking', 'jobcore' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
	<a class="wpjc-btn wpjc-btn--sm wpjc-btn--ghost" href="<?php echo esc_url( wpjc_profile_url() ); ?>"><?php esc_html_e( 'Edit profile', 'jobcore' ); ?></a>
</div>

<ul class="wpjc-acc-stats<?php echo 3 === count( $wpjc_stats ) ? ' wpjc-acc-stats--3' : ''; ?>">
	<?php foreach ( $wpjc_stats as $wpjc_stat ) : ?>
		<li>
			<a class="wpjc-acc-stat" href="<?php echo esc_url( $wpjc_stat[4] ); ?>">
				<span class="wpjc-acc-stat__icon"><?php echo wpjc_icon( $wpjc_stat[0], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<?php if ( null === $wpjc_stat[1] ) : ?>
					<span class="wpjc-acc-stat__num" data-wpjc-fav-total>0</span>
				<?php else : ?>
					<span class="wpjc-acc-stat__num"><?php echo esc_html( number_format_i18n( $wpjc_stat[1] ) ); ?></span>
				<?php endif; ?>
				<span class="wpjc-acc-stat__label"><?php echo esc_html( $wpjc_stat[2] ); ?></span>
				<span class="wpjc-acc-stat__hint"><?php echo esc_html( $wpjc_stat[3] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>

<ul class="wpjc-acc-tiles">
	<?php foreach ( $wpjc_tiles as $wpjc_tile ) : ?>
		<li>
			<a class="wpjc-acc-tile" href="<?php echo esc_url( $wpjc_tile[3] ); ?>">
				<span class="wpjc-acc-tile__icon"><?php echo wpjc_icon( $wpjc_tile[0], 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<span class="wpjc-acc-tile__title"><?php echo esc_html( $wpjc_tile[1] ); ?></span>
				<span class="wpjc-acc-tile__dek"><?php echo esc_html( $wpjc_tile[2] ); ?></span>
			</a>
		</li>
	<?php endforeach; ?>
</ul>

<?php if ( 'employer' === $wpjc_type ) : ?>
<section class="wpjc-acc-card">
	<div class="wpjc-acc-card__head">
		<h2><?php esc_html_e( 'Your job ads', 'jobcore' ); ?></h2>
		<?php if ( $jobs ) : ?>
			<a class="wpjc-more" href="<?php echo esc_url( wpjc_account_url( 'jobs' ) ); ?>"><?php esc_html_e( 'All job ads', 'jobcore' ); ?> <?php echo wpjc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $jobs ) : ?>
		<ul class="wpjc-acc-list">
			<?php foreach ( array_slice( $jobs, 0, 3 ) as $wpjc_job ) : ?>
				<?php $wpjc_state = wpjc_job_owner_status( $wpjc_job ); ?>
				<li class="wpjc-acc-row">
					<span class="wpjc-acc-row__icon"><?php echo wpjc_icon( 'briefcase', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<div class="wpjc-acc-row__body">
						<p class="wpjc-acc-row__title"><a href="<?php echo esc_url( wpjc_account_url( 'jobs', $wpjc_job->ID ) ); ?>"><?php echo esc_html( get_the_title( $wpjc_job ) ); ?></a></p>
						<p class="wpjc-acc-row__meta">
							<?php
							$wpjc_n = wpjc_job_application_count( $wpjc_job->ID );
							/* translators: %s: number of applications */
							echo esc_html( sprintf( _n( '%s application', '%s applications', $wpjc_n, 'jobcore' ), number_format_i18n( $wpjc_n ) ) );
							?>
						</p>
					</div>
					<span class="wpjc-acc-badge wpjc-acc-badge--<?php echo esc_attr( $wpjc_state[0] ); ?>"><?php echo esc_html( $wpjc_state[1] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<p class="wpjc-acc-muted"><?php echo $companies ? esc_html__( 'You have no job ads yet. Post your first one — it takes a few minutes.', 'jobcore' ) : esc_html__( 'Start by adding your company — then post your first job ad.', 'jobcore' ); ?></p>
	<?php endif; ?>
</section>
<?php else : ?>
<section class="wpjc-acc-card">
	<div class="wpjc-acc-card__head">
		<h2><?php esc_html_e( 'Latest applications', 'jobcore' ); ?></h2>
		<?php if ( $applications ) : ?>
			<a class="wpjc-more" href="<?php echo esc_url( wpjc_account_url( 'applications' ) ); ?>"><?php esc_html_e( 'All applications', 'jobcore' ); ?> <?php echo wpjc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $applications ) : ?>
		<ul class="wpjc-acc-list">
			<?php
			foreach ( array_slice( $applications, 0, 3 ) as $wpjc_app ) {
				wpjc_template(
					'account/application-row',
					array(
						'app'     => $wpjc_app,
						'compact' => true,
					)
				);
			}
			?>
		</ul>
	<?php else : ?>
		<p class="wpjc-acc-muted"><?php esc_html_e( 'You have not applied for a job yet. When you do, you will see it here.', 'jobcore' ); ?></p>
	<?php endif; ?>
</section>
<?php endif; ?>
