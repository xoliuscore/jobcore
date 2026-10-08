<?php
/**
 * Sign in and sign up in the board's own look (jobs account → guests), with the account type
 * chosen on sign-up. "Profile and security" (password, e-mail) stays with WordPress / News.
 *
 * Setting "login_page": board (default) uses these screens; site keeps the site's login page
 * (the News. login or wp-login.php).
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_nopriv_wpjc_login', 'wpjc_handle_login' );
add_action( 'admin_post_wpjc_login', 'wpjc_handle_login' );
add_action( 'admin_post_nopriv_wpjc_signup', 'wpjc_handle_signup' );
add_action( 'admin_post_nopriv_wpjc_lost', 'wpjc_handle_lost' );
add_action( 'admin_post_wpjc_lost', 'wpjc_handle_lost' );
add_action( 'admin_post_nopriv_wpjc_reset', 'wpjc_handle_reset' );
add_action( 'admin_post_wpjc_reset', 'wpjc_handle_reset' );
add_action( 'admin_post_wpjc_signup', 'wpjc_handle_signup' );

/** Whether the board uses its own sign-in screens. */
function wpjc_board_login() {
	return 'site' !== wpjc_opt( 'login_page' );
}

/** Whether new accounts can be created (WordPress "Anyone can register", or the News. sign-up). */
function wpjc_signup_open() {
	$open = (bool) get_option( 'users_can_register' );
	if ( ! $open && function_exists( 'news_core_account_signup_open' ) ) {
		$open = (bool) news_core_account_signup_open();
	}
	return (bool) apply_filters( 'wpjc_signup_open', $open );
}

/** Whether new accounts confirm their e-mail address first (the News. account setting). */
function wpjc_signup_confirm() {
	return function_exists( 'news_core_account_confirm_required' ) && defined( 'NEWS_CORE_META_PENDING' ) && function_exists( 'news_core_send_confirmation' ) && news_core_account_confirm_required();
}

/**
 * URL of the board's sign-in / sign-up screen.
 *
 * @param string $mode     login|signup.
 * @param string $redirect Where to go afterwards.
 * @param string $type     Suggested account type for sign-up (candidate|employer|'').
 */
function wpjc_auth_url( $mode = 'login', $redirect = '', $type = '' ) {
	$args = array( 'wpjc_auth' => in_array( $mode, array( 'signup', 'lost', 'reset' ), true ) ? $mode : 'login' );
	if ( '' !== $redirect ) {
		$args['redirect_to'] = rawurlencode( $redirect );
	}
	if ( in_array( $type, array( 'candidate', 'employer' ), true ) ) {
		$args['wpjc_type'] = $type;
	}
	return add_query_arg( $args, wpjc_account_url() );
}

/**
 * Safe place to go after signing in.
 *
 * @param string $raw Requested URL.
 */
function wpjc_auth_redirect( $raw ) {
	$url = wp_validate_redirect( esc_url_raw( (string) $raw ), '' );
	return '' !== $url ? $url : wpjc_account_url();
}

/**
 * Back to the sign-in screen with a message.
 *
 * @param string $mode     login|signup.
 * @param string $msg      Message code.
 * @param string $redirect Where the user wanted to go.
 * @param array  $keep     Values to show again (sign-up).
 */
