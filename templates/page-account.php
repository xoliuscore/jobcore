<?php
/**
 * Jobs account page (/jobs/account/…): sign-in gate for guests, else sidebar + current screen.
 * The layout follows the News. theme's My account hub.
 *
 * Override in a theme: wp-job-core/page-account.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

wpjc_get_header();
?>
<?php $wpjc_guest_screen = ! is_user_logged_in() && wpjc_board_login(); ?>
<main id="main" class="wpjc-acc-page<?php echo $wpjc_guest_screen ? ' wpjc-acc-page--auth' : ''; ?>">
	<?php if ( $wpjc_guest_screen ) : ?>
		<div class="wpjc-ep__band wpjc-auth-band" aria-hidden="true"></div>
	<?php endif; ?>
	<div class="wpjc-wrap">
		<?php
		if ( ! is_user_logged_in() ) {
			wpjc_template( 'account/gate' );
		} else {
			$wpjc_ctx = wpjc_account_context();
			?>
			<div class="wpjc-acc">
				<?php wpjc_template( 'account/side', $wpjc_ctx ); ?>
				<div class="wpjc-acc__main">
					<?php wpjc_template( wpjc_account_panel( $wpjc_ctx['section'], $wpjc_ctx['item'] ), $wpjc_ctx ); ?>
				</div>
			</div>
			<?php
		}
		?>
	</div>
</main>
<?php
wpjc_get_footer();
