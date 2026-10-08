<?php
/**
 * Applications received: the employer's inbox in the jobs account, application statuses,
 * and CVs / photos kept in a protected folder for a limited time.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_wpjc_app_status', 'wpjc_handle_app_status' );
add_action( 'admin_post_nopriv_wpjc_app_status', 'wpjc_account_require_login' );
add_action( 'admin_post_wpjc_app_file', 'wpjc_handle_app_file' );
add_action( 'admin_post_nopriv_wpjc_app_file', 'wpjc_account_require_login' );

/**
 * Application statuses: key => employer label.
 *
 * @return array<string, string>
 */
function wpjc_app_statuses() {
	return array(
		'new'         => __( 'New', 'jobcore' ),
		'shortlisted' => __( 'Shortlisted', 'jobcore' ),
		'rejected'    => __( 'Rejected', 'jobcore' ),
	);
}

/**
 * Status of an application.
 *
 * @param int|WP_Post $app Application.
 */
function wpjc_app_status( $app ) {
	$status = (string) get_post_meta( is_object( $app ) ? $app->ID : (int) $app, '_wpjc_app_status', true );
	return isset( wpjc_app_statuses()[ $status ] ) ? $status : 'new';
}

/**
 * Status the candidate sees: employer decision, or that the job ad is gone.
 *
 * @param WP_Post      $app Application.
 * @param WP_Post|null $job Job ad.
 * @return array{0:string,1:string} Badge class suffix, label.
 */
function wpjc_app_candidate_status( $app, $job = null ) {
	$status = wpjc_app_status( $app );
	if ( 'shortlisted' === $status ) {
		return array( 'app-shortlisted', __( 'Shortlisted', 'jobcore' ) );
	}
	if ( 'rejected' === $status ) {
		return array( 'app-rejected', __( 'Not selected', 'jobcore' ) );
	}
	$job = $job instanceof WP_Post ? $job : get_post( (int) $app->post_parent );
	if ( ! $job || 'publish' !== $job->post_status ) {
		return array( 'closed', __( 'Job ad removed', 'jobcore' ) );
	}
	if ( ! wpjc_can_apply( $job ) ) {
		return array( 'closed', __( 'Applications closed', 'jobcore' ) );
	}
	return array( 'sent', __( 'Sent', 'jobcore' ) );
}

/* ---------- Protected file storage ---------- */

/**
 * Folder for application files. Define WPJC_APP_FILES_DIR to keep them outside the web root;
 * otherwise a folder in uploads with a random name, denied on Apache and IIS. On nginx the
 * random folder and file names keep the files unguessable.
 *
 * @return string Path without trailing slash, '' when it cannot be created.
 */
function wpjc_app_dir() {
	if ( defined( 'WPJC_APP_FILES_DIR' ) && WPJC_APP_FILES_DIR ) {
		$dir = untrailingslashit( (string) WPJC_APP_FILES_DIR );
	} else {
		$key = (string) get_option( 'wpjc_app_dir_key' );
		if ( ! preg_match( '/^[a-z0-9]{16}$/', $key ) ) {
			$key = strtolower( wp_generate_password( 16, false ) );
			update_option( 'wpjc_app_dir_key', $key, false );
		}
		$upload = wp_upload_dir( null, false );
		$dir    = trailingslashit( $upload['basedir'] ) . 'wpjc-applications-' . $key;
	}
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return '';
	}
	$guards = array(
		'.htaccess'  => "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
		'index.php'  => "<?php\n// Silence is golden.\n",
		'web.config' => "<?xml version=\"1.0\"?>\n<configuration><system.webServer><authorization><deny users=\"*\" /></authorization></system.webServer></configuration>\n",
	);
	foreach ( $guards as $name => $body ) {
		if ( ! file_exists( $dir . '/' . $name ) ) {
			file_put_contents( $dir . '/' . $name, $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny guard files in uploads, no WP_Filesystem credentials on the front end.
		}
	}
	return $dir;
}

/**
 * Keep copies of an application's uploads (the originals are deleted after the e-mail).
 *
 * @param int      $app_id Application.
 * @param string[] $files  Uploaded file paths.
 */
