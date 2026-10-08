<?php
/**
 * News. site header (brand bar from the theme) with the jobs menu in the section bar,
 * and a mobile drawer with jobs search + menu. Uses the theme's header CSS/JS.
 *
 * Override in a theme: wp-job-core/standalone/header-news.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

$wpjc_brand  = wpjc_brand();
$wpjc_nav    = wpjc_nav_items();
$wpjc_active = wpjc_nav_active();
?>
<header class="site-header" id="site-header">
	<?php news_part( 'header/brand-bar' ); ?>
	<nav class="nav-bar wpjc-newsnav" aria-label="<?php esc_attr_e( 'Jobs menu', 'jobcore' ); ?>">
		<div class="shell nav-bar__inner">
			<div class="nav-bar__links">
				<?php foreach ( $wpjc_nav as $wpjc_key => $wpjc_item ) : ?>
					<a href="<?php echo esc_url( $wpjc_item[1] ); ?>" class="nav-link<?php echo $wpjc_key === $wpjc_active ? ' is-active' : ''; ?>"<?php echo $wpjc_key === $wpjc_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $wpjc_item[0] ); ?></a>
				<?php endforeach; ?>
				<?php wpjc_template( 'standalone/favorites', array( 'btn_class' => 'nav-link wpjc-newsnav__fav' ) ); ?>
			</div>
		</div>
	</nav>
</header>

<aside class="mobile-nav" id="mobileNav" aria-label="<?php esc_attr_e( 'Jobs menu', 'jobcore' ); ?>">
	<div class="mobile-nav__top">
		<a href="<?php echo esc_url( wpjc_home_url() ); ?>" aria-label="<?php echo esc_attr( $wpjc_brand['name'] ); ?>"><?php echo news_header_logo_html( 'mobile-nav__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?></a>
		<button type="button" class="mobile-nav__close" id="menuClose" aria-label="<?php esc_attr_e( 'Close menu', 'jobcore' ); ?>"><?php echo wpjc_icon( 'close', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
	</div>
	<div class="mobile-nav__body">
		<form class="wpjc-search wpjc-search--drawer" method="get" action="<?php echo esc_url( wpjc_home_url() ); ?>" role="search">
			<label class="sr-only screen-reader-text" for="wpjc-mnav-q"><?php esc_html_e( 'Job title or function', 'jobcore' ); ?></label>
			<input class="wpjc-search__input" type="search" id="wpjc-mnav-q" name="wpjc_q" placeholder="<?php echo esc_attr( (string) wpjc_opt( 'search_hint' ) ); ?>">
			<button type="submit" class="wpjc-search__btn" aria-label="<?php esc_attr_e( 'Search jobs', 'jobcore' ); ?>"><?php echo wpjc_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
		</form>
		<nav class="mobile-nav__links" aria-label="<?php esc_attr_e( 'Jobs', 'jobcore' ); ?>">
			<?php foreach ( $wpjc_nav as $wpjc_key => $wpjc_item ) : ?>
				<a href="<?php echo esc_url( $wpjc_item[1] ); ?>" class="mobile-nav__link<?php echo $wpjc_key === $wpjc_active ? ' is-active' : ''; ?>"><?php echo esc_html( $wpjc_item[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<div class="mobile-nav__sep" aria-hidden="true"></div>
		<nav class="mobile-nav__links mobile-nav__links--sec" aria-label="<?php esc_attr_e( 'Site', 'jobcore' ); ?>">
			<a href="<?php echo esc_url( wpjc_account_url() ); ?>" class="mobile-nav__link"><?php esc_html_e( 'My jobs account', 'jobcore' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="mobile-nav__link"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>
		</nav>
	</div>
	<?php if ( $wpjc_brand['post_url'] ) : ?>
		<div class="mobile-nav__footer">
			<a href="<?php echo esc_url( $wpjc_brand['post_url'] ); ?>" class="btn btn--primary btn--block"><?php esc_html_e( 'Post a job', 'jobcore' ); ?></a>
		</div>
	<?php endif; ?>
</aside>
<div class="scrim" id="scrim"></div>
