<?php
/**
 * Import from WP Job Manager: jobs, companies (with logos), job types and categories move into
 * JobCore in small batches. WP Job Manager does not need to be active — its data is read straight
 * from the database. Running the import again updates what was imported before instead of
 * duplicating it. Old job addresses redirect to the new ones.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_WPJM_PAGE  = 'wpjc-wpjm';
const WPJC_WPJM_BATCH = 20;

/** Admin URL of the import screen. */
function wpjc_wpjm_url() {
	return admin_url( 'edit.php?post_type=wpjc_job&page=' . WPJC_WPJM_PAGE );
}

/**
 * WP Job Manager job statuses we read, and what they become in JobCore.
 *
 * @return array<string, string>
 */
function wpjc_wpjm_status_map() {
	return array(
		'publish'         => 'publish',
		'expired'         => 'publish', // Stays out of the board through its past end date.
		'pending'         => 'pending',
		'pending_payment' => 'pending',
		'draft'           => 'draft',
		'preview'         => 'draft',
		'private'         => 'private',
	);
}

/**
 * IDs of WP Job Manager jobs, oldest first.
 *
 * @param array $opts { expired: bool, filled: bool }.
 * @return int[]
 */
function wpjc_wpjm_job_ids( array $opts = array() ) {
	global $wpdb;
	$statuses = array_keys( wpjc_wpjm_status_map() );
	if ( empty( $opts['expired'] ) ) {
		$statuses = array_values( array_diff( $statuses, array( 'expired' ) ) );
	}
	$in  = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'job_listing' AND post_status IN ($in) ORDER BY post_date_gmt ASC, ID ASC";
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- placeholders built above.
	$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $statuses ) ) );
	if ( empty( $opts['filled'] ) ) {
		$ids = array_values( array_filter( $ids, static fn( $id ) => ! get_post_meta( $id, '_filled', true ) ) );
	}
	return $ids;
}

/**
 * What is there to import.
 *
 * @return array{jobs:int,open:int,expired:int,companies:int,types:int,categories:int,applications:int,imported:int}
 */
function wpjc_wpjm_summary() {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$by = (array) $wpdb->get_results( "SELECT post_status AS s, COUNT(*) AS n FROM {$wpdb->posts} WHERE post_type = 'job_listing' GROUP BY post_status", OBJECT_K );
	$n  = static fn( $s ) => isset( $by[ $s ] ) ? (int) $by[ $s ]->n : 0;
	$companies = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT m.meta_value) FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE p.post_type = 'job_listing' AND m.meta_key = '_company_name' AND m.meta_value <> ''" );
	$terms     = static fn( $tax ) => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $tax ) );
	$apps      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'job_application'" );
	$imported  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_wpjc_wpjm_id'" );
	// phpcs:enable
	$total = 0;
	foreach ( array_keys( wpjc_wpjm_status_map() ) as $s ) {
		$total += $n( $s );
	}
	return array(
		'jobs'         => $total,
		'open'         => $n( 'publish' ),
		'expired'      => $n( 'expired' ),
		'companies'    => $companies,
		'types'        => $terms( 'job_listing_type' ),
		'categories'   => $terms( 'job_listing_category' ),
		'applications' => $apps,
		'imported'     => $imported,
	);
}

/** Is there WP Job Manager data on this site? */
function wpjc_wpjm_has_data() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return (bool) $wpdb->get_var( "SELECT 1 FROM {$wpdb->posts} WHERE post_type = 'job_listing' LIMIT 1" );
}

/**
 * Terms of a WP Job Manager job, read from the database (the taxonomy may not be registered).
 *
 * @param int    $post_id  Job.
 * @param string $taxonomy WP Job Manager taxonomy.
 * @return array<int, array{name:string,slug:string,parent:string,description:string}>
 */
