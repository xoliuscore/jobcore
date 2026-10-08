<?php
/**
 * Google Indexing API: tell Google right away when a job goes live, changes or closes,
 * instead of waiting for the next crawl. Google offers this API for job posting pages.
 *
 * Needs a Google Cloud service account (JSON key) that is an owner of the site in Search
 * Console. The key is kept in its own option, never loaded on every page and never shown again.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_INDEX_KEY = 'wpjc_index_key';
const WPJC_INDEX_LOG = 'wpjc_index_log';

/**
 * The saved service account: client_email, private_key, token_uri (or null).
 *
 * @return array{client_email:string,private_key:string,token_uri:string}|null
 */
function wpjc_index_account() {
	$raw = get_option( WPJC_INDEX_KEY, '' );
	$key = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : null;
	if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) ) {
		return null;
	}
	return array(
		'client_email' => (string) $key['client_email'],
		'private_key'  => (string) $key['private_key'],
		'token_uri'    => ! empty( $key['token_uri'] ) ? (string) $key['token_uri'] : 'https://oauth2.googleapis.com/token',
	);
}

/** Whether pings are on and a key is saved. */
function wpjc_index_on() {
	return (bool) wpjc_opt( 'indexing_on' ) && null !== wpjc_index_account() && function_exists( 'openssl_sign' );
}

/**
 * Check and store an uploaded service account key.
 *
 * @param string $json Key file contents.
 * @return true|WP_Error
 */
function wpjc_index_save_key( $json ) {
	$key = json_decode( trim( (string) $json ), true );
	if ( ! is_array( $key ) || ( $key['type'] ?? '' ) !== 'service_account' || empty( $key['client_email'] ) || empty( $key['private_key'] ) ) {
		return new WP_Error( 'wpjc_index_key', __( 'This is not a service account key. In Google Cloud, open the service account, choose Keys → Add key → JSON, and paste the whole file.', 'jobcore' ) );
	}
	if ( ! function_exists( 'openssl_pkey_get_private' ) || ! openssl_pkey_get_private( (string) $key['private_key'] ) ) {
		return new WP_Error( 'wpjc_index_key', __( 'The private key in this file could not be read.', 'jobcore' ) );
	}
	$keep = array(
		'type'         => 'service_account',
		'client_email' => sanitize_email( (string) $key['client_email'] ),
		'private_key'  => (string) $key['private_key'],
		'token_uri'    => esc_url_raw( (string) ( $key['token_uri'] ?? 'https://oauth2.googleapis.com/token' ) ),
	);
	delete_option( WPJC_INDEX_KEY );
	add_option( WPJC_INDEX_KEY, wp_json_encode( $keep ), '', false );
	delete_transient( 'wpjc_index_token' );
	return true;
}

/** Forget the key and the cached token. */
function wpjc_index_forget_key() {
	delete_option( WPJC_INDEX_KEY );
	delete_transient( 'wpjc_index_token' );
}

/**
 * base64url.
 *
 * @param string $data Bytes.
 * @return string
 */
function wpjc_b64url( $data ) {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- JWT encoding.
}

/**
 * Access token for the Indexing API (cached for 50 minutes).
 *
 * @return string|WP_Error
 */
