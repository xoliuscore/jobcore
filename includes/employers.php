<?php
/**
 * Employers: taxonomy term meta (logo, website) and helpers.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		foreach ( array( 'wpjc_logo', 'wpjc_website' ) as $key ) {
			register_term_meta(
				'wpjc_employer',
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'esc_url_raw',
				)
			);
		}
	}
);

/**
 * Logo picker (Media Library) for the employer screens.
 *
 * @param int    $logo_id Attachment ID (0 when none).
 * @param string $url     Current logo URL (also older logos saved as a plain URL).
 */
function wpjc_employer_logo_picker( $logo_id = 0, $url = '' ) {
	$logo_id = (int) $logo_id;
	$src     = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	$src     = $src ? $src : (string) $url;
	?>
	<div class="wpjc-logo-pick<?php echo $src ? ' has-logo' : ''; ?>" data-wpjc-logo-pick>
		<button type="button" class="wpjc-logo-pick__preview" data-wpjc-logo-select aria-label="<?php esc_attr_e( 'Select logo', 'jobcore' ); ?>">
			<img src="<?php echo esc_url( $src ); ?>" alt=""<?php echo $src ? '' : ' hidden'; ?>>
			<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
		</button>
		<div class="wpjc-logo-pick__side">
			<p class="wpjc-logo-pick__actions">
				<button type="button" class="button" data-wpjc-logo-select>
					<span class="wpjc-logo-pick__add"><?php esc_html_e( 'Upload or select logo', 'jobcore' ); ?></span>
					<span class="wpjc-logo-pick__change"><?php esc_html_e( 'Change logo', 'jobcore' ); ?></span>
				</button>
				<button type="button" class="button-link button-link-delete" data-wpjc-logo-remove><?php esc_html_e( 'Remove', 'jobcore' ); ?></button>
			</p>
			<p class="description"><?php esc_html_e( 'Square image works best: PNG, JPG, WebP or SVG, at least 200 × 200 px.', 'jobcore' ); ?></p>
		</div>
		<input type="hidden" name="wpjc_logo_id" value="<?php echo esc_attr( $logo_id ? (string) $logo_id : '' ); ?>" data-wpjc-logo-id>
		<input type="hidden" name="wpjc_logo" value="<?php echo esc_attr( $logo_id ? '' : (string) $url ); ?>" data-wpjc-logo-url>
	</div>
	<?php
}

/**
 * Logo + website fields on the add-employer screen.
 */
add_action(
	'wpjc_employer_add_form_fields',
	static function () {
		wp_nonce_field( 'wpjc_employer_meta', 'wpjc_employer_nonce' );
		?>
		<div class="form-field">
			<label><?php esc_html_e( 'Logo', 'jobcore' ); ?></label>
			<?php wpjc_employer_logo_picker(); ?>
		</div>
		<div class="form-field">
			<label for="wpjc_website"><?php esc_html_e( 'Website', 'jobcore' ); ?></label>
			<input type="url" name="wpjc_website" id="wpjc_website" value="">
		</div>
		<?php
	}
);

/**
 * Logo + website fields on the edit-employer screen.
 *
 * @param WP_Term $term Term.
 */
add_action(
	'wpjc_employer_edit_form_fields',
	static function ( $term ) {
		$logo    = (string) get_term_meta( $term->term_id, 'wpjc_logo', true );
		$website = (string) get_term_meta( $term->term_id, 'wpjc_website', true );
		wp_nonce_field( 'wpjc_employer_meta', 'wpjc_employer_nonce' );
		?>
		<tr class="form-field">
			<th scope="row"><?php esc_html_e( 'Logo', 'jobcore' ); ?></th>
			<td><?php wpjc_employer_logo_picker( (int) get_term_meta( $term->term_id, 'wpjc_logo_id', true ), $logo ); ?></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_website"><?php esc_html_e( 'Website', 'jobcore' ); ?></label></th>
			<td><input type="url" name="wpjc_website" id="wpjc_website" value="<?php echo esc_attr( $website ); ?>"></td>
		</tr>
		<?php
	}
);

/**
 * Save employer term meta.
 *
 * @param int $term_id Term ID.
 */
