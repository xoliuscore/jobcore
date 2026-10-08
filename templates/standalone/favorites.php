<?php
/**
 * Bookmarks menu (filled by jobs.js from the account, or this device for guests).
 *
 * @package JobCore
 * @var string $btn_class Extra button classes.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_btn_class = isset( $btn_class ) ? (string) $btn_class : 'wpjc-nav__item';
?>
<div class="wpjc-pop wpjc-pop--fav">
	<button type="button" class="<?php echo esc_attr( $wpjc_btn_class ); ?> wpjc-pop__btn" aria-expanded="false" aria-controls="wpjc-fav-pop" aria-label="<?php esc_attr_e( 'Bookmarks', 'jobcore' ); ?>">
		<span class="wpjc-pop__ico">
			<?php echo wpjc_icon( 'bookmark', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span class="wpjc-badge" data-wpjc-fav-count hidden>0</span>
		</span>
		<span class="wpjc-nav__label"><?php esc_html_e( 'Bookmarks', 'jobcore' ); ?></span>
	</button>
	<div class="wpjc-pop__panel" id="wpjc-fav-pop">
		<p class="wpjc-pop__title"><?php esc_html_e( 'Bookmarks', 'jobcore' ); ?></p>
		<div class="wpjc-pop__body">
			<ul class="wpjc-favlist" data-wpjc-fav-list></ul>
			<div class="wpjc-pop__empty" data-wpjc-fav-empty>
				<?php echo wpjc_icon( 'bookmark', 30 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<strong><?php esc_html_e( 'No bookmarks yet', 'jobcore' ); ?></strong>
				<span><?php esc_html_e( 'Tap the bookmark on a job to save it here.', 'jobcore' ); ?></span>
			</div>
		</div>
		<div class="wpjc-pop__foot">
			<?php if ( is_user_logged_in() ) : ?>
				<p>
					<?php echo wpjc_icon( 'info', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: account bookmarks link */
							__( 'Bookmarks are saved in your account. %s', 'jobcore' ),
							'<a href="' . esc_url( wpjc_account_url( 'saved' ) ) . '">' . esc_html__( 'View all', 'jobcore' ) . '</a>'
						),
						array(
							'a' => array(
								'href' => true,
							),
						)
					);
					?>
				</p>
			<?php else : ?>
				<p>
					<?php echo wpjc_icon( 'info', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<?php esc_html_e( 'Bookmarks stay on this device for 7 days.', 'jobcore' ); ?>
				</p>
				<p>
					<?php
					$wpjc_signup = wpjc_register_url( wpjc_home_url() );
					$wpjc_keep   = $wpjc_signup
						? '<a href="' . esc_url( $wpjc_signup ) . '">' . esc_html__( 'Create an account', 'jobcore' ) . '</a>'
						: '<a href="' . esc_url( wpjc_login_url( wpjc_home_url() ) ) . '">' . esc_html__( 'Sign in', 'jobcore' ) . '</a>';
					echo wp_kses(
						sprintf(
							/* translators: %s: create account / sign in link */
							__( '%s to keep them longer.', 'jobcore' ),
							$wpjc_keep
						),
						array(
							'a' => array(
								'href' => true,
							),
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
