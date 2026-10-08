<?php
/**
 * Jobs account: a screen for the other account type — switch (soft) or explain (strict).
 *
 * Override in a theme: wp-job-core/account/switch.php.
 *
 * @package JobCore
 * @var string     $section Section the user opened.
 * @var WP_User    $user    Current user.
 * @var array|null $notice  Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_types  = wpjc_account_types();
$wpjc_want   = wpjc_section_audience( $section );
$wpjc_strict = 'strict' === wpjc_accounts_mode();
$wpjc_hiring = 'employer' === $wpjc_want;

wpjc_template(
	'account/head',
	array(
		'title'  => wpjc_account_title( $section, wpjc_account_item() ),
		'notice' => $notice,
	)
);
?>
<div class="wpjc-acc-card wpjc-acc-empty">
	<span class="wpjc-acc-empty__icon"><?php echo wpjc_icon( $wpjc_types[ $wpjc_want ][2] ?? 'user', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	<?php if ( $wpjc_strict ) : ?>
		<h2 class="wpjc-acc-empty__title"><?php echo $wpjc_hiring ? esc_html__( 'This is for employer accounts', 'jobcore' ) : esc_html__( 'This is for job seeker accounts', 'jobcore' ); ?></h2>
		<p class="wpjc-acc-empty__text">
			<?php
			echo esc_html(
				$wpjc_hiring
					? __( 'Your account is set up for job seeking. To hire, sign up again with your company e-mail, or ask the board team to change your account.', 'jobcore' )
					: __( 'Your account is set up for hiring. To look for a job, use a personal account, or ask the board team to change this one.', 'jobcore' )
			);
			?>
		</p>
		<?php $wpjc_mail = sanitize_email( (string) wpjc_opt( 'contact_email' ) ); ?>
		<?php if ( $wpjc_mail ) : ?>
			<a class="wpjc-btn wpjc-btn--sm wpjc-btn--ghost" href="<?php echo esc_url( 'mailto:' . $wpjc_mail ); ?>"><?php echo wpjc_icon( 'email', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Contact the board team', 'jobcore' ); ?></a>
		<?php endif; ?>
	<?php else : ?>
		<h2 class="wpjc-acc-empty__title"><?php echo $wpjc_hiring ? esc_html__( 'Hiring too?', 'jobcore' ) : esc_html__( 'Looking for a job?', 'jobcore' ); ?></h2>
		<p class="wpjc-acc-empty__text">
			<?php
			echo esc_html(
				$wpjc_hiring
					? __( 'Your account is set up for job seeking. Switch it to hiring to add a company and post job ads — your applications stay where they are.', 'jobcore' )
					: __( 'Your account is set up for hiring. Switch it to job seeking to apply, save jobs and get job alerts — your companies and job ads stay where they are.', 'jobcore' )
			);
			?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wpjc_account_form_fields( 'account_type' ); ?>
			<input type="hidden" name="back" value="<?php echo esc_attr( $section ); ?>">
			<button class="wpjc-btn wpjc-btn--sm" type="submit" name="type" value="<?php echo esc_attr( $wpjc_want ); ?>"><?php echo $wpjc_hiring ? esc_html__( 'Switch to hiring', 'jobcore' ) : esc_html__( 'Switch to job seeking', 'jobcore' ); ?></button>
		</form>
	<?php endif; ?>
</div>
