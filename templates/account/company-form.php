<?php
/**
 * Jobs account: add / edit a company. Sections say who sees what (job ads, company page, only us).
 *
 * @package JobCore
 * @var string     $item   'new' or term ID.
 * @var WP_User    $user   Current user.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_is_new = 'new' === $item;
$wpjc_term   = $wpjc_is_new ? null : get_term( (int) $item, 'wpjc_employer' );
if ( ! $wpjc_is_new && ( ! $wpjc_term instanceof WP_Term || ! wpjc_user_owns_company( $wpjc_term->term_id, $user->ID ) ) ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'building',
			'title'  => __( 'Company not found', 'jobcore' ),
			'text'   => __( 'It is not in your account.', 'jobcore' ),
			'button' => array( __( 'Back to my companies', 'jobcore' ), wpjc_account_url( 'companies' ), 'arrow' ),
		)
	);
	return;
}

$wpjc_flash  = wpjc_account_flash();
$wpjc_errors = $wpjc_flash['errors'];
$wpjc_v      = array_merge( wpjc_company_values( $wpjc_term ), $wpjc_flash['values'] );
if ( $wpjc_is_new && ! $wpjc_flash['values'] ) {
	$wpjc_v['contact_name']  = trim( $user->first_name . ' ' . $user->last_name );
	$wpjc_v['contact_name']  = '' !== $wpjc_v['contact_name'] ? $wpjc_v['contact_name'] : $user->display_name;
	$wpjc_v['contact_email'] = $user->user_email;
}
$wpjc_fields = wpjc_company_fields();
$wpjc_logo   = $wpjc_term ? wpjc_company_meta( $wpjc_term->term_id, 'logo' ) : '';

wpjc_template(
	'account/head',
	array(
		'title'  => wpjc_account_title( 'companies', $item ),
		'dek'    => __( 'The details you enter here are shown to candidates with every job ad. Each group says what is public and what stays with us.', 'jobcore' ),
		'crumbs' => array( array( __( 'My companies', 'jobcore' ), wpjc_account_url( 'companies' ) ) ),
		'notice' => $notice,
	)
);

/**
 * One text input.
 *
 * @param string $key   Field key.
 * @param array  $attrs type, placeholder, help, autocomplete.
 */
