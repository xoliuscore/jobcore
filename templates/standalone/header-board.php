<?php
/**
 * Board header: logo, icon nav, bookmarks and profile menus, mobile drawer with search.
 *
 * Override in a theme: wp-job-core/standalone/header-board.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

$wpjc_brand  = wpjc_brand();
$wpjc_nav    = wpjc_nav_items();
$wpjc_active = wpjc_nav_active();
$wpjc_user   = wp_get_current_user();
$wpjc_here   = home_url( add_query_arg( array() ) );

/**
 * Profile menu links: [label, URL, icon]. Add-ons (applications, alerts) extend this.
 *
 * @param array   $links Links.
 * @param WP_User $user  Current user.
 */
$wpjc_links = array();
if ( $wpjc_user->exists() ) {
	foreach ( wpjc_account_sections() as $wpjc_key => $wpjc_section ) {
		$wpjc_links[] = array( $wpjc_section[0], wpjc_account_url( $wpjc_key ), $wpjc_section[1] );
	}
} else {
	$wpjc_links[] = array( __( 'Post a job', 'jobcore' ), $wpjc_brand['post_url'], 'plus-c' );
	$wpjc_links[] = array( __( 'Add company', 'jobcore' ), $wpjc_brand['employer_url'], 'building' );
}
$wpjc_profile = (array) apply_filters(
	'wpjc_profile_links',
	array_values(
		array_filter(
			$wpjc_links,
			static function ( $item ) {
				return ! empty( $item[1] );
			}
		)
	),
	$wpjc_user
);
$wpjc_register = wpjc_register_url( $wpjc_here );
?>
<header class="wpjc-top">
	<div class="wpjc-wrap wpjc-top__inner">
		<button type="button" class="wpjc-burger" aria-controls="wpjc-drawer" aria-expanded="false" aria-label="<?php esc_attr_e( 'Menu and search', 'jobcore' ); ?>">
			<svg width="28" height="28" viewBox="0 0 64 64" fill="none" aria-hidden="true" focusable="false">
				<rect x="8" y="8" width="48" height="3" rx="2" fill="currentColor"/>
				<rect x="8" y="20" width="20" height="3" rx="2" fill="currentColor"/>
				<rect x="8" y="32" width="20" height="3" rx="2" fill="currentColor"/>
				<rect x="8" y="44" width="48" height="3" rx="2" fill="currentColor"/>
				<circle cx="40" cy="26" r="6" stroke="currentColor" stroke-width="3"/>
				<line x1="44.5" y1="30.5" x2="50" y2="36" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
			</svg>
		</button>

		<a class="wpjc-top__brand" href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php echo wpjc_board_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>

		<nav class="wpjc-nav" aria-label="<?php esc_attr_e( 'Jobs menu', 'jobcore' ); ?>">
			<?php foreach ( $wpjc_nav as $wpjc_key => $wpjc_item ) : ?>
				<a class="wpjc-nav__item<?php echo $wpjc_key === $wpjc_active ? ' is-active' : ''; ?>" href="<?php echo esc_url( $wpjc_item[1] ); ?>"<?php echo $wpjc_key === $wpjc_active ? ' aria-current="page"' : ''; ?>>
					<?php echo wpjc_icon( $wpjc_item[2], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span><?php echo esc_html( $wpjc_item[0] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<?php wpjc_template( 'standalone/favorites' ); ?>

		<?php wpjc_template( 'standalone/cart' ); ?>

		<div class="wpjc-pop wpjc-pop--user<?php echo $wpjc_user->exists() ? '' : ' wpjc-pop--login'; ?>">
			<?php if ( $wpjc_user->exists() ) : ?>
				<button type="button" class="wpjc-nav__item wpjc-pop__btn" aria-expanded="false" aria-controls="wpjc-user-pop" aria-label="<?php esc_attr_e( 'Profile', 'jobcore' ); ?>">
					<?php echo wpjc_avatar_html( $wpjc_user, 22, 'wpjc-avatar wpjc-avatar--sm' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<span class="wpjc-nav__label"><?php esc_html_e( 'Profile', 'jobcore' ); ?></span>
				</button>
			<?php else : ?>
				<a class="wpjc-login wpjc-pop__btn" href="<?php echo esc_url( wpjc_login_url( wpjc_account_url() ) ); ?>" aria-label="<?php esc_attr_e( 'Log in', 'jobcore' ); ?>" aria-expanded="false" aria-controls="wpjc-user-pop">
					<span class="wpjc-login__ico" aria-hidden="true"><?php echo wpjc_icon( 'user-fill', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<span class="wpjc-login__label"><?php esc_html_e( 'Log in', 'jobcore' ); ?></span>
				</a>
			<?php endif; ?>
			<div class="wpjc-pop__panel" id="wpjc-user-pop">
				<p class="wpjc-pop__title"><?php echo $wpjc_user->exists() ? esc_html__( 'My profile', 'jobcore' ) : esc_html__( 'Account', 'jobcore' ); ?></p>
				<div class="wpjc-pop__body">
					<?php if ( $wpjc_user->exists() ) : ?>
						<div class="wpjc-me">
							<?php echo wpjc_avatar_html( $wpjc_user, 44, 'wpjc-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
							<span class="wpjc-me__text">
								<strong><?php echo esc_html( $wpjc_user->display_name ); ?></strong>
								<span><?php echo esc_html( $wpjc_user->user_email ); ?></span>
							</span>
						</div>
					<?php endif; ?>
					<?php echo wpjc_scheme_switch_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<ul class="wpjc-menu">
						<?php if ( ! $wpjc_user->exists() ) : ?>
							<li><a href="<?php echo esc_url( wpjc_login_url( wpjc_account_url() ) ); ?>"><?php echo wpjc_icon( 'login', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Log in', 'jobcore' ); ?></a></li>
							<?php if ( $wpjc_register ) : ?>
								<li><a href="<?php echo esc_url( $wpjc_register ); ?>"><?php echo wpjc_icon( 'user', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Create account', 'jobcore' ); ?></a></li>
							<?php endif; ?>
						<?php endif; ?>
						<?php foreach ( $wpjc_profile as $wpjc_link ) : ?>
							<li><a href="<?php echo esc_url( $wpjc_link[1] ); ?>"><?php echo wpjc_icon( (string) ( $wpjc_link[2] ?? 'arrow' ), 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_link[0] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php if ( $wpjc_user->exists() ) : ?>
					<ul class="wpjc-menu wpjc-pop__foot">
						<li><a href="<?php echo esc_url( wpjc_profile_url() ); ?>"><?php echo wpjc_icon( 'cog', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Profile and security', 'jobcore' ); ?></a></li>
						<li><a class="wpjc-menu__danger" href="<?php echo esc_url( wp_logout_url( $wpjc_here ) ); ?>"><?php echo wpjc_icon( 'logout', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Log out', 'jobcore' ); ?></a></li>
					</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</header>

<div class="wpjc-drawer" id="wpjc-drawer" hidden>
	<div class="wpjc-drawer__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Menu', 'jobcore' ); ?>">
		<div class="wpjc-drawer__head">
			<a class="wpjc-top__brand" href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php echo wpjc_board_logo_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
			<button type="button" class="wpjc-drawer__close" data-wpjc-drawer-close aria-label="<?php esc_attr_e( 'Close menu', 'jobcore' ); ?>"><?php echo wpjc_icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		</div>
		<form class="wpjc-search wpjc-search--drawer" method="get" action="<?php echo esc_url( wpjc_home_url() ); ?>" role="search">
			<label class="sr-only screen-reader-text" for="wpjc-drawer-q"><?php esc_html_e( 'Job title or function', 'jobcore' ); ?></label>
			<input class="wpjc-search__input" type="search" id="wpjc-drawer-q" name="wpjc_q" placeholder="<?php echo esc_attr( (string) wpjc_opt( 'search_hint' ) ); ?>">
			<button type="submit" class="wpjc-search__btn" aria-label="<?php esc_attr_e( 'Search jobs', 'jobcore' ); ?>"><?php echo wpjc_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		</form>
		<ul class="wpjc-menu wpjc-drawer__nav">
			<?php foreach ( $wpjc_nav as $wpjc_key => $wpjc_item ) : ?>
				<li><a class="<?php echo $wpjc_key === $wpjc_active ? 'is-active' : ''; ?>" href="<?php echo esc_url( $wpjc_item[1] ); ?>"><?php echo wpjc_icon( $wpjc_item[2], 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_item[0] ); ?></a></li>
			<?php endforeach; ?>
			<?php wpjc_template( 'standalone/cart', array( 'context' => 'drawer' ) ); ?>
			<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo wpjc_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a></li>
		</ul>
	</div>
</div>
