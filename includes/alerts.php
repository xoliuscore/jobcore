<?php
/**
 * Job alerts: saved searches per user (user meta `wpjc_alerts`), e-mailed right away when a
 * matching job goes live, or as a daily / weekly digest (WP-Cron).
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/** Max alerts per user. */
const WPJC_MAX_ALERTS = 20;

/**
 * Sending options: key => label.
 *
 * @return array<string, string>
 */
function wpjc_alert_frequencies() {
	return array(
		'instant' => __( 'Right away', 'jobcore' ),
		'daily'   => __( 'Once a day', 'jobcore' ),
		'weekly'  => __( 'Once a week', 'jobcore' ),
	);
}

/**
 * Empty alert.
 *
 * @return array<string, mixed>
 */
function wpjc_alert_defaults() {
	return array(
		'id'        => '',
		'name'      => '',
		'keywords'  => '',
		'category'  => 0,
		'type'      => 0,
		'employer'  => 0,
		'location'  => '',
		'remote'    => false,
		'frequency' => 'daily',
		'active'    => true,
		'created'   => 0,
		'last_sent' => 0,
	);
}

/**
 * A user's alerts keyed by ID.
 *
 * @param int $user_id User ID.
 * @return array<string, array<string, mixed>>
 */
function wpjc_user_alerts( $user_id ) {
	$raw = $user_id ? get_user_meta( (int) $user_id, 'wpjc_alerts', true ) : array();
	$out = array();
	foreach ( is_array( $raw ) ? $raw : array() as $alert ) {
		if ( is_array( $alert ) && ! empty( $alert['id'] ) ) {
			$alert                        = array_merge( wpjc_alert_defaults(), array_intersect_key( $alert, wpjc_alert_defaults() ) );
			$out[ (string) $alert['id'] ] = $alert;
		}
	}
	return $out;
}

/**
 * Save a user's alerts.
 *
 * @param int   $user_id User ID.
 * @param array $alerts  Alerts keyed by ID.
 */
function wpjc_save_user_alerts( $user_id, array $alerts ) {
	if ( $alerts ) {
		update_user_meta( (int) $user_id, 'wpjc_alerts', $alerts );
	} else {
		delete_user_meta( (int) $user_id, 'wpjc_alerts' );
	}
}

/**
 * Add an alert for a user.
 *
 * @param int   $user_id User ID.
 * @param array $alert   Fields (see wpjc_alert_defaults()).
 * @return string Alert ID, '' when the limit is reached.
 */
function wpjc_add_alert( $user_id, array $alert ) {
	$alerts = wpjc_user_alerts( $user_id );
	if ( count( $alerts ) >= WPJC_MAX_ALERTS ) {
		return '';
	}
	$alert['id']        = strtolower( wp_generate_password( 10, false ) );
	$alert['created']   = time();
	$alert['last_sent'] = time();
	$alert              = array_merge( wpjc_alert_defaults(), array_intersect_key( $alert, wpjc_alert_defaults() ) );

	$alerts[ $alert['id'] ] = $alert;
	wpjc_save_user_alerts( $user_id, $alerts );
	return $alert['id'];
}

/**
 * Short labels describing an alert's criteria.
 *
 * @param array $alert Alert.
 * @return string[]
 */
function wpjc_alert_criteria( array $alert ) {
	$out = array();
	if ( '' !== $alert['keywords'] ) {
		$out[] = '“' . $alert['keywords'] . '”';
	}
	foreach ( array( 'employer' => 'wpjc_employer', 'category' => 'wpjc_job_category', 'type' => 'wpjc_job_type' ) as $key => $taxonomy ) {
		$term = $alert[ $key ] ? get_term( (int) $alert[ $key ], $taxonomy ) : null;
		if ( $term instanceof WP_Term ) {
			$out[] = $term->name;
		}
	}
	if ( '' !== $alert['location'] ) {
		$out[] = $alert['location'];
	}
	if ( $alert['remote'] ) {
		$out[] = __( 'Remote', 'jobcore' );
	}
	return $out ? $out : array( __( 'All new jobs', 'jobcore' ) );
}

/**
 * Alert display name: its own, else its criteria.
 *
 * @param array $alert Alert.
 */
function wpjc_alert_name( array $alert ) {
	return '' !== $alert['name'] ? $alert['name'] : implode( ' · ', wpjc_alert_criteria( $alert ) );
}

/**
 * Open jobs matching an alert.
 *
 * @param array $alert Alert.
 * @param int   $after Only jobs published after this Unix time.
 * @param int   $limit Max jobs.
 * @return WP_Post[]
 */
function wpjc_alert_jobs( array $alert, $after = 0, $limit = 20 ) {
	$posts = wpjc_query_jobs(
		array(
			'keywords' => (string) $alert['keywords'],
			'location' => (string) $alert['location'],
			'remote'   => (bool) $alert['remote'],
			'category' => (int) $alert['category'],
			'type'     => (int) $alert['type'],
			'employer' => (int) $alert['employer'],
			'after'    => (int) $after,
			'per_page' => (int) $limit,
		)
	)->posts;
	return array_values(
		array_filter(
			$posts,
			static function ( $post ) {
				return ! get_post_meta( $post->ID, '_wpjc_sample', true );
			}
		)
	);
}

