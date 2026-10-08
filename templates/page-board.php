<?php
/**
 * Jobs page in the standalone look.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

wpjc_get_header();
?>
<main id="main" class="wpjc-board-page">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</main>
<?php
wpjc_get_footer();