function wpjc_wpjm_terms( $post_id, $taxonomy ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = (array) $wpdb->get_results(
		$wpdb->prepare(
			"SELECT t.name, t.slug, tt.description, pt.slug AS parent FROM {$wpdb->term_relationships} tr
			JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
			JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
			LEFT JOIN {$wpdb->term_taxonomy} ptt ON ptt.term_id = tt.parent AND ptt.taxonomy = tt.taxonomy
			LEFT JOIN {$wpdb->terms} pt ON pt.term_id = ptt.term_id
			WHERE tr.object_id = %d AND tt.taxonomy = %s",
			$post_id,
			$taxonomy
		),
		ARRAY_A
	);
	return array_map(
		static fn( $r ) => array(
			'name'        => (string) $r['name'],
			'slug'        => (string) $r['slug'],
			'parent'      => (string) ( $r['parent'] ?? '' ),
			'description' => (string) $r['description'],
		),
		$rows
	);
}

/**
 * Find or create a JobCore term (keeps the WP Job Manager slug so links stay readable).
 *
 * @param string $taxonomy JobCore taxonomy.
 * @param array  $row      Term from wpjc_wpjm_terms().
 * @return int Term ID.
 */
function wpjc_wpjm_term( $taxonomy, array $row ) {
	$term = get_term_by( 'slug', $row['slug'], $taxonomy );
	if ( ! $term ) {
		$term = get_term_by( 'name', $row['name'], $taxonomy );
	}
	if ( $term instanceof WP_Term ) {
		return (int) $term->term_id;
	}
	$parent = 0;
	if ( '' !== $row['parent'] && is_taxonomy_hierarchical( $taxonomy ) ) {
		$p      = get_term_by( 'slug', $row['parent'], $taxonomy );
		$parent = $p instanceof WP_Term ? (int) $p->term_id : 0;
	}
	$made = wp_insert_term(
		$row['name'],
		$taxonomy,
		array(
			'slug'        => $row['slug'],
			'description' => $row['description'],
			'parent'      => $parent,
		)
	);
	return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
}

/**
 * Find or create the employer for a WP Job Manager job: name, website, tagline, logo, owner.
 *
 * @param WP_Post $job WP Job Manager job.
 * @return int Employer term ID (0 when the job has no company name).
 */
function wpjc_wpjm_employer( WP_Post $job ) {
	$name = trim( (string) get_post_meta( $job->ID, '_company_name', true ) );
	if ( '' === $name ) {
		return 0;
	}
	$term = get_term_by( 'name', $name, 'wpjc_employer' );
	if ( $term instanceof WP_Term ) {
		$id = (int) $term->term_id;
	} else {
		$made = wp_insert_term(
			$name,
			'wpjc_employer',
			array( 'description' => sanitize_textarea_field( (string) get_post_meta( $job->ID, '_company_tagline', true ) ) )
		);
		if ( is_wp_error( $made ) ) {
			return 0;
		}
		$id = (int) $made['term_id'];
		update_term_meta( $id, 'wpjc_wpjm_import', 1 );
		if ( $job->post_author && ! user_can( (int) $job->post_author, 'manage_options' ) ) {
			update_term_meta( $id, 'wpjc_owner', (string) (int) $job->post_author );
		}
	}
	/* Fill gaps only — never overwrite what is already set on the employer. */
	$site = esc_url_raw( (string) get_post_meta( $job->ID, '_company_website', true ) );
	if ( $site && '' === (string) get_term_meta( $id, 'wpjc_website', true ) ) {
		update_term_meta( $id, 'wpjc_website', $site );
	}
	$logo = (int) get_post_thumbnail_id( $job );
	if ( $logo && ! get_term_meta( $id, 'wpjc_logo_id', true ) ) {
		$src = wp_get_attachment_image_url( $logo, 'medium' );
		if ( $src ) {
			update_term_meta( $id, 'wpjc_logo_id', $logo );
			update_term_meta( $id, 'wpjc_logo', $src );
		}
	}
	return $id;
}

/**
 * Import one WP Job Manager job. Updates the earlier copy when it was imported before.
 *
 * @param int $wpjm_id WP Job Manager job ID.
 * @return string created|updated|skipped
 */