function wpjc_auth_back( $mode, $msg, $redirect, array $keep = array() ) {
	if ( $keep ) {
		set_transient( 'wpjc_auth_' . md5( wpjc_client_ip() ), $keep, 10 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( add_query_arg( 'wpjc_msg', $msg, wpjc_auth_url( $mode, $redirect ) ) );
	exit;
}

/** The visitor's IP, for rate limits only (never stored with the account). */
function wpjc_client_ip() {
	return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
}

/**
 * Sign in.
 */
function wpjc_handle_login() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked below.
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$nonce    = isset( $_POST['wpjc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpjc_login' ) ) {
		wpjc_auth_back( 'login', 'expired', $redirect );
	}
	if ( is_user_logged_in() ) {
		wp_safe_redirect( wpjc_auth_redirect( $redirect ) );
		exit;
	}
	// Slow down password guessing: 10 failed tries per address and hour.
	$key   = 'wpjc_login_fail_' . md5( wpjc_client_ip() );
	$fails = (int) get_transient( $key );
	if ( $fails >= 10 ) {
		wpjc_auth_back( 'login', 'login_wait', $redirect );
	}
	$user = wp_signon(
		array(
			'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
			'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
			'remember'      => ! empty( $_POST['rememberme'] ),
		),
		is_ssl()
	);
	// phpcs:enable
	if ( is_wp_error( $user ) ) {
		set_transient( $key, $fails + 1, HOUR_IN_SECONDS );
		$code = $user->get_error_code();
		wpjc_auth_back( 'login', in_array( $code, array( 'news_pending', 'not_confirmed', 'unconfirmed' ), true ) ? 'login_confirm' : 'login_failed', $redirect );
	}
	delete_transient( $key );
	wp_safe_redirect( wpjc_auth_redirect( $redirect ) );
	exit;
}

/**
 * Sign up: name, e-mail, password, account type. Signs the new user in.
 */
function wpjc_handle_signup() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked below.
	$redirect = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';
	$nonce    = isset( $_POST['wpjc_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpjc_signup' ) ) {
		wpjc_auth_back( 'signup', 'expired', $redirect );
	}
	if ( ! wpjc_signup_open() ) {
		wpjc_auth_back( 'login', 'signup_closed', $redirect );
	}
	// Bots fill every field; people never see this one.
	if ( ! empty( $_POST['wpjc_website_hp'] ) ) {
		wp_safe_redirect( wpjc_account_url() );
		exit;
	}
	$keep = array(
		'name'  => isset( $_POST['name'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 80 ) : '',
		'email' => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
		'type'  => isset( $_POST['wpjc_account_type'] ) ? sanitize_key( wp_unslash( $_POST['wpjc_account_type'] ) ) : '',
	);
	$pass    = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitized.
	$consent = ! empty( $_POST['consent'] );
	// phpcs:enable
	if ( '' === $keep['name'] || ! is_email( $keep['email'] ) || strlen( $pass ) < 8 || ! $consent ) {
		wpjc_auth_back( 'signup', strlen( $pass ) < 8 && '' !== $pass ? 'signup_password' : 'signup_fields', $redirect, $keep );
	}
	if ( 'off' !== wpjc_accounts_mode() && ! isset( wpjc_account_types()[ $keep['type'] ] ) ) {
		wpjc_auth_back( 'signup', 'signup_type', $redirect, $keep );
	}
	$confirm = wpjc_signup_confirm();
	if ( email_exists( $keep['email'] ) ) {
		// With e-mail confirmation on, the answer is the same as for a new account (no account fishing).
		wpjc_auth_back( 'login', $confirm ? 'signup_sent' : 'signup_exists', $redirect );
	}
	// At most 5 new accounts per address and hour.
	$rate = 'wpjc_signups_' . md5( wpjc_client_ip() );
	if ( (int) get_transient( $rate ) >= 5 ) {
		wpjc_auth_back( 'signup', 'signup_wait', $redirect, $keep );
	}

	$base  = sanitize_user( strtolower( strtok( $keep['email'], '@' ) ), true );
	$base  = '' !== $base ? $base : 'user';
	$login = $base;
	for ( $i = 2; username_exists( $login ); $i++ ) {
		$login = $base . $i;
	}
	$parts   = preg_split( '/\s+/', $keep['name'], 2 );
	$user_id = wp_insert_user(
		array(
			'user_login'   => $login,
			'user_email'   => $keep['email'],
			'user_pass'    => $pass,
			'display_name' => $keep['name'],
			'first_name'   => $parts[0],
			'last_name'    => $parts[1] ?? '',
			'role'         => (string) apply_filters( 'wpjc_signup_role', get_option( 'default_role', 'subscriber' ) ),
		)
	);
	if ( is_wp_error( $user_id ) ) {
		wpjc_auth_back( 'signup', 'signup_fields', $redirect, $keep );
	}
	set_transient( $rate, (int) get_transient( $rate ) + 1, HOUR_IN_SECONDS );
	if ( isset( wpjc_account_types()[ $keep['type'] ] ) ) {
		wpjc_set_user_type( $user_id, $keep['type'] );
	}
	update_user_meta( $user_id, 'wpjc_signup_consent', time() );
	do_action( 'wpjc_signed_up', $user_id, $keep['type'] );

	if ( $confirm ) {
		// The News. account flow confirms the address; the account opens after the link is used.
		update_user_meta( $user_id, NEWS_CORE_META_PENDING, 1 );
		wp_new_user_notification( $user_id, null, 'admin' );
		news_core_send_confirmation( get_user_by( 'id', $user_id ) );
		wpjc_auth_back( 'login', 'signup_sent', $redirect );
	}

	wp_set_current_user( $user_id );
	wp_set_auth_cookie( $user_id, true, is_ssl() );
	wp_new_user_notification( $user_id, null, 'admin' );
	wp_mail(
		$keep['email'],
		/* translators: %s: board name */
		sprintf( __( 'Welcome to %s', 'jobcore' ), wpjc_brand()['name'] ),
		implode(
			"\n",
			array(
				/* translators: %s: name */
				sprintf( __( 'Hi %s,', 'jobcore' ), $parts[0] ),
				'',
				__( 'your jobs account is ready. You can sign in with this e-mail address and the password you chose.', 'jobcore' ),
				wpjc_account_url(),
			)
		)
	);

	// First steps: the screen the account type needs, unless they were on their way somewhere.
	$next = wp_validate_redirect( esc_url_raw( $redirect ), '' );
	if ( '' === $next || untrailingslashit( $next ) === untrailingslashit( wpjc_account_url() ) ) {
		$next = 'employer' === $keep['type'] ? wpjc_account_url( 'companies', 'new' ) : wpjc_account_url();
		$next = (string) apply_filters( 'wpjc_signup_next', $next, $keep['type'], $user_id );
	}
	wp_safe_redirect( add_query_arg( 'wpjc_msg', 'welcome', $next ) );
	exit;
}

/**
 * Whether a URL belongs to the job board (jobs page, account, single jobs, employers).
 *
 * @param string $url URL.
 * @return bool
 */
function wpjc_is_board_url( $url ) {
	if ( '' === (string) $url || '' === wpjc_jobs_page_path() ) {
		return false;
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$base = (string) wp_parse_url( wpjc_home_url(), PHP_URL_PATH );
	if ( '' !== $base && 0 === strpos( trailingslashit( $path ), trailingslashit( $base ) ) ) {
		return true;
	}
	$id = url_to_postid( $url );
	return $id && in_array( get_post_type( $id ), array( 'wpjc_job', 'wpjc_employer' ), true );
}

/**
 * The URL of the page being shown.
 *
 * @return string
 */
function wpjc_current_url() {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	return home_url( $uri );
}

/*
 * Sign-in and sign-up links built anywhere on a board page — the site header,
 * comment forms, other plugins — open the board screen instead of wp-login.php.
 */
add_filter(
	'login_url',
	static function ( $url, $redirect = '', $force = false ) {
		if ( $force || is_admin() || wp_doing_ajax() || ! wpjc_board_login() ) {
			return $url;
		}
		$on_board = wpjc_is_board_url( $redirect ) || ( '' === (string) $redirect && wpjc_is_board_url( wpjc_current_url() ) );
		if ( ! $on_board && ! ( did_action( 'wp' ) && wpjc_is_board_url( wpjc_current_url() ) ) ) {
			return $url;
		}
		return wpjc_auth_url( 'login', '' !== (string) $redirect ? (string) $redirect : wpjc_current_url() );
	},
	20,
	3
);
add_filter(
	'register_url',
	static function ( $url ) {
		if ( is_admin() || ! wpjc_board_login() || ! did_action( 'wp' ) || ! wpjc_is_board_url( wpjc_current_url() ) ) {
			return $url;
		}
		return wpjc_auth_url( 'signup', wpjc_current_url() );
	},
	20
);

/**
 * Forgot password: e-mail a link to the board's own reset screen. The answer is the same
 * whether or not the address has an account.
 */
function wpjc_handle_lost() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked next.
	if ( ! isset( $_POST['wpjc_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wpjc_nonce'] ) ), 'wpjc_lost' ) ) {
		wpjc_auth_back( 'lost', 'expired', '' );
	}
	$rate = 'wpjc_lost_' . md5( wpjc_client_ip() );
	if ( (int) get_transient( $rate ) >= 5 ) {
		wpjc_auth_back( 'lost', 'login_wait', '' );
	}
	set_transient( $rate, (int) get_transient( $rate ) + 1, HOUR_IN_SECONDS );
	$who  = isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '';
	$user = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
	if ( '' === $who ) {
		wpjc_auth_back( 'lost', 'lost_empty', '' );
	}
	if ( $user ) {
		$key = get_password_reset_key( $user );
		if ( ! is_wp_error( $key ) ) {
			$link = add_query_arg(
				array(
					'key'   => rawurlencode( $key ),
					'login' => rawurlencode( $user->user_login ),
				),
				wpjc_auth_url( 'reset' )
			);
			$name = wpjc_brand()['name'];
			/* translators: %s: board name */
			$subject = sprintf( __( 'Reset your password for %s', 'jobcore' ), $name );
			$body    = sprintf(
				/* translators: 1: person's name, 2: board name, 3: link */
				__( "Hello %1\$s,\n\nsomeone asked to reset the password for your account on %2\$s. To choose a new password, open this link:\n\n%3\$s\n\nThe link works once and expires in 24 hours. If you did not ask for this, you can ignore this e-mail — your password stays as it is.", 'jobcore' ),
				$user->display_name,
				$name,
				$link
			);
			wp_mail( $user->user_email, $subject, $body );
		}
	}
	wpjc_auth_back( 'login', 'lost_sent', '' );
}

