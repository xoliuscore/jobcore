<?php
/**
 * Jobs account: sidebar with the user card and the account menu (a dropdown on phones).
 *
 * @package JobCore
 * @var string    $section      Current section.
 * @var WP_User   $user         Current user.
 * @var WP_Post[] $applications Applications.
 * @var WP_Post[] $saved        Saved jobs.
 * @var array     $alerts       Alerts.
 * @var WP_Post[] $jobs         Job ads.
 * @var WP_Term[] $companies    Companies.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_sections = wpjc_account_sections();
$wpjc_counts   = array(
	'applications' => count( $applications ),
	'saved'        => count( $saved ),
	'alerts'       => count( $alerts ),
	'jobs'         => count( $jobs ),
	'received'     => $jobs ? wpjc_received_new_count( $user->ID ) : 0,
	'companies'    => count( $companies ),
);
/**
 * Numbers shown next to the account menu items.
 *
 * @param array   $counts  Section => number.
 * @param WP_User $user    User.
 * @param string  $section Current section.
 */
$wpjc_counts = (array) apply_filters( 'wpjc_account_counts', $wpjc_counts, $user, $section );
if ( ! $jobs && 'received' !== $section ) {
	unset( $wpjc_sections['received'] );
}
/**
 * Menu items this user sees (a hidden screen still opens from its URL).
 *
 * @param array   $sections Sections.
 * @param WP_User $user     User.
 * @param string  $section  Current section.
 */
$wpjc_sections = (array) apply_filters( 'wpjc_account_menu', $wpjc_sections, $user, $section );
?>
<aside class="wpjc-acc-side" aria-label="<?php esc_attr_e( 'Jobs account', 'jobcore' ); ?>">
	<button type="button" class="wpjc-acc-toggle" data-wpjc-acc-toggle aria-expanded="false" aria-controls="wpjc-acc-menu">
		<span class="wpjc-acc-toggle__meta"><?php esc_html_e( 'Jobs account', 'jobcore' ); ?></span>
		<span class="wpjc-acc-toggle__current"><?php echo esc_html( $wpjc_sections[ $section ][0] ?? wpjc_account_title( $section ) ); ?><?php echo wpjc_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	</button>
	<div class="wpjc-acc-side__panel" id="wpjc-acc-menu">
		<div class="wpjc-acc-side__user">
			<?php echo wpjc_avatar_html( $user, 44, 'wpjc-acc-side__avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
			<span class="wpjc-acc-side__who">
				<span class="wpjc-acc-side__name"><?php echo esc_html( $user->display_name ); ?></span>
				<span class="wpjc-acc-side__email"><?php echo esc_html( $user->user_email ); ?></span>
			</span>
		</div>
		<ul class="wpjc-acc-nav">
			<?php foreach ( $wpjc_sections as $wpjc_key => $wpjc_item ) : ?>
				<li>
					<a href="<?php echo esc_url( wpjc_account_url( $wpjc_key ) ); ?>"<?php echo $wpjc_key === $section ? ' class="is-active" aria-current="page"' : ''; ?>>
						<?php echo wpjc_icon( $wpjc_item[1], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
						<span><?php echo esc_html( $wpjc_item[0] ); ?></span>
						<?php if ( ! empty( $wpjc_counts[ $wpjc_key ] ) ) : ?>
							<span class="wpjc-acc-nav__count"><?php echo esc_html( number_format_i18n( $wpjc_counts[ $wpjc_key ] ) ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
		<div class="wpjc-acc-nav__foot">
			<a class="wpjc-acc-nav__link" href="<?php echo esc_url( wpjc_profile_url() ); ?>"><?php echo wpjc_icon( 'cog', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Profile and security', 'jobcore' ); ?></a>
			<a class="wpjc-acc-nav__link wpjc-acc-nav__link--out" href="<?php echo esc_url( wp_logout_url( wpjc_home_url() ) ); ?>"><?php echo wpjc_icon( 'logout', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Log out', 'jobcore' ); ?></a>
		</div>
	</div>
</aside>
