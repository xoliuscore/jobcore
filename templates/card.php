<?php
/**
 * Job card: company, save, title, location, dates, listing progress, apply, tier.
 *
 * Override in a theme: wp-job-core/card.php.
 *
 * @package JobCore
 * @var WP_Post $post
 */

defined( 'ABSPATH' ) || exit;

$wpjc_post = get_post( $post ?? null );
if ( ! $wpjc_post ) {
	return;
}
$wpjc_company  = wpjc_job_company( $wpjc_post );
$wpjc_location = wpjc_meta( $wpjc_post, 'location' );
$wpjc_remote   = (bool) wpjc_meta( $wpjc_post, 'remote' );
$wpjc_tier     = wpjc_job_tier( $wpjc_post );
$wpjc_tier_def = wpjc_tiers()[ $wpjc_tier ];
$wpjc_progress = wpjc_job_progress( $wpjc_post );
$wpjc_url      = get_permalink( $wpjc_post );
$wpjc_title    = get_the_title( $wpjc_post );
$wpjc_where    = $wpjc_location ? $wpjc_location : ( $wpjc_remote ? __( 'Remote', 'jobcore' ) : '' );
$wpjc_faved    = is_user_logged_in() && in_array( (int) $wpjc_post->ID, wpjc_favs(), true );
?>
<li class="wpjc-job wpjc-job--<?php echo esc_attr( $wpjc_tier ); ?>" style="--wpjc-tier:<?php echo esc_attr( $wpjc_tier_def['color'] ); ?>">
	<div class="wpjc-job__top">
		<span class="wpjc-job__company"><?php echo esc_html( $wpjc_company['name'] ); ?></span>
		<button type="button" class="wpjc-fav" aria-pressed="<?php echo $wpjc_faved ? 'true' : 'false'; ?>"
			aria-label="<?php echo $wpjc_faved ? esc_attr__( 'Remove bookmark', 'jobcore' ) : esc_attr__( 'Bookmark job', 'jobcore' ); ?>"
			data-wpjc-fav="<?php echo esc_attr( (string) $wpjc_post->ID ); ?>"
			data-title="<?php echo esc_attr( $wpjc_title ); ?>"
			data-company="<?php echo esc_attr( $wpjc_company['name'] ); ?>"
			data-url="<?php echo esc_url( $wpjc_url ); ?>"
			data-logo="<?php echo esc_url( $wpjc_company['logo'] ); ?>">
			<?php echo wpjc_icon( 'bookmark', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		</button>
	</div>
	<div class="wpjc-job__main">
		<div class="wpjc-job__text">
			<h3 class="wpjc-job__title"><a class="wpjc-job__link" href="<?php echo esc_url( $wpjc_url ); ?>"><?php echo esc_html( $wpjc_title ); ?></a></h3>
			<?php if ( $wpjc_where ) : ?>
				<p class="wpjc-job__meta"><?php echo wpjc_icon( 'pin', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $wpjc_where ); ?></span></p>
			<?php endif; ?>
			<p class="wpjc-job__meta"><?php echo wpjc_icon( 'calendar', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( wpjc_job_dates( $wpjc_post ) ); ?></span></p>
		</div>
		<?php if ( $wpjc_company['logo'] ) : ?>
			<span class="wpjc-job__logo"><img src="<?php echo esc_url( $wpjc_company['logo'] ); ?>" alt="" loading="lazy" decoding="async"></span>
		<?php endif; ?>
	</div>
	<?php if ( null !== $wpjc_progress ) : ?>
		<span class="wpjc-job__bar" role="presentation"><span style="width:<?php echo esc_attr( (string) $wpjc_progress ); ?>%"></span></span>
	<?php endif; ?>
	<div class="wpjc-job__foot">
		<?php if ( wpjc_can_apply( $wpjc_post ) ) : ?>
			<a class="wpjc-job__apply" href="<?php echo esc_url( $wpjc_url . '#wpjc-apply' ); ?>"><?php esc_html_e( 'Apply', 'jobcore' ); ?></a>
		<?php else : ?>
			<span></span>
		<?php endif; ?>
		<span class="wpjc-job__tier" title="<?php echo esc_attr( $wpjc_tier_def['label'] ); ?>"><?php echo esc_html( $wpjc_tier_def['short'] ); ?></span>
	</div>
</li>
