<?php
/**
 * Jobs account (front end, signed-in users): my applications, saved jobs, job alerts,
 * my job ads and my companies. Forms post to admin-post.php and come back with ?wpjc_msg=.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'company_save', 'job_save', 'job_do', 'alert_save', 'alert_do' ) as $wpjc_action ) {
	add_action( 'admin_post_wpjc_' . $wpjc_action, 'wpjc_handle_' . $wpjc_action );
	add_action( 'admin_post_nopriv_wpjc_' . $wpjc_action, 'wpjc_account_require_login' );
}
unset( $wpjc_action );

/** Max company logo size in MB. */
const WPJC_LOGO_MB = 2;

/**
 * Hidden fields for an account form.
 *
 * @param string $action Action without the wpjc_ prefix.
 */
function wpjc_account_form_fields( $action ) {
	printf( '<input type="hidden" name="action" value="%s">', esc_attr( 'wpjc_' . $action ) );
	wp_nonce_field( 'wpjc_' . $action, 'wpjc_nonce' );
}

/**
 * Guests posting an account form go to sign in.
 */
function wpjc_account_require_login() {
	wp_safe_redirect( wpjc_login_url( wpjc_account_url() ) );
	exit;
}

/**
 * Check the user and nonce of an account form; returns the user ID.
 *
 * @param string $action Action without the wpjc_ prefix.
 */
function wpjc_account_guard( $action ) {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		wpjc_account_require_login();
	}
	$nonce = isset( $_POST['wpjc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpjc_' . $action ) ) {
		wpjc_account_back( 'overview', '', 'expired' );
	}
	return $user_id;
}

/**
 * Redirect to an account screen with a message; optionally keep the submitted values.
 *
 * @param string     $section Section.
 * @param string|int $item    'new', ID or ''.
 * @param string     $msg     Message code.
 * @param array|null $flash   Form values to show again, with 'errors' => field keys.
 */
