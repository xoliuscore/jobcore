<?php
/**
 * Jobs import / export on News. → Import / Export.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_IMPORT_MAX = 12 * MB_IN_BYTES;

/**
 * Employer term meta that travels with an export (no user or attachment IDs).
 *
 * @return string[]
 */
function wpjc_export_employer_meta_keys() {
	return array(
		'wpjc_logo',
		'wpjc_website',
		'wpjc_legal_name',
		'wpjc_reg_no',
		'wpjc_vat',
		'wpjc_address',
		'wpjc_city',
		'wpjc_postcode',
		'wpjc_country',
		'wpjc_contact_name',
		'wpjc_contact_phone',
		'wpjc_contact_email',
		'wpjc_active',
	);
}

/**
 * Job post statuses included in an export.
 *
 * @return string[]
 */
function wpjc_export_job_statuses() {
	$statuses = array( 'publish', 'pending', 'draft', 'future', 'private' );
	if ( defined( 'WPJCPL_UNPAID' ) ) {
		$statuses[] = WPJCPL_UNPAID;
	}
	return $statuses;
}

/**
 * Terms of a jobs taxonomy for the export document.
 *
 * @param string $taxonomy Taxonomy.
 * @return array<int, array<string, mixed>>
 */
function wpjc_export_terms( $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $term ) {
		$row = array(
			'slug'        => $term->slug,
			'name'        => $term->name,
			'description' => $term->description,
		);
		if ( is_taxonomy_hierarchical( $taxonomy ) && $term->parent ) {
			$parent = get_term( $term->parent, $taxonomy );
			$row['parent'] = $parent instanceof WP_Term ? $parent->slug : '';
		}
		if ( 'wpjc_employer' === $taxonomy ) {
			$meta = array();
			foreach ( wpjc_export_employer_meta_keys() as $key ) {
				$val = get_term_meta( $term->term_id, $key, true );
				if ( '' !== $val && false !== $val ) {
					$meta[ $key ] = is_scalar( $val ) ? (string) $val : '';
				}
			}
			$row['meta'] = $meta;
		}
		$out[] = $row;
	}
	return $out;
}

/**
 * One job as a portable array.
 *
 * @param WP_Post $job Job.
 * @return array<string, mixed>
 */
function wpjc_export_job( WP_Post $job ) {
	$meta = array();
	foreach ( array_keys( wpjc_meta_fields() ) as $key ) {
		$meta[ $key ] = get_post_meta( $job->ID, '_wpjc_' . $key, true );
	}
	$custom = array();
	foreach ( get_post_meta( $job->ID ) as $key => $values ) {
		if ( ! str_starts_with( $key, '_wpjc_cf_' ) ) {
			continue;
		}
		$custom[ substr( $key, 9 ) ] = isset( $values[0] ) ? (string) $values[0] : '';
	}
	$terms = static function ( $taxonomy ) use ( $job ) {
		$slugs = wp_get_object_terms( $job->ID, $taxonomy, array( 'fields' => 'slugs' ) );
		return is_wp_error( $slugs ) ? array() : array_values( $slugs );
	};
	return array(
		'slug'       => $job->post_name,
		'title'      => $job->post_title,
		'content'    => $job->post_content,
		'excerpt'    => $job->post_excerpt,
		'status'     => $job->post_status,
		'date'       => $job->post_date_gmt,
		'photo'      => (string) get_the_post_thumbnail_url( $job, 'full' ),
		'meta'       => $meta,
		'custom'     => $custom,
		'types'      => $terms( 'wpjc_job_type' ),
		'categories' => $terms( 'wpjc_job_category' ),
		'employers'  => $terms( 'wpjc_employer' ),
	);
}

/**
 * The jobs export document.
 *
 * @return array<string, mixed>
 */
