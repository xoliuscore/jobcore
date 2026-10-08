<?php
/**
 * Jobs account: one application.
 *
 * @package JobCore
 * @var WP_Post $app     Application.
 * @var bool    $compact Without the cover letter and files.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_job     = get_post( (int) $app->post_parent );
$wpjc_live    = $wpjc_job && 'publish' === $wpjc_job->post_status;
$wpjc_company = $wpjc_job ? wpjc_job_company( $wpjc_job ) : array(
	'name' => '',
	'logo' => '',
);
$wpjc_title   = $wpjc_job ? get_the_title( $wpjc_job ) : (string) get_post_meta( $app->ID, '_wpjc_app_job', true );
$wpjc_files   = array_filter( (array) get_post_meta( $app->ID, '_wpjc_app_files', true ) );
$wpjc_state   = wpjc_app_candidate_status( $app, $wpjc_job );
?>
<li class="wpjc-acc-row">
	<?php echo wpjc_logo_html( $wpjc_company, 'wpjc-acc-row__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
	<div class="wpjc-acc-row__body">
		<p class="wpjc-acc-row__title">
			<?php if ( $wpjc_live ) : ?>
				<a href="<?php echo esc_url( get_permalink( $wpjc_job ) ); ?>"><?php echo esc_html( $wpjc_title ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $wpjc_title ); ?>
			<?php endif; ?>
		</p>
		<p class="wpjc-acc-row__meta">
			<?php if ( $wpjc_company['name'] ) : ?>
				<span><?php echo esc_html( $wpjc_company['name'] ); ?></span>
			<?php endif; ?>
			<span>
				<?php
				/* translators: %s: date */
				echo esc_html( sprintf( __( 'Applied %s', 'jobcore' ), wp_date( 'd.m.Y, H:i', (int) get_post_time( 'U', true, $app ) ) ) );
				?>
			</span>
		</p>
		<?php if ( empty( $compact ) ) : ?>
			<details class="wpjc-acc-row__more">
				<summary><?php esc_html_e( 'Your cover letter', 'jobcore' ); ?></summary>
				<div class="wpjc-acc-row__letter"><?php echo wp_kses_post( wpautop( esc_html( $app->post_content ) ) ); ?></div>
				<?php if ( $wpjc_files ) : ?>
					<p class="wpjc-acc-row__files"><?php echo wpjc_icon( 'file', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( implode( ', ', $wpjc_files ) ); ?></p>
				<?php endif; ?>
			</details>
		<?php endif; ?>
	</div>
	<span class="wpjc-acc-badge wpjc-acc-badge--<?php echo esc_attr( $wpjc_state[0] ); ?>"><?php echo esc_html( $wpjc_state[1] ); ?></span>
</li>
