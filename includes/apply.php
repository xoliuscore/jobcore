<?php
/**
 * E-mail applications (core): contact fields, cover letter, optional documents and photo.
 * Premium modules can store applications via the `wpjc_application_submitted` action.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_nopriv_wpjc_apply', 'wpjc_handle_apply' );
add_action( 'admin_post_wpjc_apply', 'wpjc_handle_apply' );

/**
 * Upload limits: number of documents, documents total MB and photo MB (capped by the server upload limit).
 *
 * @return array{files:int,docs_mb:int,photo_mb:int}
 */
function wpjc_apply_limits() {
	$limits = (array) apply_filters(
		'wpjc_apply_limits',
		array(
			'files'    => 3,
			'docs_mb'  => 15,
			'photo_mb' => 8,
		)
	);
	$server = (int) floor( wp_max_upload_size() / MB_IN_BYTES );
	$cap    = static function ( $mb ) use ( $server ) {
		return max( 1, $server ? min( (int) $mb, $server ) : (int) $mb );
	};
	return array(
		'files'    => max( 1, (int) ( $limits['files'] ?? 3 ) ),
		'docs_mb'  => $cap( $limits['docs_mb'] ?? 15 ),
		'photo_mb' => $cap( $limits['photo_mb'] ?? 8 ),
	);
}

/**
 * Pre-filled cover letter for a job.
 *
 * @param int|WP_Post $job Job.
 * @return string
 */
function wpjc_cover_letter_template( $job ) {
	$site = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$text = sprintf(
		/* translators: 1: job title, 2: site address */
		__( "Dear Sir or Madam,\n\nI am applying for the position of %1\$s advertised on %2\$s.\n\nI would be glad to attend an interview.\n\nKind regards", 'jobcore' ),
		get_the_title( $job ),
		$site ? $site : get_bloginfo( 'name' )
	);
	return (string) apply_filters( 'wpjc_cover_letter_template', $text, $job );
}

/**
 * Message for a failed application status code.
 *
 * @param string $code Status code from the redirect.
 * @return string
 */
function wpjc_apply_error_message( $code ) {
	$limits = wpjc_apply_limits();
	switch ( $code ) {
		case 'fields':
			$msg = __( 'Please fill in your name, a valid e-mail, your phone number and the cover letter.', 'jobcore' );
			break;
		case 'docs':
			/* translators: 1: max number of files, 2: max total size in MB */
			$msg = sprintf( __( 'Documents must be PDF, DOC or DOCX files: up to %1$d files, %2$d MB in total.', 'jobcore' ), $limits['files'], $limits['docs_mb'] );
			break;
		case 'photo':
			/* translators: %d: max size in MB */
			$msg = sprintf( __( 'The photo must be a JPG, PNG or GIF file under %d MB.', 'jobcore' ), $limits['photo_mb'] );
			break;
		default:
			$msg = __( 'The application could not be sent. Please try again.', 'jobcore' );
	}
	return (string) apply_filters( 'wpjc_apply_error_message', $msg, $code );
}

/**
 * Redirect back to the job's apply box with a status.
 *
 * @param int    $job_id Job ID.
 * @param string $status 1 or an error code.
 * @param array  $files  Uploaded files to delete first.
 */
function wpjc_apply_redirect( $job_id, $status, array $files = array() ) {
	foreach ( $files as $path ) {
		wp_delete_file( $path );
	}
	wp_safe_redirect( add_query_arg( 'wpjc_applied', $status, get_permalink( $job_id ) ) . '#wpjc-apply' );
	exit;
}

