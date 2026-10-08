<?php
/**
 * Jobs account: applications received for my job ads.
 *
 * @package JobCore
 * @var WP_User    $user   Current user.
 * @var WP_Post[]  $jobs   Job ads.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
$wpjc_f_job    = isset( $_GET['job'] ) ? absint( $_GET['job'] ) : 0;
$wpjc_f_status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
// phpcs:enable
$wpjc_statuses = wpjc_app_statuses();
$wpjc_f_status = isset( $wpjc_statuses[ $wpjc_f_status ] ) ? $wpjc_f_status : '';
$wpjc_all      = wpjc_received_applications( $user->ID, $wpjc_f_job );
$wpjc_counts   = array_fill_keys( array_keys( $wpjc_statuses ), 0 );
foreach ( $wpjc_all as $wpjc_app ) {
	++$wpjc_counts[ wpjc_app_status( $wpjc_app ) ];
}
$wpjc_list = '' === $wpjc_f_status ? $wpjc_all : array_filter(
	$wpjc_all,
	static function ( $app ) use ( $wpjc_f_status ) {
		return wpjc_app_status( $app ) === $wpjc_f_status;
	}
);
$wpjc_keep = (bool) wpjc_opt( 'app_files' );

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'Applications received', 'jobcore' ),
		'dek'    => __( 'Candidates who applied for your job ads. The status you set here is shown in their account.', 'jobcore' ),
		'notice' => $notice,
	)
);

if ( ! $jobs ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'inbox',
			'title'  => __( 'No job ads yet', 'jobcore' ),
			'text'   => __( 'Post a job ad: every application for it is listed here, with the CV and cover letter.', 'jobcore' ),
			'button' => array( __( 'Post a job', 'jobcore' ), wpjc_account_url( 'jobs', 'new' ), 'plus' ),
		)
	);
	return;
}
?>
<div class="wpjc-inbox__bar">
	<nav class="wpjc-inbox__tabs" aria-label="<?php esc_attr_e( 'Filter by status', 'jobcore' ); ?>">
		<a href="<?php echo esc_url( wpjc_received_url( $wpjc_f_job ) ); ?>"<?php echo '' === $wpjc_f_status ? ' class="is-active" aria-current="true"' : ''; ?>><?php esc_html_e( 'All', 'jobcore' ); ?> <span><?php echo esc_html( number_format_i18n( count( $wpjc_all ) ) ); ?></span></a>
		<?php foreach ( $wpjc_statuses as $wpjc_key => $wpjc_label ) : ?>
			<a href="<?php echo esc_url( wpjc_received_url( $wpjc_f_job, $wpjc_key ) ); ?>"<?php echo $wpjc_key === $wpjc_f_status ? ' class="is-active" aria-current="true"' : ''; ?>><?php echo esc_html( $wpjc_label ); ?> <span><?php echo esc_html( number_format_i18n( $wpjc_counts[ $wpjc_key ] ) ); ?></span></a>
		<?php endforeach; ?>
	</nav>
	<form class="wpjc-inbox__job" method="get" action="<?php echo esc_url( wpjc_account_url( 'received' ) ); ?>">
		<?php if ( ! wpjc_pretty_urls() ) : ?>
			<input type="hidden" name="wpjc_account" value="received">
			<input type="hidden" name="page_id" value="<?php echo esc_attr( (string) (int) get_option( 'wpjc_jobs_page_id' ) ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $wpjc_f_status ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $wpjc_f_status ); ?>">
		<?php endif; ?>
		<label class="screen-reader-text" for="wpjc-inbox-job"><?php esc_html_e( 'Job ad', 'jobcore' ); ?></label>
		<select id="wpjc-inbox-job" name="job" data-wpjc-autosubmit>
			<option value="0"><?php esc_html_e( 'All job ads', 'jobcore' ); ?></option>
			<?php foreach ( $jobs as $wpjc_job ) : ?>
				<option value="<?php echo esc_attr( (string) $wpjc_job->ID ); ?>" <?php selected( $wpjc_f_job, $wpjc_job->ID ); ?>><?php echo esc_html( get_the_title( $wpjc_job ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<noscript><button class="wpjc-btn wpjc-btn--sm wpjc-btn--ghost" type="submit"><?php esc_html_e( 'Show', 'jobcore' ); ?></button></noscript>
	</form>
</div>

<?php
if ( ! $wpjc_list ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'  => 'inbox',
			'title' => $wpjc_all ? __( 'No applications with this status', 'jobcore' ) : __( 'No applications yet', 'jobcore' ),
			'text'  => $wpjc_all ? __( 'Choose another status above.', 'jobcore' ) : __( 'When candidates apply for your job ads, you will find them here.', 'jobcore' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php
		foreach ( $wpjc_list as $wpjc_app ) :
			$wpjc_status = wpjc_app_status( $wpjc_app );
			$wpjc_first  = (string) get_post_meta( $wpjc_app->ID, '_wpjc_app_first', true );
			$wpjc_name   = trim( $wpjc_first . ' ' . get_post_meta( $wpjc_app->ID, '_wpjc_app_last', true ) );
			$wpjc_email  = (string) get_post_meta( $wpjc_app->ID, '_wpjc_app_email', true );
			$wpjc_phone  = (string) get_post_meta( $wpjc_app->ID, '_wpjc_app_phone', true );
			$wpjc_stored = wpjc_app_stored_files( $wpjc_app->ID );
			$wpjc_names  = array_filter( (array) get_post_meta( $wpjc_app->ID, '_wpjc_app_files', true ) );
			$wpjc_until  = (int) get_post_meta( $wpjc_app->ID, '_wpjc_app_files_until', true );
			$wpjc_title  = get_the_title( (int) $wpjc_app->post_parent );
			?>
			<li class="wpjc-acc-row wpjc-inbox__row is-<?php echo esc_attr( $wpjc_status ); ?>" id="wpjc-app-<?php echo esc_attr( (string) $wpjc_app->ID ); ?>">
				<span class="wpjc-acc-row__icon wpjc-inbox__avatar" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( '' !== $wpjc_name ? $wpjc_name : '?', 0, 1 ) ) ); ?></span>
				<div class="wpjc-acc-row__body">
					<p class="wpjc-acc-row__title"><?php echo esc_html( $wpjc_name ); ?></p>
					<p class="wpjc-acc-row__meta">
						<span><?php echo wpjc_icon( 'briefcase', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_title ? $wpjc_title : (string) get_post_meta( $wpjc_app->ID, '_wpjc_app_job', true ) ); ?></span>
						<span><?php echo wpjc_icon( 'clock', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( wp_date( 'd.m.Y, H:i', (int) get_post_time( 'U', true, $wpjc_app ) ) ); ?></span>
						<?php if ( $wpjc_stored ) : ?>
							<span><?php echo wpjc_icon( 'file', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<?php
								/* translators: %s: number of files */
								echo esc_html( sprintf( _n( '%s file', '%s files', count( $wpjc_stored ), 'jobcore' ), number_format_i18n( count( $wpjc_stored ) ) ) );
								?>
							</span>
						<?php endif; ?>
					</p>
					<details class="wpjc-acc-row__more wpjc-inbox__more">
						<summary><?php esc_html_e( 'Open application', 'jobcore' ); ?></summary>
						<ul class="wpjc-inbox__contact">
							<li><?php echo wpjc_icon( 'email', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_url( 'mailto:' . $wpjc_email ); ?>"><?php echo esc_html( $wpjc_email ); ?></a></li>
							<?php if ( '' !== $wpjc_phone ) : ?>
								<li><?php echo wpjc_icon( 'phone', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $wpjc_phone ) ); ?>"><?php echo esc_html( $wpjc_phone ); ?></a></li>
							<?php endif; ?>
						</ul>
						<div class="wpjc-acc-row__letter"><?php echo wp_kses_post( wpautop( esc_html( $wpjc_app->post_content ) ) ); ?></div>
						<?php
						/**
						 * After the cover letter of a received application (add-ons link a resume, scores …).
						 *
						 * @param WP_Post $app Application.
						 */
						do_action( 'wpjc_received_application_after_letter', $wpjc_app );
						?>
						<?php if ( $wpjc_stored ) : ?>
							<ul class="wpjc-inbox__files">
								<?php foreach ( $wpjc_stored as $wpjc_i => $wpjc_file ) : ?>
									<li><a href="<?php echo esc_url( wpjc_app_file_url( $wpjc_app->ID, $wpjc_i ) ); ?>"><?php echo wpjc_icon( 'download', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_file['name'] ); ?> <small><?php echo esc_html( size_format( (int) $wpjc_file['size'] ) ); ?></small></a></li>
								<?php endforeach; ?>
							</ul>
							<?php if ( $wpjc_until ) : ?>
								<p class="wpjc-inbox__note">
									<?php
									/* translators: %s: date */
									echo esc_html( sprintf( __( 'Files are kept until %s.', 'jobcore' ), wp_date( 'd.m.Y', $wpjc_until ) ) );
									?>
								</p>
							<?php endif; ?>
						<?php elseif ( $wpjc_names ) : ?>
							<p class="wpjc-acc-row__files"><?php echo wpjc_icon( 'file', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( implode( ', ', $wpjc_names ) ); ?></p>
							<p class="wpjc-inbox__note"><?php echo esc_html( $wpjc_keep ? __( 'The files were deleted from the site; you received them by e-mail.', 'jobcore' ) : __( 'The files were sent to you by e-mail.', 'jobcore' ) ); ?></p>
						<?php endif; ?>
						<form class="wpjc-inbox__status" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wpjc_account_form_fields( 'app_status' ); ?>
							<input type="hidden" name="app_id" value="<?php echo esc_attr( (string) $wpjc_app->ID ); ?>">
							<input type="hidden" name="filter_job" value="<?php echo esc_attr( (string) $wpjc_f_job ); ?>">
							<input type="hidden" name="filter_status" value="<?php echo esc_attr( $wpjc_f_status ); ?>">
							<span><?php esc_html_e( 'Status (visible to the candidate):', 'jobcore' ); ?></span>
							<?php foreach ( $wpjc_statuses as $wpjc_key => $wpjc_label ) : ?>
								<button type="submit" name="status" value="<?php echo esc_attr( $wpjc_key ); ?>" class="wpjc-inbox__pick wpjc-inbox__pick--<?php echo esc_attr( $wpjc_key ); ?>" aria-pressed="<?php echo $wpjc_key === $wpjc_status ? 'true' : 'false'; ?>"><?php echo esc_html( $wpjc_label ); ?></button>
							<?php endforeach; ?>
						</form>
					</details>
				</div>
				<span class="wpjc-acc-badge wpjc-acc-badge--app-<?php echo esc_attr( $wpjc_status ); ?>"><?php echo esc_html( $wpjc_statuses[ $wpjc_status ] ); ?></span>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
