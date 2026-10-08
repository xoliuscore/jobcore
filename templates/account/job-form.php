<?php
/**
 * Jobs account: post / edit a job ad.
 *
 * @package JobCore
 * @var string     $item      'new' or job ID.
 * @var WP_User    $user      Current user.
 * @var WP_Term[]  $companies Companies.
 * @var array|null $notice    Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_is_new = 'new' === $item;
$wpjc_job    = $wpjc_is_new ? null : get_post( (int) $item );
if ( ! $wpjc_is_new && ! wpjc_user_owns_job( $wpjc_job, $user->ID ) ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'briefcase',
			'title'  => __( 'Job ad not found', 'jobcore' ),
			'text'   => __( 'It is not in your account.', 'jobcore' ),
			'button' => array( __( 'Back to my job ads', 'jobcore' ), wpjc_account_url( 'jobs' ), 'arrow' ),
		)
	);
	return;
}

$wpjc_active = array_values(
	array_filter(
		$companies,
		static function ( $term ) {
			return wpjc_company_is_active( $term->term_id );
		}
	)
);
$wpjc_email  = sanitize_email( (string) wpjc_opt( 'contact_email' ) );
$wpjc_dek    = $wpjc_is_new
	? ( wpjc_opt( 'fe_review' ) ? __( 'Fill in the job ad. We check every new ad quickly before it goes live.', 'jobcore' ) : __( 'Fill in the job ad. It goes live as soon as you send it.', 'jobcore' ) )
	: __( 'Changes are visible on the board right away.', 'jobcore' );
$wpjc_dek    = (string) apply_filters( 'wpjc_job_form_intro', $wpjc_dek, $wpjc_job );

wpjc_template(
	'account/head',
	array(
		'title'  => wpjc_account_title( 'jobs', $item ),
		'dek'    => $wpjc_dek,
		'crumbs' => array( array( __( 'My job ads', 'jobcore' ), wpjc_account_url( 'jobs' ) ) ),
		'notice' => $notice,
	)
);

if ( ! $wpjc_active ) :
	?>
	<div class="wpjc-acc-callout">
		<p class="wpjc-acc-callout__title"><?php echo wpjc_icon( 'building', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Add a company first', 'jobcore' ); ?></p>
		<p><?php echo $companies ? esc_html__( 'Job ads are published under a company, and all your companies are switched off. Switch one on to post a job.', 'jobcore' ) : esc_html__( 'Job ads are published under a company, and your account is not linked to one yet.', 'jobcore' ); ?></p>
		<a class="wpjc-btn wpjc-btn--sm" href="<?php echo esc_url( $companies ? wpjc_account_url( 'companies' ) : wpjc_account_url( 'companies', 'new' ) ); ?>"><span class="wpjc-btn__ico"><?php echo wpjc_icon( 'plus', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span><?php echo $companies ? esc_html__( 'My companies', 'jobcore' ) : esc_html__( 'Add a company', 'jobcore' ); ?></a>
		<?php if ( $wpjc_email ) : ?>
			<p class="wpjc-acc-callout__small">
				<?php
				/* translators: %s: e-mail link */
				printf( esc_html__( 'If you think this is a mistake, write to us at %s.', 'jobcore' ), '<a href="' . esc_url( 'mailto:' . antispambot( $wpjc_email ) ) . '">' . esc_html( antispambot( $wpjc_email ) ) . '</a>' );
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
	return;
endif;

$wpjc_flash  = wpjc_account_flash();
$wpjc_errors = $wpjc_flash['errors'];
$wpjc_v      = array_merge( wpjc_job_values( $wpjc_job ), $wpjc_flash['values'] );
if ( $wpjc_is_new && ! $wpjc_flash['values'] ) {
	$wpjc_v['company']     = $wpjc_active[0]->term_id;
	$wpjc_contact          = wpjc_company_meta( $wpjc_active[0]->term_id, 'contact_email' );
	$wpjc_v['apply_email'] = $wpjc_contact ? $wpjc_contact : $user->user_email;
	$wpjc_v['location']    = wpjc_company_meta( $wpjc_active[0]->term_id, 'city' );
}
$wpjc_max    = wp_date( 'Y-m-d', time() + (int) wpjc_opt( 'fe_days' ) * DAY_IN_SECONDS );
$wpjc_photo  = $wpjc_job ? (string) get_the_post_thumbnail_url( $wpjc_job, 'medium' ) : '';
$wpjc_limits = wpjc_apply_limits();