$wpjc_input = static function ( $key, array $attrs = array() ) use ( $wpjc_fields, $wpjc_v, $wpjc_errors ) {
	$id = 'company_' . $key;
	?>
	<p class="wpjc-acc-field<?php echo ! empty( $attrs['wide'] ) ? ' wpjc-acc-field--wide' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $wpjc_fields[ $key ][0] ); ?><?php echo $wpjc_fields[ $key ][1] ? ' <span class="wpjc-acc-req" aria-hidden="true">*</span>' : ''; ?></label>
		<input class="wpjc-input" type="<?php echo esc_attr( $attrs['type'] ?? 'text' ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( (string) ( $wpjc_v[ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( $attrs['placeholder'] ?? '' ); ?>" maxlength="<?php echo esc_attr( (string) ( $attrs['max'] ?? 120 ) ); ?>"<?php echo $wpjc_fields[ $key ][1] ? ' required' : ''; ?><?php echo isset( $attrs['auto'] ) ? ' autocomplete="' . esc_attr( $attrs['auto'] ) . '"' : ''; ?><?php echo wpjc_field_error( $key, $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
		<?php if ( ! empty( $attrs['help'] ) ) : ?>
			<span class="wpjc-acc-help"><?php echo esc_html( $attrs['help'] ); ?></span>
		<?php endif; ?>
	</p>
	<?php
};

/**
 * Section intro (left column).
 *
 * @param string $title Title.
 * @param string $text  Text.
 * @param string $badge public|profile|private.
 */
$wpjc_intro = static function ( $title, $text, $badge ) {
	$badges = array(
		'public'  => array( 'eye', __( 'Shown on your job ads', 'jobcore' ) ),
		'profile' => array( 'eye', __( 'Shown on your company page', 'jobcore' ) ),
		'private' => array( 'lock', __( 'Stays with us', 'jobcore' ) ),
	);
	?>
	<div class="wpjc-acc-sec__intro">
		<h2><?php echo esc_html( $title ); ?></h2>
		<p><?php echo esc_html( $text ); ?></p>
		<span class="wpjc-acc-vis wpjc-acc-vis--<?php echo esc_attr( $badge ); ?>"><?php echo wpjc_icon( $badges[ $badge ][0], 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $badges[ $badge ][1] ); ?></span>
	</div>
	<?php
};
?>
<form class="wpjc-acc-card wpjc-acc-form wpjc-acc-form--sections" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
	<?php wpjc_account_form_fields( 'company_save' ); ?>
	<input type="hidden" name="company_id" value="<?php echo esc_attr( $wpjc_term ? (string) $wpjc_term->term_id : '0' ); ?>">

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'Basic details', 'jobcore' ), __( 'The name candidates know you by.', 'jobcore' ), 'public' ); ?>
		<div class="wpjc-acc-fields">
			<?php
			$wpjc_input(
				'name',
				array(
					'wide'        => true,
					'placeholder' => __( 'e.g. Your company or brand', 'jobcore' ),
					'help'        => __( 'Short name shown on every job ad. Avoid legal suffixes.', 'jobcore' ),
				)
			);
			$wpjc_input(
				'legal_name',
				array(
					'wide'        => true,
					'placeholder' => __( 'e.g. Your Company Ltd.', 'jobcore' ),
					'help'        => __( 'Name as in the company register.', 'jobcore' ),
				)
			);
			?>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'Business details', 'jobcore' ), __( 'Registration and VAT numbers on your company page and invoices.', 'jobcore' ), 'profile' ); ?>
		<div class="wpjc-acc-fields">
			<?php
			$wpjc_input(
				'reg_no',
				array(
					'placeholder' => __( 'Company register number', 'jobcore' ),
					'help'        => __( 'As on your certificate of registration.', 'jobcore' ),
				)
			);
			$wpjc_input(
				'vat',
				array(
					'placeholder' => __( 'VAT ID', 'jobcore' ),
					'help'        => __( 'Leave empty if the company is not VAT registered.', 'jobcore' ),
				)
			);
			?>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'Head office', 'jobcore' ), __( 'The address is used for invoices too.', 'jobcore' ), 'public' ); ?>
		<div class="wpjc-acc-fields">
			<?php
			$wpjc_input(
				'address',
				array(
					'placeholder' => __( 'Street and number', 'jobcore' ),
					'auto'        => 'street-address',
				)
			);
			$wpjc_input(
				'city',
				array(
					'placeholder' => __( 'e.g. Berlin', 'jobcore' ),
					'auto'        => 'address-level2',
				)
			);
			?>
			<p class="wpjc-acc-field">
				<label for="company_country"><?php echo esc_html( $wpjc_fields['country'][0] ); ?> <span class="wpjc-acc-req" aria-hidden="true">*</span></label>
				<select class="wpjc-input" id="company_country" name="company_country" required<?php echo wpjc_field_error( 'country', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
					<option value=""><?php esc_html_e( 'Choose a country', 'jobcore' ); ?></option>
					<?php foreach ( wpjc_countries() as $wpjc_country ) : ?>
						<option value="<?php echo esc_attr( $wpjc_country ); ?>" <?php selected( $wpjc_v['country'], $wpjc_country ); ?>><?php echo esc_html( $wpjc_country ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<?php
			$wpjc_input(
				'postcode',
				array(
					'placeholder' => __( 'e.g. 10115', 'jobcore' ),
					'max'         => 20,
					'auto'        => 'postal-code',
				)
			);
			?>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'Company logo', 'jobcore' ), __( 'Shown with every job ad and in the employers list.', 'jobcore' ), 'public' ); ?>
		<div class="wpjc-acc-fields">
			<div class="wpjc-acc-field wpjc-acc-field--wide">
				<div class="wpjc-upload wpjc-upload--logo" data-wpjc-upload data-max-files="1" data-max-mb="<?php echo esc_attr( (string) WPJC_LOGO_MB ); ?>">
					<?php if ( $wpjc_logo ) : ?>
						<div class="wpjc-acc-logo-now">
							<img src="<?php echo esc_url( $wpjc_logo ); ?>" alt="" width="64" height="64">
							<label><input type="checkbox" name="company_logo_remove" value="1"> <?php esc_html_e( 'Remove logo', 'jobcore' ); ?></label>
						</div>
					<?php endif; ?>
					<label class="wpjc-btn wpjc-btn--ghost wpjc-upload__btn" for="company_logo"><?php echo wpjc_icon( 'image', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $wpjc_logo ? esc_html__( 'Replace logo', 'jobcore' ) : esc_html__( 'Choose logo', 'jobcore' ); ?></label>
					<input class="wpjc-upload__input" type="file" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/gif,image/webp"<?php echo wpjc_field_error( 'logo', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
					<span class="wpjc-drop__hint">
						<?php
						/* translators: %d: max size in MB */
						echo esc_html( sprintf( __( 'JPG, PNG, GIF or WebP up to %d MB. A square logo of at least 200 × 200 px looks best.', 'jobcore' ), WPJC_LOGO_MB ) );
						?>
					</span>
					<ul class="wpjc-upload__list" data-wpjc-upload-list></ul>
					<p class="wpjc-upload__error" data-wpjc-upload-error role="alert" hidden></p>
				</div>
			</div>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'About the company', 'jobcore' ), __( 'Introduce yourself to candidates.', 'jobcore' ), 'profile' ); ?>
		<div class="wpjc-acc-fields">
			<p class="wpjc-acc-field wpjc-acc-field--wide">
				<label for="company_description"><?php echo esc_html( $wpjc_fields['description'][0] ); ?></label>
				<textarea class="wpjc-input" id="company_description" name="company_description" rows="5" maxlength="3000" placeholder="<?php esc_attr_e( 'Who you are, how many people work with you, what it is like to work for you and why someone should join.', 'jobcore' ); ?>"><?php echo esc_textarea( (string) $wpjc_v['description'] ); ?></textarea>
				<span class="wpjc-acc-help"><?php esc_html_e( 'A few sentences are enough. Shown on your company page and to candidates.', 'jobcore' ); ?></span>
			</p>
			<?php
			$wpjc_input(
				'website',
				array(
					'type'        => 'url',
					'placeholder' => 'https://www.example.com',
					'max'         => 200,
				)
			);
			$wpjc_input(
				'linkedin',
				array(
					'type'        => 'url',
					'placeholder' => 'https://www.linkedin.com/company/…',
					'max'         => 200,
				)
			);
			?>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<?php $wpjc_intro( __( 'Contact person', 'jobcore' ), __( 'Who we contact about job ads, offers and invoices.', 'jobcore' ), 'private' ); ?>
		<div class="wpjc-acc-fields">
			<?php
			$wpjc_input(
				'contact_name',
				array(
					'wide' => true,
					'help' => __( 'The person responsible for job ads. Candidates do not see it.', 'jobcore' ),
					'auto' => 'name',
				)
			);
			$wpjc_input(
				'contact_email',
				array(
					'type' => 'email',
					'auto' => 'email',
				)
			);
			$wpjc_input(
				'contact_phone',
				array(
					'type' => 'tel',
					'auto' => 'tel',
					'max'  => 40,
				)
			);
			?>
		</div>
	</section>

	<section class="wpjc-acc-sec">
		<div class="wpjc-acc-sec__intro">
			<h2><?php esc_html_e( 'Publishing', 'jobcore' ); ?></h2>
			<p><?php esc_html_e( 'Visibility of the company on the board.', 'jobcore' ); ?></p>
		</div>
		<div class="wpjc-acc-fields">
			<p class="wpjc-acc-field wpjc-acc-field--wide wpjc-acc-field--check">
				<label>
					<input type="checkbox" name="company_active" value="1" <?php checked( '1', (string) $wpjc_v['active'] ); ?>>
					<span><strong><?php esc_html_e( 'Company is active', 'jobcore' ); ?></strong><span class="wpjc-acc-help"><?php esc_html_e( 'Inactive companies and their job ads are hidden, and you cannot post new ads for them.', 'jobcore' ); ?></span></span>
				</label>
			</p>
			<?php if ( $wpjc_is_new ) : ?>
				<p class="wpjc-acc-field wpjc-acc-field--wide wpjc-acc-field--check">
					<label>
						<input type="checkbox" name="company_consent" value="1" required<?php echo wpjc_field_error( 'consent', $wpjc_errors ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute. ?>>
						<span>
							<?php
							$wpjc_privacy = get_privacy_policy_url();
							printf(
								/* translators: %s: privacy policy link */
								esc_html__( 'I have read the %s and I am allowed to act for this company.', 'jobcore' ),
								$wpjc_privacy ? '<a href="' . esc_url( $wpjc_privacy ) . '" target="_blank" rel="noopener">' . esc_html__( 'privacy policy', 'jobcore' ) . '</a>' : esc_html__( 'privacy policy', 'jobcore' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped here.
							);
							?>
							<span class="wpjc-acc-req" aria-hidden="true">*</span>
						</span>
					</label>
				</p>
			<?php endif; ?>
		</div>
	</section>

	<div class="wpjc-acc-form__foot">
		<a class="wpjc-btn wpjc-btn--ghost wpjc-btn--sm" href="<?php echo esc_url( wpjc_account_url( 'companies' ) ); ?>"><?php esc_html_e( 'Cancel', 'jobcore' ); ?></a>
		<button class="wpjc-btn wpjc-btn--sm" type="submit"><?php echo wpjc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Save company', 'jobcore' ); ?></button>
	</div>
</form>
<?php
if ( $wpjc_term instanceof WP_Term ) {
	/**
	 * After the company form of an existing company (e.g. "show your jobs on your website").
	 *
	 * @param WP_Term $term Company.
	 */
	do_action( 'wpjc_company_form_after', $wpjc_term );
}
