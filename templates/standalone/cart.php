<?php
/**
 * WooCommerce cart link for the jobs header and drawer.
 *
 * Printed hidden; jobs.js shows it once the WooCommerce cart cookie says something is in the
 * cart and fills the count from the Store API, so cached pages stay right.
 *
 * @package JobCore
 * @var string $context 'nav' (header) or 'drawer' (mobile menu).
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_cart_url' ) ) {
	return;
}

$wpjc_context = isset( $context ) && 'drawer' === $context ? 'drawer' : 'nav';
$wpjc_attrs   = sprintf(
	'href="%1$s" data-wpjc-cart data-cart-api="%2$s" aria-label="%3$s" hidden',
	esc_url( wc_get_cart_url() ),
	esc_url( rest_url( 'wc/store/v1/cart' ) ),
	esc_attr__( 'Cart', 'jobcore' )
);
?>
<?php if ( 'drawer' === $wpjc_context ) : ?>
	<li class="wpjc-cart-li" data-wpjc-cart-wrap hidden><a <?php echo $wpjc_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>><?php echo wpjc_icon( 'cart', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Cart', 'jobcore' ); ?> <span class="wpjc-cart__num" data-wpjc-cart-count></span></a></li>
<?php else : ?>
	<a class="wpjc-nav__item wpjc-cart" <?php echo $wpjc_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
		<span class="wpjc-pop__ico">
			<?php echo wpjc_icon( 'cart', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="wpjc-badge" data-wpjc-cart-count hidden>0</span>
		</span>
		<span class="wpjc-nav__label"><?php esc_html_e( 'Cart', 'jobcore' ); ?></span>
	</a>
<?php endif; ?>
