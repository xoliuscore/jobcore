<?php
/**
 * Jobs account: pick the account type (job seeking or hiring).
 *
 * Override in a theme: wp-job-core/account/choose.php.
 *
 * @package JobCore
 * @var string     $section Section the user opened.
 * @var WP_User    $user    Current user.
 * @var array|null $notice  Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_first = trim( (string) $user->first_name );
$wpjc_want  = wpjc_section_audience( $section );
wpjc_template(
	'account/head',
	array(
		/* translators: %s: first name */
		'title'  => '' !== $wpjc_first ? sprintf( __( 'Welcome, %s', 'jobcore' ), $wpjc_first ) : __( 'Welcome', 'jobcore' ),
		'dek'    => __( 'What brings you here? We show you the tools that fit.', 'jobcore' ),
		'notice' => $notice,
	)
);
?>
<form class="wpjc-acc-choose" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wpjc_account_form_fields( 'account_type' ); ?>
	<input type="hidden" name="back" value="<?php echo esc_attr( $section ); ?>">
	<?php foreach ( wpjc_account_types() as $wpjc_key => $wpjc_type ) : ?>
		<button class="wpjc-acc-card wpjc-acc-choose__opt<?php echo $wpjc_want === $wpjc_key ? ' is-suggested' : ''; ?>" type="submit" name="type" value="<?php echo esc_attr( $wpjc_key ); ?>">
			<span class="wpjc-acc-choose__icon"><?php echo wpjc_icon( $wpjc_type[2], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<span class="wpjc-acc-choose__title"><?php echo esc_html( $wpjc_type[0] ); ?></span>
			<span class="wpjc-acc-choose__text"><?php echo esc_html( $wpjc_type[1] ); ?></span>
			<span class="wpjc-acc-choose__go"><?php esc_html_e( 'Continue', 'jobcore' ); ?> <?php echo wpjc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		</button>
	<?php endforeach; ?>
</form>
<p class="wpjc-acc-muted wpjc-acc-choose__note">
	<?php
	echo esc_html(
		'strict' === wpjc_accounts_mode()
			? __( 'On this board, job seekers and employers use separate accounts. Choose carefully — only the board team can change it later.', 'jobcore' )
			: __( 'You can switch later in your jobs account.', 'jobcore' )
	);
	?>
</p>