function wpjc_app_keep_files( $app_id, array $files ) {
	if ( ! $files || ! wpjc_opt( 'app_files' ) ) {
		return;
	}
	$dir = wpjc_app_dir();
	if ( '' === $dir ) {
		return;
	}
	$kept = array();
	foreach ( $files as $path ) {
		if ( ! is_file( $path ) ) {
			continue;
		}
		$name   = wp_basename( $path );
		$stored = $app_id . '-' . strtolower( wp_generate_password( 32, false ) );
		if ( copy( $path, $dir . '/' . $stored ) ) {
			$type   = wp_check_filetype( $name );
			$kept[] = array(
				'name' => $name,
				'file' => $stored,
				'size' => (int) filesize( $path ),
				'type' => (string) $type['type'],
			);
		}
	}
	if ( $kept ) {
		update_post_meta( $app_id, '_wpjc_app_stored', $kept );
		update_post_meta( $app_id, '_wpjc_app_files_until', time() + (int) wpjc_opt( 'app_files_days' ) * DAY_IN_SECONDS );
	}
}

/**
 * Files still stored for an application.
 *
 * @param int $app_id Application.
 * @return array<int, array{name:string,file:string,size:int,type:string}>
 */
function wpjc_app_stored_files( $app_id ) {
	$files = get_post_meta( (int) $app_id, '_wpjc_app_stored', true );
	return is_array( $files ) ? array_values( $files ) : array();
}

/**
 * Delete the stored files of an application (the file names stay on the record).
 *
 * @param int $app_id Application.
 */
function wpjc_app_delete_files( $app_id ) {
	$files = wpjc_app_stored_files( $app_id );
	if ( $files ) {
		$dir = wpjc_app_dir();
		foreach ( $files as $file ) {
			$path = $dir . '/' . wp_basename( $file['file'] );
			if ( '' !== $dir && is_file( $path ) ) {
				wp_delete_file( $path );
			}
		}
	}
	delete_post_meta( $app_id, '_wpjc_app_stored' );
	delete_post_meta( $app_id, '_wpjc_app_files_until' );
}

add_action(
	'before_delete_post',
	static function ( $post_id, $post ) {
		if ( $post instanceof WP_Post && 'wpjc_application' === $post->post_type ) {
			wpjc_app_delete_files( $post_id );
		}
	},
	10,
	2
);

/* Daily: delete files past their keep date. */
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'wpjc_app_files_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpjc_app_files_cleanup' );
		}
	}
);

add_action( 'wpjc_app_files_cleanup', 'wpjc_app_files_cleanup' );

/**
 * Delete application files whose keep date has passed.
 */
function wpjc_app_files_cleanup() {
	$ids = get_posts(
		array(
			'post_type'      => 'wpjc_application',
			'post_status'    => 'any',
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- daily cron, small result set.
				array(
					'key'     => '_wpjc_app_files_until',
					'value'   => time(),
					'compare' => '<',
					'type'    => 'NUMERIC',
				),
			),
		)
	);
	foreach ( $ids as $id ) {
		wpjc_app_delete_files( (int) $id );
	}
}

/**
 * Whether a user may see an application: the owner of its job ad, or an editor.
 *
 * @param WP_Post|null $app     Application.
 * @param int          $user_id User.
 */
function wpjc_user_can_see_app( $app, $user_id ) {
	if ( ! $app instanceof WP_Post || 'wpjc_application' !== $app->post_type || ! $user_id ) {
		return false;
	}
	return user_can( $user_id, 'edit_post', $app->ID ) || wpjc_user_owns_job( get_post( (int) $app->post_parent ), $user_id );
}

/**
 * Download link for a stored file.
 *
 * @param int $app_id Application.
 * @param int $index  File index.
 */
