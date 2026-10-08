<?php
/**
 * Jobs account: my applications.
 *
 * @package JobCore
 * @var WP_Post[]  $applications Applications.
 * @var array|null $notice       Notice.
 */

defined( 'ABSPATH' ) || exit;

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'My applications', 'jobcore' ),
		'dek'    => __( 'Every job you applied for from this account. You can see whether the employer has shortlisted you or not. They may also reply by e-mail or phone.', 'jobcore' ),
		'notice' => $notice,
	)
);

if ( ! $applications ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'send',
			'title'  => __( 'No applications yet', 'jobcore' ),
			'text'   => __( 'When you apply for a job, you will find an overview of everything you sent here.', 'jobcore' ),
			'button' => array( __( 'Browse jobs', 'jobcore' ), wpjc_view_url( 'all' ), 'search' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php
		foreach ( $applications as $wpjc_app ) {
			wpjc_template( 'account/application-row', array( 'app' => $wpjc_app ) );
		}
		?>
	</ul>
</div>