function wpjc_save_employer_meta( $term_id ) {
	if ( ! isset( $_POST['wpjc_employer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpjc_employer_nonce'] ) ), 'wpjc_employer_meta' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}
	if ( isset( $_POST['wpjc_website'] ) ) {
		update_term_meta( $term_id, 'wpjc_website', esc_url_raw( wp_unslash( $_POST['wpjc_website'] ) ) );
	}
	if ( isset( $_POST['wpjc_logo_id'] ) ) {
		$logo_id = absint( wp_unslash( $_POST['wpjc_logo_id'] ) );
		$url     = isset( $_POST['wpjc_logo'] ) ? esc_url_raw( wp_unslash( $_POST['wpjc_logo'] ) ) : '';
		if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
			update_term_meta( $term_id, 'wpjc_logo_id', $logo_id );
			update_term_meta( $term_id, 'wpjc_logo', (string) wp_get_attachment_image_url( $logo_id, 'medium' ) );
		} elseif ( '' !== $url ) {
			delete_term_meta( $term_id, 'wpjc_logo_id' );
			update_term_meta( $term_id, 'wpjc_logo', $url );
		} else {
			delete_term_meta( $term_id, 'wpjc_logo_id' );
			delete_term_meta( $term_id, 'wpjc_logo' );
		}
	}

	if ( ! isset( $_POST['wpjc_company_edit'] ) ) {
		return;
	}

	$owner = isset( $_POST['wpjc_owner'] ) ? absint( wp_unslash( $_POST['wpjc_owner'] ) ) : 0;
	if ( $owner && get_userdata( $owner ) ) {
		update_term_meta( $term_id, 'wpjc_owner', $owner );
	} else {
		delete_term_meta( $term_id, 'wpjc_owner' );
	}

	update_term_meta( $term_id, 'wpjc_active', empty( $_POST['wpjc_active'] ) ? '0' : '1' );

	$text = array( 'legal_name', 'reg_no', 'vat', 'address', 'city', 'postcode', 'contact_name', 'contact_phone' );
	foreach ( $text as $key ) {
		$field = 'wpjc_' . $key;
		$val   = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		update_term_meta( $term_id, $field, $val );
	}

	$country = isset( $_POST['wpjc_country'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_country'] ) ) : '';
	update_term_meta( $term_id, 'wpjc_country', in_array( $country, wpjc_countries(), true ) ? $country : '' );

	$email = isset( $_POST['wpjc_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['wpjc_contact_email'] ) ) : '';
	update_term_meta( $term_id, 'wpjc_contact_email', $email );
}

/**
 * Media Library logo picker on the employer screens.
 */
add_action(
	'admin_enqueue_scripts',
	static function () {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-wpjc_employer' !== $screen->id ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wpjc-employer-admin', WPJC_URL . 'assets/css/admin-employer.css', array( 'dashicons' ), WPJC_VERSION . '.' . filemtime( WPJC_DIR . 'assets/css/admin-employer.css' ) );
		wp_enqueue_script( 'wpjc-employer-admin', WPJC_URL . 'assets/js/admin-employer.js', array( 'media-editor' ), WPJC_VERSION . '.' . filemtime( WPJC_DIR . 'assets/js/admin-employer.js' ), true );
		wp_localize_script(
			'wpjc-employer-admin',
			'wpjcEmployerAdmin',
			array(
				'title'  => __( 'Employer logo', 'jobcore' ),
				'button' => __( 'Use as logo', 'jobcore' ),
			)
		);
	}
);
add_action( 'created_wpjc_employer', 'wpjc_save_employer_meta' );
add_action( 'edited_wpjc_employer', 'wpjc_save_employer_meta' );

/**
 * Company details from the jobs account — editable so a typing mistake can be fixed here.
 *
 * @param WP_Term $term Term.
 */
add_action(
	'wpjc_employer_edit_form_fields',
	static function ( $term ) {
		$id     = (int) $term->term_id;
		$owner  = (int) get_term_meta( $id, 'wpjc_owner', true );
		$active = wpjc_company_is_active( $id );
		?>
		<tr class="form-field">
			<th scope="row" colspan="2">
				<h2 style="margin:12px 0 0"><?php esc_html_e( 'Added in the jobs account', 'jobcore' ); ?></h2>
				<input type="hidden" name="wpjc_company_edit" value="1">
			</th>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_owner"><?php esc_html_e( 'Owner', 'jobcore' ); ?></label></th>
			<td>
				<select name="wpjc_owner" id="wpjc_owner">
					<option value="0"><?php esc_html_e( '— No owner —', 'jobcore' ); ?></option>
					<?php
					foreach ( get_users( array( 'orderby' => 'display_name', 'order' => 'ASC' ) ) as $wpjc_user ) {
						printf(
							'<option value="%1$d"%2$s>%3$s</option>',
							(int) $wpjc_user->ID,
							selected( $owner, (int) $wpjc_user->ID, false ),
							esc_html( $wpjc_user->display_name . ' (' . $wpjc_user->user_email . ')' )
						);
					}
					?>
				</select>
				<p class="description"><?php esc_html_e( 'The user who can edit this company in the jobs account.', 'jobcore' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_active"><?php esc_html_e( 'Status', 'jobcore' ); ?></label></th>
			<td>
				<select name="wpjc_active" id="wpjc_active">
					<option value="1" <?php selected( $active ); ?>><?php esc_html_e( 'Active', 'jobcore' ); ?></option>
					<option value="0" <?php selected( ! $active ); ?>><?php esc_html_e( 'Inactive (hidden)', 'jobcore' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Inactive companies and their job ads are hidden on the board.', 'jobcore' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_legal_name"><?php esc_html_e( 'Full company name', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_legal_name" id="wpjc_legal_name" value="<?php echo esc_attr( wpjc_company_meta( $id, 'legal_name' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_reg_no"><?php esc_html_e( 'Registration number', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_reg_no" id="wpjc_reg_no" value="<?php echo esc_attr( wpjc_company_meta( $id, 'reg_no' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_vat"><?php esc_html_e( 'VAT number', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_vat" id="wpjc_vat" value="<?php echo esc_attr( wpjc_company_meta( $id, 'vat' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_address"><?php esc_html_e( 'Address', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_address" id="wpjc_address" value="<?php echo esc_attr( wpjc_company_meta( $id, 'address' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_postcode"><?php esc_html_e( 'Postcode', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_postcode" id="wpjc_postcode" value="<?php echo esc_attr( wpjc_company_meta( $id, 'postcode' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_city"><?php esc_html_e( 'City', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_city" id="wpjc_city" value="<?php echo esc_attr( wpjc_company_meta( $id, 'city' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_country"><?php esc_html_e( 'Country', 'jobcore' ); ?></label></th>
			<td>
				<select name="wpjc_country" id="wpjc_country">
					<option value=""><?php esc_html_e( 'Choose a country', 'jobcore' ); ?></option>
					<?php foreach ( wpjc_countries() as $wpjc_country ) : ?>
						<option value="<?php echo esc_attr( $wpjc_country ); ?>" <?php selected( wpjc_company_meta( $id, 'country' ), $wpjc_country ); ?>><?php echo esc_html( $wpjc_country ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_contact_name"><?php esc_html_e( 'Contact person', 'jobcore' ); ?></label></th>
			<td>
				<input type="text" name="wpjc_contact_name" id="wpjc_contact_name" value="<?php echo esc_attr( wpjc_company_meta( $id, 'contact_name' ) ); ?>" placeholder="<?php esc_attr_e( 'Full name', 'jobcore' ); ?>">
				<p class="description"><?php esc_html_e( 'For the board team only — candidates do not see this.', 'jobcore' ); ?></p>
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_contact_email"><?php esc_html_e( 'Contact e-mail', 'jobcore' ); ?></label></th>
			<td><input type="email" name="wpjc_contact_email" id="wpjc_contact_email" value="<?php echo esc_attr( wpjc_company_meta( $id, 'contact_email' ) ); ?>"></td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="wpjc_contact_phone"><?php esc_html_e( 'Contact phone', 'jobcore' ); ?></label></th>
			<td><input type="text" name="wpjc_contact_phone" id="wpjc_contact_phone" value="<?php echo esc_attr( wpjc_company_meta( $id, 'contact_phone' ) ); ?>"></td>
		</tr>
		<?php
	},
	20
);

/**
 * Company meta saved from the jobs account (key without the wpjc_ prefix).
 *
 * @param int    $term_id Employer term ID.
 * @param string $key     Key.
 */
function wpjc_company_meta( $term_id, $key ) {
	return (string) get_term_meta( (int) $term_id, 'wpjc_' . $key, true );
}

/**
 * Whether an employer is shown publicly (owners can switch their company off).
 *
 * @param int $term_id Employer term ID.
 */
function wpjc_company_is_active( $term_id ) {
	return '0' !== (string) get_term_meta( (int) $term_id, 'wpjc_active', true );
}

/**
 * Employers switched off by their owner: hidden with their jobs.
 *
 * @return int[]
 */
function wpjc_inactive_employer_ids() {
	static $ids = null;
	if ( null === $ids ) {
		$ids = get_terms(
			array(
				'taxonomy'   => 'wpjc_employer',
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => 'wpjc_active', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => '0', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
	}
	return $ids;
}

/**
 * Companies a user added in the jobs account.
 *
 * @param int $user_id User ID.
 * @return WP_Term[]
 */
function wpjc_user_companies( $user_id ) {
	if ( ! $user_id ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'wpjc_employer',
			'hide_empty' => false,
			'orderby'    => 'name',
			'meta_key'   => 'wpjc_owner', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => (string) (int) $user_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Whether a user may edit an employer (owner, or an editor of employers).
 *
 * @param int $term_id Employer term ID.
 * @param int $user_id User ID.
 */
function wpjc_user_owns_company( $term_id, $user_id ) {
	if ( ! $term_id || ! $user_id ) {
		return false;
	}
	return (int) get_term_meta( (int) $term_id, 'wpjc_owner', true ) === (int) $user_id || user_can( $user_id, 'manage_categories' );
}

/**
 * Countries for the company form.
 *
 * @return string[]
 */
function wpjc_countries() {
	return (array) apply_filters(
		'wpjc_countries',
		array( 'Albania', 'Andorra', 'Australia', 'Austria', 'Belgium', 'Bosnia and Herzegovina', 'Bulgaria', 'Canada', 'Croatia', 'Cyprus', 'Czechia', 'Denmark', 'Estonia', 'Finland', 'France', 'Germany', 'Greece', 'Hungary', 'Iceland', 'Ireland', 'Italy', 'Kosovo', 'Latvia', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Malta', 'Moldova', 'Monaco', 'Montenegro', 'Netherlands', 'North Macedonia', 'Norway', 'Poland', 'Portugal', 'Romania', 'San Marino', 'Serbia', 'Slovakia', 'Slovenia', 'Spain', 'Sweden', 'Switzerland', 'Turkey', 'Ukraine', 'United Kingdom', 'United States' )
	);
}

/**
 * Company data for a job: employer term first, then the job's own fields.
 *
 * @param int|WP_Post|null $post Job.
 * @return array{name:string,logo:string,website:string,url:string}
 */
function wpjc_job_company( $post = null ) {
	$post = get_post( $post );
	$out  = array(
		'name'    => '',
		'logo'    => '',
		'website' => '',
		'url'     => '',
	);
	if ( ! $post ) {
		return $out;
	}
	$terms = get_the_terms( $post, 'wpjc_employer' );
	if ( $terms && ! is_wp_error( $terms ) ) {
		$term           = $terms[0];
		$out['name']    = $term->name;
		$out['logo']    = (string) get_term_meta( $term->term_id, 'wpjc_logo', true );
		$out['website'] = (string) get_term_meta( $term->term_id, 'wpjc_website', true );
		$link           = get_term_link( $term );
		$out['url']     = is_wp_error( $link ) ? '' : $link;
	}
	if ( '' === $out['name'] ) {
		$out['name'] = wpjc_meta( $post, 'company' );
	}
	if ( '' === $out['website'] ) {
		$out['website'] = wpjc_meta( $post, 'company_website' );
	}
	return $out;
}

/**
 * Logo markup (image or initials placeholder).
 *
 * @param array  $company From wpjc_job_company() or an employer row.
 * @param string $class   CSS class.
 * @param bool   $link    Wrap in the employer page URL when one exists.
 * @return string Safe HTML.
 */
function wpjc_logo_html( array $company, $class = 'wpjc-logo', $link = false ) {
	$name = (string) ( $company['name'] ?? '' );
	$url  = $link ? (string) ( $company['url'] ?? '' ) : '';
	if ( ! empty( $company['logo'] ) ) {
		$img = sprintf(
			'<img src="%s" alt="" loading="lazy" decoding="async">',
			esc_url( $company['logo'] )
		);
		if ( $url ) {
			return sprintf(
				'<a class="%1$s" href="%2$s"%3$s>%4$s</a>',
				esc_attr( $class ),
				esc_url( $url ),
				$name ? ' aria-label="' . esc_attr( $name ) . '"' : '',
				$img
			);
		}
		return sprintf( '<span class="%1$s">%2$s</span>', esc_attr( $class ), $img );
	}
	$initial = '' !== $name ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : '';
	if ( $url ) {
		return sprintf(
			'<a class="%1$s %1$s--ph" href="%2$s"%3$s>%4$s</a>',
			esc_attr( $class ),
			esc_url( $url ),
			$name ? ' aria-label="' . esc_attr( $name ) . '"' : '',
			esc_html( $initial )
		);
	}
	return sprintf(
		'<span class="%1$s %1$s--ph" aria-hidden="true">%2$s</span>',
		esc_attr( $class ),
		esc_html( $initial )
	);
}

/**
 * Everything the public employer page shows.
 *
 * The contact person (name, e-mail, phone) stays private: it is for the board team only.
 *
 * @param WP_Term $term Employer.
 * @return array<string, mixed>
 */
function wpjc_employer_profile( WP_Term $term ) {
	$id      = (int) $term->term_id;
	$website = (string) get_term_meta( $id, 'wpjc_website', true );
	$street  = wpjc_company_meta( $id, 'address' );
	$city    = wpjc_company_meta( $id, 'city' );
	$country = wpjc_company_meta( $id, 'country' );
	$place   = trim( wpjc_company_meta( $id, 'postcode' ) . ' ' . $city );
	$address = array_values( array_filter( array( wpjc_company_meta( $id, 'legal_name' ), $street, $place, $country ) ) );
	$lookup  = implode( ', ', array_filter( array( $street, $place, $country ) ) );

	$open = wpjc_query_jobs(
		array(
			'employer' => $id,
			'per_page' => 60,
		)
	)->posts;

	$cities     = array();
	$categories = array();
	$featured   = false;
	$latest     = 0;
	foreach ( $open as $job ) {
		$where = wpjc_meta( $job, 'location' );
		$where = '' !== $where ? $where : ( wpjc_meta( $job, 'remote' ) ? __( 'Remote', 'jobcore' ) : '' );
		if ( '' !== $where ) {
			$cities[ $where ] = ( $cities[ $where ] ?? 0 ) + 1;
		}
		$cats = get_the_terms( $job, 'wpjc_job_category' );
		foreach ( ( $cats && ! is_wp_error( $cats ) ) ? $cats : array() as $cat ) {
			if ( ! isset( $categories[ $cat->term_id ] ) ) {
				$link = get_term_link( $cat );

				$categories[ $cat->term_id ] = array(
					'name'  => $cat->name,
					'url'   => is_wp_error( $link ) ? '' : $link,
					'count' => 0,
				);
			}
			++$categories[ $cat->term_id ]['count'];
		}
		$featured = $featured || 'premium_plus' === wpjc_job_tier( $job );
		$latest   = max( $latest, (int) get_post_time( 'U', true, $job ) );
	}
	arsort( $cities );
	uasort(
		$categories,
		static function ( $a, $b ) {
			return $b['count'] <=> $a['count'];
		}
	);

	return array(
		'id'         => $id,
		'name'       => $term->name,
		'about'      => $term->description,
		'logo'       => (string) get_term_meta( $id, 'wpjc_logo', true ),
		'website'    => $website,
		'site_label' => preg_replace( '#^https?://(www\.)?#', '', untrailingslashit( $website ) ),
		'linkedin'   => wpjc_company_meta( $id, 'linkedin' ),
		'legal_name' => wpjc_company_meta( $id, 'legal_name' ),
		'reg_no'     => wpjc_company_meta( $id, 'reg_no' ),
		'vat'        => wpjc_company_meta( $id, 'vat' ),
		'address'    => $address,
		'map'        => '' !== $lookup ? 'https://www.openstreetmap.org/search?query=' . rawurlencode( $lookup ) : '',
		'base'       => implode( ', ', array_filter( array( $city, $country ) ) ),
		'open'       => $open,
		'closed'     => wpjc_employer_closed_jobs( $id ),
		'cities'     => $cities,
		'categories' => array_values( $categories ),
		'featured'   => $featured,
		'latest'     => $latest,
	);
}

/**
 * Recently closed jobs of an employer (expired or filled), newest first.
 *
 * @param int $term_id Employer term ID.
 * @param int $limit   Max jobs.
 * @return WP_Post[]
 */
function wpjc_employer_closed_jobs( $term_id, $limit = 6 ) {
	return get_posts(
		array(
			'post_type'      => 'wpjc_job',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $limit,
			'no_found_rows'  => true,
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				array(
					'taxonomy' => 'wpjc_employer',
					'terms'    => (int) $term_id,
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
				'relation' => 'OR',
				array(
					'key'   => '_wpjc_filled',
					'value' => '1',
				),
				array(
					'key'     => '_wpjc_expires',
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => '<',
					'type'    => 'DATE',
				),
			),
		)
	);
}

/**
 * When a closed job closed: its expiry date, else the last edit (filled).
 *
 * @param WP_Post $job Job.
 * @return int Unix time.
 */
function wpjc_job_closed_on( WP_Post $job ) {
	$expires = wpjc_meta( $job, 'expires' );
	if ( $expires && $expires < wp_date( 'Y-m-d' ) ) {
		return (int) strtotime( $expires . ' 12:00:00' );
	}
	return (int) get_post_modified_time( 'U', true, $job );
}

/*
 * Switched-off companies have no public page (their owner still sees it).
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_tax( 'wpjc_employer' ) ) {
			return;
		}
		$term = get_queried_object();
		if ( $term instanceof WP_Term && ! wpjc_company_is_active( $term->term_id ) && ! wpjc_user_owns_company( $term->term_id, get_current_user_id() ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

/**
 * Employer page title: "{Name}: jobs and company profile" (also replaces SEO plugins' "… Archives").
 *
 * @param mixed $title Title (string or WP title parts).
 * @return mixed
 */
function wpjc_employer_title( $title ) {
	$term = is_tax( 'wpjc_employer' ) ? get_queried_object() : null;
	if ( ! $term instanceof WP_Term ) {
		return $title;
	}
	/* translators: %s: employer name */
	$text = sprintf( __( '%s: jobs and company profile', 'jobcore' ), $term->name );
	if ( is_array( $title ) ) {
		$title['title'] = $text;
		return $title;
	}
	return $text . ' - ' . wpjc_brand()['name'];
}
add_filter( 'document_title_parts', 'wpjc_employer_title' );
add_filter( 'wpseo_title', 'wpjc_employer_title' );

/**
 * Employers with open jobs, most jobs first.
 *
 * @param int $limit Max employers.
 * @return array<int, array{name:string,logo:string,count:int,url:string}>
 */
function wpjc_featured_employers( $limit = 12 ) {
	$terms = get_terms(
		array(
			'taxonomy'   => 'wpjc_employer',
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
			'number'     => max( 1, (int) $limit ),
			'exclude'    => wpjc_inactive_employer_ids(),
		)
	);
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	$out = array();
	foreach ( $terms as $term ) {
		$link  = get_term_link( $term );
		$out[] = array(
			'name'  => $term->name,
			'logo'  => (string) get_term_meta( $term->term_id, 'wpjc_logo', true ),
			'count' => (int) $term->count,
			'url'   => is_wp_error( $link ) ? '' : $link,
		);
	}
	return $out;
}
