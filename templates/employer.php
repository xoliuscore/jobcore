<?php
/**
 * Employer card (logo, name, open jobs).
 *
 * @package JobCore
 * @var array $employer From wpjc_featured_employers().
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $employer['name'] ) ) {
	return;
}
?>
<li class="wpjc-emp">
	<a class="wpjc-emp__link" href="<?php echo esc_url( $employer['url'] ); ?>">
		<span class="wpjc-emp__logo">
			<?php echo wpjc_logo_html( $employer, 'wpjc-emp__img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
		</span>
		<span class="wpjc-emp__foot">
			<span class="wpjc-emp__name"><?php echo esc_html( $employer['name'] ); ?></span>
			<span class="wpjc-emp__count" title="<?php esc_attr_e( 'Open jobs', 'jobcore' ); ?>"><?php echo esc_html( number_format_i18n( (int) $employer['count'] ) ); ?></span>
		</span>
	</a>
</li>