function wpjc_index_token() {
	$cached = get_transient( 'wpjc_index_token' );
	if ( is_string( $cached ) && '' !== $cached ) {
		return $cached;
	}
	$acc = wpjc_index_account();
	if ( ! $acc ) {
		return new WP_Error( 'wpjc_index_nokey', __( 'No service account key saved.', 'jobcore' ) );
	}
	$now    = time();
	$header = wpjc_b64url( (string) wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
	$claims = wpjc_b64url(
		(string) wp_json_encode(
			array(
				'iss'   => $acc['client_email'],
				'scope' => 'https://www.googleapis.com/auth/indexing',
				'aud'   => $acc['token_uri'],
				'iat'   => $now,
				'exp'   => $now + 3600,
			)
		)
	);
	$sig    = '';
	if ( ! openssl_sign( $header . '.' . $claims, $sig, $acc['private_key'], 'sha256WithRSAEncryption' ) ) {
		return new WP_Error( 'wpjc_index_sign', __( 'Could not sign the request with the saved key.', 'jobcore' ) );
	}
	$res = wp_remote_post(
		$acc['token_uri'],
		array(
			'timeout' => 15,
			'body'    => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $header . '.' . $claims . '.' . wpjc_b64url( $sig ),
			),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	if ( empty( $body['access_token'] ) ) {
		/* translators: %s: error from Google */
		return new WP_Error( 'wpjc_index_auth', sprintf( __( 'Google did not accept the key: %s', 'jobcore' ), (string) ( $body['error_description'] ?? $body['error'] ?? wp_remote_retrieve_response_code( $res ) ) ) );
	}
	set_transient( 'wpjc_index_token', (string) $body['access_token'], 50 * MINUTE_IN_SECONDS );
	return (string) $body['access_token'];
}

/**
 * Tell Google about one job page.
 *
 * @param string $url  Job URL.
 * @param string $type URL_UPDATED or URL_DELETED.
 * @return true|WP_Error
 */
function wpjc_index_notify( $url, $type ) {
	$token = wpjc_index_token();
	if ( is_wp_error( $token ) ) {
		wpjc_index_log( $url, $type, $token->get_error_message() );
		return $token;
	}
	$res  = wp_remote_post(
		'https://indexing.googleapis.com/v3/urlNotifications:publish',
		array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'url'  => $url,
					'type' => $type,
				)
			),
		)
	);
	$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
	if ( 200 !== $code ) {
		$body = is_wp_error( $res ) ? null : json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$msg  = is_wp_error( $res ) ? $res->get_error_message() : (string) ( $body['error']['message'] ?? 'HTTP ' . $code );
		if ( 401 === $code ) {
			delete_transient( 'wpjc_index_token' );
		}
		wpjc_index_log( $url, $type, $msg );
		return new WP_Error( 'wpjc_index_http', $msg );
	}
	wpjc_index_log( $url, $type, '' );
	return true;
}

/**
 * Keep the last 30 notifications for the settings screen.
 *
 * @param string $url   URL.
 * @param string $type  Type.
 * @param string $error Error ('' = sent).
 */
function wpjc_index_log( $url, $type, $error ) {
	$log = (array) get_option( WPJC_INDEX_LOG, array() );
	array_unshift(
		$log,
		array(
			'time'  => time(),
			'url'   => (string) $url,
			'type'  => (string) $type,
			'error' => (string) $error,
		)
	);
	update_option( WPJC_INDEX_LOG, array_slice( $log, 0, 30 ), false );
}

/**
 * What Google should know about a job now: URL_UPDATED (open) or URL_DELETED (closed).
 *
 * @param WP_Post $job Job.
 * @return string
 */
function wpjc_index_state( WP_Post $job ) {
	return wpjc_job_is_closed( $job ) ? 'URL_DELETED' : 'URL_UPDATED';
}

/**
 * Queue a job for a ping a minute from now (several saves in a row send one ping).
 *
 * @param int $job_id Job ID.
 */
function wpjc_index_queue( $job_id ) {
	if ( ! wpjc_index_on() || wp_is_post_revision( $job_id ) || wp_is_post_autosave( $job_id ) ) {
		return;
	}
	$job = get_post( $job_id );
	if ( ! $job || 'wpjc_job' !== $job->post_type ) {
		return;
	}
	// The address goes along now: a deleted job has no permalink later.
	$url = get_permalink( $job );
	if ( 'publish' !== $job->post_status ) {
		$url = (string) get_post_meta( $job_id, '_wpjc_idx_url', true );
	}
	if ( ! $url ) {
		return;
	}
	if ( ! wp_next_scheduled( 'wpjc_index_ping', array( (int) $job_id ) ) ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wpjc_index_ping', array( (int) $job_id ) );
	}
	update_post_meta( $job_id, '_wpjc_idx_url', esc_url_raw( $url ) );
}

add_action(
	'wpjc_index_ping',
	static function ( $job_id ) {
		$job = get_post( (int) $job_id );
		$url = (string) get_post_meta( (int) $job_id, '_wpjc_idx_url', true );
		if ( '' === $url ) {
			return;
		}
		$state = $job ? wpjc_index_state( $job ) : 'URL_DELETED';
		$sent  = (string) get_post_meta( (int) $job_id, '_wpjc_idx', true );
		// A job Google never heard of needs no "deleted".
		if ( 'URL_DELETED' === $state && 'URL_UPDATED' !== $sent ) {
			return;
		}
		if ( true === wpjc_index_notify( $url, $state ) && $job ) {
			update_post_meta( (int) $job_id, '_wpjc_idx', $state );
		}
	}
);

