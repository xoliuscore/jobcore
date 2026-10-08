<?php
/**
 * Stored applications: a private record of every application sent through the apply form,
 * listed in the jobs account ("My applications") and under Jobs → Applications.
 * Uploaded files are not kept; only their names are.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		register_post_type(
			'wpjc_application',
			array(
				'labels'              => array(
					'name'          => __( 'Applications', 'jobcore' ),
					'singular_name' => __( 'Application', 'jobcore' ),
					'all_items'     => __( 'Applications', 'jobcore' ),
					'edit_item'     => __( 'Application', 'jobcore' ),
					'search_items'  => __( 'Search applications', 'jobcore' ),
					'not_found'     => __( 'No applications yet.', 'jobcore' ),
				),
				'public'              => false,
				'show_ui'             => true,
				'show_in_menu'        => 'edit.php?post_type=wpjc_job',
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
				'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
				'map_meta_cap'        => true,
			)
		);
	},
	5
);

/**
 * Save an application: the employer finds it under "Applications received", even when the e-mail failed.
 *
 * @param WP_Post  $job   Job.
 * @param array    $data  Applicant fields.
 * @param string[] $files Uploaded file paths.
 * @param bool     $ok    Whether the e-mail to the employer was sent.
 * @return int Application ID, 0 when not stored.
 */
function wpjc_store_application( $job, $data, $files, $ok ) {
	if ( ! apply_filters( 'wpjc_store_applications', true, $job, $data ) ) {
		return 0;
	}
	$name = trim( $data['first_name'] . ' ' . $data['last_name'] );
	$id   = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'wpjc_application',
				'post_status'  => 'private',
				'post_parent'  => (int) $job->ID,
				'post_author'  => get_current_user_id(),
				/* translators: 1: applicant name, 2: job title */
				'post_title'   => sprintf( __( '%1$s — %2$s', 'jobcore' ), $name, get_the_title( $job ) ),
				'post_content' => $data['message'],
				'meta_input'   => array(
					'_wpjc_app_first'  => $data['first_name'],
					'_wpjc_app_last'   => $data['last_name'],
					'_wpjc_app_email'  => $data['email'],
					'_wpjc_app_phone'  => $data['phone'],
					'_wpjc_app_files'  => array_map( 'wp_basename', (array) $files ),
					'_wpjc_app_job'    => get_the_title( $job ),
					'_wpjc_app_status' => 'new',
					'_wpjc_app_mailed' => $ok ? 1 : 0,
				),
			)
		),
		true
	);
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	wpjc_app_keep_files( (int) $id, (array) $files );
	do_action( 'wpjc_application_stored', (int) $id, $job, $data );
	return (int) $id;
}

/**
 * A user's applications, newest first.
 *
 * @param int $user_id User ID.
 * @param int $limit   Max rows.
 * @return WP_Post[]
 */
function wpjc_user_applications( $user_id, $limit = 100 ) {
	if ( ! $user_id ) {
		return array();
	}
	return get_posts(
		array(
			'post_type'      => 'wpjc_application',
			'post_status'    => 'private',
			'author'         => (int) $user_id,
			'posts_per_page' => (int) $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
}

/**
 * The user's latest application for a job, if any.
 *
 * @param int $job_id  Job ID.
 * @param int $user_id User ID.
 * @return WP_Post|null
 */
function wpjc_user_application_for( $job_id, $user_id ) {
	if ( ! $job_id || ! $user_id ) {
		return null;
	}
	$found = get_posts(
		array(
			'post_type'      => 'wpjc_application',
			'post_status'    => 'private',
			'author'         => (int) $user_id,
			'post_parent'    => (int) $job_id,
			'posts_per_page' => 1,
		)
	);
	return $found ? $found[0] : null;
}

/**
 * Number of applications received for a job.
 *
 * @param int $job_id Job ID.
 */
function wpjc_job_application_count( $job_id ) {
	$q = new WP_Query(
		array(
			'post_type'      => 'wpjc_application',
			'post_status'    => 'private',
			'post_parent'    => (int) $job_id,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return (int) $q->found_posts;
}

/*
 * Admin list: applicant contact and job columns.
 */
add_filter(
	'manage_wpjc_application_posts_columns',
	static function ( $cols ) {
		return array(
			'cb'         => $cols['cb'] ?? '',
			'title'      => __( 'Application', 'jobcore' ),
			'wpjc_job'   => __( 'Job', 'jobcore' ),
			'wpjc_email' => __( 'E-mail', 'jobcore' ),
			'wpjc_phone' => __( 'Phone', 'jobcore' ),
			'date'       => __( 'Date', 'jobcore' ),
		);
	}
);

add_action(
	'manage_wpjc_application_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'wpjc_job' === $col ) {
			$job = get_post( (int) wp_get_post_parent_id( $post_id ) );
			if ( $job ) {
				printf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_edit_post_link( $job ) ), esc_html( get_the_title( $job ) ) );
			} else {
				echo esc_html( (string) get_post_meta( $post_id, '_wpjc_app_job', true ) );
			}
		} elseif ( 'wpjc_email' === $col ) {
			$email = (string) get_post_meta( $post_id, '_wpjc_app_email', true );
			printf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
		} elseif ( 'wpjc_phone' === $col ) {
			echo esc_html( (string) get_post_meta( $post_id, '_wpjc_app_phone', true ) );
		}
	},
	10,
	2
);

