<?php
/**
 * Account types: job seekers and employers.
 *
 * One login can do everything ("off"), or each account says what it is for:
 * - soft (default): the menu and overview show what fits the account type; switching is one click.
 * - strict: job seekers cannot add companies or post jobs, employers cannot use job seeker tools.
 *   Only the board team changes the type (in the user's profile in wp-admin).
 *
 * The type is picked once: from where someone signed up (e.g. "Post a job"), from what they
 * already have (companies, applications), or on a short chooser screen.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_wpjc_account_type', 'wpjc_handle_account_type' );
add_action( 'admin_post_nopriv_wpjc_account_type', 'wpjc_account_require_login' );

/**
 * Account mode: off | soft | strict.
 */
function wpjc_accounts_mode() {
	$mode = (string) wpjc_opt( 'accounts' );
	return in_array( $mode, array( 'off', 'soft', 'strict' ), true ) ? $mode : 'soft';
}

/**
 * Account types: key => [title, text, icon].
 *
 * @return array<string, array{0:string,1:string,2:string}>
 */
function wpjc_account_types() {
	return (array) apply_filters(
		'wpjc_account_types',
		array(
			'candidate' => array( __( 'I’m looking for a job', 'jobcore' ), __( 'Apply in a minute, save jobs and get new ones by e-mail.', 'jobcore' ), 'search' ),
			'employer'  => array( __( 'I’m hiring', 'jobcore' ), __( 'Add your company, post job ads and manage the applications you receive.', 'jobcore' ), 'building' ),
		)
	);
}

/**
 * Which account type a screen is for: candidate, employer or both.
 *
 * @return array<string, string>
 */
function wpjc_section_audiences() {
	return (array) apply_filters(
		'wpjc_account_audiences',
		array(
			'overview'     => 'both',
			'applications' => 'candidate',
			'saved'        => 'candidate',
			'alerts'       => 'candidate',
			'jobs'         => 'employer',
			'received'     => 'employer',
			'companies'    => 'employer',
		)
	);
}

/**
 * Audience of one section.
 *
 * @param string $section Section.
 */
function wpjc_section_audience( $section ) {
	return wpjc_section_audiences()[ $section ] ?? 'both';
}

/**
 * Stored account type of a user, guessed once from what the account already has.
 *
 * @param int $user_id User.
 * @return string candidate|employer|'' (not chosen yet).
 */
function wpjc_user_type( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id ) {
		return '';
	}
	$type = (string) get_user_meta( $user_id, 'wpjc_account_type', true );
	if ( isset( wpjc_account_types()[ $type ] ) ) {
		return $type;
	}
	$guess = '';
	if ( wpjc_user_companies( $user_id ) || wpjc_user_jobs( $user_id ) ) {
		$guess = 'employer';
	} elseif ( wpjc_user_applications( $user_id, 1 ) || wpjc_user_alerts( $user_id ) ) {
		$guess = 'candidate';
	}
	/**
	 * Account type guessed for a user who never picked one ('' = ask).
	 *
	 * @param string $guess   candidate|employer|''.
	 * @param int    $user_id User.
	 */
	$guess = (string) apply_filters( 'wpjc_guess_account_type', $guess, $user_id );
	if ( isset( wpjc_account_types()[ $guess ] ) ) {
		update_user_meta( $user_id, 'wpjc_account_type', $guess );
		return $guess;
	}
	return '';
}

/**
 * Set a user's account type.
 *
 * @param int    $user_id User.
 * @param string $type    candidate|employer.
 */
function wpjc_set_user_type( $user_id, $type ) {
	if ( isset( wpjc_account_types()[ $type ] ) ) {
		update_user_meta( (int) $user_id, 'wpjc_account_type', $type );
		do_action( 'wpjc_account_type_set', (int) $user_id, $type );
	}
}

/**
 * Whether a user may use a section: '' (yes), choose (pick a type first), switch (soft: other type),
 * locked (strict: other type).
 *
 * @param string $section Section.
 * @param int    $user_id User.
 */