/*
 * A job went live: send "right away" alerts a minute later (in the background).
 */
add_action(
	'transition_post_status',
	static function ( $new, $old, $post ) {
		if ( 'publish' === $new && 'publish' !== $old && $post instanceof WP_Post && 'wpjc_job' === $post->post_type ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wpjc_alerts_instant', array( (int) $post->ID ) );
		}
	},
	10,
	3
);

add_action( 'wpjc_alerts_instant', 'wpjc_send_instant_alerts' );

/**
 * E-mail users whose "right away" alerts match a new job.
 *
 * @param int $job_id Job ID.
 */
function wpjc_send_instant_alerts( $job_id ) {
	$job = get_post( (int) $job_id );
	if ( ! $job || 'publish' !== $job->post_status || get_post_meta( $job->ID, '_wpjc_sample', true ) ) {
		return;
	}
	foreach ( wpjc_alert_users() as $user ) {
		$alerts  = wpjc_user_alerts( $user->ID );
		$matched = array();
		foreach ( $alerts as $id => $alert ) {
			if ( ! $alert['active'] || 'instant' !== $alert['frequency'] ) {
				continue;
			}
			$hits = wpjc_alert_jobs( $alert, (int) get_post_time( 'U', true, $job ) - 1, 50 );
			if ( in_array( $job->ID, wp_list_pluck( $hits, 'ID' ), true ) ) {
				$matched[]                  = $alert;
				$alerts[ $id ]['last_sent'] = time();
			}
		}
		if ( $matched ) {
			wpjc_alert_mail( $user, $matched[0], array( $job ) );
			wpjc_save_user_alerts( $user->ID, $alerts );
		}
	}
}

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'wpjc_alerts_digest' ) ) {
			wp_schedule_event( strtotime( 'tomorrow 07:00' ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', 'wpjc_alerts_digest' );
		}
	}
);

add_action( 'wpjc_alerts_digest', 'wpjc_send_digest_alerts' );

/**
 * Daily run: daily alerts every day, weekly alerts every 7 days.
 */
function wpjc_send_digest_alerts() {
	foreach ( wpjc_alert_users() as $user ) {
		$alerts  = wpjc_user_alerts( $user->ID );
		$changed = false;
		foreach ( $alerts as $id => $alert ) {
			if ( ! $alert['active'] || 'instant' === $alert['frequency'] ) {
				continue;
			}
			$since = (int) max( $alert['last_sent'], $alert['created'] );
			if ( 'weekly' === $alert['frequency'] && time() - $since < 7 * DAY_IN_SECONDS - HOUR_IN_SECONDS ) {
				continue;
			}
			$jobs = wpjc_alert_jobs( $alert, $since, 20 );
			if ( $jobs ) {
				wpjc_alert_mail( $user, $alert, $jobs );
			}
			$alerts[ $id ]['last_sent'] = time();
			$changed                    = true;
		}
		if ( $changed ) {
			wpjc_save_user_alerts( $user->ID, $alerts );
		}
	}
}

/**
 * Users with at least one alert.
 *
 * @return WP_User[]
 */
function wpjc_alert_users() {
	return get_users(
		array(
			'meta_key'     => 'wpjc_alerts', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_compare' => 'EXISTS',
			'number'       => -1,
		)
	);
}

/**
 * Send one alert e-mail.
 *
 * @param WP_User   $user  Recipient.
 * @param array     $alert Alert.
 * @param WP_Post[] $jobs  Jobs.
 */
function wpjc_alert_mail( WP_User $user, array $alert, array $jobs ) {
	$lines = array(
		/* translators: %s: user name */
		sprintf( __( 'Hello %s,', 'jobcore' ), $user->display_name ),
		'',
		/* translators: %s: alert name */
		sprintf( _n( 'There is a new job for your alert %s:', 'There are new jobs for your alert %s:', count( $jobs ), 'jobcore' ), wpjc_alert_name( $alert ) ),
		'',
	);
	foreach ( $jobs as $job ) {
		$company = wpjc_job_company( $job );
		$meta    = array_filter( array( $company['name'], wpjc_meta( $job, 'location' ) ) );
		$lines[] = '• ' . get_the_title( $job ) . ( $meta ? ' — ' . implode( ', ', $meta ) : '' );
		$lines[] = '  ' . get_permalink( $job );
	}
	$lines[] = '';
	$lines[] = '—';
	/* translators: %s: URL */
	$lines[] = sprintf( __( 'Change or stop your alerts: %s', 'jobcore' ), wpjc_account_url( 'alerts' ) );

	wp_mail(
		$user->user_email,
		/* translators: 1: board name, 2: alert name */
		sprintf( __( '[%1$s] New jobs: %2$s', 'jobcore' ), wpjc_brand()['name'], wpjc_alert_name( $alert ) ),
		implode( "\n", $lines )
	);
}

