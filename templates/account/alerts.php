<?php
/**
 * Jobs account: job alerts.
 *
 * @package JobCore
 * @var array      $alerts Alerts keyed by ID.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_on    = count(
	array_filter(
		$alerts,
		static function ( $alert ) {
			return $alert['active'];
		}
	)
);
$wpjc_freqs = wpjc_alert_frequencies();

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'Job alerts', 'jobcore' ),
		'dek'    => __( 'Save a search and we e-mail you new jobs that match it.', 'jobcore' ),
		'action' => array( __( 'New alert', 'jobcore' ), wpjc_account_url( 'alerts', 'new' ), 'plus' ),
		'notice' => $notice,
	)
);
?>
<ul class="wpjc-acc-stats wpjc-acc-stats--3">
	<li><div class="wpjc-acc-stat wpjc-acc-stat--row"><span class="wpjc-acc-stat__icon"><?php echo wpjc_icon( 'bell', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><span class="wpjc-acc-stat__label"><?php esc_html_e( 'All alerts', 'jobcore' ); ?></span><span class="wpjc-acc-stat__num"><?php echo esc_html( number_format_i18n( count( $alerts ) ) ); ?></span></span></div></li>
	<li><div class="wpjc-acc-stat wpjc-acc-stat--row"><span class="wpjc-acc-stat__icon wpjc-acc-stat__icon--ok"><?php echo wpjc_icon( 'bell', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><span class="wpjc-acc-stat__label"><?php esc_html_e( 'Active', 'jobcore' ); ?></span><span class="wpjc-acc-stat__num"><?php echo esc_html( number_format_i18n( $wpjc_on ) ); ?></span></span></div></li>
	<li><div class="wpjc-acc-stat wpjc-acc-stat--row"><span class="wpjc-acc-stat__icon wpjc-acc-stat__icon--muted"><?php echo wpjc_icon( 'bell-off', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><span><span class="wpjc-acc-stat__label"><?php esc_html_e( 'Paused', 'jobcore' ); ?></span><span class="wpjc-acc-stat__num"><?php echo esc_html( number_format_i18n( count( $alerts ) - $wpjc_on ) ); ?></span></span></div></li>
</ul>

<?php
if ( ! $alerts ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'bell-off',
			'title'  => __( 'You have no job alerts', 'jobcore' ),
			'text'   => __( 'Create your first alert to hear about new jobs as soon as they are posted.', 'jobcore' ),
			'button' => array( __( 'Create an alert', 'jobcore' ), wpjc_account_url( 'alerts', 'new' ), 'plus' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php foreach ( $alerts as $wpjc_id => $wpjc_alert ) : ?>
			<li class="wpjc-acc-row<?php echo $wpjc_alert['active'] ? '' : ' is-off'; ?>">
				<span class="wpjc-acc-row__icon"><?php echo wpjc_icon( $wpjc_alert['active'] ? 'bell' : 'bell-off', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
				<div class="wpjc-acc-row__body">
					<p class="wpjc-acc-row__title"><a href="<?php echo esc_url( wpjc_account_url( 'alerts', $wpjc_id ) ); ?>"><?php echo esc_html( wpjc_alert_name( $wpjc_alert ) ); ?></a></p>
					<p class="wpjc-acc-chips">
						<?php foreach ( wpjc_alert_criteria( $wpjc_alert ) as $wpjc_chip ) : ?>
							<span class="wpjc-acc-chip"><?php echo esc_html( $wpjc_chip ); ?></span>
						<?php endforeach; ?>
						<span class="wpjc-acc-chip wpjc-acc-chip--freq"><?php echo wpjc_icon( 'clock', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_freqs[ $wpjc_alert['frequency'] ] ?? '' ); ?></span>
					</p>
				</div>
				<div class="wpjc-acc-row__actions">
					<a class="wpjc-acc-iconbtn" href="<?php echo esc_url( wpjc_account_url( 'alerts', $wpjc_id ) ); ?>" title="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>"><?php echo wpjc_icon( 'edit', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wpjc_account_form_fields( 'alert_do' ); ?>
						<input type="hidden" name="alert_id" value="<?php echo esc_attr( $wpjc_id ); ?>">
						<button class="wpjc-acc-iconbtn" type="submit" name="do" value="<?php echo $wpjc_alert['active'] ? 'pause' : 'resume'; ?>" title="<?php echo $wpjc_alert['active'] ? esc_attr__( 'Pause', 'jobcore' ) : esc_attr__( 'Switch on', 'jobcore' ); ?>" aria-label="<?php echo $wpjc_alert['active'] ? esc_attr__( 'Pause', 'jobcore' ) : esc_attr__( 'Switch on', 'jobcore' ); ?>"><?php echo wpjc_icon( $wpjc_alert['active'] ? 'pause' : 'play', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-wpjc-confirm="<?php esc_attr_e( 'Delete this job alert?', 'jobcore' ); ?>">
						<?php wpjc_account_form_fields( 'alert_do' ); ?>
						<input type="hidden" name="alert_id" value="<?php echo esc_attr( $wpjc_id ); ?>">
						<button class="wpjc-acc-iconbtn wpjc-acc-iconbtn--danger" type="submit" name="do" value="delete" title="<?php esc_attr_e( 'Delete', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'Delete', 'jobcore' ); ?>"><?php echo wpjc_icon( 'trash', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					</form>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
