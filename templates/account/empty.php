<?php
/**
 * Jobs account: empty state card.
 *
 * @package JobCore
 * @var string     $icon   Icon key.
 * @var string     $title  Title.
 * @var string     $text   Text.
 * @var array|null $button [label, URL, icon].
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wpjc-acc-card wpjc-acc-empty">
	<span class="wpjc-acc-empty__icon"><?php echo wpjc_icon( $icon, 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	<h2 class="wpjc-acc-empty__title"><?php echo esc_html( $title ); ?></h2>
	<p class="wpjc-acc-empty__text"><?php echo esc_html( $text ); ?></p>
	<?php if ( ! empty( $button ) ) : ?>
		<a class="wpjc-btn wpjc-btn--sm" href="<?php echo esc_url( $button[1] ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( $button[2] ?? 'plus', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo esc_html( $button[0] ); ?></a>
	<?php endif; ?>
</div>
