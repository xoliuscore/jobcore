<?php
/**
 * Single job: header card, description, details, apply box, related jobs.
 *
 * Override in a theme: wp-job-core/single-job.php.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

wpjc_get_header();

while ( have_posts() ) :
	the_post();
	$wpjc_id       = get_the_ID();
	$wpjc_company  = wpjc_job_company( $wpjc_id );
	$wpjc_location = wpjc_meta( $wpjc_id, 'location' );
	$wpjc_remote   = (bool) wpjc_meta( $wpjc_id, 'remote' );
	$wpjc_expires  = wpjc_meta( $wpjc_id, 'expires' );
	$wpjc_types    = get_the_terms( $wpjc_id, 'wpjc_job_type' );
	$wpjc_cats     = get_the_terms( $wpjc_id, 'wpjc_job_category' );
	$wpjc_cat      = ( $wpjc_cats && ! is_wp_error( $wpjc_cats ) ) ? $wpjc_cats[0] : null;
	$wpjc_related  = wpjc_related_jobs( $wpjc_id );
	$wpjc_link     = get_permalink();
	$wpjc_hero     = has_post_thumbnail();
	$wpjc_applied  = isset( $_GET['wpjc_applied'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_applied'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only status flag.
	$wpjc_me       = wp_get_current_user();
	$wpjc_sent     = wpjc_user_application_for( $wpjc_id, $wpjc_me->ID );

	$wpjc_share = array(
		'facebook' => array( __( 'Share on Facebook', 'jobcore' ), 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $wpjc_link ) ),
		'linkedin' => array( __( 'Share on LinkedIn', 'jobcore' ), 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $wpjc_link ) ),
		'email'    => array( __( 'Send by e-mail', 'jobcore' ), 'mailto:?subject=' . rawurlencode( get_the_title() ) . '&body=' . rawurlencode( $wpjc_link ) ),
	);

	$wpjc_details = array();
	if ( $wpjc_location ) {
		$wpjc_details[ __( 'Location', 'jobcore' ) ] = esc_html( $wpjc_location ) . ( $wpjc_remote ? ' · ' . esc_html__( 'Remote possible', 'jobcore' ) : '' );
	} elseif ( $wpjc_remote ) {
		$wpjc_details[ __( 'Location', 'jobcore' ) ] = esc_html__( 'Remote', 'jobcore' );
	}
	if ( $wpjc_company['name'] ) {
		$wpjc_details[ __( 'Employer', 'jobcore' ) ] = $wpjc_company['url']
			? sprintf( '<a href="%s">%s</a>', esc_url( $wpjc_company['url'] ), esc_html( $wpjc_company['name'] ) )
			: esc_html( $wpjc_company['name'] );
	}
	if ( $wpjc_cat ) {
		$wpjc_details[ __( 'Category', 'jobcore' ) ] = sprintf( '<a href="%s">%s</a>', esc_url( get_term_link( $wpjc_cat ) ), esc_html( $wpjc_cat->name ) );
	}
	if ( $wpjc_types && ! is_wp_error( $wpjc_types ) ) {
		$wpjc_details[ __( 'Job type', 'jobcore' ) ] = esc_html( implode( ', ', wp_list_pluck( $wpjc_types, 'name' ) ) );
	}
	$wpjc_details[ __( 'Published', 'jobcore' ) ] = esc_html( wp_date( 'd.m.Y', (int) get_post_time( 'U' ) ) );
	if ( $wpjc_expires ) {
		$wpjc_details[ __( 'Open until', 'jobcore' ) ] = esc_html( wp_date( 'd.m.Y', (int) strtotime( $wpjc_expires . ' 12:00:00' ) ) );
	}
	if ( $wpjc_company['website'] ) {
		$wpjc_details[ __( 'Website', 'jobcore' ) ] = sprintf(
			'<a href="%1$s" rel="noopener noreferrer" target="_blank">%2$s</a>',
			esc_url( $wpjc_company['website'] ),
			esc_html( preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $wpjc_company['website'] ) ) )
		);
	}
	$wpjc_details = (array) apply_filters( 'wpjc_single_details', $wpjc_details, $wpjc_id );
	?>
	<main id="main" class="wpjc-single-page">
		<div class="wpjc-wrap wpjc-single-wrap">
		<nav class="wpjc-crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'jobcore' ); ?>">
			<a href="<?php echo esc_url( wpjc_home_url() ); ?>"><?php esc_html_e( 'Jobs', 'jobcore' ); ?></a>
			<?php if ( $wpjc_cat ) : ?>
				<span aria-hidden="true">&rsaquo;</span>
				<a href="<?php echo esc_url( get_term_link( $wpjc_cat ) ); ?>"><?php echo esc_html( $wpjc_cat->name ); ?></a>
			<?php endif; ?>
			<span aria-hidden="true">&rsaquo;</span>
			<span aria-current="page"><?php the_title(); ?></span>
		</nav>

		<article <?php post_class( 'wpjc-single' ); ?>>
			<header class="wpjc-single__head">
				<?php echo wpjc_logo_html( $wpjc_company, 'wpjc-single__logo', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
				<h1 class="wpjc-single__title"><?php the_title(); ?></h1>
				<p class="wpjc-single__sub">
					<?php
					echo esc_html(
						implode(
							' · ',
							array_filter( array( $wpjc_company['name'], $wpjc_location, wpjc_job_dates( $wpjc_id ) ) )
						)
					);
					?>
				</p>
				<?php if ( ! wpjc_can_apply() ) : ?>
					<p class="wpjc-single__closed"><?php esc_html_e( 'Applications for this job are closed.', 'jobcore' ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( $wpjc_hero ) : ?>
				<?php
				$wpjc_media = wp_get_attachment_image_src( (int) get_post_thumbnail_id(), 'full' );
				$wpjc_ratio = $wpjc_media && $wpjc_media[1] && $wpjc_media[2] ? round( $wpjc_media[1] / $wpjc_media[2], 4 ) : 0;
				?>
				<figure class="wpjc-single__media"<?php echo $wpjc_ratio ? ' style="aspect-ratio:' . esc_attr( (string) $wpjc_ratio ) . '"' : ''; ?>>
					<?php
					the_post_thumbnail(
						'full',
						array(
							'alt'   => '',
							'sizes' => '(max-width: 860px) 100vw, 820px',
						)
					);
					?>
				</figure>
			<?php endif; ?>

			<div class="wpjc-single__bar">
				<span><?php echo esc_html( wp_date( 'd.m.Y', (int) get_post_time( 'U' ) ) ); ?></span>
				<span class="wpjc-share">
					<?php foreach ( $wpjc_share as $wpjc_net => $wpjc_item ) : ?>
						<a class="wpjc-share__btn wpjc-share__btn--<?php echo esc_attr( $wpjc_net ); ?>" href="<?php echo esc_url( $wpjc_item[1] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $wpjc_item[0] ); ?>"><?php echo wpjc_icon( $wpjc_net ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endforeach; ?>
					<button type="button" class="wpjc-share__btn" data-wpjc-copy="<?php echo esc_url( $wpjc_link ); ?>" aria-label="<?php esc_attr_e( 'Copy link', 'jobcore' ); ?>"><?php echo wpjc_icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				</span>
			</div>

			<div class="wpjc-single__desc">
				<?php the_content(); ?>
			</div>

			<?php do_action( 'wpjc_single_after_desc', get_post() ); ?>

			<dl class="wpjc-details">
				<?php foreach ( $wpjc_details as $wpjc_label => $wpjc_value ) : ?>
					<div class="wpjc-details__row">
						<dt><?php echo esc_html( $wpjc_label ); ?></dt>
						<dd><?php echo wp_kses_post( $wpjc_value ); ?></dd>
					</div>
				<?php endforeach; ?>
			</dl>
		</article>

		<?php if ( wpjc_can_apply() || $wpjc_applied ) : ?>
			<section class="wpjc-applybox" id="wpjc-apply" aria-labelledby="wpjc-apply-title">
				<h2 class="wpjc-applybox__title" id="wpjc-apply-title"><?php esc_html_e( 'Apply for this job', 'jobcore' ); ?></h2>

				<div class="wpjc-applybox__job">
					<?php echo wpjc_logo_html( $wpjc_company, 'wpjc-applybox__logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper. ?>
					<div>
						<p class="wpjc-applybox__name"><?php the_title(); ?></p>
						<?php if ( $wpjc_company['name'] ) : ?>
							<p class="wpjc-applybox__company"><?php echo esc_html( $wpjc_company['name'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '1' === $wpjc_applied ) : ?>
					<p class="wpjc-notice wpjc-notice--ok" role="status">
						<?php esc_html_e( 'Your application was sent. Good luck!', 'jobcore' ); ?>
						<?php if ( $wpjc_me->exists() ) : ?>
							<a href="<?php echo esc_url( wpjc_account_url( 'applications' ) ); ?>"><?php esc_html_e( 'My applications', 'jobcore' ); ?></a>
						<?php endif; ?>
					</p>
				<?php elseif ( $wpjc_sent && ! $wpjc_applied ) : ?>
					<p class="wpjc-notice wpjc-notice--info" role="status">
						<?php
						/* translators: %s: date */
						echo esc_html( sprintf( __( 'You applied for this job on %s.', 'jobcore' ), wp_date( 'd.m.Y', (int) get_post_time( 'U', true, $wpjc_sent ) ) ) );
						?>
						<a href="<?php echo esc_url( wpjc_account_url( 'applications' ) ); ?>"><?php esc_html_e( 'My applications', 'jobcore' ); ?></a>
					</p>
				<?php elseif ( $wpjc_applied ) : ?>
					<p class="wpjc-notice wpjc-notice--warn" role="alert"><?php echo esc_html( wpjc_apply_error_message( $wpjc_applied ) ); ?></p>
				<?php endif; ?>

				<?php
				if ( wpjc_can_apply() && '1' !== $wpjc_applied ) :
					$wpjc_apply_url = wpjc_meta( $wpjc_id, 'apply_url' );
					if ( $wpjc_apply_url ) :
						?>
						<p class="wpjc-applybox__ext">
							<a class="wpjc-btn wpjc-btn--block" href="<?php echo esc_url( $wpjc_apply_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Apply on the employer’s site', 'jobcore' ); ?></a>
						</p>
					<?php else : ?>
						<form class="wpjc-apply" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="wpjc_apply">
							<input type="hidden" name="job_id" value="<?php echo esc_attr( (string) $wpjc_id ); ?>">
							<?php wp_nonce_field( 'wpjc_apply_' . $wpjc_id, 'wpjc_apply_nonce' ); ?>
							<p class="wpjc-hp" aria-hidden="true">
								<label for="wpjc_website_hp"><?php esc_html_e( 'Leave empty', 'jobcore' ); ?></label>
								<input type="text" id="wpjc_website_hp" name="wpjc_website_hp" tabindex="-1" autocomplete="off">
							</p>

							<div class="wpjc-apply__grid">
								<p>
									<label for="wpjc_first_name"><?php esc_html_e( 'First name', 'jobcore' ); ?> <span aria-hidden="true">*</span></label>
									<input required type="text" id="wpjc_first_name" name="applicant_first_name" autocomplete="given-name" maxlength="80" value="<?php echo esc_attr( (string) $wpjc_me->first_name ); ?>">
								</p>
								<p>
									<label for="wpjc_last_name"><?php esc_html_e( 'Last name', 'jobcore' ); ?> <span aria-hidden="true">*</span></label>
									<input required type="text" id="wpjc_last_name" name="applicant_last_name" autocomplete="family-name" maxlength="80" value="<?php echo esc_attr( (string) $wpjc_me->last_name ); ?>">
								</p>
								<p>
									<label for="wpjc_email"><?php esc_html_e( 'E-mail', 'jobcore' ); ?> <span aria-hidden="true">*</span></label>
									<input required type="email" id="wpjc_email" name="applicant_email" autocomplete="email" maxlength="120" value="<?php echo esc_attr( (string) $wpjc_me->user_email ); ?>">
								</p>
								<p>
									<label for="wpjc_phone"><?php esc_html_e( 'Phone', 'jobcore' ); ?> <span aria-hidden="true">*</span></label>
									<input required type="tel" id="wpjc_phone" name="applicant_phone" autocomplete="tel" maxlength="40">
								</p>
							</div>

							<p>
								<label for="wpjc_message"><?php esc_html_e( 'Cover letter', 'jobcore' ); ?> <span aria-hidden="true">*</span></label>
								<textarea required id="wpjc_message" name="applicant_message" rows="9" maxlength="5000" aria-describedby="wpjc_message_hint"><?php echo esc_textarea( wpjc_cover_letter_template( $wpjc_id ) ); ?></textarea>
								<span class="wpjc-apply__hint" id="wpjc_message_hint"><?php esc_html_e( 'Write as precisely as you can why you are applying for this position, and briefly introduce yourself.', 'jobcore' ); ?></span>
							</p>

							<?php $wpjc_limits = wpjc_apply_limits(); ?>
							<div class="wpjc-upload" data-wpjc-upload data-max-files="<?php echo esc_attr( (string) $wpjc_limits['files'] ); ?>" data-max-mb="<?php echo esc_attr( (string) $wpjc_limits['docs_mb'] ); ?>">
								<p class="wpjc-upload__head">
									<?php echo wpjc_icon( 'file', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
									<span class="wpjc-upload__title"><?php esc_html_e( 'Documents (CV, references, cover letter)', 'jobcore' ); ?></span>
									<span class="wpjc-upload__opt"><?php echo wpjc_icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Optional', 'jobcore' ); ?></span>
								</p>
								<label class="wpjc-drop" for="wpjc_docs">
									<span class="wpjc-drop__cta"><?php echo wpjc_icon( 'plus', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Add documents', 'jobcore' ); ?></span>
									<span class="wpjc-drop__hint">
										<?php
										/* translators: 1: max number of files, 2: max total size in MB */
										echo esc_html( sprintf( __( 'PDF, DOC or DOCX · up to %1$d files · %2$d MB in total', 'jobcore' ), $wpjc_limits['files'], $wpjc_limits['docs_mb'] ) );
										?>
									</span>
								</label>
								<input class="wpjc-upload__input" type="file" id="wpjc_docs" name="applicant_docs[]" multiple accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
								<ul class="wpjc-upload__list" data-wpjc-upload-list></ul>
								<p class="wpjc-upload__error" data-wpjc-upload-error role="alert" hidden></p>
							</div>

							<div class="wpjc-upload wpjc-upload--photo" data-wpjc-upload data-max-files="1" data-max-mb="<?php echo esc_attr( (string) $wpjc_limits['photo_mb'] ); ?>">
								<p class="wpjc-upload__head">
									<?php echo wpjc_icon( 'image', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
									<span class="wpjc-upload__title"><?php esc_html_e( 'Your photo', 'jobcore' ); ?></span>
									<span class="wpjc-upload__opt"><?php echo wpjc_icon( 'info', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Optional', 'jobcore' ); ?></span>
								</p>
								<label class="wpjc-btn wpjc-btn--ghost wpjc-upload__btn" for="wpjc_photo"><?php echo wpjc_icon( 'plus', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Add photo', 'jobcore' ); ?></label>
								<input class="wpjc-upload__input" type="file" id="wpjc_photo" name="applicant_photo" accept="image/jpeg,image/png,image/gif">
								<span class="wpjc-drop__hint">
									<?php
									/* translators: %d: max size in MB */
									echo esc_html( sprintf( __( 'JPG, PNG or GIF · up to %d MB · not required.', 'jobcore' ), $wpjc_limits['photo_mb'] ) );
									?>
								</span>
								<ul class="wpjc-upload__list" data-wpjc-upload-list></ul>
								<p class="wpjc-upload__error" data-wpjc-upload-error role="alert" hidden></p>
							</div>

							<?php do_action( 'wpjc_apply_form_fields', get_post( $wpjc_id ) ); ?>

							<?php if ( $wpjc_me->exists() ) : ?>
								<p class="wpjc-apply__check">
									<label><input type="checkbox" name="wpjc_alert_similar" value="1"> <?php esc_html_e( 'E-mail me new jobs like this one (a daily job alert you can change in your account).', 'jobcore' ); ?></label>
								</p>
							<?php else : ?>
								<p class="wpjc-apply__check wpjc-apply__check--guest">
									<?php
									printf(
										/* translators: %s: sign-in link */
										esc_html__( '%s to keep track of your applications and get job alerts.', 'jobcore' ),
										'<a href="' . esc_url( wpjc_login_url( get_permalink() . '#wpjc-apply' ) ) . '">' . esc_html__( 'Sign in', 'jobcore' ) . '</a>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
									);
									?>
								</p>
							<?php endif; ?>

							<p class="wpjc-apply__send">
								<button type="submit" class="wpjc-btn wpjc-btn--lg"><?php echo wpjc_icon( 'send', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Send application', 'jobcore' ); ?></button>
							</p>
							<p class="wpjc-apply__legal">
								<?php
								$wpjc_privacy = get_privacy_policy_url();
								$wpjc_policy  = $wpjc_privacy
									? '<a href="' . esc_url( $wpjc_privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'jobcore' ) . '</a>'
									: esc_html__( 'privacy policy', 'jobcore' );
								printf(
									/* translators: %s: privacy policy link */
									esc_html__( 'By sending your application you confirm that you are at least 16 years old and accept the %s. Your details go only to this employer, and we send you a copy of the application by e-mail.', 'jobcore' ),
									$wpjc_policy // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
								);
								?>
							</p>
						</form>
					<?php endif; ?>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php do_action( 'wpjc_single_after_apply', get_post() ); ?>

		<?php if ( $wpjc_related['employer'] ) : ?>
			<section class="wpjc-related">
				<h2 class="wpjc-section__title">
					<?php
					/* translators: %s: employer name */
					echo esc_html( sprintf( __( 'More jobs from %s', 'jobcore' ), $wpjc_company['name'] ) );
					?>
				</h2>
				<ul class="wpjc-jobs wpjc-jobs--grid wpjc-jobs--2">
					<?php foreach ( $wpjc_related['employer'] as $wpjc_job ) : ?>
						<?php wpjc_template( 'card', array( 'post' => $wpjc_job ) ); ?>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>

		<?php if ( $wpjc_related['similar'] ) : ?>
			<section class="wpjc-related">
				<h2 class="wpjc-section__title"><?php esc_html_e( 'Similar jobs', 'jobcore' ); ?></h2>
				<ul class="wpjc-jobs wpjc-jobs--grid wpjc-jobs--2">
					<?php foreach ( $wpjc_related['similar'] as $wpjc_job ) : ?>
						<?php wpjc_template( 'card', array( 'post' => $wpjc_job ) ); ?>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endif; ?>
		</div>
	</main>
	<?php
endwhile;

wpjc_get_footer();
