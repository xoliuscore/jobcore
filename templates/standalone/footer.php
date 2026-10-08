<?php
/**
 * Standalone board footer.
 *
 * Override in a theme: wp-job-core/standalone/footer.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

$wpjc_brand = wpjc_brand();
$wpjc_cats  = get_terms(
	array(
		'taxonomy'   => 'wpjc_job_category',
		'hide_empty' => true,
		'orderby'    => 'count',
		'order'      => 'DESC',
		'number'     => 6,
	)
);
?>
<footer class="wpjc-foot">
	<div class="wpjc-wrap wpjc-foot__inner">
		<div class="wpjc-foot__col wpjc-foot__col--brand">
			<a class="wpjc-foot__brand" href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php echo wpjc_board_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
			<?php if ( wpjc_opt( 'list_intro' ) ) : ?>
				<p><?php echo esc_html( (string) wpjc_opt( 'list_intro' ) ); ?></p>
			<?php endif; ?>
			<?php if ( $wpjc_brand['email'] ) : ?>
				<p class="wpjc-foot__contact"><?php echo wpjc_icon( 'email', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_url( 'mailto:' . antispambot( $wpjc_brand['email'] ) ); ?>"><?php echo esc_html( antispambot( $wpjc_brand['email'] ) ); ?></a></p>
			<?php endif; ?>
			<?php if ( $wpjc_brand['phone'] ) : ?>
				<p class="wpjc-foot__contact"><?php echo wpjc_icon( 'phone', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $wpjc_brand['phone'] ) ); ?>"><?php echo esc_html( $wpjc_brand['phone'] ); ?></a></p>
			<?php endif; ?>
		</div>

		<?php if ( $wpjc_cats && ! is_wp_error( $wpjc_cats ) ) : ?>
			<div class="wpjc-foot__col">
				<p class="wpjc-foot__title"><?php esc_html_e( 'Categories', 'jobcore' ); ?></p>
				<ul>
					<?php foreach ( $wpjc_cats as $wpjc_cat ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $wpjc_cat ) ); ?>"><?php echo esc_html( $wpjc_cat->name ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<div class="wpjc-foot__col">
			<p class="wpjc-foot__title"><?php esc_html_e( 'Job seekers', 'jobcore' ); ?></p>
			<ul>
				<li><a href="<?php echo esc_url( wpjc_view_url( 'all' ) ); ?>"><?php esc_html_e( 'All jobs', 'jobcore' ); ?></a></li>
				<li><a href="<?php echo esc_url( wpjc_view_url( 'employers' ) ); ?>"><?php esc_html_e( 'Employers', 'jobcore' ); ?></a></li>
			</ul>
		</div>

		<div class="wpjc-foot__col">
			<p class="wpjc-foot__title"><?php esc_html_e( 'Employers', 'jobcore' ); ?></p>
			<ul>
				<?php if ( $wpjc_brand['post_url'] ) : ?>
					<li><a href="<?php echo esc_url( $wpjc_brand['post_url'] ); ?>"><?php esc_html_e( 'Post a job', 'jobcore' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $wpjc_brand['employer_url'] ) : ?>
					<li><a href="<?php echo esc_url( $wpjc_brand['employer_url'] ); ?>"><?php esc_html_e( 'Add company', 'jobcore' ); ?></a></li>
				<?php endif; ?>
				<?php
				/**
				 * More links in the footer's Employers column: [label => URL].
				 *
				 * @param array $links Links.
				 */
				foreach ( (array) apply_filters( 'wpjc_footer_employer_links', array() ) as $wpjc_label => $wpjc_link ) :
					?>
					<li><a href="<?php echo esc_url( $wpjc_link ); ?>"><?php echo esc_html( $wpjc_label ); ?></a></li>
				<?php endforeach; ?>
				<?php if ( get_privacy_policy_url() ) : ?>
					<li><a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Privacy policy', 'jobcore' ); ?></a></li>
				<?php endif; ?>
			</ul>
			<?php if ( $wpjc_brand['social'] ) : ?>
				<p class="wpjc-foot__social">
					<?php foreach ( $wpjc_brand['social'] as $wpjc_net => $wpjc_url ) : ?>
						<a class="wpjc-strip__net wpjc-strip__net--<?php echo esc_attr( $wpjc_net ); ?>" href="<?php echo esc_url( $wpjc_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( wpjc_social_label( $wpjc_net ) ); ?>"><?php echo wpjc_icon( $wpjc_net, 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endforeach; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
	<div class="wpjc-wrap wpjc-foot__bottom">
		<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php echo esc_html( $wpjc_brand['name'] ); ?></span>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
	</div>
</footer>
<?php
if ( 'news' === wpjc_header_style() ) {
	news_part( 'global/dialogs' );
}
wp_footer();
?>
</body>
</html>