function wpjc_wpjm_import_job( $wpjm_id ) {
	$src = get_post( $wpjm_id );
	if ( ! $src || 'job_listing' !== $src->post_type ) {
		return 'skipped';
	}
	$map    = wpjc_wpjm_status_map();
	$status = $map[ $src->post_status ] ?? 'draft';
	global $wpdb;
	// Direct lookup: board queries on wpjc_job are filtered (end dates, tiers), this must see every job.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$found = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wpjc_wpjm_id' WHERE p.post_type = 'wpjc_job' AND p.post_status <> 'trash' AND m.meta_value = %s LIMIT 1", (string) $src->ID ) ) );
	$payload = array(
		'post_type'         => 'wpjc_job',
		'post_title'        => $src->post_title,
		'post_content'      => $src->post_content,
		'post_excerpt'      => $src->post_excerpt,
		'post_status'       => $status,
		'post_author'       => (int) $src->post_author,
		'post_date'         => $src->post_date,
		'post_date_gmt'     => $src->post_date_gmt,
		'post_name'         => $src->post_name,
		'comment_status'    => 'closed',
	);
	if ( $found ) {
		$payload['ID'] = (int) $found[0];
		$id            = wp_update_post( wp_slash( $payload ), true );
		$result        = 'updated';
	} else {
		$id     = wp_insert_post( wp_slash( $payload ), true );
		$result = 'created';
	}
	if ( is_wp_error( $id ) || ! $id ) {
		return 'skipped';
	}
	$id = (int) $id;

	/* Fields. */
	$m        = static fn( $k ) => (string) get_post_meta( $src->ID, $k, true );
	$apply    = trim( $m( '_application' ) );
	$expires  = $m( '_job_expires' );
	if ( 'expired' === $src->post_status && ( '' === $expires || strtotime( $expires ) >= strtotime( 'today' ) ) ) {
		$expires = wp_date( 'Y-m-d', strtotime( 'yesterday' ) );
	}
	$fields = array(
		'company'         => $m( '_company_name' ),
		'company_website' => $m( '_company_website' ),
		'location'        => $m( '_job_location' ),
		'remote'          => (bool) $m( '_remote_position' ),
		'apply_email'     => is_email( $apply ) ? $apply : '',
		'apply_url'       => ( ! is_email( $apply ) && preg_match( '#^https?://#i', $apply ) ) ? $apply : '',
		'expires'         => $expires && strtotime( $expires ) ? gmdate( 'Y-m-d', strtotime( $expires ) ) : '',
		'tier'            => $m( '_featured' ) ? 'premium' : 'basic',
		'filled'          => (bool) $m( '_filled' ),
	);
	foreach ( $fields as $key => $value ) {
		if ( isset( wpjc_meta_fields()[ $key ] ) ) {
			update_post_meta( $id, '_wpjc_' . $key, wpjc_sanitize_meta_value( $key, $value ) );
		}
	}
	update_post_meta( $id, '_wpjc_wpjm_id', (string) $src->ID );
	update_post_meta( $id, '_wpjc_wpjm_slug', $src->post_name );

	/* Salary goes into the Field Editor's "salary" field when that add-on has one. */
	$salary = trim( $m( '_job_salary' ) );
	if ( '' !== $salary && function_exists( 'wpjcf_field' ) && wpjcf_field( 'job', 'salary' ) ) {
		$unit = trim( $m( '_job_salary_currency' ) . ' ' . $m( '_job_salary_unit' ) );
		update_post_meta( $id, '_wpjc_cf_salary', sanitize_text_field( $salary . ( $unit ? ' ' . $unit : '' ) ) );
	}

	/* Types, categories, employer. */
	$types = array();
	foreach ( wpjc_wpjm_terms( $src->ID, 'job_listing_type' ) as $row ) {
		$types[] = wpjc_wpjm_term( 'wpjc_job_type', $row );
	}
	wp_set_object_terms( $id, array_values( array_filter( $types ) ), 'wpjc_job_type' );
	$cats = array();
	foreach ( wpjc_wpjm_terms( $src->ID, 'job_listing_category' ) as $row ) {
		$cats[] = wpjc_wpjm_term( 'wpjc_job_category', $row );
	}
	wp_set_object_terms( $id, array_values( array_filter( $cats ) ), 'wpjc_job_category' );
	$employer = wpjc_wpjm_employer( $src );
	wp_set_object_terms( $id, $employer ? array( $employer ) : array(), 'wpjc_employer' );

	return $result;
}