function wpjc_section_block( $section, $user_id ) {
	$audience = wpjc_section_audience( $section );
	if ( 'both' === $audience || 'off' === wpjc_accounts_mode() || user_can( (int) $user_id, 'manage_options' ) ) {
		return '';
	}
	$type = wpjc_user_type( $user_id );
	if ( '' === $type ) {
		if ( 'soft' === wpjc_accounts_mode() ) {
			// First use decides: opening "Post a job" makes it an employer account.
			wpjc_set_user_type( $user_id, $audience );
			return '';
		}
		return 'choose';
	}
	if ( $type === $audience ) {
		return '';
	}
	return 'strict' === wpjc_accounts_mode() ? 'locked' : 'switch';
}

/**
 * Account forms check the type too (strict mode); soft mode follows the user's action.
 *
 * @param string $section Section the form belongs to.
 * @param int    $user_id User.
 */
function wpjc_account_require_audience( $section, $user_id ) {
	$block = wpjc_section_block( $section, $user_id );
	if ( 'switch' === $block ) {
		wpjc_set_user_type( $user_id, wpjc_section_audience( $section ) );
		return;
	}
	if ( '' !== $block ) {
		wpjc_account_back( $section, '', 'wrong_type' );
	}
}

/* Screens for the wrong type show the chooser or a switch card instead. */
add_filter(
	'wpjc_account_panel',
	static function ( $slug, $section ) {
		$block = wpjc_section_block( $section, get_current_user_id() );
		if ( 'choose' === $block ) {
			return 'account/choose';
		}
		if ( '' !== $block ) {
			return 'account/switch';
		}
		if ( 'overview' === $section && 'off' !== wpjc_accounts_mode() && '' === wpjc_user_type( get_current_user_id() ) && ! current_user_can( 'manage_options' ) ) {
			return 'account/choose';
		}
		return $slug;
	},
	100,
	2
);

/* The menu shows the screens of the account type. */
add_filter(
	'wpjc_account_menu',
	static function ( $sections, $user, $section ) {
		if ( 'off' === wpjc_accounts_mode() || user_can( $user, 'manage_options' ) ) {
			return $sections;
		}
		$type = wpjc_user_type( $user->ID );
		if ( '' === $type ) {
			return array_intersect_key( $sections, array( 'overview' => 1 ) );
		}
		foreach ( array_keys( $sections ) as $key ) {
			$audience = wpjc_section_audience( $key );
			if ( 'both' !== $audience && $audience !== $type && $key !== $section ) {
				unset( $sections[ $key ] );
			}
		}
		return $sections;
	},
	5,
	3
);

/**
 * Save the account type (chooser and switch card).
 */
function wpjc_handle_account_type() {
	$user_id = wpjc_account_guard( 'account_type' );
	$type    = sanitize_key( wpjc_post_text( 'type' ) );
	$back    = sanitize_key( wpjc_post_text( 'back' ) );
	$back    = isset( wpjc_account_sections()[ $back ] ) ? $back : 'overview';
	$current = wpjc_user_type( $user_id );
	if ( ! isset( wpjc_account_types()[ $type ] ) ) {
		wpjc_account_back( 'overview', '', 'not_found' );
	}
	if ( 'strict' === wpjc_accounts_mode() && '' !== $current && $current !== $type && ! current_user_can( 'manage_options' ) ) {
		wpjc_account_back( 'overview', '', 'wrong_type' );
	}
	wpjc_set_user_type( $user_id, $type );
	// Back to the screen they came for, if it fits; else the overview.
	$fits = in_array( wpjc_section_audience( $back ), array( 'both', $type ), true );
	wpjc_account_back( $fits ? $back : 'overview', '', 'type_' . $type );
}