/**
 * Choose a new password from the e-mailed link.
 */
function wpjc_handle_reset() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked next.
	$key   = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
	$login = isset( $_POST['login'] ) ? sanitize_user( wp_unslash( $_POST['login'] ) ) : '';
	$pass  = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- passwords are not sanitised.
	$ok    = isset( $_POST['wpjc_nonce'] ) && wp_verify_nonce( sanitize_key( wp_unslash( $_POST['wpjc_nonce'] ) ), 'wpjc_reset' );
	// phpcs:enable
	$back = static function ( $msg ) use ( $key, $login ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'key'      => rawurlencode( $key ),
					'login'    => rawurlencode( $login ),
					'wpjc_msg' => $msg,
				),
				wpjc_auth_url( 'reset' )
			)
		);
		exit;
	};
	if ( ! $ok ) {
		$back( 'expired' );
	}
	$user = check_password_reset_key( $key, $login );
	if ( is_wp_error( $user ) ) {
		wpjc_auth_back( 'lost', 'reset_invalid', '' );
	}
	if ( strlen( $pass ) < 8 ) {
		$back( 'signup_password' );
	}
	reset_password( $user, $pass );
	wpjc_auth_back( 'login', 'reset_done', '' );
}

// Lost-password links on board pages open the board screen too.
add_filter(
	'lostpassword_url',
	static function ( $url ) {
		if ( is_admin() || ! wpjc_board_login() || ! did_action( 'wp' ) || ! wpjc_is_board_url( wpjc_current_url() ) ) {
			return $url;
		}
		return wpjc_auth_url( 'lost' );
	},
	20
);