/**
 * Files of one upload field as a list (handles `name[]` multi-file fields).
 *
 * @param string $field Field name.
 * @return array<int, array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function wpjc_apply_files( $field ) {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked in wpjc_handle_apply(); files validated by wp_handle_upload().
	$raw = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : array();
	if ( ! $raw || ! isset( $raw['name'] ) ) {
		return array();
	}
	$out = array();
	foreach ( (array) $raw['name'] as $i => $name ) {
		$error = (int) ( is_array( $raw['error'] ) ? $raw['error'][ $i ] : $raw['error'] );
		if ( UPLOAD_ERR_NO_FILE === $error ) {
			continue;
		}
		$out[] = array(
			'name'     => (string) $name,
			'type'     => (string) ( is_array( $raw['type'] ) ? $raw['type'][ $i ] : $raw['type'] ),
			'tmp_name' => (string) ( is_array( $raw['tmp_name'] ) ? $raw['tmp_name'][ $i ] : $raw['tmp_name'] ),
			'error'    => $error,
			'size'     => (int) ( is_array( $raw['size'] ) ? $raw['size'][ $i ] : $raw['size'] ),
		);
	}
	return $out;
}

/**
 * Move one uploaded file into uploads with a neutral name.
 *
 * @param array  $file   File from wpjc_apply_files().
 * @param array  $mimes  Allowed ext => mime.
 * @param string $prefix File name prefix.
 * @return string Path, '' on failure.
 */
function wpjc_apply_upload( array $file, array $mimes, $prefix ) {
	if ( UPLOAD_ERR_OK !== $file['error'] ) {
		return '';
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$file['name'] = sanitize_file_name( $prefix . '-' . wp_generate_password( 6, false ) . '.' . strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) );
	$upload       = wp_handle_upload(
		$file,
		array(
			'test_form' => false,
			'mimes'     => $mimes,
		)
	);
	return empty( $upload['file'] ) || ! empty( $upload['error'] ) ? '' : (string) $upload['file'];
}

/**
 * Handle apply form POST.
 */
