<?php
/**
 * Jobs board: hero search, contact strip, employers row, one column per listing tier.
 *
 * Override in a theme: wp-job-core/board.php.
 *
 * @package JobCore
 * @var string        $view      home|all|employers|results.
 * @var string        $title
 * @var string        $intro
 * @var string        $heading   Body heading for list views.
 * @var string        $keywords
 * @var int           $paged
 * @var array         $employers
 * @var WP_Query[]    $tiers     Tier key => query (home view).
 * @var WP_Query|null $query     List views.
 * @var array         $filters   Query args to keep in pagination links.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_obj = get_queried_object();
if ( $wpjc_obj instanceof WP_Term ) {
	$wpjc_here = get_term_link( $wpjc_obj );
} elseif ( is_post_type_archive( 'wpjc_job' ) ) {
	$wpjc_here = get_post_type_archive_link( 'wpjc_job' );
} else {
	$wpjc_here = get_permalink();
}
if ( ! $wpjc_here || is_wp_error( $wpjc_here ) ) {
	$wpjc_here = wpjc_home_url();
}
$wpjc_pages = ! empty( $base_url ) ? $base_url : $wpjc_here;
$wpjc_scope = $wpjc_obj instanceof WP_Term && in_array( $wpjc_obj->taxonomy, array( 'wpjc_job_category', 'wpjc_job_type' ), true );
$wpjc_find  = $wpjc_scope ? wpjc_home_url() : $wpjc_here;
$wpjc_srch  = isset( $search ) ? (array) $search : wpjc_search_filters();
$wpjc_vals  = isset( $applied ) ? (array) $applied : $wpjc_srch;

$wpjc_brand   = wpjc_brand();
$wpjc_tiers   = wpjc_tiers();
$wpjc_hero_im = (string) wpjc_opt( 'hero_image' );
$wpjc_title_id = 'wpjc-title-' . wp_unique_id();
?>
<div class="wpjc-board wpjc-board--<?php echo esc_attr( $view ); ?>">

	<section class="wpjc-hero<?php echo 'home' === $view ? '' : ' wpjc-hero--compact'; ?>" aria-labelledby="<?php echo esc_attr( $wpjc_title_id ); ?>">
		<div class="wpjc-wrap wpjc-hero__inner">
			<h1 class="wpjc-hero__title" id="<?php echo esc_attr( $wpjc_title_id ); ?>"><?php echo esc_html( $title ); ?></h1>
			<?php if ( $intro && 'home' === $view ) : ?>
				<p class="wpjc-hero__intro"><?php echo esc_html( $intro ); ?></p>
			<?php endif; ?>
			<form class="wpjc-search" id="<?php echo esc_attr( $wpjc_title_id ); ?>-form" method="get" action="<?php echo esc_url( $wpjc_find ); ?>" role="search">
				<label class="sr-only screen-reader-text" for="<?php echo esc_attr( $wpjc_title_id ); ?>-q"><?php esc_html_e( 'Job title or function', 'jobcore' ); ?></label>
				<input class="wpjc-search__input" type="search" id="<?php echo esc_attr( $wpjc_title_id ); ?>-q" name="wpjc_q" value="<?php echo esc_attr( $keywords ); ?>" placeholder="<?php echo esc_attr( (string) wpjc_opt( 'search_hint' ) ); ?>" autocomplete="off">
				<?php do_action( 'wpjc_search_inside' ); ?>
				<button type="submit" class="wpjc-search__btn" aria-label="<?php esc_attr_e( 'Search jobs', 'jobcore' ); ?>"><?php echo wpjc_icon( 'search', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
			</form>
			<?php
			wpjc_template(
				'search-advanced',
				array(
					'form_id' => $wpjc_title_id . '-form',
					'values'  => $wpjc_vals,
					'open'    => wpjc_filters_advanced_count( $wpjc_srch ) > 0,
				)
			);
			?>
		</div>
		<?php if ( $wpjc_hero_im && 'home' === $view ) : ?>
			<img class="wpjc-hero__img" src="<?php echo esc_url( $wpjc_hero_im ); ?>" alt="" decoding="async">
		<?php endif; ?>
	</section>

	<?php if ( $wpjc_brand['email'] || $wpjc_brand['phone'] || $wpjc_brand['social'] || $wpjc_brand['post_url'] || $wpjc_brand['resume_url'] || $wpjc_brand['employer_url'] ) : ?>
		<div class="wpjc-strip">
			<div class="wpjc-wrap wpjc-strip__inner">
				<div class="wpjc-strip__info">
					<?php if ( $wpjc_brand['email'] ) : ?>
						<a class="wpjc-strip__item" href="<?php echo esc_url( 'mailto:' . antispambot( $wpjc_brand['email'] ) ); ?>">
							<span class="wpjc-strip__ico"><?php echo wpjc_icon( 'email', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span><span class="wpjc-strip__k"><?php esc_html_e( 'E-mail:', 'jobcore' ); ?></span> <?php echo esc_html( antispambot( $wpjc_brand['email'] ) ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $wpjc_brand['phone'] ) : ?>
						<a class="wpjc-strip__item" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $wpjc_brand['phone'] ) ); ?>">
							<span class="wpjc-strip__ico"><?php echo wpjc_icon( 'phone', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<span><span class="wpjc-strip__k"><?php esc_html_e( 'Phone:', 'jobcore' ); ?></span> <?php echo esc_html( $wpjc_brand['phone'] ); ?></span>
						</a>
					<?php endif; ?>
					<?php if ( $wpjc_brand['social'] ) : ?>
						<span class="wpjc-strip__social">
							<span class="wpjc-strip__k"><?php esc_html_e( 'Follow us:', 'jobcore' ); ?></span>
							<?php foreach ( $wpjc_brand['social'] as $wpjc_net => $wpjc_url ) : ?>
								<a class="wpjc-strip__net wpjc-strip__net--<?php echo esc_attr( $wpjc_net ); ?>" href="<?php echo esc_url( $wpjc_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( wpjc_social_label( $wpjc_net ) ); ?>"><?php echo wpjc_icon( $wpjc_net, 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
							<?php endforeach; ?>
						</span>
					<?php endif; ?>
				</div>
				<div class="wpjc-strip__actions">
					<?php if ( $wpjc_brand['post_url'] ) : ?>
						<a class="wpjc-btn wpjc-btn--sm" href="<?php echo esc_url( $wpjc_brand['post_url'] ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( 'plus', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'Post a job', 'jobcore' ); ?></a>
					<?php endif; ?>

					<?php if ( $wpjc_brand['resume_url'] ) : ?>
						<a class="wpjc-btn wpjc-btn--sm" href="<?php echo esc_url( $wpjc_brand['resume_url'] ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( 'id', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'Create resume', 'jobcore' ); ?></a>
					<?php endif; ?>
					<?php if ( $wpjc_brand['employer_url'] ) : ?>
						<a class="wpjc-btn wpjc-btn--sm wpjc-btn--ghost" href="<?php echo esc_url( $wpjc_brand['employer_url'] ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( 'building', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php esc_html_e( 'Create employer', 'jobcore' ); ?></a>
					<?php endif; ?>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<div class="wpjc-wrap wpjc-body">

		<?php if ( 'home' === $view ) : ?>

			<?php do_action( 'wpjc_board_home_top' ); ?>

			<?php if ( $employers ) : ?>
				<section class="wpjc-sec" id="wpjc-employers">
					<div class="wpjc-sec__head">
						<h2 class="wpjc-h"><?php esc_html_e( 'Featured employers', 'jobcore' ); ?></h2>
						<a class="wpjc-more" href="<?php echo esc_url( wpjc_view_url( 'employers' ) ); ?>"><?php esc_html_e( 'All employers', 'jobcore' ); ?> <?php echo wpjc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					</div>
					<ul class="wpjc-emprow">
						<?php
						foreach ( $employers as $wpjc_emp ) {
							wpjc_template( 'employer', array( 'employer' => $wpjc_emp ) );
						}
						?>
					</ul>
				</section>
			<?php endif; ?>

			<div class="wpjc-tiers">
				<?php
				$wpjc_last = array_key_last( $tiers );
				foreach ( $tiers as $wpjc_tier => $wpjc_q ) :
					$wpjc_def = $wpjc_tiers[ $wpjc_tier ];
					?>
					<section class="wpjc-tier wpjc-tier--<?php echo esc_attr( $wpjc_tier ); ?>" style="--wpjc-bar:<?php echo esc_attr( $wpjc_def['color'] ); ?>">
						<div class="wpjc-sec__head">
							<h2 class="wpjc-h">
								<?php
								/* translators: %s: tier name, e.g. Premium */
								echo esc_html( sprintf( __( '%s jobs', 'jobcore' ), $wpjc_def['label'] ) );
								?>
							</h2>
							<?php if ( $wpjc_tier === $wpjc_last ) : ?>
								<a class="wpjc-more" href="<?php echo esc_url( wpjc_view_url( 'all' ) ); ?>"><?php esc_html_e( 'All jobs', 'jobcore' ); ?> <?php echo wpjc_icon( 'arrow', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
							<?php endif; ?>
						</div>
						<?php if ( $wpjc_q->have_posts() ) : ?>
							<ul class="wpjc-jobs">
								<?php
								foreach ( $wpjc_q->posts as $wpjc_job ) {
									wpjc_template( 'card', array( 'post' => $wpjc_job ) );
								}
								?>
							</ul>
						<?php else : ?>
							<p class="wpjc-empty"><?php esc_html_e( 'No jobs here yet.', 'jobcore' ); ?></p>
						<?php endif; ?>
					</section>
				<?php endforeach; ?>
			</div>

			<?php do_action( 'wpjc_board_home_bottom' ); ?>

		<?php elseif ( 'employers' === $view ) : ?>

			<section class="wpjc-sec">
				<div class="wpjc-sec__head">
					<h2 class="wpjc-h"><?php echo esc_html( $heading ); ?></h2>
				</div>
				<?php if ( $employers ) : ?>
					<ul class="wpjc-empgrid">
						<?php
						foreach ( $employers as $wpjc_emp ) {
							wpjc_template( 'employer', array( 'employer' => $wpjc_emp ) );
						}
						?>
					</ul>
				<?php else : ?>
					<p class="wpjc-empty"><?php esc_html_e( 'No employers yet.', 'jobcore' ); ?></p>
				<?php endif; ?>
			</section>

		<?php else : ?>

			<section class="wpjc-sec">
				<div class="wpjc-sec__head">
					<h2 class="wpjc-h">
						<?php echo esc_html( $heading ); ?>
						<span class="wpjc-count"><?php echo esc_html( number_format_i18n( (int) $query->found_posts ) ); ?></span>
					</h2>
					<?php if ( wpjc_filters_active( $wpjc_srch ) ) : ?>
						<a class="wpjc-more" href="<?php echo esc_url( $wpjc_pages ); ?>"><?php esc_html_e( 'Clear search', 'jobcore' ); ?></a>
					<?php endif; ?>
				</div>
				<?php
				$wpjc_chips = array();
				if ( '' !== $wpjc_srch['keywords'] ) {
					$wpjc_chips['wpjc_q'] = array( 'search', '“' . $wpjc_srch['keywords'] . '”' );
				}
				// One chip per picked term or place; removing it keeps the others.
				foreach ( array( 'cats' => array( 'wpjc_cat', 'wpjc_job_category', 'tag' ), 'types' => array( 'wpjc_type', 'wpjc_job_type', 'briefcase' ) ) as $wpjc_key => $wpjc_def ) {
					$wpjc_ids = (array) ( $wpjc_srch[ $wpjc_key ] ?? array() );
					foreach ( $wpjc_ids as $wpjc_id ) {
						$wpjc_term = get_term( $wpjc_id, $wpjc_def[1] );
						if ( $wpjc_term instanceof WP_Term ) {
							$wpjc_rest = implode( ',', array_diff( $wpjc_ids, array( $wpjc_id ) ) );
							$wpjc_chips[ $wpjc_def[0] . '-' . $wpjc_id ] = array( $wpjc_def[2], wpjc_term_text( $wpjc_term ), array( $wpjc_def[0] => $wpjc_rest ) );
						}
					}
				}
				foreach ( (array) ( $wpjc_srch['places'] ?? array() ) as $wpjc_place ) {
					$wpjc_chips[ 'wpjc_place-' . md5( $wpjc_place ) ] = array( 'pin', $wpjc_place, array( 'wpjc_place' => array_values( array_diff( $wpjc_srch['places'], array( $wpjc_place ) ) ) ) );
				}
				if ( '' !== $wpjc_srch['location'] ) {
					$wpjc_chips['wpjc_loc'] = array( 'pin', $wpjc_srch['location'] );
				}
				if ( $wpjc_srch['remote'] ) {
					$wpjc_chips['wpjc_remote'] = array( 'globe', __( 'Remote possible', 'jobcore' ) );
				}
				if ( $wpjc_srch['days'] ) {
					$wpjc_chips['wpjc_days'] = array( 'clock', wpjc_posted_options()[ $wpjc_srch['days'] ] );
				}
				?>
				<?php if ( $wpjc_chips ) : ?>
					<ul class="wpjc-chips" aria-label="<?php esc_attr_e( 'Active filters', 'jobcore' ); ?>">
						<?php foreach ( $wpjc_chips as $wpjc_param => $wpjc_chip ) : ?>
							<li>
								<a class="wpjc-chip" href="<?php echo esc_url( add_query_arg( array_filter( isset( $wpjc_chip[2] ) ? array_merge( $filters, $wpjc_chip[2] ) : array_diff_key( $filters, array( $wpjc_param => 1 ) ) ), $wpjc_pages ) ); ?>">
									<?php echo wpjc_icon( $wpjc_chip[0], 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
									<span><?php echo esc_html( $wpjc_chip[1] ); ?></span>
									<span class="wpjc-chip__x" aria-hidden="true"><?php echo wpjc_icon( 'close', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<span class="sr-only screen-reader-text">
										<?php
										/* translators: %s: filter, e.g. Berlin */
										echo esc_html( sprintf( __( 'Remove filter: %s', 'jobcore' ), $wpjc_chip[1] ) );
										?>
									</span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php if ( $query->have_posts() ) : ?>
					<ul class="wpjc-jobs wpjc-jobs--grid">
						<?php
						foreach ( $query->posts as $wpjc_i => $wpjc_job ) {
							wpjc_template( 'card', array( 'post' => $wpjc_job ) );
							/**
							 * After a job card in the results list (e.g. an in-feed ad as an <li>).
							 *
							 * @param int $position 1-based position on the page.
							 * @param int $count    Cards on the page.
							 */
							do_action( 'wpjc_jobs_list_after_card', $wpjc_i + 1, count( $query->posts ) );
						}
						?>
					</ul>
					<?php if ( $query->max_num_pages > 1 ) : ?>
						<nav class="wpjc-pager" aria-label="<?php esc_attr_e( 'Jobs pages', 'jobcore' ); ?>">
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => esc_url_raw( add_query_arg( 'wpjc_page', '%#%', $wpjc_pages ) ),
										'format'    => '',
										'current'   => $paged,
										'total'     => (int) $query->max_num_pages,
										'add_args'  => $filters,
										'prev_text' => '&larr;',
										'next_text' => '&rarr;',
									)
								)
							);
							?>
						</nav>
					<?php endif; ?>
				<?php else : ?>
					<p class="wpjc-empty"><?php echo wpjc_filters_active( $wpjc_srch ) ? esc_html__( 'No jobs matched your search.', 'jobcore' ) : esc_html__( 'No jobs yet. Check back soon.', 'jobcore' ); ?></p>
				<?php endif; ?>
			</section>

		<?php endif; ?>
	</div>
</div>