function wpjc_export_data() {
	$jobs = get_posts(
		array(
			'post_type'      => 'wpjc_job',
			'post_status'    => wpjc_export_job_statuses(),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$data = array(
		'type'       => 'wp-job-core',
		'version'    => WPJC_VERSION,
		'exported'   => gmdate( 'c' ),
		'site'       => home_url( '/' ),
		'settings'   => array_intersect_key(
			wp_parse_args( (array) get_option( 'wpjc_settings', array() ), wpjc_default_settings() ),
			wpjc_default_settings()
		),
		'taxonomies' => array(
			'wpjc_job_type'     => wpjc_export_terms( 'wpjc_job_type' ),
			'wpjc_job_category' => wpjc_export_terms( 'wpjc_job_category' ),
			'wpjc_employer'     => wpjc_export_terms( 'wpjc_employer' ),
		),
		'jobs'       => array_map( 'wpjc_export_job', $jobs ),
	);
	if ( function_exists( 'wpjcf_all' ) ) {
		$data['fields'] = wpjcf_all();
	}
	if ( function_exists( 'wpjca_settings' ) ) {
		$ads    = wpjca_settings();
		$slots  = array();
		foreach ( $ads['slots'] as $slot ) {
			$slug = $slot['job'] ? (string) get_post_field( 'post_name', (int) $slot['job'] ) : '';
			if ( '' === $slug ) {
				continue;
			}
			$slots[] = array(
				'job'   => $slug,
				'image' => (string) $slot['image'],
			);
		}
		$data['ads'] = array(
			'enabled' => (int) $ads['enabled'],
			'title'   => (string) $ads['title'],
			'slots'   => $slots,
		);
	}
	return $data;
}

add_action(
	'admin_post_wpjc_jobs_export',
	static function () {
		if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'jobcore' ) );
		}
		check_admin_referer( 'wpjc_jobs_export' );
		$data = wpjc_export_data();
		$name = sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) . '-jobs-' . gmdate( 'Y-m-d' ) . '.json' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON download.
		exit;
	}
);

/**
 * Reuse or sideload an image from a URL. Returns 0 when it cannot be imported.
 *
 * @param string $url    Image URL.
 * @param int    $parent Parent post.
 */
function wpjc_import_image( $url, $parent = 0 ) {
	$url = esc_url_raw( (string) $url );
	if ( '' === $url ) {
		return 0;
	}
	$found = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_wpjc_import_src', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'no_found_rows'  => true,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$id = media_sideload_image( $url, $parent, null, 'id' );
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	update_post_meta( (int) $id, '_wpjc_import_src', $url );
	return (int) $id;
}

/**
 * Create or update a taxonomy term from an export row.
 *
 * @param string $taxonomy Taxonomy.
 * @param array  $row      Term row.
 * @return int Term ID.
 */
function wpjc_import_term( $taxonomy, array $row ) {
	$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
	$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
	if ( '' === $slug || '' === $name ) {
		return 0;
	}
	$parent = 0;
	if ( ! empty( $row['parent'] ) ) {
		$found = get_term_by( 'slug', sanitize_title( (string) $row['parent'] ), $taxonomy );
		$parent = $found instanceof WP_Term ? (int) $found->term_id : 0;
	}
	$args = array(
		'slug'        => $slug,
		'description' => sanitize_textarea_field( (string) ( $row['description'] ?? '' ) ),
		'parent'      => $parent,
	);
	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( $term instanceof WP_Term ) {
		wp_update_term( $term->term_id, $taxonomy, array_merge( $args, array( 'name' => $name ) ) );
		$id = (int) $term->term_id;
	} else {
		$made = wp_insert_term( $name, $taxonomy, $args );
		$id   = is_wp_error( $made ) ? 0 : (int) $made['term_id'];
	}
	if ( $id && 'wpjc_employer' === $taxonomy && isset( $row['meta'] ) && is_array( $row['meta'] ) ) {
		foreach ( wpjc_export_employer_meta_keys() as $key ) {
			if ( ! array_key_exists( $key, $row['meta'] ) ) {
				continue;
			}
			$val = (string) $row['meta'][ $key ];
			if ( in_array( $key, array( 'wpjc_logo', 'wpjc_website' ), true ) ) {
				$val = esc_url_raw( $val );
			} elseif ( 'wpjc_contact_email' === $key ) {
				$val = sanitize_email( $val );
			} elseif ( 'wpjc_active' === $key ) {
				$val = empty( $val ) ? '0' : '1';
			} else {
				$val = sanitize_text_field( $val );
			}
			update_term_meta( $id, $key, $val );
		}
		if ( ! empty( $row['meta']['wpjc_logo'] ) ) {
			$logo_id = wpjc_import_image( (string) $row['meta']['wpjc_logo'] );
			if ( $logo_id ) {
				update_term_meta( $id, 'wpjc_logo_id', $logo_id );
				$src = wp_get_attachment_image_url( $logo_id, 'medium' );
				if ( $src ) {
					update_term_meta( $id, 'wpjc_logo', $src );
				}
			}
		}
	}
	return $id;
}