add_filter(
	'wpjc_account_notices',
	static function ( $list ) {
		$list['type_candidate'] = array( 'ok', __( 'Your account is set up for job seeking.', 'jobcore' ) );
		$list['type_employer']  = array( 'ok', __( 'Your account is set up for hiring.', 'jobcore' ) );
		$list['wrong_type']     = array( 'error', __( 'This is not available for your type of account.', 'jobcore' ) );
		return $list;
	}
);

/* Sign-up: the page someone came from decides the type ("Post a job" → hiring). */
add_action(
	'user_register',
	static function ( $user_id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- reading the sign-up form's own fields after WordPress handled it.
		$type = isset( $_POST['wpjc_account_type'] ) ? sanitize_key( wp_unslash( $_POST['wpjc_account_type'] ) ) : '';
		if ( ! isset( wpjc_account_types()[ $type ] ) ) {
			$to   = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
			$type = (bool) preg_match( '#/account/(companies|jobs|received|candidates)\b|wpjc_account=(companies|jobs|received|candidates)#', $to ) ? 'employer' : '';
			if ( '' === $type && preg_match( '#/account/(resume|alerts|applications|saved)\b|wpjc_account=(resume|alerts|applications|saved)|/job/#', $to ) ) {
				$type = 'candidate';
			}
		}
		// phpcs:enable
		if ( '' !== $type ) {
			wpjc_set_user_type( (int) $user_id, $type );
		}
	}
);

/* wp-login.php sign-up: an optional choice (other sign-up forms can post wpjc_account_type too). */
add_action(
	'register_form',
	static function () {
		if ( 'off' === wpjc_accounts_mode() ) {
			return;
		}
		echo '<p class="wpjc-signup-type"><span>' . esc_html__( 'I am', 'jobcore' ) . '</span><br>';
		foreach ( wpjc_account_types() as $key => $type ) {
			printf( '<label style="display:block;margin:6px 0"><input type="radio" name="wpjc_account_type" value="%s"%s> %s</label>', esc_attr( $key ), checked( 'candidate', $key, false ), esc_html( $type[0] ) );
		}
		echo '</p>';
	}
);

/* wp-admin user profile: the board team can change the type (the only way in strict mode). */
$wpjc_profile_field = static function ( $user ) {
	if ( ! current_user_can( 'edit_users' ) || 'off' === wpjc_accounts_mode() ) {
		return;
	}
	$type = (string) get_user_meta( $user->ID, 'wpjc_account_type', true );
	?>
	<h2><?php esc_html_e( 'Jobs account', 'jobcore' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="wpjc_account_type"><?php esc_html_e( 'Account type', 'jobcore' ); ?></label></th>
			<td>
				<select id="wpjc_account_type" name="wpjc_account_type">
					<option value=""><?php esc_html_e( 'Not chosen yet', 'jobcore' ); ?></option>
					<?php foreach ( wpjc_account_types() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $type, $key ); ?>><?php echo esc_html( 'candidate' === $key ? __( 'Job seeker', 'jobcore' ) : __( 'Employer', 'jobcore' ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php wp_nonce_field( 'wpjc_profile_type', 'wpjc_profile_type_nonce' ); ?>
			</td>
		</tr>
	</table>
	<?php
};
add_action( 'show_user_profile', $wpjc_profile_field );
add_action( 'edit_user_profile', $wpjc_profile_field );
unset( $wpjc_profile_field );

$wpjc_profile_save = static function ( $user_id ) {
	if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['wpjc_profile_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpjc_profile_type_nonce'] ) ), 'wpjc_profile_type' ) ) {
		return;
	}
	$type = isset( $_POST['wpjc_account_type'] ) ? sanitize_key( wp_unslash( $_POST['wpjc_account_type'] ) ) : '';
	if ( isset( wpjc_account_types()[ $type ] ) ) {
		wpjc_set_user_type( $user_id, $type );
	} else {
		delete_user_meta( $user_id, 'wpjc_account_type' );
	}
};
add_action( 'personal_options_update', $wpjc_profile_save );
add_action( 'edit_user_profile_update', $wpjc_profile_save );
unset( $wpjc_profile_save );