add_action(
	'save_post_wpjc_job',
	static function ( $post_id ) {
		wpjc_index_queue( (int) $post_id );
	},
	30
);
foreach ( array( 'added_post_meta', 'updated_post_meta' ) as $wpjc_hook ) {
	add_action(
		$wpjc_hook,
		static function ( $meta_id, $post_id, $key ) {
			if ( in_array( $key, array( '_wpjc_filled', '_wpjc_expires' ), true ) && 'wpjc_job' === get_post_type( $post_id ) ) {
				wpjc_index_queue( (int) $post_id );
			}
		},
		10,
		3
	);
}
add_action(
	'before_delete_post',
	static function ( $post_id ) {
		if ( ! wpjc_index_on() || 'wpjc_job' !== get_post_type( $post_id ) || 'URL_UPDATED' !== get_post_meta( $post_id, '_wpjc_idx', true ) ) {
			return;
		}
		$url = (string) get_post_meta( $post_id, '_wpjc_idx_url', true );
		if ( $url ) {
			wpjc_index_notify( $url, 'URL_DELETED' );
		}
	}
);

// Daily: jobs that expired since yesterday leave Google too.
add_action(
	'init',
	static function () {
		if ( wpjc_index_on() && ! wp_next_scheduled( 'wpjc_index_sweep' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'wpjc_index_sweep' );
		}
	}
);
add_action(
	'wpjc_index_sweep',
	static function () {
		if ( ! wpjc_index_on() ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'wpjc_job',
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => '_wpjc_idx', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => 'URL_UPDATED', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			$job = get_post( $id );
			if ( $job && wpjc_job_is_closed( $job ) ) {
				$url = (string) get_post_meta( $id, '_wpjc_idx_url', true );
				if ( $url && true === wpjc_index_notify( $url, 'URL_DELETED' ) ) {
					update_post_meta( $id, '_wpjc_idx', 'URL_DELETED' );
				}
			}
		}
	}
);

/**
 * Send every open job once (after connecting). At most 150 per run to stay inside Google's
 * default daily quota of 200.
 *
 * @return int Jobs sent.
 */
function wpjc_index_send_open_jobs() {
	$sent = 0;
	$jobs = wpjc_query_jobs( array( 'per_page' => 150 ) )->posts;
	foreach ( $jobs as $job ) {
		if ( 'URL_UPDATED' === get_post_meta( $job->ID, '_wpjc_idx', true ) ) {
			continue;
		}
		$url = get_permalink( $job );
		update_post_meta( $job->ID, '_wpjc_idx_url', esc_url_raw( $url ) );
		$res = wpjc_index_notify( $url, 'URL_UPDATED' );
		if ( true !== $res ) {
			break;
		}
		update_post_meta( $job->ID, '_wpjc_idx', 'URL_UPDATED' );
		++$sent;
	}
	return $sent;
}

// Settings screen actions: save / remove key, test, send all.
add_action(
	'admin_post_wpjc_index',
	static function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'jobcore' ) );
		}
		check_admin_referer( 'wpjc_index' );
		$do  = isset( $_POST['wpjc_do'] ) ? sanitize_key( wp_unslash( $_POST['wpjc_do'] ) ) : '';
		$msg = '';
		$ok  = true;
		if ( 'save' === $do ) {
			$res = wpjc_index_save_key( isset( $_POST['wpjc_key'] ) ? wp_unslash( $_POST['wpjc_key'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON key, checked in wpjc_index_save_key().
			$ok  = true === $res;
			$msg = $ok ? __( 'Key saved. Use “Test connection” next.', 'jobcore' ) : $res->get_error_message();
		} elseif ( 'forget' === $do ) {
			wpjc_index_forget_key();
			$msg = __( 'Key removed.', 'jobcore' );
		} elseif ( 'test' === $do ) {
			delete_transient( 'wpjc_index_token' );
			$res = wpjc_index_token();
			$ok  = ! is_wp_error( $res );
			$msg = $ok ? __( 'Connected: Google accepted the key.', 'jobcore' ) : $res->get_error_message();
		} elseif ( 'all' === $do ) {
			$n   = wpjc_index_send_open_jobs();
			/* translators: %d: number of jobs */
			$msg = sprintf( _n( 'Sent %d job to Google.', 'Sent %d jobs to Google.', $n, 'jobcore' ), $n );
		}
		set_transient( 'wpjc_index_msg_' . get_current_user_id(), array( $ok ? 'success' : 'error', $msg ), 60 );
		wp_safe_redirect( wpjc_settings_url( 'feeds' ) );
		exit;
	}
);
