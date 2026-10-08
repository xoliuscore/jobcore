<?php
/**
 * Jobs account: bookmarked jobs on this account.
 *
 * @package JobCore
 * @var WP_Post[]  $saved  Saved jobs.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'Bookmarks', 'jobcore' ),
		'dek'    => __( 'Jobs you bookmarked. They stay on this account, on any device you sign in from.', 'jobcore' ),
		'notice' => $notice,
	)
);

if ( ! $saved ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'bookmark',
			'title'  => __( 'No bookmarks yet', 'jobcore' ),
			'text'   => __( 'Tap the bookmark on a job to keep it here. You can come back to it from any device.', 'jobcore' ),
			'button' => array( __( 'Browse jobs', 'jobcore' ), wpjc_view_url( 'all' ), 'search' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php
		foreach ( $saved as $wpjc_job ) :
			$wpjc_live    = 'publish' === $wpjc_job->post_status;
			$wpjc_company = wpjc_job_company( $wpjc_job );
			$wpjc_title   = get_the_title( $wpjc_job );
			$wpjc_url     = $wpjc_live ? get_permalink( $wpjc_job ) : '';
			?>
			<li class="wpjc-acc-row" data-wpjc-saved="<?php echo esc_attr( (string) $wpjc_job->ID ); ?>">
				<?php echo wpjc_logo_html( $wpjc_company, 'wpjc-acc-row__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<div class="wpjc-acc-row__body">
					<p class="wpjc-acc-row__title">
						<?php if ( $wpjc_url ) : ?>
							<a href="<?php echo esc_url( $wpjc_url ); ?>"><?php echo esc_html( $wpjc_title ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $wpjc_title ); ?>
						<?php endif; ?>
					</p>
					<p class="wpjc-acc-row__meta">
						<?php if ( $wpjc_company['name'] ) : ?>
							<span><?php echo esc_html( $wpjc_company['name'] ); ?></span>
						<?php endif; ?>
						<?php if ( $wpjc_live ) : ?>
							<span><?php echo wpjc_icon( 'calendar', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( wpjc_job_dates( $wpjc_job ) ); ?></span>
						<?php else : ?>
							<span><?php esc_html_e( 'This job ad is no longer listed.', 'jobcore' ); ?></span>
						<?php endif; ?>
					</p>
				</div>
				<div class="wpjc-acc-row__actions">
					<button type="button" class="wpjc-acc-iconbtn" aria-pressed="true"
						aria-label="<?php esc_attr_e( 'Remove bookmark', 'jobcore' ); ?>"
						data-wpjc-fav="<?php echo esc_attr( (string) $wpjc_job->ID ); ?>"
						data-title="<?php echo esc_attr( $wpjc_title ); ?>"
						data-company="<?php echo esc_attr( $wpjc_company['name'] ); ?>"
						data-url="<?php echo esc_url( $wpjc_url ); ?>"
						data-logo="<?php echo esc_url( $wpjc_company['logo'] ); ?>">
						<?php echo wpjc_icon( 'bookmark', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</button>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