function wpjc_handle_apply() {
	$job_id = isset( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
	$nonce  = isset( $_POST['wpjc_apply_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_apply_nonce'] ) ) : '';
	if ( ! $job_id || ! wp_verify_nonce( $nonce, 'wpjc_apply_' . $job_id ) ) {
		wp_safe_redirect( wpjc_home_url() );
		exit;
	}

	$job = get_post( $job_id );
	if ( ! $job || 'wpjc_job' !== $job->post_type || 'publish' !== $job->post_status || ! wpjc_can_apply( $job ) ) {
		wp_safe_redirect( wpjc_home_url() );
		exit;
	}

	$external = wpjc_meta( $job, 'apply_url' );
	if ( $external ) {
		wp_redirect( esc_url_raw( $external ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- employer-provided external URL saved by an editor.
		exit;
	}

	// Bots fill every field; people never see this one. Pretend success.
	if ( ! empty( $_POST['wpjc_website_hp'] ) ) {
		wpjc_apply_redirect( $job_id, '1' );
	}

	$data = array(
		'first_name' => isset( $_POST['applicant_first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['applicant_first_name'] ) ) : '',
		'last_name'  => isset( $_POST['applicant_last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['applicant_last_name'] ) ) : '',
		'email'      => isset( $_POST['applicant_email'] ) ? sanitize_email( wp_unslash( $_POST['applicant_email'] ) ) : '',
		'phone'      => isset( $_POST['applicant_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['applicant_phone'] ) ) : '',
		'message'    => isset( $_POST['applicant_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['applicant_message'] ) ) : '',
	);
	$data = (array) apply_filters( 'wpjc_apply_data', $data, $job );
	$to   = wpjc_meta( $job, 'apply_email' );
	$need = array( $data['first_name'], $data['last_name'], $data['phone'], $data['message'] );
	if ( ! $to || in_array( '', $need, true ) || ! is_email( $data['email'] ) ) {
		wpjc_apply_redirect( $job_id, 'fields' );
	}
	$extra_err = (string) apply_filters( 'wpjc_apply_validate', '', $data, $job );
	if ( '' !== $extra_err ) {
		wpjc_apply_redirect( $job_id, $extra_err );
	}

	$limits = wpjc_apply_limits();
	$files  = array();

	$docs = wpjc_apply_files( 'applicant_docs' );
	if ( count( $docs ) > $limits['files'] || array_sum( wp_list_pluck( $docs, 'size' ) ) > $limits['docs_mb'] * MB_IN_BYTES ) {
		wpjc_apply_redirect( $job_id, 'docs' );
	}
	foreach ( $docs as $i => $doc ) {
		$path = wpjc_apply_upload(
			$doc,
			array(
				'pdf'  => 'application/pdf',
				'doc'  => 'application/msword',
				'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			),
			'document-' . $data['last_name'] . '-' . ( $i + 1 )
		);
		if ( '' === $path ) {
			wpjc_apply_redirect( $job_id, 'docs', $files );
		}
		$files[] = $path;
	}

	$photo = wpjc_apply_files( 'applicant_photo' );
	if ( $photo ) {
		$path = $photo[0]['size'] <= $limits['photo_mb'] * MB_IN_BYTES
			? wpjc_apply_upload(
				$photo[0],
				array(
					'jpg|jpeg|jpe' => 'image/jpeg',
					'png'          => 'image/png',
					'gif'          => 'image/gif',
				),
				'photo-' . $data['last_name']
			)
			: '';
		if ( '' === $path ) {
			wpjc_apply_redirect( $job_id, 'photo', $files );
		}
		$files[] = $path;
	}

	/**
	 * Files sent with the application, after the visitor's uploads. Add-ons may add copies
	 * (e.g. a saved CV); every path in the list is deleted after sending unless kept.
	 *
	 * @param string[] $files Paths.
	 * @param WP_Post  $job   Job.
	 * @param array    $data  Applicant fields.
	 */
	$files = array_values( array_filter( (array) apply_filters( 'wpjc_apply_files', $files, $job, $data ), 'is_string' ) );

	$name    = $data['first_name'] . ' ' . $data['last_name'];
	$subject = sprintf(
		/* translators: 1: job title, 2: applicant name */
		__( 'Application: %1$s — %2$s', 'jobcore' ),
		get_the_title( $job ),
		$name
	);
	$body    = implode( "\n", array( $name, $data['email'], $data['phone'], '', $data['message'], '', '—', get_permalink( $job ) ) );
	$body    = (string) apply_filters( 'wpjc_apply_email_body', $body, $job, $data );
	$headers = array( 'Reply-To: ' . $name . ' <' . $data['email'] . '>' );

	$ok = (bool) wp_mail( $to, $subject, $body, $headers, $files );

	if ( $ok && apply_filters( 'wpjc_send_applicant_copy', true, $job, $data ) ) {
		wp_mail(
			$data['email'],
			/* translators: %s: job title */
			sprintf( __( 'Your application: %s', 'jobcore' ), get_the_title( $job ) ),
			implode(
				"\n",
				array(
					/* translators: %s: job title */
					sprintf( __( 'Thank you — your application for “%s” has been sent to the employer. This is your copy:', 'jobcore' ), get_the_title( $job ) ),
					'',
					$data['message'],
					'',
					'—',
					get_permalink( $job ),
				)
			)
		);
	}

	/**
	 * After an application e-mail was sent (or failed).
	 *
	 * @param WP_Post  $job   Job.
	 * @param array    $data  Applicant fields.
	 * @param string[] $files Uploaded documents and photo (paths), deleted afterwards unless kept.
	 * @param bool     $ok    Whether the e-mail to the employer was sent.
	 */
	$app_id = wpjc_store_application( $job, $data, $files, $ok );

	do_action( 'wpjc_application_submitted', $job, $data, $files, $ok );

	if ( ! apply_filters( 'wpjc_keep_application_files', false, $files, $job ) ) {
		foreach ( $files as $path ) {
			wp_delete_file( $path );
		}
	}

	wpjc_apply_redirect( $job_id, $ok || $app_id ? '1' : 'mail' );
}
