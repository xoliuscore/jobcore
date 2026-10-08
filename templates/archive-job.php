<?php
/**
 * Job archive, employer / type / category pages: the board filtered by the current term.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

$wpjc_atts = '';
$wpjc_term = get_queried_object();
if ( $wpjc_term instanceof WP_Term ) {
	if ( 'wpjc_employer' === $wpjc_term->taxonomy ) {
		$wpjc_atts = sprintf( ' employer="%d"', $wpjc_term->term_id );
	} elseif ( 'wpjc_job_category' === $wpjc_term->taxonomy ) {
		$wpjc_atts = sprintf( ' category="%d"', $wpjc_term->term_id );
	} elseif ( 'wpjc_job_type' === $wpjc_term->taxonomy ) {
		$wpjc_atts = sprintf( ' type="%d"', $wpjc_term->term_id );
	}
	$wpjc_atts .= sprintf( ' title="%s"', esc_attr( $wpjc_term->name ) );
}

wpjc_get_header();
?>
<main id="main" class="wpjc-archive-page">
	<?php echo do_shortcode( '[wpjc_jobs' . $wpjc_atts . ']' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- shortcode output escapes itself. ?>
</main>
<?php
wpjc_get_footer();