add_filter(
	'wpjc_account_notices',
	static function ( $list ) {
		$list['welcome'] = array( 'ok', __( 'Welcome! Your account is ready.', 'jobcore' ) );
		return $list;
	}
);

/**
 * Message on the sign-in screen: [type, text] or null.
 *
 * @return array{0:string,1:string}|null
 */
function wpjc_auth_notice() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only message code.
	$code = isset( $_GET['wpjc_msg'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_msg'] ) ) : '';
	$list = array(
		'expired'         => array( 'warn', __( 'The form expired. Please try again.', 'jobcore' ) ),
		'login_failed'    => array( 'warn', __( 'The e-mail address or password is not right. Please try again.', 'jobcore' ) ),
		'login_confirm'   => array( 'warn', __( 'Please confirm your e-mail address first — we sent you a link.', 'jobcore' ) ),
		'login_wait'      => array( 'warn', __( 'Too many tries. Please wait a while or reset your password.', 'jobcore' ) ),
		'signup_fields'   => array( 'warn', __( 'Please fill in your name, a valid e-mail address and a password, and accept the privacy policy.', 'jobcore' ) ),
		'signup_password' => array( 'warn', __( 'Please choose a password with at least 8 characters.', 'jobcore' ) ),
		'signup_type'     => array( 'warn', __( 'Please tell us whether you are looking for a job or hiring.', 'jobcore' ) ),
		'signup_exists'   => array( 'warn', __( 'There is already an account with this e-mail address. Sign in, or reset your password.', 'jobcore' ) ),
		'signup_wait'     => array( 'warn', __( 'Too many new accounts from your connection. Please try again later.', 'jobcore' ) ),
		'signup_closed'   => array( 'warn', __( 'New accounts are not open at the moment.', 'jobcore' ) ),
		'lost_empty'      => array( 'warn', __( 'Please enter your e-mail address.', 'jobcore' ) ),
		'lost_sent'       => array( 'ok', __( 'If there is an account with this address, we just sent it a link to choose a new password. Please check your inbox.', 'jobcore' ) ),
		'reset_invalid'   => array( 'warn', __( 'This link has expired or was already used. Ask for a new one below.', 'jobcore' ) ),
		'reset_done'      => array( 'ok', __( 'Your new password is saved. You can sign in now.', 'jobcore' ) ),
		'signup_sent'     => array( 'ok', __( 'Almost done: we sent you an e-mail. Open the link in it to confirm your address, then sign in here.', 'jobcore' ) ),
	);
	return $list[ $code ] ?? null;
}

/** Values kept from a failed sign-up (read once). */
function wpjc_auth_kept() {
	$key  = 'wpjc_auth_' . md5( wpjc_client_ip() );
	$keep = get_transient( $key );
	if ( false !== $keep ) {
		delete_transient( $key );
	}
	return is_array( $keep ) ? $keep : array();
}

/* "Lost your password?" — the News. page when it exists, else WordPress. */
function wpjc_lost_password_url() {
	if ( wpjc_board_login() && '' !== wpjc_jobs_page_path() ) {
		return wpjc_auth_url( 'lost' );
	}
	if ( function_exists( 'news_core_account_url' ) ) {
		$url = news_core_account_url( 'login', array( 'mode' => 'forgot' ) );
		if ( $url ) {
			return (string) $url;
		}
	}
	return wp_lostpassword_url( wpjc_account_url() );
}