/* ---------- Admin screen ---------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=wpjc_job',
			__( 'Import from WP Job Manager', 'jobcore' ),
			__( 'Import from WP Job Manager', 'jobcore' ),
			'manage_options',
			WPJC_WPJM_PAGE,
			'wpjc_wpjm_render',
			90
		);
	}
);

/* Also listed where WordPress keeps its importers: Tools → Import. */
add_action(
	'admin_init',
	static function () {
		if ( function_exists( 'register_importer' ) ) {
			register_importer(
				'jobcore-wpjm',
				__( 'WP Job Manager → JobCore', 'jobcore' ),
				__( 'Move jobs, companies, logos, job types and categories from WP Job Manager into JobCore.', 'jobcore' ),
				static function () {
					wp_safe_redirect( wpjc_wpjm_url() );
					exit;
				}
			);
		}
	}
);
/* Fires before any output on admin.php?import=jobcore-wpjm, so the redirect can still happen. */
add_action(
	'load-importer-jobcore-wpjm',
	static function () {
		wp_safe_redirect( wpjc_wpjm_url() );
		exit;
	}
);

add_filter(
	'wpjc_admin_nav',
	static function ( $nav ) {
		$group                  = __( 'System', 'jobcore' );
		$nav[ $group ]           = (array) ( $nav[ $group ] ?? array() );
		$nav[ $group ]['wpjm']   = array(
			__( 'Import from WP Job Manager', 'jobcore' ),
			'migrate',
			__( 'Move jobs, companies, logos, job types and categories from WP Job Manager into JobCore.', 'jobcore' ),
			wpjc_wpjm_url(),
		);
		return $nav;
	}
);

add_filter(
	'wpjc_is_admin_screen',
	static function ( $ours, $screen ) {
		return $ours || ( $screen && 'wpjc_job_page_' . WPJC_WPJM_PAGE === $screen->id );
	},
	10,
	2
);

/* A one-time hint on the Jobs dashboard and Plugins screen when WP Job Manager data is found. */
add_action(
	'admin_notices',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'wpjc_job_page_' . WPJC_DASHBOARD ), true ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( 'wpjc_wpjm_done' ) || get_user_meta( get_current_user_id(), 'wpjc_wpjm_hide', true ) || ! wpjc_wpjm_has_data() ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <a class="button button-primary" href="%3$s">%4$s</a></p></div>',
			esc_html__( 'WP Job Manager jobs found.', 'jobcore' ),
			esc_html__( 'Move your jobs, companies and logos into JobCore in a few clicks — nothing is deleted.', 'jobcore' ),
			esc_url( wpjc_wpjm_url() ),
			esc_html__( 'Import now', 'jobcore' )
		);
	}
);