function wpjc_account_back( $section, $item, $msg, $flash = null ) {
	if ( is_array( $flash ) ) {
		set_transient( 'wpjc_flash_' . get_current_user_id(), $flash, 10 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( add_query_arg( 'wpjc_msg', rawurlencode( $msg ), wpjc_account_url( $section, $item ) ) );
	exit;
}

/**
 * Values kept from a failed submit (read once).
 *
 * @return array{values:array,errors:string[]}
 */
function wpjc_account_flash() {
	$key   = 'wpjc_flash_' . get_current_user_id();
	$flash = get_transient( $key );
	if ( false !== $flash ) {
		delete_transient( $key );
	}
	$flash = is_array( $flash ) ? $flash : array();
	return array(
		'values' => (array) ( $flash['values'] ?? array() ),
		'errors' => array_map( 'strval', (array) ( $flash['errors'] ?? array() ) ),
	);
}

/**
 * Notice for ?wpjc_msg=: [type, text] or null.
 *
 * @return array{0:string,1:string}|null
 */
function wpjc_account_notice() {
	$code = isset( $_GET['wpjc_msg'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message code.
	$limits = wpjc_apply_limits();
	$list   = array(
		'expired'         => array( 'error', __( 'The form expired. Please try again.', 'jobcore' ) ),
		'not_found'       => array( 'error', __( 'That item was not found in your account.', 'jobcore' ) ),
		'fields'          => array( 'error', __( 'Please fill in the fields marked in red.', 'jobcore' ) ),
		'company_added'   => array( 'ok', __( 'Company added. You can post jobs for it now.', 'jobcore' ) ),
		'company_saved'   => array( 'ok', __( 'Company details saved.', 'jobcore' ) ),
		'company_exists'  => array( 'error', __( 'A company with this name is already on the board. If it is yours, contact us.', 'jobcore' ) ),
		/* translators: %d: max size in MB */
		'logo'            => array( 'error', sprintf( __( 'The logo must be a JPG, PNG, GIF or WebP image up to %d MB.', 'jobcore' ), WPJC_LOGO_MB ) ),
		'job_pending'     => array( 'ok', __( 'Thank you! Your job ad was sent and goes live after a quick review.', 'jobcore' ) ),
		'job_live'        => array( 'ok', __( 'Your job ad is live.', 'jobcore' ) ),
		'job_saved'       => array( 'ok', __( 'Job ad saved.', 'jobcore' ) ),
		/* translators: %d: max size in MB */
		'job_photo'       => array( 'error', sprintf( __( 'The photo must be a JPG, PNG or WebP image up to %d MB.', 'jobcore' ), $limits['photo_mb'] ) ),
		'job_filled'      => array( 'ok', __( 'Marked as filled: candidates can no longer apply.', 'jobcore' ) ),
		'job_reopened'    => array( 'ok', __( 'The job ad is open for applications again.', 'jobcore' ) ),
		'job_deleted'     => array( 'ok', __( 'Job ad deleted.', 'jobcore' ) ),
		'alert_added'     => array( 'ok', __( 'Job alert created. We will e-mail you new matching jobs.', 'jobcore' ) ),
		'alert_saved'     => array( 'ok', __( 'Job alert saved.', 'jobcore' ) ),
		'alert_paused'    => array( 'ok', __( 'Job alert paused.', 'jobcore' ) ),
		'alert_resumed'   => array( 'ok', __( 'Job alert switched on again.', 'jobcore' ) ),
		'alert_deleted'   => array( 'ok', __( 'Job alert deleted.', 'jobcore' ) ),
		/* translators: %d: max number of alerts */
		'alert_limit'     => array( 'error', sprintf( __( 'You can have up to %d job alerts.', 'jobcore' ), WPJC_MAX_ALERTS ) ),
	);
	$list = (array) apply_filters( 'wpjc_account_notices', $list );
	return $list[ $code ] ?? null;
}

/**
 * Upload an image from the jobs account to the Media Library.
 *
 * @param string $field  File field.
 * @param int    $max_mb Max size.
 * @return int Attachment ID, 0 when no file was chosen, -1 on error.
 */
function wpjc_account_upload_image( $field, $max_mb ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput -- nonce checked by the caller; the file is validated by media_handle_upload().
	if ( empty( $_FILES[ $field ]['name'] ) || UPLOAD_ERR_NO_FILE === (int) $_FILES[ $field ]['error'] ) {
		return 0;
	}
	if ( UPLOAD_ERR_OK !== (int) $_FILES[ $field ]['error'] || (int) $_FILES[ $field ]['size'] > $max_mb * MB_IN_BYTES ) {
		return -1;
	}
	// phpcs:enable
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_handle_upload(
		$field,
		0,
		array(),
		array(
			'test_form' => false,
			'mimes'     => array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'gif'          => 'image/gif',
				'webp'         => 'image/webp',
			),
		)
	);
	if ( is_wp_error( $id ) ) {
		return -1;
	}
	update_post_meta( (int) $id, '_wpjc_owner', get_current_user_id() );
	return (int) $id;
}

/**
 * Text field from $_POST.
 *
 * @param string $key Key.
 */
function wpjc_post_text( $key ) {
	return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in wpjc_account_guard().
}

/* ---------- Companies ---------- */

/**
 * Company form fields: key => [label, required].
 *
 * @return array<string, array{0:string,1:bool}>
 */
function wpjc_company_fields() {
	return array(
		'name'          => array( __( 'Company / brand name', 'jobcore' ), true ),
		'legal_name'    => array( __( 'Full company name', 'jobcore' ), true ),
		'reg_no'        => array( __( 'Registration number', 'jobcore' ), true ),
		'vat'           => array( __( 'VAT number', 'jobcore' ), false ),
		'address'       => array( __( 'Address', 'jobcore' ), true ),
		'city'          => array( __( 'City', 'jobcore' ), true ),
		'country'       => array( __( 'Country', 'jobcore' ), true ),
		'postcode'      => array( __( 'Postcode', 'jobcore' ), true ),
		'description'   => array( __( 'About the company', 'jobcore' ), false ),
		'website'       => array( __( 'Website', 'jobcore' ), false ),
		'linkedin'      => array( __( 'LinkedIn page', 'jobcore' ), false ),
		'contact_name'  => array( __( 'Full name', 'jobcore' ), true ),
		'contact_email' => array( __( 'E-mail', 'jobcore' ), true ),
		'contact_phone' => array( __( 'Phone', 'jobcore' ), true ),
	);
}

/**
 * Stored values of a company for the form.
 *
 * @param WP_Term|null $term Employer.
 * @return array<string, mixed>
 */
function wpjc_company_values( $term ) {
	$values = array_fill_keys( array_keys( wpjc_company_fields() ), '' );
	$values += array(
		'active' => '1',
		'logo'   => '',
	);
	if ( ! $term instanceof WP_Term ) {
		return $values;
	}
	foreach ( array_keys( $values ) as $key ) {
		$values[ $key ] = wpjc_company_meta( $term->term_id, $key );
	}
	$values['name']        = $term->name;
	$values['description'] = $term->description;
	$values['active']      = wpjc_company_is_active( $term->term_id ) ? '1' : '0';
	return $values;
}

/**
 * Save a company from the jobs account.
 */
function wpjc_handle_company_save() {
	$user_id = wpjc_account_guard( 'company_save' );
	wpjc_account_require_audience( 'companies', $user_id );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in wpjc_account_guard().
	$term_id = isset( $_POST['company_id'] ) ? absint( $_POST['company_id'] ) : 0;
	$item    = $term_id ? $term_id : 'new';
	if ( $term_id && ! wpjc_user_owns_company( $term_id, $user_id ) ) {
		wpjc_account_back( 'companies', '', 'not_found' );
	}

	$values = array();
	foreach ( array_keys( wpjc_company_fields() ) as $key ) {
		$values[ $key ] = wpjc_post_text( 'company_' . $key );
	}
	$values['description']   = isset( $_POST['company_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['company_description'] ) ) : '';
	$values['website']       = esc_url_raw( $values['website'] );
	$values['linkedin']      = esc_url_raw( $values['linkedin'] );
	$values['contact_email'] = sanitize_email( $values['contact_email'] );
	$values['country']       = in_array( $values['country'], wpjc_countries(), true ) ? $values['country'] : '';
	$values['active']        = empty( $_POST['company_active'] ) ? '0' : '1';
	$consent                 = ! empty( $_POST['company_consent'] );
	$remove_logo             = ! empty( $_POST['company_logo_remove'] );
	// phpcs:enable

	$errors = array();
	foreach ( wpjc_company_fields() as $key => $field ) {
		if ( $field[1] && '' === $values[ $key ] ) {
			$errors[] = $key;
		}
	}
	if ( '' !== $values['contact_email'] && ! is_email( $values['contact_email'] ) ) {
		$errors[] = 'contact_email';
	}
	if ( ! $term_id && ! $consent ) {
		$errors[] = 'consent';
	}
	$flash = array(
		'values' => $values,
		'errors' => $errors,
	);
	if ( $errors ) {
		wpjc_account_back( 'companies', $item, 'fields', $flash );
	}

	$logo_id = wpjc_account_upload_image( 'company_logo', WPJC_LOGO_MB );
	if ( $logo_id < 0 ) {
		$flash['errors'] = array( 'logo' );
		wpjc_account_back( 'companies', $item, 'logo', $flash );
	}

	$term_args = wp_slash(
		array(
			'name'        => $values['name'],
			'description' => $values['description'],
		)
	);
	if ( $term_id ) {
		$result = wp_update_term( $term_id, 'wpjc_employer', $term_args );
	} else {
		$result = wp_insert_term( $term_args['name'], 'wpjc_employer', array( 'description' => $term_args['description'] ) );
	}
	if ( is_wp_error( $result ) ) {
		if ( $logo_id > 0 ) {
			wp_delete_attachment( $logo_id, true );
		}
		$flash['errors'] = array( 'name' );
		wpjc_account_back( 'companies', $item, 'company_exists', $flash );
	}
	$term_id = (int) $result['term_id'];

	foreach ( array( 'legal_name', 'reg_no', 'vat', 'address', 'city', 'country', 'postcode', 'website', 'linkedin', 'contact_name', 'contact_email', 'contact_phone', 'active' ) as $key ) {
		update_term_meta( $term_id, 'wpjc_' . $key, wp_slash( $values[ $key ] ) );
	}
	if ( 'new' === $item ) {
		update_term_meta( $term_id, 'wpjc_owner', $user_id );
		update_term_meta( $term_id, 'wpjc_consent', time() );
	}
	$old_logo = (int) get_term_meta( $term_id, 'wpjc_logo_id', true );
	if ( $logo_id > 0 || $remove_logo ) {
		if ( $old_logo && (int) get_post_meta( $old_logo, '_wpjc_owner', true ) === $user_id ) {
			wp_delete_attachment( $old_logo, true );
		}
		delete_term_meta( $term_id, 'wpjc_logo_id' );
		delete_term_meta( $term_id, 'wpjc_logo' );
	}
	if ( $logo_id > 0 ) {
		update_term_meta( $term_id, 'wpjc_logo_id', $logo_id );
		update_term_meta( $term_id, 'wpjc_logo', (string) wp_get_attachment_image_url( $logo_id, 'medium' ) );
	}

	do_action( 'wpjc_company_saved', $term_id, $values, 'new' === $item );
	wpjc_account_back( 'companies', '', 'new' === $item ? 'company_added' : 'company_saved' );
}

/* ---------- Job ads ---------- */

/**
 * Whether a user may edit a job from the jobs account.
 *
 * @param WP_Post|null $job     Job.
 * @param int          $user_id User ID.
 */
function wpjc_user_owns_job( $job, $user_id ) {
	return $job instanceof WP_Post && 'wpjc_job' === $job->post_type && 'trash' !== $job->post_status
		&& ( (int) $job->post_author === (int) $user_id || user_can( $user_id, 'edit_post', $job->ID ) );
}

/**
 * Jobs a user posted from the jobs account (any status but trash).
 *
 * @param int $user_id User ID.
 * @return WP_Post[]
 */
function wpjc_user_jobs( $user_id ) {
	if ( ! $user_id ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'wpjc_job',
			'post_status'    => (array) apply_filters( 'wpjc_account_job_statuses', array( 'publish', 'pending', 'draft', 'future' ) ),
			'author'         => (int) $user_id,
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

/**
 * Status of a job ad for its owner: [key, label].
 *
 * @param WP_Post $job Job.
 * @return array{0:string,1:string}
 */
function wpjc_job_owner_status( WP_Post $job ) {
	$expires = wpjc_meta( $job, 'expires' );
	if ( 'publish' !== $job->post_status ) {
		$state = array( 'pending', __( 'Waiting for review', 'jobcore' ) );
	} elseif ( wpjc_meta( $job, 'filled' ) ) {
		$state = array( 'filled', __( 'Filled', 'jobcore' ) );
	} elseif ( $expires && $expires < wp_date( 'Y-m-d' ) ) {
		$state = array( 'expired', __( 'Expired', 'jobcore' ) );
	} else {
		$state = array( 'live', __( 'Live', 'jobcore' ) );
	}
	return (array) apply_filters( 'wpjc_job_owner_status', $state, $job );
}

/**
 * Whether the job form asks for an "Open until" date (add-ons that sell a fixed run time turn it off).
 *
 * @param WP_Post|null $job Job, null for a new one.
 */
function wpjc_job_asks_expires( $job ) {
	return (bool) apply_filters( 'wpjc_job_ask_expires', true, $job );
}

/**
 * Stored values of a job for the form.
 *
 * @param WP_Post|null $job Job.
 * @return array<string, mixed>
 */
function wpjc_job_values( $job ) {
	$days   = (int) wpjc_opt( 'fe_days' );
	$values = array(
		'company'     => 0,
		'title'       => '',
		'category'    => 0,
		'type'        => 0,
		'location'    => '',
		'remote'      => '',
		'description' => '',
		'expires'     => wp_date( 'Y-m-d', time() + min( 30, $days ) * DAY_IN_SECONDS ),
		'apply_how'   => 'email',
		'apply_email' => '',
		'apply_url'   => '',
	);
	if ( ! $job instanceof WP_Post ) {
		return (array) apply_filters( 'wpjc_job_values', $values, $job );
	}
	$first = static function ( $taxonomy ) use ( $job ) {
		$ids = wp_get_post_terms( $job->ID, $taxonomy, array( 'fields' => 'ids' ) );
		return ( $ids && ! is_wp_error( $ids ) ) ? (int) $ids[0] : 0;
	};
	$values = array(
		'company'     => $first( 'wpjc_employer' ),
		'title'       => $job->post_title,
		'category'    => $first( 'wpjc_job_category' ),
		'type'        => $first( 'wpjc_job_type' ),
		'location'    => wpjc_meta( $job, 'location' ),
		'remote'      => wpjc_meta( $job, 'remote' ),
		'description' => $job->post_content,
		'expires'     => wpjc_meta( $job, 'expires' ),
		'apply_how'   => '' !== wpjc_meta( $job, 'apply_url' ) ? 'url' : 'email',
		'apply_email' => wpjc_meta( $job, 'apply_email' ),
		'apply_url'   => wpjc_meta( $job, 'apply_url' ),
	);
	return (array) apply_filters( 'wpjc_job_values', $values, $job );
}

/**
 * Save a job ad from the jobs account.
 */
function wpjc_handle_job_save() {
	$user_id = wpjc_account_guard( 'job_save' );
	wpjc_account_require_audience( 'jobs', $user_id );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in wpjc_account_guard().
	$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
	$job    = $job_id ? get_post( $job_id ) : null;
	$item   = $job_id ? $job_id : 'new';
	if ( $job_id && ! wpjc_user_owns_job( $job, $user_id ) ) {
		wpjc_account_back( 'jobs', '', 'not_found' );
	}

	$values = array(
		'company'     => isset( $_POST['job_company'] ) ? absint( $_POST['job_company'] ) : 0,
		'title'       => wpjc_post_text( 'job_title' ),
		'category'    => isset( $_POST['job_category'] ) ? absint( $_POST['job_category'] ) : 0,
		'type'        => isset( $_POST['job_type'] ) ? absint( $_POST['job_type'] ) : 0,
		'location'    => wpjc_post_text( 'job_location' ),
		'remote'      => empty( $_POST['job_remote'] ) ? '' : '1',
		'description' => isset( $_POST['job_description'] ) ? wp_kses_post( wp_unslash( $_POST['job_description'] ) ) : '',
		'expires'     => wpjc_post_text( 'job_expires' ),
		'apply_how'   => 'url' === wpjc_post_text( 'job_apply_how' ) ? 'url' : 'email',
		'apply_email' => sanitize_email( wpjc_post_text( 'job_apply_email' ) ),
		'apply_url'   => esc_url_raw( wpjc_post_text( 'job_apply_url' ) ),
	);
	$remove_photo = ! empty( $_POST['job_photo_remove'] );
	// phpcs:enable

	$ask_expires = wpjc_job_asks_expires( $job );
	if ( ! $ask_expires ) {
		$values['expires'] = $job ? wpjc_meta( $job, 'expires' ) : wpjc_job_values( null )['expires'];
	}
	/**
	 * Values from the job form, before validation (add-ons add their own fields).
	 *
	 * @param array        $values  Values.
	 * @param WP_Post|null $job     Job being edited, null for a new one.
	 * @param int          $user_id User.
	 */
	$values = (array) apply_filters( 'wpjc_job_save_values', $values, $job, $user_id );

	$errors = array();
	if ( ! $values['company'] || ! wpjc_user_owns_company( $values['company'], $user_id ) || ! wpjc_company_is_active( $values['company'] ) ) {
		$errors[] = 'company';
	}
	if ( '' === $values['title'] ) {
		$errors[] = 'title';
	}
	if ( ! $values['category'] || ! term_exists( $values['category'], 'wpjc_job_category' ) ) {
		$errors[] = 'category';
	}
	if ( $values['type'] && ! term_exists( $values['type'], 'wpjc_job_type' ) ) {
		$values['type'] = 0;
	}
	if ( '' === $values['location'] && ! $values['remote'] ) {
		$errors[] = 'location';
	}
	if ( '' === trim( wp_strip_all_tags( $values['description'] ) ) ) {
		$errors[] = 'description';
	}
	$max   = wp_date( 'Y-m-d', time() + (int) wpjc_opt( 'fe_days' ) * DAY_IN_SECONDS );
	$today = wp_date( 'Y-m-d' );
	if ( $ask_expires && ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $values['expires'] ) || $values['expires'] < $today || $values['expires'] > $max ) ) {
		$errors[] = 'expires';
	}
	if ( 'email' === $values['apply_how'] && ! is_email( $values['apply_email'] ) ) {
		$errors[] = 'apply_email';
	}
	if ( 'url' === $values['apply_how'] && ! wp_http_validate_url( $values['apply_url'] ) ) {
		$errors[] = 'apply_url';
	}
	/**
	 * Field keys with errors in the job form.
	 *
	 * @param string[]     $errors  Field keys.
	 * @param array        $values  Values.
	 * @param WP_Post|null $job     Job being edited, null for a new one.
	 * @param int          $user_id User.
	 */
	$errors = array_values( array_unique( (array) apply_filters( 'wpjc_job_save_errors', $errors, $values, $job, $user_id ) ) );
	$flash  = array(
		'values' => $values,
		'errors' => $errors,
	);
	if ( $errors ) {
		wpjc_account_back( 'jobs', $item, 'fields', $flash );
	}

	$photo_id = wpjc_account_upload_image( 'job_photo', wpjc_apply_limits()['photo_mb'] );
	if ( $photo_id < 0 ) {
		$flash['errors'] = array( 'photo' );
		wpjc_account_back( 'jobs', $item, 'job_photo', $flash );
	}

	$meta = array(
		'_wpjc_location'    => $values['location'],
		'_wpjc_remote'      => $values['remote'],
		'_wpjc_expires'     => $values['expires'],
		'_wpjc_apply_email' => 'email' === $values['apply_how'] ? $values['apply_email'] : '',
		'_wpjc_apply_url'   => 'url' === $values['apply_how'] ? $values['apply_url'] : '',
	);
	$post = array(
		'post_type'    => 'wpjc_job',
		'post_title'   => $values['title'],
		'post_content' => $values['description'],
		'meta_input'   => $meta,
	);
	if ( $job ) {
		$post['ID'] = $job->ID;
		$saved      = wp_update_post( wp_slash( $post ), true );
		$status     = $job->post_status;
	} else {
		/**
		 * Status of a new job ad from the jobs account.
		 *
		 * @param string $status  pending (review on) or publish.
		 * @param array  $values  Form values.
		 * @param int    $user_id User.
		 */
		$status                           = (string) apply_filters( 'wpjc_new_job_status', wpjc_opt( 'fe_review' ) ? 'pending' : 'publish', $values, $user_id );
		$post['post_status']              = $status;
		$post['post_author']              = $user_id;
		$post['meta_input']['_wpjc_tier'] = 'basic';
		$saved                            = wp_insert_post( wp_slash( $post ), true );
	}
	if ( is_wp_error( $saved ) || ! $saved ) {
		wpjc_account_back( 'jobs', $item, 'fields', $flash );
	}
	$job_id = (int) $saved;

	wp_set_object_terms( $job_id, array( $values['company'] ), 'wpjc_employer' );
	wp_set_object_terms( $job_id, array( $values['category'] ), 'wpjc_job_category' );
	wp_set_object_terms( $job_id, $values['type'] ? array( $values['type'] ) : array(), 'wpjc_job_type' );

	if ( $photo_id > 0 || $remove_photo ) {
		$old = (int) get_post_thumbnail_id( $job_id );
		if ( $old && (int) get_post_meta( $old, '_wpjc_owner', true ) === $user_id ) {
			wp_delete_attachment( $old, true );
		}
		delete_post_thumbnail( $job_id );
	}
	if ( $photo_id > 0 ) {
		set_post_thumbnail( $job_id, $photo_id );
	}

	if ( ! $job && 'pending' === $status ) {
		wpjc_notify_review( $job_id );
	}
	do_action( 'wpjc_account_job_saved', $job_id, $values, ! $job );

	if ( $job ) {
		wpjc_account_back( 'jobs', '', 'job_saved' );
	}
	wpjc_account_back( 'jobs', '', 'pending' === $status ? 'job_pending' : 'job_live' );
}

/**
 * E-mail the board contact about a job ad waiting for review.
 *
 * @param int $job_id Job ID.
 */
function wpjc_notify_review( $job_id ) {
	$to      = sanitize_email( (string) wpjc_opt( 'contact_email' ) );
	$to      = $to ? $to : (string) get_option( 'admin_email' );
	$company = wpjc_job_company( $job_id );
	$user    = get_userdata( (int) get_post_field( 'post_author', $job_id ) );
	$user    = $user ? $user : wp_get_current_user();
	wp_mail(
		$to,
		/* translators: %s: job title */
		sprintf( __( 'Job ad waiting for review: %s', 'jobcore' ), get_the_title( $job_id ) ),
		implode(
			"\n",
			array(
				get_the_title( $job_id ) . ( $company['name'] ? ' — ' . $company['name'] : '' ),
				/* translators: 1: user name, 2: e-mail */
				sprintf( __( 'Posted by %1$s (%2$s)', 'jobcore' ), $user->display_name, $user->user_email ),
				'',
				/* translators: %s: URL */
				sprintf( __( 'Review and publish: %s', 'jobcore' ), admin_url( 'post.php?post=' . (int) $job_id . '&action=edit' ) ),
			)
		)
	);
}

/**
 * Job ad actions: mark filled, reopen, delete.
 */
function wpjc_handle_job_do() {
	$user_id = wpjc_account_guard( 'job_do' );
	wpjc_account_require_audience( 'jobs', $user_id );
	$job     = get_post( isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked in wpjc_account_guard().
	$do      = wpjc_post_text( 'do' );
	if ( ! wpjc_user_owns_job( $job, $user_id ) ) {
		wpjc_account_back( 'jobs', '', 'not_found' );
	}
	if ( 'filled' === $do ) {
		update_post_meta( $job->ID, '_wpjc_filled', '1' );
		wpjc_account_back( 'jobs', '', 'job_filled' );
	}
	if ( 'reopen' === $do ) {
		update_post_meta( $job->ID, '_wpjc_filled', '0' );
		wpjc_account_back( 'jobs', '', 'job_reopened' );
	}
	if ( 'delete' === $do ) {
		wp_trash_post( $job->ID );
		wpjc_account_back( 'jobs', '', 'job_deleted' );
	}
	wpjc_account_back( 'jobs', '', 'not_found' );
}

/* ---------- Job alerts ---------- */

/**
 * Save a job alert.
 */
function wpjc_handle_alert_save() {
	$user_id = wpjc_account_guard( 'alert_save' );
	wpjc_account_require_audience( 'alerts', $user_id );
	$id      = sanitize_key( wpjc_post_text( 'alert_id' ) );
	$alerts  = wpjc_user_alerts( $user_id );
	if ( '' !== $id && ! isset( $alerts[ $id ] ) ) {
		wpjc_account_back( 'alerts', '', 'not_found' );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in wpjc_account_guard().
	$values = array(
		'name'      => wpjc_post_text( 'alert_name' ),
		'keywords'  => wpjc_post_text( 'alert_keywords' ),
		'category'  => isset( $_POST['alert_category'] ) ? absint( $_POST['alert_category'] ) : 0,
		'type'      => isset( $_POST['alert_type'] ) ? absint( $_POST['alert_type'] ) : 0,
		'location'  => wpjc_post_text( 'alert_location' ),
		'remote'    => ! empty( $_POST['alert_remote'] ),
		'frequency' => wpjc_post_text( 'alert_frequency' ),
	);
	// phpcs:enable
	$values['frequency'] = isset( wpjc_alert_frequencies()[ $values['frequency'] ] ) ? $values['frequency'] : 'daily';
	if ( $values['category'] && ! term_exists( $values['category'], 'wpjc_job_category' ) ) {
		$values['category'] = 0;
	}
	if ( $values['type'] && ! term_exists( $values['type'], 'wpjc_job_type' ) ) {
		$values['type'] = 0;
	}

	if ( '' === $id ) {
		if ( '' === wpjc_add_alert( $user_id, $values ) ) {
			wpjc_account_back( 'alerts', '', 'alert_limit' );
		}
		wpjc_account_back( 'alerts', '', 'alert_added' );
	}
	$alerts[ $id ] = array_merge( $alerts[ $id ], $values );
	wpjc_save_user_alerts( $user_id, $alerts );
	wpjc_account_back( 'alerts', '', 'alert_saved' );
}

/**
 * Job alert actions: pause, resume, delete.
 */
function wpjc_handle_alert_do() {
	$user_id = wpjc_account_guard( 'alert_do' );
	wpjc_account_require_audience( 'alerts', $user_id );
	$id      = sanitize_key( wpjc_post_text( 'alert_id' ) );
	$do      = wpjc_post_text( 'do' );
	$alerts  = wpjc_user_alerts( $user_id );
	if ( ! isset( $alerts[ $id ] ) ) {
		wpjc_account_back( 'alerts', '', 'not_found' );
	}
	if ( 'delete' === $do ) {
		unset( $alerts[ $id ] );
		wpjc_save_user_alerts( $user_id, $alerts );
		wpjc_account_back( 'alerts', '', 'alert_deleted' );
	}
	$on                      = 'resume' === $do;
	$alerts[ $id ]['active'] = $on;
	if ( $on ) {
		$alerts[ $id ]['last_sent'] = time();
	}
	wpjc_save_user_alerts( $user_id, $alerts );
	wpjc_account_back( 'alerts', '', $on ? 'alert_resumed' : 'alert_paused' );
}

/* ---------- Screen ---------- */

/**
 * Data shared by the account templates.
 *
 * @return array<string, mixed>
 */
function wpjc_account_context() {
	$user = wp_get_current_user();
	$ctx  = array(
		'section'      => wpjc_account_section(),
		'item'         => wpjc_account_item(),
		'user'         => $user,
		'applications' => wpjc_user_applications( $user->ID ),
		'saved'        => wpjc_fav_jobs( $user->ID ),
		'alerts'       => wpjc_user_alerts( $user->ID ),
		'jobs'         => wpjc_user_jobs( $user->ID ),
		'companies'    => wpjc_user_companies( $user->ID ),
		'notice'       => wpjc_account_notice(),
	);
	/**
	 * Data shared by the account templates (add-ons add their own).
	 *
	 * @param array   $ctx  Data.
	 * @param WP_User $user User.
	 */
	return (array) apply_filters( 'wpjc_account_context', $ctx, $user );
}

/**
 * Template for the current account screen.
 *
 * @param string $section Section.
 * @param string $item    'new', ID or ''.
 */
function wpjc_account_panel( $section, $item ) {
	$forms = array(
		'alerts'    => 'alert-form',
		'jobs'      => 'job-form',
		'companies' => 'company-form',
	);
	$slug  = ( '' !== $item && isset( $forms[ $section ] ) ) ? 'account/' . $forms[ $section ] : 'account/' . $section;
	/**
	 * Template slug of an account screen. Add-ons point their slugs to their own files
	 * with the wpjc_template_file filter.
	 *
	 * @param string $slug    Template slug.
	 * @param string $section Section.
	 * @param string $item    'new', ID or ''.
	 */
	return (string) apply_filters( 'wpjc_account_panel', $slug, $section, $item );
}

/**
 * Mark a field the server rejected.
 *
 * @param string   $key    Field key.
 * @param string[] $errors Rejected keys.
 * @return string Attributes.
 */
function wpjc_field_error( $key, array $errors ) {
	return in_array( $key, $errors, true ) ? ' aria-invalid="true"' : '';
}