add_action(
	'add_meta_boxes_wpjc_application',
	static function () {
		add_meta_box(
			'wpjc_application_details',
			__( 'Applicant', 'jobcore' ),
			static function ( $post ) {
				$files  = (array) get_post_meta( $post->ID, '_wpjc_app_files', true );
				$stored = wpjc_app_stored_files( $post->ID );
				$rows   = array(
					__( 'Name', 'jobcore' )   => trim( get_post_meta( $post->ID, '_wpjc_app_first', true ) . ' ' . get_post_meta( $post->ID, '_wpjc_app_last', true ) ),
					__( 'E-mail', 'jobcore' ) => (string) get_post_meta( $post->ID, '_wpjc_app_email', true ),
					__( 'Phone', 'jobcore' )  => (string) get_post_meta( $post->ID, '_wpjc_app_phone', true ),
					__( 'Job', 'jobcore' )    => (string) get_post_meta( $post->ID, '_wpjc_app_job', true ),
					__( 'Status', 'jobcore' ) => wpjc_app_statuses()[ wpjc_app_status( $post ) ],
				);
				echo '<table class="widefat striped"><tbody>';
				foreach ( $rows as $label => $value ) {
					printf( '<tr><th style="width:120px">%1$s</th><td>%2$s</td></tr>', esc_html( $label ), esc_html( $value ) );
				}
				echo '<tr><th style="width:120px">' . esc_html__( 'Attached', 'jobcore' ) . '</th><td>';
				if ( $stored ) {
					foreach ( $stored as $i => $file ) {
						printf( '<a href="%1$s">%2$s</a><br>', esc_url( wpjc_app_file_url( $post->ID, $i ) ), esc_html( $file['name'] ) );
					}
				} else {
					echo esc_html( $files ? implode( ', ', $files ) : __( 'No files', 'jobcore' ) );
				}
				echo '</td></tr>';
				do_action( 'wpjc_application_admin_rows', $post );
				echo '</tbody></table>';
				$until = (int) get_post_meta( $post->ID, '_wpjc_app_files_until', true );
				echo '<p class="description">' . esc_html(
					$stored && $until
						/* translators: %s: date */
						? sprintf( __( 'The files are stored privately until %s, then deleted.', 'jobcore' ), wp_date( get_option( 'date_format' ), $until ) )
						: __( 'The files were e-mailed to the employer and are not stored on this site.', 'jobcore' )
				) . '</p>';
			},
			'wpjc_application',
			'side'
		);
	}
);

/*
 * Privacy tools (Tools → Export / Erase Personal Data): applications by e-mail address.
 */
add_filter(
	'wp_privacy_personal_data_exporters',
	static function ( $exporters ) {
		$exporters['wp-job-core-applications'] = array(
			'exporter_friendly_name' => __( 'Job applications', 'jobcore' ),
			'callback'               => 'wpjc_privacy_export_applications',
		);
		return $exporters;
	}
);

add_filter(
	'wp_privacy_personal_data_erasers',
	static function ( $erasers ) {
		$erasers['wp-job-core-applications'] = array(
			'eraser_friendly_name' => __( 'Job applications', 'jobcore' ),
			'callback'             => 'wpjc_privacy_erase_applications',
		);
		return $erasers;
	}
);

/**
 * Applications sent with an e-mail address (or by the user who has it).
 *
 * @param string $email E-mail.
 * @return WP_Post[]
 */
function wpjc_applications_by_email( $email ) {
	$args = array(
		'post_type'      => 'wpjc_application',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => '_wpjc_app_email', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'     => $email, // phpcs:ignore WordPress.DB.SlowDBQuery
	);
	$found = get_posts( $args );
	$user  = get_user_by( 'email', $email );
	if ( $user ) {
		unset( $args['meta_key'], $args['meta_value'] );
		$args['author'] = $user->ID;
		$found          = array_merge( $found, get_posts( $args ) );
	}
	$unique = array();
	foreach ( $found as $post ) {
		$unique[ $post->ID ] = $post;
	}
	return array_values( $unique );
}

/**
 * Personal data exporter.
 *
 * @param string $email E-mail.
 * @return array
 */
function wpjc_privacy_export_applications( $email ) {
	$items = array();
	foreach ( wpjc_applications_by_email( $email ) as $post ) {
		$items[] = array(
			'group_id'    => 'wpjc-applications',
			'group_label' => __( 'Job applications', 'jobcore' ),
			'item_id'     => 'wpjc-application-' . $post->ID,
			'data'        => array(
				array(
					'name'  => __( 'Job', 'jobcore' ),
					'value' => (string) get_post_meta( $post->ID, '_wpjc_app_job', true ),
				),
				array(
					'name'  => __( 'Date', 'jobcore' ),
					'value' => get_the_date( 'Y-m-d H:i', $post ),
				),
				array(
					'name'  => __( 'Name', 'jobcore' ),
					'value' => trim( get_post_meta( $post->ID, '_wpjc_app_first', true ) . ' ' . get_post_meta( $post->ID, '_wpjc_app_last', true ) ),
				),
				array(
					'name'  => __( 'Phone', 'jobcore' ),
					'value' => (string) get_post_meta( $post->ID, '_wpjc_app_phone', true ),
				),
				array(
					'name'  => __( 'Cover letter', 'jobcore' ),
					'value' => $post->post_content,
				),
			),
		);
	}
	return array(
		'data' => $items,
		'done' => true,
	);
}

/**
 * Personal data eraser.
 *
 * @param string $email E-mail.
 * @return array
 */
function wpjc_privacy_erase_applications( $email ) {
	$removed = false;
	foreach ( wpjc_applications_by_email( $email ) as $post ) {
		$removed = (bool) wp_delete_post( $post->ID, true ) || $removed;
	}
	return array(
		'items_removed'  => $removed,
		'items_retained' => false,
		'messages'       => array(),
		'done'           => true,
	);
}