/**
 * Create or update a job from an export row.
 *
 * @param array $row Job row.
 * @return int Job ID.
 */
function wpjc_import_job( array $row ) {
	$slug  = sanitize_title( (string) ( $row['slug'] ?? '' ) );
	$title = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
	if ( '' === $slug || '' === $title ) {
		return 0;
	}
	$status  = sanitize_key( (string) ( $row['status'] ?? 'draft' ) );
	$allowed = wpjc_export_job_statuses();
	if ( ! in_array( $status, $allowed, true ) ) {
		$status = 'draft';
	}
	$date = sanitize_text_field( (string) ( $row['date'] ?? '' ) );
	if ( $date && false === strtotime( $date ) ) {
		$date = '';
	}
	$found = get_posts(
		array(
			'name'           => $slug,
			'post_type'      => 'wpjc_job',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	$payload = array(
		'post_type'    => 'wpjc_job',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_content' => wp_kses_post( (string) ( $row['content'] ?? '' ) ),
		'post_excerpt' => wp_kses_post( (string) ( $row['excerpt'] ?? '' ) ),
		'post_status'  => $status,
		'post_author'  => get_current_user_id(),
	);
	if ( $date ) {
		$payload['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', strtotime( $date ) );
	}
	if ( $found ) {
		$payload['ID'] = (int) $found[0];
		$id            = wp_update_post( wp_slash( $payload ), true );
	} else {
		$id = wp_insert_post( wp_slash( $payload ), true );
	}
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	$id = (int) $id;
	foreach ( (array) ( $row['meta'] ?? array() ) as $key => $value ) {
		$key = sanitize_key( (string) $key );
		if ( ! isset( wpjc_meta_fields()[ $key ] ) ) {
			continue;
		}
		update_post_meta( $id, '_wpjc_' . $key, wpjc_sanitize_meta_value( $key, $value ) );
	}
	foreach ( (array) ( $row['custom'] ?? array() ) as $key => $value ) {
		$key = sanitize_key( (string) $key );
		if ( '' === $key ) {
			continue;
		}
		$field = function_exists( 'wpjcf_field' ) ? wpjcf_field( 'job', $key ) : null;
		$val   = $field && function_exists( 'wpjcf_sanitize_value' )
			? wpjcf_sanitize_value( $field, $value )
			: sanitize_text_field( (string) $value );
		update_post_meta( $id, '_wpjc_cf_' . $key, $val );
	}
	$assign = array(
		'wpjc_job_type'     => (array) ( $row['types'] ?? array() ),
		'wpjc_job_category' => (array) ( $row['categories'] ?? array() ),
		'wpjc_employer'     => (array) ( $row['employers'] ?? array() ),
	);
	foreach ( $assign as $taxonomy => $slugs ) {
		$term_ids = array();
		foreach ( $slugs as $term_slug ) {
			$term = get_term_by( 'slug', sanitize_title( (string) $term_slug ), $taxonomy );
			if ( $term instanceof WP_Term ) {
				$term_ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $id, $term_ids, $taxonomy );
	}
	$photo = wpjc_import_image( (string) ( $row['photo'] ?? '' ), $id );
	if ( $photo ) {
		set_post_thumbnail( $id, $photo );
	}
	return $id;
}

/**
 * Apply a jobs export document.
 *
 * @param array $data  Decoded JSON.
 * @param bool  $reset Trash jobs whose slug is not in the file.
 * @return array|WP_Error [ jobs, employers, settings ]
 */
function wpjc_import_data( array $data, $reset = false ) {
	if ( 'wp-job-core' !== ( $data['type'] ?? '' ) || ! isset( $data['jobs'] ) || ! is_array( $data['jobs'] ) ) {
		return new WP_Error( 'wpjc_import_format', __( 'This is not a JobCore jobs file.', 'jobcore' ) );
	}

	if ( isset( $data['fields'] ) && is_array( $data['fields'] ) && function_exists( 'wpjcf_save_all' ) ) {
		wpjcf_save_all( $data['fields'] );
	}

	if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
		update_option( 'wpjc_settings', wpjc_sanitize_settings( array_merge( wpjc_default_settings(), $data['settings'] ) ) );
	}

	$taxonomies = (array) ( $data['taxonomies'] ?? array() );
	foreach ( array( 'wpjc_job_type', 'wpjc_job_category', 'wpjc_employer' ) as $taxonomy ) {
		$rows = isset( $taxonomies[ $taxonomy ] ) && is_array( $taxonomies[ $taxonomy ] ) ? $taxonomies[ $taxonomy ] : array();
		usort(
			$rows,
			static function ( $a, $b ) {
				$ap = empty( $a['parent'] ) ? 0 : 1;
				$bp = empty( $b['parent'] ) ? 0 : 1;
				return $ap <=> $bp;
			}
		);
		foreach ( $rows as $row ) {
			if ( is_array( $row ) ) {
				wpjc_import_term( $taxonomy, $row );
			}
		}
	}

	$slugs = array();
	$jobs  = 0;
	foreach ( $data['jobs'] as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}
		$id = wpjc_import_job( $row );
		if ( $id ) {
			++$jobs;
			$slugs[] = sanitize_title( (string) ( $row['slug'] ?? '' ) );
		}
	}

	if ( $reset ) {
		$keep = array_filter( $slugs );
		$gone = get_posts(
			array(
				'post_type'      => 'wpjc_job',
				'post_status'    => wpjc_export_job_statuses(),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $gone as $job_id ) {
			$name = (string) get_post_field( 'post_name', $job_id );
			if ( ! in_array( $name, $keep, true ) ) {
				wp_trash_post( (int) $job_id );
			}
		}
	}

	if ( isset( $data['ads'] ) && is_array( $data['ads'] ) && function_exists( 'wpjca_save_settings' ) ) {
		$slots = array();
		foreach ( (array) ( $data['ads']['slots'] ?? array() ) as $slot ) {
			if ( ! is_array( $slot ) || empty( $slot['job'] ) ) {
				continue;
			}
			$found = get_posts(
				array(
					'name'           => sanitize_title( (string) $slot['job'] ),
					'post_type'      => 'wpjc_job',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				)
			);
			if ( $found ) {
				$slots[] = array(
					'job'   => (int) $found[0],
					'image' => esc_url_raw( (string) ( $slot['image'] ?? '' ) ),
				);
			}
		}
		wpjca_save_settings(
			array(
				'enabled' => empty( $data['ads']['enabled'] ) ? 0 : 1,
				'title'   => (string) ( $data['ads']['title'] ?? '' ),
				'slots'   => $slots,
			)
		);
	}

	if ( function_exists( 'wpjc_flush_facets' ) ) {
		wpjc_flush_facets();
	}

	$employers = isset( $taxonomies['wpjc_employer'] ) && is_array( $taxonomies['wpjc_employer'] ) ? count( $taxonomies['wpjc_employer'] ) : 0;
	return array( $jobs, $employers, isset( $data['settings'] ) ? 1 : 0 );
}

add_action(
	'admin_post_wpjc_jobs_import',
	static function () {
		if ( ! current_user_can( 'edit_theme_options' ) || ! current_user_can( 'edit_others_posts' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'jobcore' ) );
		}
		check_admin_referer( 'wpjc_jobs_import' );
		$back = admin_url( 'admin.php?page=news-tools' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload; only the temp path is used.
		$file = isset( $_FILES['wpjc_jobs'] ) && is_array( $_FILES['wpjc_jobs'] ) ? $_FILES['wpjc_jobs'] : array();
		$tmp  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		if ( ! $tmp || ! is_uploaded_file( $tmp ) || (int) ( $file['size'] ?? 0 ) > WPJC_IMPORT_MAX ) {
			wp_safe_redirect( add_query_arg( 'wpjc-import', 'nofile', $back ) . '#wpjc-tools' );
			exit;
		}
		$data = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$res  = is_array( $data ) ? wpjc_import_data( $data, ! empty( $_POST['reset'] ) ) : new WP_Error( 'wpjc_import_format', '' );
		if ( is_wp_error( $res ) ) {
			wp_safe_redirect( add_query_arg( 'wpjc-import', 'invalid', $back ) . '#wpjc-tools' );
			exit;
		}
		wp_safe_redirect( add_query_arg( array( 'wpjc-import' => 'done', 'j' => $res[0], 'e' => $res[1] ), $back ) . '#wpjc-tools' );
		exit;
	}
);

/**
 * Jobs cards on News. → Import / Export.
 */
function wpjc_tools_cards() {
	if ( ! current_user_can( 'edit_others_posts' ) ) {
		return;
	}
	$state = isset( $_GET['wpjc-import'] ) ? sanitize_key( wp_unslash( $_GET['wpjc-import'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display state only.
	if ( 'done' === $state ) {
		$jobs      = isset( $_GET['j'] ) ? absint( wp_unslash( $_GET['j'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$employers = isset( $_GET['e'] ) ? absint( wp_unslash( $_GET['e'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success inline"><p>';
		echo esc_html(
			sprintf(
				/* translators: 1: number of jobs, 2: number of employers */
				__( 'Imported: %1$d job ads and %2$d employers. Board settings in the file were applied.', 'jobcore' ),
				$jobs,
				$employers
			)
		);
		echo '</p></div>';
	} elseif ( 'invalid' === $state ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'This is not a JobCore jobs file (or it is damaged). Nothing was changed.', 'jobcore' ) . '</p></div>';
	} elseif ( 'nofile' === $state ) {
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Please choose a jobs file (.json, max. 12 MB).', 'jobcore' ) . '</p></div>';
	}

	$count = (int) wp_count_posts( 'wpjc_job' )->publish;
	?>
	<h2 class="news-tools__h" id="wpjc-tools"><?php esc_html_e( 'Jobs', 'jobcore' ); ?></h2>
	<div class="news-tools">
		<section class="news-tools__card">
			<h3><?php esc_html_e( 'Export jobs', 'jobcore' ); ?></h3>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of published jobs */
						_n(
							'Saves your %d published job ad, plus drafts, employers, categories, job types and board settings — to back them up or copy them to another News. site.',
							'Saves your %d published job ads, plus drafts, employers, categories, job types and board settings — to back them up or copy them to another News. site.',
							$count,
							'jobcore'
						),
						$count
					)
				);
				?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpjc_jobs_export' ); ?>
				<input type="hidden" name="action" value="wpjc_jobs_export">
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Download jobs file', 'jobcore' ); ?></button></p>
			</form>
		</section>

		<section class="news-tools__card">
			<h3><?php esc_html_e( 'Import jobs', 'jobcore' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Loads a jobs file exported from a JobCore site. Jobs are matched by slug (updated when they already exist). Photos are downloaded when the original site is still reachable. Applications are not included.', 'jobcore' ); ?></p>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wpjc_jobs_import' ); ?>
				<input type="hidden" name="action" value="wpjc_jobs_import">
				<p><input type="file" name="wpjc_jobs" accept=".json,application/json" required></p>
				<p><label><input type="checkbox" name="reset" value="1"> <?php esc_html_e( 'Move job ads that are not in the file to the Trash (exact copy)', 'jobcore' ); ?></label></p>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Import jobs', 'jobcore' ); ?></button></p>
			</form>
		</section>
	</div>
	<?php
}

add_action( 'news_tools_after_settings', 'wpjc_tools_cards' );

add_filter(
	'wpjc_admin_nav',
	static function ( $nav ) {
		if ( ! function_exists( 'news_tools_screen' ) ) {
			return $nav;
		}
		$group                = __( 'System', 'jobcore' );
		$nav[ $group ]        = (array) ( $nav[ $group ] ?? array() );
		$nav[ $group ]['import'] = array(
			__( 'Import / Export', 'jobcore' ),
			'migrate',
			__( 'Save jobs, employers and board settings to a file, or load them from another site.', 'jobcore' ),
			admin_url( 'admin.php?page=news-tools#wpjc-tools' ),
		);
		return $nav;
	}
);
