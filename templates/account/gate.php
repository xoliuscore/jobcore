<?php
/**
 * Jobs account for guests: sign in or create an account, in the board's look. Sign-up asks
 * whether the person is looking for a job or hiring.
 *
 * Override in a theme: wp-job-core/account/gate.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
$wpjc_here = wpjc_account_url( wpjc_account_section(), wpjc_account_item() );
$wpjc_to   = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( rawurldecode( sanitize_text_field( wp_unslash( $_GET['redirect_to'] ) ) ) ), '' ) : '';
$wpjc_to   = '' !== $wpjc_to ? $wpjc_to : $wpjc_here;
$wpjc_mode = isset( $_GET['wpjc_auth'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_auth'] ) ) : 'login';
$wpjc_mode = in_array( $wpjc_mode, array( 'signup', 'lost', 'reset' ), true ) ? $wpjc_mode : 'login';
$wpjc_rkey = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
$wpjc_rlog = isset( $_GET['login'] ) ? sanitize_user( wp_unslash( $_GET['login'] ) ) : '';
$wpjc_hint = isset( $_GET['wpjc_type'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_type'] ) ) : '';
// phpcs:enable

// The screen someone wanted tells us who they are likely to be.
if ( '' === $wpjc_hint ) {
	$wpjc_path = (string) wp_parse_url( $wpjc_to, PHP_URL_PATH ) . '?' . (string) wp_parse_url( $wpjc_to, PHP_URL_QUERY );
	foreach ( wpjc_section_audiences() as $wpjc_key => $wpjc_aud ) {
		if ( 'both' !== $wpjc_aud && ( false !== strpos( $wpjc_path, '/account/' . $wpjc_key ) || false !== strpos( $wpjc_path, 'wpjc_account=' . $wpjc_key ) ) ) {
			$wpjc_hint = $wpjc_aud;
			break;
		}
	}
}

$wpjc_board  = wpjc_board_login();
$wpjc_open   = wpjc_signup_open();
$wpjc_typed  = 'off' !== wpjc_accounts_mode();
$wpjc_notice = wpjc_auth_notice();
$wpjc_keep   = 'signup' === $wpjc_mode ? wpjc_auth_kept() : array();
if ( 'reset' === $wpjc_mode && is_wp_error( check_password_reset_key( $wpjc_rkey, $wpjc_rlog ) ) ) {
	$wpjc_mode   = 'lost';
	$wpjc_notice = array( 'warn', __( 'This link has expired or was already used. Ask for a new one below.', 'jobcore' ) );
}
$wpjc_type   = (string) ( $wpjc_keep['type'] ?? $wpjc_hint );
$wpjc_name   = wpjc_brand()['name'];

if ( ! $wpjc_board ) :
	// The site's own login page.
	$wpjc_register = wpjc_register_url( $wpjc_here );
	?>
	<div class="wpjc-acc-gate">
		<span class="wpjc-acc-gate__icon"><?php echo wpjc_icon( 'user', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
		<h1><?php esc_html_e( 'Sign in to your jobs account', 'jobcore' ); ?></h1>
		<p><?php esc_html_e( 'Follow your applications, get new jobs by e-mail and post job ads for your company.', 'jobcore' ); ?></p>
		<a class="wpjc-btn wpjc-btn--block" href="<?php echo esc_url( wpjc_login_url( $wpjc_here ) ); ?>"><?php echo wpjc_icon( 'login', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Sign in', 'jobcore' ); ?></a>
		<?php if ( $wpjc_register ) : ?>
			<a class="wpjc-btn wpjc-btn--ghost wpjc-btn--block" href="<?php echo esc_url( $wpjc_register ); ?>"><?php esc_html_e( 'Create a free account', 'jobcore' ); ?></a>
		<?php endif; ?>
		<?php echo wpjc_social_login_html( $wpjc_here ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the social login plugin. ?>
	</div>
	<?php
	return;
endif;
?>
<?php
/* translators: %s: board name */
$wpjc_dek = sprintf( __( 'One account for everything on %s.', 'jobcore' ), $wpjc_name );
$wpjc_show = 'signup' === $wpjc_mode && $wpjc_open ? 'signup' : 'login';
$wpjc_pane = static function ( $mode ) use ( $wpjc_show ) {
	echo ' data-wpjc-auth-pane="' . esc_attr( $mode ) . '"' . ( $mode === $wpjc_show ? '' : ' hidden' );
};
$wpjc_go = static function ( $mode ) use ( $wpjc_to, $wpjc_hint ) {
	echo ' href="' . esc_url( wpjc_auth_url( $mode, $wpjc_to, 'signup' === $mode ? $wpjc_hint : '' ) ) . '" data-wpjc-auth-go="' . esc_attr( $mode ) . '"';
};
?>
<?php if ( 'lost' === $wpjc_mode || 'reset' === $wpjc_mode ) : ?>
<div class="wpjc-auth-wrap">
	<header class="wpjc-auth-head">
		<nav class="wpjc-crumbs wpjc-auth-head__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'jobcore' ); ?>">
			<a href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php esc_html_e( 'Jobs', 'jobcore' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<a href="<?php echo esc_url( wpjc_auth_url( 'login' ) ); ?>"><?php esc_html_e( 'Your account', 'jobcore' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<span aria-current="page"><?php esc_html_e( 'Password', 'jobcore' ); ?></span>
		</nav>
		<?php if ( 'lost' === $wpjc_mode ) : ?>
			<h1 class="wpjc-auth-head__title"><?php esc_html_e( 'Forgot your password?', 'jobcore' ); ?></h1>
			<p class="wpjc-auth-head__dek"><?php esc_html_e( 'No problem. Enter your e-mail address and we will send you a link to choose a new one.', 'jobcore' ); ?></p>
		<?php else : ?>
			<h1 class="wpjc-auth-head__title"><?php esc_html_e( 'Choose a new password', 'jobcore' ); ?></h1>
			<p class="wpjc-auth-head__dek"><?php esc_html_e( 'Pick something you do not use anywhere else.', 'jobcore' ); ?></p>
		<?php endif; ?>
	</header>
	<div class="wpjc-auth wpjc-auth--single" id="wpjc-auth">
		<div class="wpjc-auth__card">
			<?php if ( $wpjc_notice ) : ?>
				<p class="wpjc-notice wpjc-notice--<?php echo 'ok' === $wpjc_notice[0] ? 'ok' : 'warn'; ?>" role="<?php echo 'ok' === $wpjc_notice[0] ? 'status' : 'alert'; ?>"><?php echo esc_html( $wpjc_notice[1] ); ?></p>
			<?php endif; ?>
			<?php if ( 'lost' === $wpjc_mode ) : ?>
				<form class="wpjc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpjc_lost">
					<?php wp_nonce_field( 'wpjc_lost', 'wpjc_nonce' ); ?>
					<p class="wpjc-auth__field">
						<label for="wpjc-lp-log"><?php esc_html_e( 'E-mail or username', 'jobcore' ); ?></label>
						<input class="wpjc-input" type="text" id="wpjc-lp-log" name="log" autocomplete="username" required>
					</p>
					<button class="wpjc-btn wpjc-btn--block" type="submit"><?php echo wpjc_icon( 'email', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Send me a link', 'jobcore' ); ?></button>
				</form>
			<?php else : ?>
				<form class="wpjc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpjc_reset">
					<input type="hidden" name="key" value="<?php echo esc_attr( $wpjc_rkey ); ?>">
					<input type="hidden" name="login" value="<?php echo esc_attr( $wpjc_rlog ); ?>">
					<?php wp_nonce_field( 'wpjc_reset', 'wpjc_nonce' ); ?>
					<input type="text" name="username" value="<?php echo esc_attr( $wpjc_rlog ); ?>" autocomplete="username" hidden>
					<p class="wpjc-auth__field">
						<label for="wpjc-rp-pass"><?php esc_html_e( 'New password', 'jobcore' ); ?></label>
						<input class="wpjc-input" type="password" id="wpjc-rp-pass" name="password" autocomplete="new-password" minlength="8" required>
						<span class="wpjc-acc-help"><?php esc_html_e( 'At least 8 characters.', 'jobcore' ); ?></span>
					</p>
					<button class="wpjc-btn wpjc-btn--block" type="submit"><?php esc_html_e( 'Save new password', 'jobcore' ); ?></button>
				</form>
			<?php endif; ?>
			<p class="wpjc-auth__switch"><?php esc_html_e( 'Remembered it?', 'jobcore' ); ?> <a href="<?php echo esc_url( wpjc_auth_url( 'login' ) ); ?>"><?php esc_html_e( 'Sign in', 'jobcore' ); ?></a></p>
		</div>
	</div>
</div>
<?php
	return;
endif;
?>
<div class="wpjc-auth-wrap" data-wpjc-auth>
	<header class="wpjc-auth-head">
		<nav class="wpjc-crumbs wpjc-auth-head__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'jobcore' ); ?>">
			<a href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php esc_html_e( 'Jobs', 'jobcore' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<span aria-current="page"><?php esc_html_e( 'Your account', 'jobcore' ); ?></span>
		</nav>
		<div<?php $wpjc_pane( 'login' ); ?>>
			<h1 class="wpjc-auth-head__title"><?php esc_html_e( 'Welcome back', 'jobcore' ); ?></h1>
			<p class="wpjc-auth-head__dek"><?php esc_html_e( 'Sign in to follow your applications, or to manage your company and job ads.', 'jobcore' ); ?></p>
		</div>
		<?php if ( $wpjc_open ) : ?>
			<div<?php $wpjc_pane( 'signup' ); ?>>
				<h1 class="wpjc-auth-head__title"><?php esc_html_e( 'Create your free account', 'jobcore' ); ?></h1>
				<p class="wpjc-auth-head__dek"><?php echo esc_html( $wpjc_dek ); ?></p>
			</div>
		<?php endif; ?>
	</header>

	<div class="wpjc-auth" id="wpjc-auth">
		<div class="wpjc-auth__card">
			<?php if ( $wpjc_open ) : ?>
				<nav class="wpjc-auth__tabs" aria-label="<?php esc_attr_e( 'Sign in or create an account', 'jobcore' ); ?>">
					<a<?php $wpjc_go( 'login' ); ?><?php echo 'login' === $wpjc_show ? ' class="is-active" aria-current="page"' : ''; ?>><?php esc_html_e( 'Sign in', 'jobcore' ); ?></a>
					<a<?php $wpjc_go( 'signup' ); ?><?php echo 'signup' === $wpjc_show ? ' class="is-active" aria-current="page"' : ''; ?>><?php esc_html_e( 'Create account', 'jobcore' ); ?></a>
				</nav>
			<?php endif; ?>

			<?php if ( $wpjc_open ) : ?>
				<div class="wpjc-auth__pane"<?php $wpjc_pane( 'signup' ); ?>>
					<?php if ( $wpjc_notice && 'signup' === $wpjc_show ) : ?>
						<p class="wpjc-notice wpjc-notice--<?php echo 'ok' === $wpjc_notice[0] ? 'ok' : 'warn'; ?>" role="<?php echo 'ok' === $wpjc_notice[0] ? 'status' : 'alert'; ?>"><?php echo esc_html( $wpjc_notice[1] ); ?></p>
					<?php endif; ?>
					<form class="wpjc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpjc_signup">
						<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wpjc_to ); ?>">
						<?php wp_nonce_field( 'wpjc_signup', 'wpjc_nonce' ); ?>
						<?php if ( $wpjc_typed ) : ?>
							<fieldset class="wpjc-auth__types">
								<legend><?php esc_html_e( 'I am …', 'jobcore' ); ?></legend>
								<?php foreach ( wpjc_account_types() as $wpjc_key => $wpjc_t ) : ?>
									<label class="wpjc-auth__type">
										<input type="radio" name="wpjc_account_type" value="<?php echo esc_attr( $wpjc_key ); ?>" required <?php checked( $wpjc_type, $wpjc_key ); ?>>
										<span class="wpjc-auth__type-box">
											<span class="wpjc-auth__type-icon"><?php echo wpjc_icon( $wpjc_t[2], 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
											<span class="wpjc-auth__type-title"><?php echo esc_html( $wpjc_t[0] ); ?></span>
											<span class="wpjc-auth__type-text"><?php echo esc_html( $wpjc_t[1] ); ?></span>
										</span>
									</label>
								<?php endforeach; ?>
							</fieldset>
						<?php endif; ?>
						<p class="wpjc-auth__field">
							<label for="wpjc-su-name"><?php esc_html_e( 'Full name', 'jobcore' ); ?></label>
							<input class="wpjc-input" type="text" id="wpjc-su-name" name="name" value="<?php echo esc_attr( (string) ( $wpjc_keep['name'] ?? '' ) ); ?>" autocomplete="name" maxlength="80" required>
						</p>
						<p class="wpjc-auth__field">
							<label for="wpjc-su-email"><?php esc_html_e( 'E-mail', 'jobcore' ); ?></label>
							<input class="wpjc-input" type="email" id="wpjc-su-email" name="email" value="<?php echo esc_attr( (string) ( $wpjc_keep['email'] ?? '' ) ); ?>" autocomplete="email" required>
						</p>
						<p class="wpjc-auth__field">
							<label for="wpjc-su-pass"><?php esc_html_e( 'Password', 'jobcore' ); ?></label>
							<input class="wpjc-input" type="password" id="wpjc-su-pass" name="password" autocomplete="new-password" minlength="8" required>
							<span class="wpjc-acc-help"><?php esc_html_e( 'At least 8 characters.', 'jobcore' ); ?></span>
						</p>
						<p class="wpjc-hp" aria-hidden="true"><label>Website <input type="text" name="wpjc_website_hp" tabindex="-1" autocomplete="off"></label></p>
						<p class="wpjc-auth__check">
							<label>
								<input type="checkbox" name="consent" value="1" required>
								<span>
									<?php
									$wpjc_privacy = get_privacy_policy_url();
									printf(
										/* translators: %s: privacy policy link */
										esc_html__( 'I have read the %s.', 'jobcore' ),
										$wpjc_privacy ? '<a href="' . esc_url( $wpjc_privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'jobcore' ) . '</a>' : esc_html__( 'privacy policy', 'jobcore' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
									);
									?>
								</span>
							</label>
						</p>
						<button class="wpjc-btn wpjc-btn--block" type="submit"><?php esc_html_e( 'Create account', 'jobcore' ); ?></button>
					</form>
					<p class="wpjc-auth__switch"><?php esc_html_e( 'Already have an account?', 'jobcore' ); ?> <a<?php $wpjc_go( 'login' ); ?>><?php esc_html_e( 'Sign in', 'jobcore' ); ?></a></p>
				</div>
			<?php endif; ?>

			<div class="wpjc-auth__pane"<?php $wpjc_pane( 'login' ); ?>>
				<?php if ( $wpjc_notice && 'login' === $wpjc_show ) : ?>
					<p class="wpjc-notice wpjc-notice--<?php echo 'ok' === $wpjc_notice[0] ? 'ok' : 'warn'; ?>" role="<?php echo 'ok' === $wpjc_notice[0] ? 'status' : 'alert'; ?>"><?php echo esc_html( $wpjc_notice[1] ); ?></p>
				<?php endif; ?>
				<form class="wpjc-auth__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpjc_login">
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $wpjc_to ); ?>">
					<?php wp_nonce_field( 'wpjc_login', 'wpjc_nonce' ); ?>
					<p class="wpjc-auth__field">
						<label for="wpjc-li-log"><?php esc_html_e( 'E-mail or username', 'jobcore' ); ?></label>
						<input class="wpjc-input" type="text" id="wpjc-li-log" name="log" autocomplete="username" required>
					</p>
					<p class="wpjc-auth__field">
						<label for="wpjc-li-pwd"><?php esc_html_e( 'Password', 'jobcore' ); ?> <a class="wpjc-auth__lost" href="<?php echo esc_url( wpjc_lost_password_url() ); ?>"><?php esc_html_e( 'Forgot it?', 'jobcore' ); ?></a></label>
						<input class="wpjc-input" type="password" id="wpjc-li-pwd" name="pwd" autocomplete="current-password" required>
					</p>
					<p class="wpjc-auth__check"><label><input type="checkbox" name="rememberme" value="1" checked> <span><?php esc_html_e( 'Keep me signed in', 'jobcore' ); ?></span></label></p>
					<button class="wpjc-btn wpjc-btn--block" type="submit"><?php echo wpjc_icon( 'login', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Sign in', 'jobcore' ); ?></button>
				</form>
				<?php if ( $wpjc_open ) : ?>
					<p class="wpjc-auth__switch"><?php esc_html_e( 'New here?', 'jobcore' ); ?> <a<?php $wpjc_go( 'signup' ); ?>><?php esc_html_e( 'Create a free account', 'jobcore' ); ?></a></p>
				<?php endif; ?>
			</div>

			<?php
			$wpjc_social = wpjc_social_login_html( $wpjc_to );
			if ( '' !== trim( $wpjc_social ) ) :
				?>
				<div class="wpjc-auth__social"><?php if ( false === stripos( $wpjc_social, 'continue with' ) ) : ?><span><?php esc_html_e( 'or', 'jobcore' ); ?></span><?php endif; ?><?php echo $wpjc_social; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by the social login plugin. ?></div>
			<?php endif; ?>

		</div>

		<aside class="wpjc-auth__why">
			<h2><?php esc_html_e( 'Looking for a job?', 'jobcore' ); ?></h2>
			<ul>
				<li><?php echo wpjc_icon( 'send', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'All your applications and their status in one place', 'jobcore' ); ?></li>
				<li><?php echo wpjc_icon( 'bell', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'New jobs by e-mail with job alerts', 'jobcore' ); ?></li>
				<li><?php echo wpjc_icon( 'bookmark', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Bookmarks on every device', 'jobcore' ); ?></li>
				<?php do_action( 'wpjc_auth_candidate_points' ); ?>
			</ul>
			<h2><?php esc_html_e( 'Hiring?', 'jobcore' ); ?></h2>
			<ul>
				<li><?php echo wpjc_icon( 'building', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Company profile and job ads', 'jobcore' ); ?></li>
				<li><?php echo wpjc_icon( 'inbox', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'An inbox for applications: shortlist, reject, download CVs', 'jobcore' ); ?></li>
				<?php do_action( 'wpjc_auth_employer_points' ); ?>
			</ul>
		</aside>
	</div>
</div>
