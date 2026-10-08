<?php
/**
 * Jobs account: breadcrumb, title, intro, main action and the status notice.
 *
 * @package JobCore
 * @var string     $title  Screen title.
 * @var string     $dek    Intro line.
 * @var array      $crumbs [label, URL] between "Jobs account" and the title.
 * @var array|null $action [label, URL, icon].
 * @var array|null $notice [type, text].
 */

defined( 'ABSPATH' ) || exit;

$wpjc_crumbs = isset( $crumbs ) ? (array) $crumbs : array();
?>
<nav class="wpjc-crumbs wpjc-acc-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'jobcore' ); ?>">
	<a href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php echo wpjc_icon( 'home', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Jobs', 'jobcore' ); ?></a>
	<span aria-hidden="true">&rsaquo;</span>
	<?php foreach ( $wpjc_crumbs as $wpjc_crumb ) : ?>
		<a href="<?php echo esc_url( $wpjc_crumb[1] ); ?>"><?php echo esc_html( $wpjc_crumb[0] ); ?></a>
		<span aria-hidden="true">&rsaquo;</span>
	<?php endforeach; ?>
	<span aria-current="page"><?php echo esc_html( $title ); ?></span>
</nav>

<div class="wpjc-acc-head">
	<div>
		<h1 class="wpjc-acc-head__title"><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $dek ) ) : ?>
			<p class="wpjc-acc-head__dek"><?php echo wp_kses_post( $dek ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $action ) ) : ?>
		<a class="wpjc-btn wpjc-btn--sm" href="<?php echo esc_url( $action[1] ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( $action[2] ?? 'plus', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo esc_html( $action[0] ); ?></a>
	<?php endif; ?>
</div>

<?php if ( ! empty( $notice ) ) : ?>
	<p class="wpjc-notice wpjc-notice--<?php echo 'ok' === $notice[0] ? 'ok' : 'warn'; ?>" role="<?php echo 'ok' === $notice[0] ? 'status' : 'alert'; ?>"><?php echo esc_html( $notice[1] ); ?></p>
<?php endif; ?>
