<?php
/**
 * Standalone document head + header (board header or the News. site header with the jobs menu).
 *
 * Override in a theme: wp-job-core/standalone/header.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="wpjc-skip" href="#main"><?php esc_html_e( 'Skip to content', 'jobcore' ); ?></a>
<?php
wpjc_template( 'news' === wpjc_header_style() ? 'standalone/header-news' : 'standalone/header-board' );
