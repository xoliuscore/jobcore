<?php
/**
 * Jobs account: my job ads.
 *
 * @package JobCore
 * @var WP_Post[]  $jobs   Job ads.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'My job ads', 'jobcore' ),
		'dek'    => __( 'Job ads you posted, with their status and the number of applications.', 'jobcore' ),
		'action' => array( __( 'Post a job', 'jobcore' ), wpjc_account_url( 'jobs', 'new' ), 'plus' ),
		'notice' => $notice,
	)
);

/**
 * Before the list of job ads (e.g. remaining prepaid ads).
 *
 * @param WP_Post[] $jobs Job ads.
 */
do_action( 'wpjc_account_jobs_before', $jobs );

if ( ! $jobs ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'briefcase',
			'title'  => __( 'No job ads yet', 'jobcore' ),
			'text'   => __( 'Post your first job ad — candidates apply with their CV and you get the applications by e-mail.', 'jobcore' ),
			'button' => array( __( 'Post a job', 'jobcore' ), wpjc_account_url( 'jobs', 'new' ), 'plus' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php
		foreach ( $jobs as $wpjc_job ) :
			$wpjc_state   = wpjc_job_owner_status( $wpjc_job );
			$wpjc_company = wpjc_job_company( $wpjc_job );
			$wpjc_apps    = wpjc_job_application_count( $wpjc_job->ID );
			$wpjc_filled  = (bool) wpjc_meta( $wpjc_job, 'filled' );
			?>
			<li class="wpjc-acc-row">
				<?php echo wpjc_logo_html( $wpjc_company, 'wpjc-acc-row__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<div class="wpjc-acc-row__body">
					<p class="wpjc-acc-row__title"><a href="<?php echo esc_url( wpjc_account_url( 'jobs', $wpjc_job->ID ) ); ?>"><?php echo esc_html( get_the_title( $wpjc_job ) ); ?></a></p>
					<p class="wpjc-acc-row__meta">
						<?php if ( $wpjc_company['name'] ) : ?>
							<span><?php echo esc_html( $wpjc_company['name'] ); ?></span>
						<?php endif; ?>
						<span><?php echo wpjc_icon( 'calendar', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( wpjc_job_dates( $wpjc_job ) ); ?></span>
						<span><?php echo wpjc_icon( 'send', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
							<?php
							/* translators: %s: number of applications */
							$wpjc_apps_label = sprintf( _n( '%s application', '%s applications', $wpjc_apps, 'jobcore' ), number_format_i18n( $wpjc_apps ) );
							if ( $wpjc_apps ) {
								printf( '<a class="wpjc-acc-row__apps" href="%1$s">%2$s</a>', esc_url( wpjc_received_url( $wpjc_job->ID ) ), esc_html( $wpjc_apps_label ) );
							} else {
								echo esc_html( $wpjc_apps_label );
							}
							?>
						</span>
					</p>
				</div>
				<span class="wpjc-acc-badge wpjc-acc-badge--<?php echo esc_attr( $wpjc_state[0] ); ?>"><?php echo esc_html( $wpjc_state[1] ); ?></span>
				<div class="wpjc-acc-row__actions">
					<?php do_action( 'wpjc_account_job_actions', $wpjc_job ); ?>
					<?php if ( 'publish' === $wpjc_job->post_status ) : ?>
						<a class="wpjc-acc-iconbtn" href="<?php echo esc_url( get_permalink( $wpjc_job ) ); ?>" title="<?php esc_attr_e( 'View', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'View', 'jobcore' ); ?>"><?php echo wpjc_icon( 'eye', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endif; ?>
					<a class="wpjc-acc-iconbtn" href="<?php echo esc_url( wpjc_account_url( 'jobs', $wpjc_job->ID ) ); ?>" title="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>"><?php echo wpjc_icon( 'edit', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php if ( 'publish' === $wpjc_job->post_status ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php wpjc_account_form_fields( 'job_do' ); ?>
							<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $wpjc_job->ID ); ?>">
							<button class="wpjc-acc-iconbtn" type="submit" name="do" value="<?php echo $wpjc_filled ? 'reopen' : 'filled'; ?>" title="<?php echo $wpjc_filled ? esc_attr__( 'Open for applications again', 'jobcore' ) : esc_attr__( 'Mark as filled', 'jobcore' ); ?>" aria-label="<?php echo $wpjc_filled ? esc_attr__( 'Open for applications again', 'jobcore' ) : esc_attr__( 'Mark as filled', 'jobcore' ); ?>"><?php echo wpjc_icon( $wpjc_filled ? 'play' : 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
						</form>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wpjc-confirm="<?php esc_attr_e( 'Delete this job ad? It is removed from the board.', 'jobcore' ); ?>">
						<?php wpjc_account_form_fields( 'job_do' ); ?>
						<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $wpjc_job->ID ); ?>">
						<button class="wpjc-acc-iconbtn wpjc-acc-iconbtn--danger" type="submit" name="do" value="delete" title="<?php esc_attr_e( 'Delete', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'jobcore' ); ?>"><?php echo wpjc_icon( 'trash', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					</form>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
