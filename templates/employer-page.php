<?php
/**
 * Employer profile: header card, about, open and recently closed jobs, company details, follow.
 *
 * Override in a theme: wp-job-core/employer-page.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

$wpjc_term = get_queried_object();
if ( ! $wpjc_term instanceof WP_Term ) {
	return;
}
$wpjc_emp    = wpjc_employer_profile( $wpjc_term );
$wpjc_brand  = wpjc_brand();
$wpjc_count  = count( $wpjc_emp['open'] );
$wpjc_link   = get_term_link( $wpjc_term );
$wpjc_link   = is_wp_error( $wpjc_link ) ? '' : $wpjc_link;
$wpjc_follow = isset( $_GET['wpjc_follow'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_follow'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag.
$wpjc_active = wpjc_company_is_active( $wpjc_term->term_id );

$wpjc_facts = array();
if ( $wpjc_emp['legal_name'] && $wpjc_emp['legal_name'] !== $wpjc_emp['name'] ) {
	$wpjc_facts[] = array( 'building', __( 'Company', 'jobcore' ), esc_html( $wpjc_emp['legal_name'] ) );
}
if ( $wpjc_emp['reg_no'] ) {
	$wpjc_facts[] = array( 'id', __( 'Registration no.', 'jobcore' ), esc_html( $wpjc_emp['reg_no'] ) );
}
if ( $wpjc_emp['vat'] ) {
	$wpjc_facts[] = array( 'receipt', __( 'VAT number', 'jobcore' ), esc_html( $wpjc_emp['vat'] ) );
}
if ( $wpjc_emp['address'] ) {
	$wpjc_lines = array_slice( $wpjc_emp['address'], $wpjc_emp['legal_name'] ? 1 : 0 );
	$wpjc_html  = implode( '<br>', array_map( 'esc_html', $wpjc_lines ) );
	if ( $wpjc_emp['map'] ) {
		$wpjc_html .= '<a class="wpjc-ep-facts__more" href="' . esc_url( $wpjc_emp['map'] ) . '" target="_blank" rel="noopener noreferrer">' . wpjc_icon( 'map', 13 ) . esc_html__( 'Show on map', 'jobcore' ) . '</a>';
	}
	if ( $wpjc_lines ) {
		$wpjc_facts[] = array( 'pin', __( 'Address', 'jobcore' ), $wpjc_html );
	}
}
if ( $wpjc_emp['website'] ) {
	$wpjc_facts[] = array( 'globe', __( 'Website', 'jobcore' ), '<a href="' . esc_url( $wpjc_emp['website'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $wpjc_emp['site_label'] ) . '</a>' );
}
if ( $wpjc_emp['linkedin'] ) {
	$wpjc_facts[] = array( 'linkedin', 'LinkedIn', '<a href="' . esc_url( $wpjc_emp['linkedin'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Company page', 'jobcore' ) . '</a>' );
}

$wpjc_schema = array_filter(
	array(
		'@context'  => 'https://schema.org',
		'@type'     => 'Organization',
		'name'      => $wpjc_emp['name'],
		'legalName' => $wpjc_emp['legal_name'],
		'url'       => $wpjc_emp['website'] ? $wpjc_emp['website'] : $wpjc_link,
		'logo'      => $wpjc_emp['logo'],
		'sameAs'    => array_values( array_filter( array( $wpjc_emp['linkedin'] ) ) ),
		'vatID'     => $wpjc_emp['vat'],
	)
);

wpjc_get_header();
?>
<main id="main" class="wpjc-single-page wpjc-ep">
	<div class="wpjc-ep__band" aria-hidden="true"></div>

	<div class="wpjc-wrap">
		<nav class="wpjc-crumbs wpjc-ep__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'jobcore' ); ?>">
			<a href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php esc_html_e( 'Jobs', 'jobcore' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<a href="<?php echo esc_url( wpjc_view_url( 'employers' ) ); ?>"><?php esc_html_e( 'Employers', 'jobcore' ); ?></a>
			<span aria-hidden="true">&rsaquo;</span>
			<span aria-current="page"><?php echo esc_html( $wpjc_emp['name'] ); ?></span>
		</nav>

		<header class="wpjc-ep-card">
			<div class="wpjc-ep-card__main">
				<?php echo wpjc_logo_html( $wpjc_emp, 'wpjc-ep-card__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<div class="wpjc-ep-card__text">
					<h1 class="wpjc-ep-card__name">
						<?php echo esc_html( $wpjc_emp['name'] ); ?>
						<?php if ( $wpjc_emp['featured'] ) : ?>
							<span class="wpjc-ep-badge"><?php echo wpjc_icon( 'star', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Featured employer', 'jobcore' ); ?></span>
						<?php endif; ?>
					</h1>
					<p class="wpjc-ep-card__meta">
						<?php if ( $wpjc_emp['base'] ) : ?>
							<span><?php echo wpjc_icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_emp['base'] ); ?></span>
						<?php endif; ?>
						<?php if ( $wpjc_emp['website'] ) : ?>
							<a href="<?php echo esc_url( $wpjc_emp['website'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo wpjc_icon( 'globe', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_emp['site_label'] ); ?></a>
						<?php endif; ?>
						<?php if ( $wpjc_emp['linkedin'] ) : ?>
							<a href="<?php echo esc_url( $wpjc_emp['linkedin'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo wpjc_icon( 'linkedin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>LinkedIn</a>
						<?php endif; ?>
					</p>
				</div>
				<div class="wpjc-ep-card__actions" id="wpjc-follow">
					<?php wpjc_follow_button( $wpjc_term->term_id ); ?>
					<?php if ( $wpjc_link ) : ?>
						<button type="button" class="wpjc-share__btn" data-wpjc-copy="<?php echo esc_url( $wpjc_link ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'jobcore' ); ?>" title="<?php esc_attr_e( 'Copy link', 'jobcore' ); ?>"><?php echo wpjc_icon( 'link', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
					<?php endif; ?>
				</div>
			</div>

			<dl class="wpjc-ep-stats">
				<div>
					<dt><?php esc_html_e( 'Open jobs', 'jobcore' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $wpjc_count ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Hiring in', 'jobcore' ); ?></dt>
					<dd>
						<?php
						$wpjc_places = array_keys( $wpjc_emp['cities'] );
						echo esc_html(
							$wpjc_places
								/* translators: %d: number of other places */
								? $wpjc_places[0] . ( count( $wpjc_places ) > 1 ? ' ' . sprintf( __( '+%d', 'jobcore' ), count( $wpjc_places ) - 1 ) : '' )
								: '—'
						);
						?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Fields', 'jobcore' ); ?></dt>
					<dd><?php echo esc_html( $wpjc_emp['categories'] ? $wpjc_emp['categories'][0]['name'] . ( count( $wpjc_emp['categories'] ) > 1 ? ' +' . ( count( $wpjc_emp['categories'] ) - 1 ) : '' ) : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'Last job posted', 'jobcore' ); ?></dt>
					<dd>
						<?php
						echo esc_html(
							$wpjc_emp['latest']
								/* translators: %s: time span, e.g. "2 days" */
								? sprintf( __( '%s ago', 'jobcore' ), human_time_diff( $wpjc_emp['latest'] ) )
								: '—'
						);
						?>
					</dd>
				</div>
			</dl>
		</header>

		<?php if ( 'on' === $wpjc_follow ) : ?>
			<p class="wpjc-notice wpjc-notice--ok wpjc-ep__notice" role="status">
				<?php
				/* translators: %s: employer name */
				echo esc_html( sprintf( __( 'You now follow %s. We e-mail you as soon as they post a new job.', 'jobcore' ), $wpjc_emp['name'] ) );
				?>
				<a href="<?php echo esc_url( wpjc_account_url( 'alerts' ) ); ?>"><?php esc_html_e( 'Manage alerts', 'jobcore' ); ?></a>
			</p>
		<?php elseif ( 'off' === $wpjc_follow ) : ?>
			<p class="wpjc-notice wpjc-notice--info wpjc-ep__notice" role="status">
				<?php
				/* translators: %s: employer name */
				echo esc_html( sprintf( __( 'You no longer follow %s.', 'jobcore' ), $wpjc_emp['name'] ) );
				?>
			</p>
		<?php elseif ( 'limit' === $wpjc_follow ) : ?>
			<p class="wpjc-notice wpjc-notice--warn wpjc-ep__notice" role="alert">
				<?php esc_html_e( 'You have reached the maximum number of job alerts. Remove one to follow this company.', 'jobcore' ); ?>
				<a href="<?php echo esc_url( wpjc_account_url( 'alerts' ) ); ?>"><?php esc_html_e( 'Manage alerts', 'jobcore' ); ?></a>
			</p>
		<?php endif; ?>

		<?php if ( ! $wpjc_active ) : ?>
			<p class="wpjc-notice wpjc-notice--warn wpjc-ep__notice" role="status"><?php esc_html_e( 'This company is switched off: only you can see this page and its jobs are hidden.', 'jobcore' ); ?></p>
		<?php endif; ?>

		<div class="wpjc-ep-grid">
			<div class="wpjc-ep-grid__main">
				<?php if ( '' !== trim( $wpjc_emp['about'] ) ) : ?>
					<section class="wpjc-ep-box">
						<h2 class="wpjc-ep-box__title">
							<?php
							/* translators: %s: employer name */
							echo esc_html( sprintf( __( 'About %s', 'jobcore' ), $wpjc_emp['name'] ) );
							?>
						</h2>
						<div class="wpjc-ep-about"><?php echo wp_kses_post( wpautop( $wpjc_emp['about'] ) ); ?></div>
					</section>
				<?php endif; ?>

				<section class="wpjc-ep-sec" aria-labelledby="wpjc-ep-open">
					<h2 class="wpjc-section__title" id="wpjc-ep-open">
						<?php esc_html_e( 'Open positions', 'jobcore' ); ?>
						<span class="wpjc-count"><?php echo esc_html( number_format_i18n( $wpjc_count ) ); ?></span>
					</h2>
					<?php if ( $wpjc_emp['open'] ) : ?>
						<ul class="wpjc-jobs wpjc-jobs--grid wpjc-jobs--2">
							<?php foreach ( $wpjc_emp['open'] as $wpjc_job ) : ?>
								<?php wpjc_template( 'card', array( 'post' => $wpjc_job ) ); ?>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<div class="wpjc-ep-empty">
							<span class="wpjc-ep-empty__icon"><?php echo wpjc_icon( 'briefcase', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
							<p><b><?php esc_html_e( 'No open positions right now', 'jobcore' ); ?></b>
							<?php
							/* translators: %s: employer name */
							echo esc_html( sprintf( __( 'Follow %s and we tell you about their next job.', 'jobcore' ), $wpjc_emp['name'] ) );
							?>
							</p>
						</div>
					<?php endif; ?>
				</section>

				<?php if ( $wpjc_emp['closed'] ) : ?>
					<section class="wpjc-ep-sec" aria-labelledby="wpjc-ep-closed">
						<h2 class="wpjc-section__title" id="wpjc-ep-closed"><?php esc_html_e( 'Recently closed', 'jobcore' ); ?></h2>
						<ul class="wpjc-ep-closed">
							<?php foreach ( $wpjc_emp['closed'] as $wpjc_job ) : ?>
								<?php $wpjc_where = wpjc_meta( $wpjc_job, 'location' ); ?>
								<li>
									<a href="<?php echo esc_url( get_permalink( $wpjc_job ) ); ?>">
										<b><?php echo esc_html( get_the_title( $wpjc_job ) ); ?></b>
										<?php if ( $wpjc_where ) : ?>
											<span><?php echo esc_html( $wpjc_where ); ?></span>
										<?php endif; ?>
									</a>
									<span class="wpjc-ep-closed__when">
										<?php
										/* translators: %s: date */
										echo esc_html( sprintf( __( 'Closed %s', 'jobcore' ), wp_date( 'd.m.Y', wpjc_job_closed_on( $wpjc_job ) ) ) );
										?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
			</div>

			<aside class="wpjc-ep-grid__side">
				<?php do_action( 'wpjc_employer_side_start', $wpjc_emp ); ?>
				<?php if ( $wpjc_facts ) : ?>
					<section class="wpjc-ep-box">
						<h2 class="wpjc-ep-box__title"><?php esc_html_e( 'Company details', 'jobcore' ); ?></h2>
						<dl class="wpjc-ep-facts">
							<?php foreach ( $wpjc_facts as $wpjc_fact ) : ?>
								<div>
									<span class="wpjc-ep-facts__ico"><?php echo wpjc_icon( $wpjc_fact[0], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
									<dt><?php echo esc_html( $wpjc_fact[1] ); ?></dt>
									<dd><?php echo $wpjc_fact[2]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped when built above. ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>

				<?php if ( $wpjc_emp['cities'] || $wpjc_emp['categories'] ) : ?>
					<section class="wpjc-ep-box">
						<?php if ( $wpjc_emp['cities'] ) : ?>
							<h2 class="wpjc-ep-box__title"><?php esc_html_e( 'Hiring in', 'jobcore' ); ?></h2>
							<ul class="wpjc-ep-chips">
								<?php foreach ( $wpjc_emp['cities'] as $wpjc_city => $wpjc_n ) : ?>
									<li><span class="wpjc-ep-chip"><?php echo wpjc_icon( 'pin', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_city ); ?><small><?php echo esc_html( number_format_i18n( $wpjc_n ) ); ?></small></span></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
						<?php if ( $wpjc_emp['categories'] ) : ?>
							<h2 class="wpjc-ep-box__title"><?php esc_html_e( 'Fields', 'jobcore' ); ?></h2>
							<ul class="wpjc-ep-chips">
								<?php foreach ( $wpjc_emp['categories'] as $wpjc_cat ) : ?>
									<li><a class="wpjc-ep-chip" href="<?php echo esc_url( $wpjc_cat['url'] ); ?>"><?php echo wpjc_icon( 'tag', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_cat['name'] ); ?><small><?php echo esc_html( number_format_i18n( $wpjc_cat['count'] ) ); ?></small></a></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="wpjc-ep-box wpjc-ep-cta">
					<span class="wpjc-ep-cta__icon"><?php echo wpjc_icon( 'bell', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
					<h2 class="wpjc-ep-box__title"><?php esc_html_e( 'Never miss a job', 'jobcore' ); ?></h2>
					<p>
						<?php
						/* translators: %s: employer name */
						echo esc_html( sprintf( __( 'Get an e-mail as soon as %s posts a new job.', 'jobcore' ), $wpjc_emp['name'] ) );
						?>
					</p>
					<?php wpjc_follow_button( $wpjc_term->term_id, 'wpjc-btn--block' ); ?>
				</section>

				<?php if ( $wpjc_brand['email'] || $wpjc_brand['phone'] ) : ?>
					<section class="wpjc-ep-box wpjc-ep-help">
						<?php
						$wpjc_help_title = trim( (string) wpjc_opt( 'help_title' ) );
						$wpjc_help_text  = trim( (string) wpjc_opt( 'help_text' ) );
						?>
						<h2 class="wpjc-ep-box__title"><?php echo esc_html( '' !== $wpjc_help_title ? $wpjc_help_title : __( 'Questions about an ad?', 'jobcore' ) ); ?></h2>
						<p><?php echo esc_html( '' !== $wpjc_help_text ? str_replace( '{board}', $wpjc_brand['name'], $wpjc_help_text ) : sprintf( /* translators: %s: board name */ __( 'The %s team is happy to help.', 'jobcore' ), $wpjc_brand['name'] ) ); ?></p>
						<ul>
							<?php if ( $wpjc_brand['email'] ) : ?>
								<li><a href="<?php echo esc_url( 'mailto:' . $wpjc_brand['email'] ); ?>"><?php echo wpjc_icon( 'email', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_brand['email'] ); ?></a></li>
							<?php endif; ?>
							<?php if ( $wpjc_brand['phone'] ) : ?>
								<li><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^\d+]/', '', $wpjc_brand['phone'] ) ); ?>"><?php echo wpjc_icon( 'phone', 15 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_brand['phone'] ); ?></a></li>
							<?php endif; ?>
						</ul>
					</section>
				<?php endif; ?>
				<?php do_action( 'wpjc_employer_side_end', $wpjc_emp ); ?>
			</aside>
		</div>
	</div>
	<script type="application/ld+json"><?php echo wp_json_encode( $wpjc_schema, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE ); ?></script>
</main>
<?php
wpjc_get_footer();