/**
 * The user's "follow company" alert for an employer.
 *
 * @param int $user_id User ID.
 * @param int $term_id Employer term ID.
 * @return string Alert ID, '' when not following.
 */
function wpjc_follow_alert_id( $user_id, $term_id ) {
	foreach ( wpjc_user_alerts( $user_id ) as $id => $alert ) {
		if ( (int) $alert['employer'] === (int) $term_id && '' === $alert['keywords'] && ! $alert['category'] && ! $alert['type'] && '' === $alert['location'] && ! $alert['remote'] ) {
			return (string) $id;
		}
	}
	return '';
}

/**
 * Follow / unfollow form for an employer page.
 *
 * @param int    $term_id Employer term ID.
 * @param string $class   Extra button class.
 */
function wpjc_follow_button( $term_id, $class = '' ) {
	$term_id = (int) $term_id;
	$on      = is_user_logged_in() && '' !== wpjc_follow_alert_id( get_current_user_id(), $term_id );
	?>
	<form class="wpjc-follow" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="wpjc_follow">
		<input type="hidden" name="employer" value="<?php echo esc_attr( (string) $term_id ); ?>">
		<?php wp_nonce_field( 'wpjc_follow_' . $term_id, 'wpjc_follow_nonce' ); ?>
		<button type="submit" class="wpjc-btn <?php echo $on ? 'wpjc-btn--ghost is-on' : ''; ?> <?php echo esc_attr( $class ); ?>" aria-pressed="<?php echo $on ? 'true' : 'false'; ?>">
			<?php echo wpjc_icon( $on ? 'check' : 'bell', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
			<span><?php echo $on ? esc_html__( 'Following', 'jobcore' ) : esc_html__( 'Follow company', 'jobcore' ); ?></span>
		</button>
	</form>
	<?php
}

/**
 * Toggle the "new jobs from this company" alert (guests sign in first).
 */
function wpjc_handle_follow() {
	$term_id = isset( $_POST['employer'] ) ? absint( $_POST['employer'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified below.
	$term    = $term_id ? get_term( $term_id, 'wpjc_employer' ) : null;
	if ( ! $term instanceof WP_Term ) {
		wp_safe_redirect( wpjc_home_url() );
		exit;
	}
	$back = get_term_link( $term );
	$back = is_wp_error( $back ) ? wpjc_home_url() : $back;
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wpjc_login_url( $back ) );
		exit;
	}
	$nonce = isset( $_POST['wpjc_follow_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['wpjc_follow_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wpjc_follow_' . $term_id ) ) {
		wp_safe_redirect( $back );
		exit;
	}

	$user_id = get_current_user_id();
	$current = wpjc_follow_alert_id( $user_id, $term_id );
	if ( '' !== $current ) {
		$alerts = wpjc_user_alerts( $user_id );
		unset( $alerts[ $current ] );
		wpjc_save_user_alerts( $user_id, $alerts );
		$state = 'off';
	} else {
		$added = wpjc_add_alert(
			$user_id,
			array(
				/* translators: %s: employer name */
				'name'      => sprintf( __( 'New jobs at %s', 'jobcore' ), $term->name ),
				'employer'  => $term_id,
				'frequency' => 'instant',
			)
		);
		$state = '' === $added ? 'limit' : 'on';
	}
	wp_safe_redirect( add_query_arg( 'wpjc_follow', $state, $back ) . '#wpjc-follow' );
	exit;
}
add_action( 'admin_post_wpjc_follow', 'wpjc_handle_follow' );
add_action( 'admin_post_nopriv_wpjc_follow', 'wpjc_handle_follow' );

/*
 * "Send me similar jobs" on the apply form (signed-in users): a daily alert for the job's category.
 */
add_action(
	'wpjc_application_submitted',
	static function ( $job, $data, $files, $ok ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce checked in wpjc_handle_apply().
		if ( ! $ok || ! is_user_logged_in() || empty( $_POST['wpjc_alert_similar'] ) ) {
			return;
		}
		$cats  = wp_get_post_terms( $job->ID, 'wpjc_job_category', array( 'fields' => 'ids' ) );
		$alert = array(
			'category'  => ( $cats && ! is_wp_error( $cats ) ) ? (int) $cats[0] : 0,
			'frequency' => 'daily',
		);
		if ( ! $alert['category'] ) {
			$alert['keywords'] = get_the_title( $job );
		}
		foreach ( wpjc_user_alerts( get_current_user_id() ) as $existing ) {
			if ( (int) $existing['category'] === $alert['category'] && '' === $existing['keywords'] && '' === $existing['location'] && ! $existing['type'] ) {
				return;
			}
		}
		wpjc_add_alert( get_current_user_id(), $alert );
	},
	20,
	4
);