$wpjc_dropdown = static function ( $taxonomy, $name, $selected, $none, $required ) use ( $wpjc_errors ) {
	$html = wp_dropdown_categories(
		array(
			'taxonomy'          => $taxonomy,
			'name'              => 'job_' . $name,
			'id'                => 'job_' . $name,
			'selected'          => (int) $selected,
			'hide_empty'        => 0,
			'hierarchical'      => 1,
			'show_option_none'  => $none,
			'option_none_value' => $required ? '' : '0',
			'orderby'           => 'name',
			'echo'              => 0,
			'class'             => 'wpjc-input',
			'required'          => $required,
		)
	);
	return in_array( $name, $wpjc_errors, true ) ? str_replace( '<select ', '<select aria-invalid="true" ', $html ) : $html;
};
?>
<form class="wpjc-acc-card wpjc-acc-form wpjc-acc-form--sections" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
	<?php wpjc_account_form_fields( 'job_save' ); ?>
	<input type="hidden" name="job_id" value="<?php echo esc_attr( $wpjc_job ? (string) $wpjc_job->ID : '0' ); ?>">

	<section class="wpjc-acc-sec">
		<div class="wpjc-acc-sec__intro">
			<h2><?php esc_html_e( 'The job', 'jobcore' ); ?></h2>
			<p><?php esc_html_e( 'Title, employer and where the work is.', 'jobcore' ); ?></p>
		</div>
		<div class="wpjc-acc-fields">
			<p class="wpjc-acc-field wpjc-acc-field--wide">
				<label for="job_company"><?php esc_html_e( 'Company', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<select class="wpjc-input" id="job_company" name="job_company" required<?php echo wpjc_field_error( 'company', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
					<?php foreach ( $wpjc_active as $wpjc_term ) : ?>
						<option value="<?php echo esc_attr( (string) $wpjc_term->term_id ); ?>" <?php selected( (int) $wpjc_v['company'], $wpjc_term->term_id ); ?>><?php echo esc_html( $wpjc_term->name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="wpjc-acc-field wpjc-acc-field--wide">
				<label for="job_title"><?php esc_html_e( 'Job title', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<input class="wpjc-input" type="text" id="job_title" name="job_title" value="<?php echo esc_attr( (string) $wpjc_v['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Warehouse Worker (m/f)', 'jobcore' ); ?>" maxlength="120" required<?php echo wpjc_field_error( 'title', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
			</p>
			<p class="wpjc-acc-field">
				<label for="job_category"><?php esc_html_e( 'Category', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<?php echo $wpjc_dropdown( 'wpjc_job_category', 'category', $wpjc_v['category'], __( 'Choose a category', 'jobcore' ), true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?>
			</p>
			<p class="wpjc-acc-field">
				<label for="job_type"><?php esc_html_e( 'Job type', 'jobcore' ); ?></label>
				<?php echo $wpjc_dropdown( 'wpjc_job_type', 'type', $wpjc_v['type'], __( 'Not specified', 'jobcore' ), false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?>
			</p>
			<p class="wpjc-acc-field">
				<label for="job_location"><?php esc_html_e( 'City', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<input class="wpjc-input" type="text" id="job_location" name="job_location" value="<?php echo esc_attr( (string) $wpjc_v['location'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Hamburg', 'jobcore' ); ?>" maxlength="80"<?php echo wpjc_field_error( 'location', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
			</p>
			<p class="wpjc-acc-field wpjc-acc-field--check">
				<label><input type="checkbox" name="job_remote" value="1" <?php checked( '1', (string) $wpjc_v['remote'] ); ?>> <?php esc_html_e( 'Remote work possible', 'jobcore' ); ?></label>
			</p>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<div class="wpjc-acc-sec__intro">
			<h2><?php esc_html_e( 'Description', 'jobcore' ); ?></h2>
			<p><?php esc_html_e( 'Tasks, what you expect and what you offer. Leave an empty line between paragraphs.', 'jobcore' ); ?></p>
		</div>
		<div class="wpjc-acc-fields">
			<p class="wpjc-acc-field wpjc-acc-field--wide">
				<label for="job_description"><?php esc_html_e( 'Job description', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<textarea class="wpjc-input" id="job_description" name="job_description" rows="12" maxlength="20000" required<?php echo wpjc_field_error( 'description', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?> placeholder="<?php esc_attr_e( "Your tasks\n- …\n\nWhat we expect\n- …\n\nWe offer\n- …", 'jobcore' ); ?>"><?php echo esc_textarea( (string) $wpjc_v['description'] ); ?></textarea>
			</p>
			<div class="wpjc-acc-field wpjc-acc-field--wide">
				<span class="wpjc-acc-label"><?php esc_html_e( 'Photo or poster', 'jobcore' ); ?> <span class="wpjc-acc-opt"><?php esc_html_e( '(optional)', 'jobcore' ); ?></span></span>
				<div class="wpjc-upload wpjc-upload--logo" data-wpjc-upload data-max-files="1" data-max-mb="<?php echo esc_attr( (string) $wpjc_limits['photo_mb'] ); ?>">
					<?php if ( $wpjc_photo ) : ?>
						<div class="wpjc-acc-logo-now wpjc-acc-logo-now--wide">
							<img src="<?php echo esc_url( $wpjc_photo ); ?>" alt="">
							<label><input type="checkbox" name="job_photo_remove" value="1"> <?php esc_html_e( 'Remove photo', 'jobcore' ); ?></label>
						</div>
					<?php endif; ?>
					<label class="wpjc-btn wpjc-btn--ghost wpjc-upload__btn" for="job_photo"><?php echo wpjc_icon( 'image', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $wpjc_photo ? esc_html__( 'Replace photo', 'jobcore' ) : esc_html__( 'Add photo', 'jobcore' ); ?></label>
					<input class="wpjc-upload__input" type="file" id="job_photo" name="job_photo" accept="image/jpeg,image/png,image/webp"<?php echo wpjc_field_error( 'photo', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
					<span class="wpjc-drop__hint">
						<?php
						/* translators: %d: max size in MB */
						echo esc_html( sprintf( __( 'JPG, PNG or WebP up to %d MB. Wide photos and tall posters are shown in full.', 'jobcore' ), $wpjc_limits['photo_mb'] ) );
						?>
					</span>
					<ul class="wpjc-upload__list" data-wpjc-upload-list></ul>
					<p class="wpjc-upload__error" data-wpjc-upload-error role="alert" hidden></p>
				</div>
			</div>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<div class="wpjc-acc-sec__intro">
			<h2><?php esc_html_e( 'Applications', 'jobcore' ); ?></h2>
			<p><?php esc_html_e( 'How candidates apply and until when.', 'jobcore' ); ?></p>
		</div>
		<div class="wpjc-acc-fields">
			<fieldset class="wpjc-acc-field wpjc-acc-field--wide">
				<legend><?php esc_html_e( 'Candidates apply', 'jobcore' ); ?></legend>
				<div class="wpjc-acc-seg">
					<label><input type="radio" name="job_apply_how" value="email" <?php checked( 'email', $wpjc_v['apply_how'] ); ?>><span><?php esc_html_e( 'With the form on the board', 'jobcore' ); ?></span></label>
					<label><input type="radio" name="job_apply_how" value="url" <?php checked( 'url', $wpjc_v['apply_how'] ); ?>><span><?php esc_html_e( 'On our website', 'jobcore' ); ?></span></label>
				</div>
			</fieldset>
			<p class="wpjc-acc-field" data-wpjc-apply-how="email">
				<label for="job_apply_email"><?php esc_html_e( 'Send applications to', 'jobcore' ); ?></label>
				<input class="wpjc-input" type="email" id="job_apply_email" name="job_apply_email" value="<?php echo esc_attr( (string) $wpjc_v['apply_email'] ); ?>" placeholder="jobs@example.com" maxlength="120"<?php echo wpjc_field_error( 'apply_email', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
				<span class="wpjc-acc-help"><?php esc_html_e( 'Each application arrives here with the CV and photo attached.', 'jobcore' ); ?></span>
			</p>
			<p class="wpjc-acc-field" data-wpjc-apply-how="url">
				<label for="job_apply_url"><?php esc_html_e( 'Application page', 'jobcore' ); ?></label>
				<input class="wpjc-input" type="url" id="job_apply_url" name="job_apply_url" value="<?php echo esc_attr( (string) $wpjc_v['apply_url'] ); ?>" placeholder="https://www.example.com/careers/…" maxlength="300"<?php echo wpjc_field_error( 'apply_url', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
			</p>
			<?php if ( wpjc_job_asks_expires( $wpjc_job ) ) : ?>
			<p class="wpjc-acc-field">
				<label for="job_expires"><?php esc_html_e( 'Open until', 'jobcore' ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<input class="wpjc-input" type="date" id="job_expires" name="job_expires" value="<?php echo esc_attr( (string) $wpjc_v['expires'] ); ?>" min="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" max="<?php echo esc_attr( $wpjc_max ); ?>" required<?php echo wpjc_field_error( 'expires', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
				<span class="wpjc-acc-help">
					<?php
					/* translators: %d: number of days */
					echo esc_html( sprintf( __( 'Up to %d days from today.', 'jobcore' ), (int) wpjc_opt( 'fe_days' ) ) );
					?>
				</span>
			</p>
			<?php endif; ?>
		</div>
	</section>

	<?php
	/**
	 * Extra sections at the end of the job form (e.g. choosing a paid package).
	 *
	 * @param WP_Post|null $job    Job being edited, null for a new one.
	 * @param array        $values Form values.
	 * @param string[]     $errors Field keys with errors.
	 */
	do_action( 'wpjc_job_form_sections', $wpjc_job, $wpjc_v, $wpjc_errors );

	$wpjc_submit = $wpjc_is_new ? ( wpjc_opt( 'fe_review' ) ? __( 'Send for review', 'jobcore' ) : __( 'Publish job ad', 'jobcore' ) ) : __( 'Save changes', 'jobcore' );
	$wpjc_submit = (string) apply_filters( 'wpjc_job_form_submit_label', $wpjc_submit, $wpjc_job );
	?>
	<div class="wpjc-acc-form__foot">
		<a class="wpjc-btn wpjc-btn--ghost wpjc-btn--sm" href="<?php echo esc_url( wpjc_account_url( 'jobs' ) ); ?>"><?php esc_html_e( 'Cancel', 'jobcore' ); ?></a>
		<button class="wpjc-btn wpjc-btn--sm" type="submit" data-wpjc-job-submit><?php echo wpjc_icon( $wpjc_is_new ? 'send' : 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $wpjc_submit ); ?></span></button>
	</div>
</form>