function wpjc_app_file_url( $app_id, $index ) {
	return add_query_arg(
		array(
			'action'   => 'wpjc_app_file',
			'app'      => (int) $app_id,
			'i'        => (int) $index,
			'_wpnonce' => wp_create_nonce( 'wpjc_app_file_' . (int) $app_id ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Send a stored file to the job's owner (or an editor).
 */
function wpjc_handle_app_file() {
	$app_id = isset( $_GET['app'] ) ? absint( $_GET['app'] ) : 0;
	$index  = isset( $_GET['i'] ) ? absint( $_GET['i'] ) : 0;
	$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpjc_app_file_' . $app_id ) || ! wpjc_user_can_see_app( get_post( $app_id ), get_current_user_id() ) ) {
		wp_die( esc_html__( 'You cannot open this file.', 'jobcore' ), '', array( 'response' => 403 ) );
	}
	$files = wpjc_app_stored_files( $app_id );
	$dir   = wpjc_app_dir();
	$file  = $files[ $index ] ?? null;
	$path  = $file && '' !== $dir ? $dir . '/' . wp_basename( $file['file'] ) : '';
	if ( '' === $path || ! is_file( $path ) ) {
		wp_die( esc_html__( 'This file is no longer stored.', 'jobcore' ), '', array( 'response' => 404 ) );
	}
	nocache_headers();
	header( 'Content-Type: ' . ( '' !== $file['type'] ? $file['type'] : 'application/octet-stream' ) );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file['name'] ) . '"' );
	header( 'Content-Length: ' . (int) filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- streams a protected file to an authorised user.
	exit;
}

/* ---------- Inbox ---------- */

/**
 * Applications for a user's job ads, newest first.
 *
 * @param int    $user_id User.
 * @param int    $job_id  Only this job (0 = all).
 * @param string $status  Only this status ('' = all).
 * @return WP_Post[]
 */
function wpjc_received_applications( $user_id, $job_id = 0, $status = '' ) {
	$jobs = wp_list_pluck( wpjc_user_jobs( $user_id ), 'ID' );
	if ( $job_id ) {
		$jobs = in_array( (int) $job_id, array_map( 'intval', $jobs ), true ) ? array( (int) $job_id ) : array();
	}
	if ( ! $jobs ) {
		return array();
	}
	$apps = get_posts(
		array(
			'post_type'       => 'wpjc_application',
			'post_status'     => 'private',
			'post_parent__in' => array_map( 'intval', $jobs ),
			'posts_per_page'  => 300,
			'orderby'         => 'date',
			'order'           => 'DESC',
		)
	);
	if ( '' === $status ) {
		return $apps;
	}
	return array_values(
		array_filter(
			$apps,
			static function ( $app ) use ( $status ) {
				return wpjc_app_status( $app ) === $status;
			}
		)
	);
}

/**
 * Number of applications with status "New" for a user's job ads.
 *
 * @param int $user_id User.
 */
function wpjc_received_new_count( $user_id ) {
	return count( wpjc_received_applications( $user_id, 0, 'new' ) );
}

/**
 * Inbox URL with filters.
 *
 * @param int    $job_id Job filter.
 * @param string $status Status filter.
 */
function wpjc_received_url( $job_id = 0, $status = '' ) {
	return add_query_arg(
		array_filter(
			array(
				'job'    => $job_id ? (int) $job_id : null,
				'status' => '' !== $status ? $status : null,
			)
		),
		wpjc_account_url( 'received' )
	);
}

/**
 * Change the status of an application (employer).
 */
function wpjc_handle_app_status() {
	$user_id = wpjc_account_guard( 'app_status' );
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in wpjc_account_guard().
	$app_id = isset( $_POST['app_id'] ) ? absint( $_POST['app_id'] ) : 0;
	$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
	$job    = isset( $_POST['filter_job'] ) ? absint( $_POST['filter_job'] ) : 0;
	$filter = isset( $_POST['filter_status'] ) ? sanitize_key( wp_unslash( $_POST['filter_status'] ) ) : '';
	// phpcs:enable
	$app = get_post( $app_id );
	if ( ! wpjc_user_can_see_app( $app, $user_id ) || ! isset( wpjc_app_statuses()[ $status ] ) ) {
		wpjc_account_back( 'received', '', 'not_found' );
	}
	update_post_meta( $app_id, '_wpjc_app_status', $status );
	do_action( 'wpjc_application_status_changed', $app_id, $status, $user_id );
	$filter = isset( wpjc_app_statuses()[ $filter ] ) ? $filter : '';
	wp_safe_redirect( add_query_arg( 'wpjc_msg', 'app_status', wpjc_received_url( $job, $filter ) ) . '#wpjc-app-' . $app_id );
	exit;
}

add_filter(
	'wpjc_account_notices',
	static function ( $list ) {
		$list['app_status'] = array( 'ok', __( 'Status saved.', 'jobcore' ) );
		return $list;
	},
	5
);

/* ---------- Wp-admin ---------- */

add_filter(
	'manage_wpjc_application_posts_columns',
	static function ( $cols ) {
		$cols = array_slice( $cols, 0, -1, true ) + array( 'wpjc_status' => __( 'Status', 'jobcore' ) ) + array_slice( $cols, -1, null, true );
		return $cols;
	},
	20
);

add_action(
	'manage_wpjc_application_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'wpjc_status' === $col ) {
			echo esc_html( wpjc_app_statuses()[ wpjc_app_status( $post_id ) ] );
		}
	},
	10,
	2
);