/** The import screen. */
function wpjc_wpjm_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$s = wpjc_wpjm_summary();
	wpjc_admin_shell_open( 'wpjm' );
	?>
	<div class="wpjc-dash-box wpjc-wpjm">
		<?php if ( ! $s['jobs'] ) : ?>
			<p><strong><?php esc_html_e( 'No WP Job Manager jobs on this site.', 'jobcore' ); ?></strong></p>
			<p><?php esc_html_e( 'When a site has jobs from WP Job Manager, this screen moves them into JobCore — WP Job Manager does not even need to be active.', 'jobcore' ); ?></p>
		<?php else : ?>
			<p><?php esc_html_e( 'Your WP Job Manager jobs are copied into JobCore. Nothing is deleted, so you can check the result before you switch WP Job Manager off — and run the import again at any time: jobs that were imported before are updated, not duplicated.', 'jobcore' ); ?></p>
			<ul class="wpjc-wpjm__stats">
				<li><b><?php echo esc_html( number_format_i18n( $s['jobs'] ) ); ?></b><span><?php esc_html_e( 'jobs', 'jobcore' ); ?></span><small><?php /* translators: 1: open, 2: expired */ echo esc_html( sprintf( __( '%1$s open · %2$s expired', 'jobcore' ), number_format_i18n( $s['open'] ), number_format_i18n( $s['expired'] ) ) ); ?></small></li>
				<li><b><?php echo esc_html( number_format_i18n( $s['companies'] ) ); ?></b><span><?php esc_html_e( 'companies', 'jobcore' ); ?></span><small><?php esc_html_e( 'become employer pages, with logos', 'jobcore' ); ?></small></li>
				<li><b><?php echo esc_html( number_format_i18n( $s['types'] + $s['categories'] ) ); ?></b><span><?php esc_html_e( 'types & categories', 'jobcore' ); ?></span><small><?php esc_html_e( 'keep their names and slugs', 'jobcore' ); ?></small></li>
				<li><b><?php echo esc_html( number_format_i18n( $s['imported'] ) ); ?></b><span><?php esc_html_e( 'already imported', 'jobcore' ); ?></span><small><?php esc_html_e( 'updated on the next run', 'jobcore' ); ?></small></li>
			</ul>

			<form class="wpjc-wpjm__form" data-wpjc-wpjm>
				<p><label><input type="checkbox" name="expired" value="1" checked> <?php esc_html_e( 'Also import expired jobs (they stay off the board, but employers keep their history)', 'jobcore' ); ?></label></p>
				<p><label><input type="checkbox" name="filled" value="1" checked> <?php esc_html_e( 'Also import filled positions', 'jobcore' ); ?></label></p>
				<p><button type="submit" class="button button-primary wpjc-opt__save"><?php esc_html_e( 'Start import', 'jobcore' ); ?></button></p>
				<div class="wpjc-wpjm__progress" hidden>
					<div class="wpjc-wpjm__bar"><span style="width:0"></span></div>
					<p class="wpjc-wpjm__status" aria-live="polite"></p>
				</div>
			</form>

			<h3><?php esc_html_e( 'What moves over', 'jobcore' ); ?></h3>
			<table class="widefat striped wpjc-wpjm__map">
				<thead><tr><th><?php esc_html_e( 'WP Job Manager', 'jobcore' ); ?></th><th><?php esc_html_e( 'JobCore', 'jobcore' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Title, description, dates, author', 'jobcore' ); ?></td><td><?php esc_html_e( 'The same — the author stays the owner of the job', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Company name, website, tagline, logo', 'jobcore' ); ?></td><td><?php esc_html_e( 'Employer page with logo, website and about text', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Location, remote position', 'jobcore' ); ?></td><td><?php esc_html_e( 'Location, remote-friendly', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Application e-mail or URL', 'jobcore' ); ?></td><td><?php esc_html_e( 'Apply by e-mail, or the external apply link', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Expiry date, filled, featured', 'jobcore' ); ?></td><td><?php esc_html_e( 'End date, position filled, Premium listing', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Job types and categories', 'jobcore' ); ?></td><td><?php esc_html_e( 'Job types and categories', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Salary', 'jobcore' ); ?></td><td><?php esc_html_e( 'The “salary” field of the Field Editor add-on, when you have one', 'jobcore' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Old job links', 'jobcore' ); ?></td><td><?php esc_html_e( 'Redirect to the new job pages once WP Job Manager is switched off', 'jobcore' ); ?></td></tr>
				</tbody>
			</table>
			<?php if ( $s['applications'] ) : ?>
				<p class="description"><?php /* translators: %s: number */ echo esc_html( sprintf( __( 'Not moved: %s applications from the WP Job Manager Applications add-on — they stay in your database untouched.', 'jobcore' ), number_format_i18n( $s['applications'] ) ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php if ( $s['jobs'] ) : ?>
		<script>
		( function () {
			var form = document.querySelector( '[data-wpjc-wpjm]' );
			if ( ! form ) { return; }
			var bar = form.querySelector( '.wpjc-wpjm__bar span' ), box = form.querySelector( '.wpjc-wpjm__progress' ), out = form.querySelector( '.wpjc-wpjm__status' ), btn = form.querySelector( 'button' );
			var tally = { created: 0, updated: 0, skipped: 0 };
			function step( offset ) {
				var body = new FormData();
				body.append( 'action', 'wpjc_wpjm_batch' );
				body.append( '_wpnonce', <?php echo wp_json_encode( wp_create_nonce( 'wpjc_wpjm' ) ); ?> );
				body.append( 'offset', offset );
				body.append( 'expired', form.expired.checked ? 1 : 0 );
				body.append( 'filled', form.filled.checked ? 1 : 0 );
				fetch( ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' } ).then( function ( r ) { return r.json(); } ).then( function ( r ) {
					if ( ! r.success ) { throw new Error( r.data || 'error' ); }
					var d = r.data;
					tally.created += d.created; tally.updated += d.updated; tally.skipped += d.skipped;
					bar.style.width = ( d.total ? Math.round( d.next / d.total * 100 ) : 100 ) + '%';
					out.textContent = <?php echo wp_json_encode( /* translators: 1: jobs imported so far, 2: jobs in total */ __( 'Imported %1$d of %2$d jobs…', 'jobcore' ) ); ?>.replace( '%1$d', d.next ).replace( '%2$d', d.total );
					if ( d.next < d.total ) { step( d.next ); return; }
					out.innerHTML = <?php echo wp_json_encode( /* translators: 1: new jobs, 2: updated jobs, 3: skipped jobs */ __( 'Done: %1$d new, %2$d updated, %3$d skipped.', 'jobcore' ) ); ?>.replace( '%1$d', tally.created ).replace( '%2$d', tally.updated ).replace( '%3$d', tally.skipped )
						+ ' <a href="' + <?php echo wp_json_encode( admin_url( 'edit.php?post_type=wpjc_job' ) ); ?> + '">' + <?php echo wp_json_encode( __( 'See the jobs', 'jobcore' ) ); ?> + '</a> · <a href="' + <?php echo wp_json_encode( admin_url( 'edit-tags.php?taxonomy=wpjc_employer&post_type=wpjc_job' ) ); ?> + '">' + <?php echo wp_json_encode( __( 'Employers', 'jobcore' ) ); ?> + '</a>';
					btn.disabled = false;
				} ).catch( function ( e ) {
					out.textContent = <?php echo wp_json_encode( __( 'The import stopped:', 'jobcore' ) ); ?> + ' ' + e.message;
					btn.disabled = false;
				} );
			}
			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				btn.disabled = true; box.hidden = false; bar.style.width = '0';
				tally = { created: 0, updated: 0, skipped: 0 };
				step( 0 );
			} );
		}() );
		</script>
	<?php endif; ?>
	<?php
	wpjc_admin_shell_close();
}

/* One batch of the import. */
add_action(
	'wp_ajax_wpjc_wpjm_batch',
	static function () {
		check_ajax_referer( 'wpjc_wpjm' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Not allowed.', 'jobcore' ), 403 );
		}
		$opts   = array(
			'expired' => ! empty( $_POST['expired'] ),
			'filled'  => ! empty( $_POST['filled'] ),
		);
		$offset = max( 0, absint( $_POST['offset'] ?? 0 ) );
		$ids    = wpjc_wpjm_job_ids( $opts );
		$tally  = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
		);
		wp_defer_term_counting( true );
		foreach ( array_slice( $ids, $offset, WPJC_WPJM_BATCH ) as $id ) {
			++$tally[ wpjc_wpjm_import_job( $id ) ];
		}
		wp_defer_term_counting( false );
		$next = min( count( $ids ), $offset + WPJC_WPJM_BATCH );
		if ( $next >= count( $ids ) ) {
			update_option( 'wpjc_wpjm_done', time(), false );
		}
		wp_send_json_success(
			array_merge(
				$tally,
				array(
					'next'  => $next,
					'total' => count( $ids ),
				)
			)
		);
	}
);

/* Old WP Job Manager job links (…/job/slug/) lead to the imported job once WP Job Manager is off. */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() || ! get_option( 'wpjc_wpjm_done' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '', PHP_URL_PATH ), '/' );
		$slug = sanitize_title( (string) basename( $path ) );
		if ( '' === $slug ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$found = (array) $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_wpjc_wpjm_slug' WHERE p.post_type = 'wpjc_job' AND p.post_status = 'publish' AND m.meta_value = %s LIMIT 1", $slug ) );
		if ( $found ) {
			wp_safe_redirect( get_permalink( (int) $found[0] ), 301 );
			exit;
		}
	},
	5
);
