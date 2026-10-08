<?php
/**
 * Add-ons & licence.
 *
 * JobCore itself is free and complete; it never locks anything. This screen lists the paid
 * add-ons, links to the Xolius shop to buy them, and stores the licence key that the add-ons
 * use for their own automatic updates. Nothing is sent anywhere until an administrator registers
 * a key (see "External services" in readme.txt).
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

const WPJC_LICENCE_PAGE       = 'wpjc-addons';
const WPJC_LICENCE_PUBLIC_KEY = 'e9fb7a34d76301a7e08f76bc92e46b10154f0592fde25699138972ce43c7b8d2';

/** Admin URL of Jobs → Add-ons. */
function wpjc_licence_url() {
	return admin_url( 'edit.php?post_type=wpjc_job&page=' . WPJC_LICENCE_PAGE );
}

/** The Xolius shop (add-ons, accounts, licence keys). */
function wpjc_shop_url() {
	$url = defined( 'WPJC_SHOP_URL' ) ? WPJC_SHOP_URL : 'https://xolius.com';
	return untrailingslashit( (string) apply_filters( 'wpjc_shop_url', $url ) );
}

/** Licence server base URL (WPJC_LICENCE_API, else NEWS_LICENCE_API, else the shop). */
function wpjc_licence_api() {
	if ( defined( 'WPJC_LICENCE_API' ) ) {
		$url = WPJC_LICENCE_API;
	} elseif ( defined( 'NEWS_LICENCE_API' ) ) {
		$url = NEWS_LICENCE_API;
	} elseif ( function_exists( 'news_licence_api' ) ) {
		$url = news_licence_api();
	} else {
		$url = wpjc_shop_url() . '/licence';
	}
	return untrailingslashit( (string) apply_filters( 'wpjc_licence_api', $url ) );
}

/** Stored licence (own registration). */
function wpjc_licence_get() {
	return wp_parse_args(
		(array) get_option( 'wpjc_licence', array() ),
		array(
			'token'      => '',
			'code_hint'  => '',
			'licence'    => array(),
			'product'    => '',
			'slugs'      => array(),
			'status'     => '',
			'checked_at' => 0,
			'error'      => '',
		)
	);
}

/**
 * Domain of a URL (lowercase, no www.).
 *
 * @param string $url URL (default: this site).
 */
function wpjc_licence_domain( $url = '' ) {
	if ( function_exists( 'news_licence_domain' ) ) {
		return news_licence_domain( $url );
	}
	$host = strtolower( (string) wp_parse_url( $url ? $url : home_url(), PHP_URL_HOST ) );
	return preg_replace( '/^www\./', '', $host );
}

/**
 * Verify a token's signature and that it belongs to this site.
 *
 * @param string $token Token from the licence server.
 * @return array|null Payload.
 */
function wpjc_licence_verify( $token ) {
	if ( function_exists( 'news_licence_verify' ) ) {
		return news_licence_verify( $token );
	}
	$parts = explode( '.', (string) $token );
	if ( 2 !== count( $parts ) || ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return null;
	}
	try {
		$ok      = sodium_crypto_sign_verify_detached( sodium_base642bin( $parts[1], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING ), $parts[0], sodium_hex2bin( WPJC_LICENCE_PUBLIC_KEY ) );
		$payload = $ok ? json_decode( sodium_base642bin( $parts[0], SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING ), true ) : null;
	} catch ( Throwable $e ) {
		return null;
	}
	return ( is_array( $payload ) && ( $payload['domain'] ?? '' ) === wpjc_licence_domain() ) ? $payload : null;
}

/**
 * Token for add-on updates: own key first, otherwise an All access key registered under News.
 */
function wpjc_licence_token() {
	$own = wpjc_licence_get();
	if ( $own['token'] && 'revoked' !== $own['status'] && null !== wpjc_licence_verify( $own['token'] ) ) {
		return $own['token'];
	}
	if ( function_exists( 'news_licence_get' ) && function_exists( 'news_licence_active' ) && news_licence_active() ) {
		$token   = (string) news_licence_get()['token'];
		$payload = wpjc_licence_verify( $token );
		if ( $payload && is_array( $payload['slugs'] ?? null ) && array_filter( $payload['slugs'], static fn( $s ) => 0 === strpos( (string) $s, 'wp-job-core-' ) ) ) {
			return $token;
		}
	}
	return '';
}

/** own | news | '' */
function wpjc_licence_source() {
	$token = wpjc_licence_token();
	if ( '' === $token ) {
		return '';
	}
	return $token === wpjc_licence_get()['token'] ? 'own' : 'news';
}

/**
 * Whether the key may update an add-on. Only slugs the licence server signed count.
 *
 * @param string $slug Add-on slug.
 */
function wpjc_licence_covers( $slug ) {
	$payload = wpjc_licence_verify( wpjc_licence_token() );
	if ( ! $payload || ! is_array( $payload['slugs'] ?? null ) ) {
		return false;
	}
	return in_array( $slug, $payload['slugs'], true );
}

/** Whether a valid key is registered on this site. */
function wpjc_licence_active() {
	return '' !== wpjc_licence_token();
}

/** Licence metadata in use (own key, or the News key on this site). */
function wpjc_licence_meta() {
	if ( 'news' === wpjc_licence_source() && function_exists( 'news_licence_get' ) ) {
		return (array) news_licence_get()['licence'];
	}
	return (array) wpjc_licence_get()['licence'];
}

/** Whether updates & support are still current for the key. */
function wpjc_licence_support() {
	if ( ! wpjc_licence_active() ) {
		return false;
	}
	if ( function_exists( 'news_licence_support' ) && 'news' === wpjc_licence_source() ) {
		return news_licence_support();
	}
	$until = (string) ( wpjc_licence_meta()['supported_until'] ?? '' );
	if ( '' === $until ) {
		return true;
	}
	$t = strtotime( $until );
	return false !== $t && $t >= time();
}

/**
 * Call the licence server. Only used when an administrator registers or removes a key,
 * and by installed paid add-ons for their updates.
 *
 * @param string $endpoint e.g. 'activate'.
 * @param array  $body     JSON body.
 * @return array|WP_Error
 */
function wpjc_licence_request( $endpoint, array $body ) {
	$res = wp_remote_post(
		wpjc_licence_api() . '/v1/' . $endpoint,
		array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'wpjc_licence_bad_response', __( 'The licence server sent an unexpected answer.', 'jobcore' ) );
	}
	$data['_status'] = (int) wp_remote_retrieve_response_code( $res );
	return $data;
}

/**
 * Friendly message for a server error code.
 *
 * @param string $code  Error code.
 * @param string $extra Domain for "in use".
 */
function wpjc_licence_error_text( $code, $extra = '' ) {
	$map = array(
		'invalid_code'  => __( 'This licence key is not valid. Check it for typos — it looks like xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.', 'jobcore' ),
		/* translators: %s: domain */
		'in_use'        => sprintf( __( 'This key is already active on %s. Deactivate it there first, or upgrade to a plan with more sites. Local and staging sites are free.', 'jobcore' ), $extra ? $extra : __( 'another site', 'jobcore' ) ),
		'revoked'       => __( 'This key was refunded or revoked.', 'jobcore' ),
		'rate_limited'  => __( 'Too many attempts. Please try again in a few minutes.', 'jobcore' ),
		'bad_site'      => __( 'The site address could not be read.', 'jobcore' ),
		'support_ended' => __( 'Updates for this key have ended. The add-ons keep working; renew to get updates again.', 'jobcore' ),
	);
	return $map[ $code ] ?? __( 'The licence server could not be reached. Your site keeps working; try again later.', 'jobcore' );
}

/**
 * The paid add-ons: slug => [ name, description, dashicon, constant with the plugin file, shop product slug ].
 *
 * @return array<string, array{0:string,1:string,2:string,3:string,4:string}>
 */
function wpjc_addons() {
	return (array) apply_filters(
		'wpjc_addons',
		array(
			'wp-job-core-paid-listings' => array( __( 'Paid Listings', 'jobcore' ), __( 'Sell job packages with WooCommerce: single ads, bundles and featured listings, paid before the job goes live.', 'jobcore' ), 'cart', 'WPJCPL_FILE', 'paid-listings' ),
			'wp-job-core-ads'           => array( __( 'Promoted Ads', 'jobcore' ), __( 'Paid ad slots on the board for employers who want to stand out.', 'jobcore' ), 'megaphone', 'WPJCA_FILE', 'promoted-ads' ),
			'wp-job-core-fields'        => array( __( 'Field Editor', 'jobcore' ), __( 'Add your own fields to the job form and the application form.', 'jobcore' ), 'forms', 'WPJCF_FILE', 'field-editor' ),
			'wp-job-core-looks'         => array( __( 'Looks', 'jobcore' ), __( 'Premium board designs, starting with Marketplace: navy header, white search panel, bold cards.', 'jobcore' ), 'art', 'WPJCL_FILE', 'looks' ),
			'wp-job-core-widget'        => array( __( 'Job Widget', 'jobcore' ), __( 'Show your latest jobs on any other website with a copy-and-paste embed.', 'jobcore' ), 'embed-generic', 'WPJCW_FILE', 'job-widget' ),
			'wp-job-core-resumes'       => array( __( 'Resumes', 'jobcore' ), __( 'Candidates keep a resume; employers search it and ask for contact details. Free, employer-only or paid access.', 'jobcore' ), 'id-alt', 'WPJCR_FILE', 'resumes' ),
			'wp-job-core-admanager'     => array( __( 'Ad Manager', 'jobcore' ), __( 'Banner slots on the board, job and employer pages: your own campaigns with stats, your ad code or Google ads, plus wallpaper takeovers.', 'jobcore' ), 'chart-bar', 'WPJCM_FILE', 'ad-manager' ),
		)
	);
}

/**
 * Shop link that puts a product in the cart and brings the key back to this screen after payment.
 *
 * @param string $product Shop product slug or bundle id.
 */
function wpjc_shop_buy_url( $product ) {
	return add_query_arg(
		array(
			'apo-get'    => rawurlencode( $product ),
			'apo-return' => rawurlencode( wpjc_licence_url() ),
		),
		wpjc_shop_url() . '/'
	);
}

/**
 * Shop page of a product.
 *
 * @param string $product Shop product slug.
 */
function wpjc_shop_product_url( $product ) {
	return wpjc_shop_url() . '/product/' . rawurlencode( $product ) . '/';
}

/** Is at least one paid add-on active? */
function wpjc_addon_active() {
	foreach ( wpjc_addons() as $a ) {
		if ( defined( $a[3] ) ) {
			return true;
		}
	}
	return false;
}

/* ---------------------------------------------------------------- Admin actions */

add_action(
	'admin_post_wpjc_licence',
	static function () {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'wpjc_licence' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'jobcore' ) );
		}
		$do    = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$state = wpjc_licence_get();

		if ( 'activate' === $do ) {
			$code = isset( $_POST['code'] ) ? strtolower( trim( sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) ) : '';
			$res  = wpjc_licence_request(
				'activate',
				array(
					'code'     => $code,
					'site_url' => home_url(),
					'site_id'  => substr( hash( 'sha256', home_url() . wp_salt( 'auth' ) ), 0, 32 ),
					'product'  => 'jobs',
				)
			);
			if ( ! is_wp_error( $res ) && ! empty( $res['token'] ) && wpjc_licence_verify( $res['token'] ) ) {
				$state = array(
					'token'      => $res['token'],
					'code_hint'  => '••••-' . substr( $code, -4 ),
					'licence'    => (array) ( $res['licence'] ?? array() ),
					'product'    => sanitize_key( (string) ( $res['product'] ?? 'jobs' ) ),
					'slugs'      => array_values( array_map( 'sanitize_key', (array) ( $res['slugs'] ?? array() ) ) ),
					'status'     => 'active',
					'checked_at' => time(),
					'error'      => '',
				);
			} else {
				$state['error'] = wpjc_licence_error_text( is_wp_error( $res ) ? '' : (string) ( $res['error'] ?? '' ), is_wp_error( $res ) ? '' : (string) ( $res['domain'] ?? '' ) );
			}
		} elseif ( 'deactivate' === $do && $state['token'] ) {
			wpjc_licence_request( 'deactivate', array( 'token' => $state['token'] ) );
			$state = array();
		}
		update_option( 'wpjc_licence', $state, false );
		delete_site_transient( 'update_plugins' );
		wp_safe_redirect( wpjc_licence_url() );
		exit;
	}
);

/**
 * Ask the licence server for the key's current state: refunds and revocations, a new end date,
 * and add-ons bought later (they arrive in a fresh signed token). A failed request changes nothing.
 */
function wpjc_licence_refresh() {
	$state = wpjc_licence_get();
	if ( ! $state['token'] ) {
		return;
	}
	$res = wpjc_licence_request( 'check', array( 'token' => $state['token'] ) );
	if ( is_wp_error( $res ) || ! isset( $res['valid'] ) ) {
		return;
	}
	$state['checked_at'] = time();
	$state['status']     = $res['valid'] ? 'active' : ( 'revoked' === ( $res['error'] ?? '' ) ? 'revoked' : 'inactive' );
	if ( ! empty( $res['token'] ) && wpjc_licence_verify( (string) $res['token'] ) ) {
		$state['token'] = (string) $res['token'];
	}
	if ( ! empty( $res['licence'] ) ) {
		$state['licence'] = (array) $res['licence'];
	}
	if ( ! empty( $res['product'] ) ) {
		$state['product'] = sanitize_key( (string) $res['product'] );
	}
	if ( ! empty( $res['slugs'] ) && is_array( $res['slugs'] ) ) {
		$state['slugs'] = array_values( array_map( 'sanitize_key', $res['slugs'] ) );
	}
	update_option( 'wpjc_licence', $state, false );
	delete_site_transient( 'update_plugins' );
}

/* Weekly check, like News. Core. */
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'wpjc_licence_check' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'wpjc_licence_check' );
		}
	}
);
add_action( 'wpjc_licence_check', 'wpjc_licence_refresh' );

/* Opening Jobs → Add-ons right after buying another add-on shows it straight away (at most every 10 minutes). */
add_action(
	'load-wpjc_job_page_' . WPJC_LICENCE_PAGE,
	static function () {
		if ( current_user_can( 'manage_options' ) && wpjc_licence_get()['checked_at'] < time() - 10 * MINUTE_IN_SECONDS ) {
			wpjc_licence_refresh();
		}
	}
);

/* ---------------------------------------------------------------- Admin screen */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=wpjc_job',
			__( 'Add-ons', 'jobcore' ),
			__( 'Add-ons', 'jobcore' ),
			'manage_options',
			WPJC_LICENCE_PAGE,
			'wpjc_licence_screen'
		);
	},
	22
);

/* The screen used to be called "Licence". */
add_action(
	'admin_init',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- redirect only.
		if ( isset( $_GET['page'] ) && 'wpjc-licence' === $_GET['page'] ) {
			wp_safe_redirect( wpjc_licence_url() );
			exit;
		}
	}
);

add_filter(
	'wpjc_admin_nav',
	static function ( $nav ) {
		$group = __( 'System', 'jobcore' );
		if ( isset( $nav[ $group ] ) ) {
			$nav[ $group ]['licence'] = array( __( 'Add-ons', 'jobcore' ), 'admin-plugins', __( 'Paid add-ons for your board and the licence key for their updates.', 'jobcore' ), wpjc_licence_url() );
		}
		return $nav;
	}
);

add_filter(
	'wpjc_is_admin_screen',
	static function ( $ours, $screen ) {
		return $ours || 'wpjc_job_page_' . WPJC_LICENCE_PAGE === $screen->id;
	},
	10,
	2
);

add_filter(
	'wpjc_admin_checklist',
	static function ( $items ) {
		if ( wpjc_addon_active() ) {
			$items[] = array(
				'label' => __( 'Register your licence key (add-on updates)', 'jobcore' ),
				'done'  => wpjc_licence_active(),
				'url'   => wpjc_licence_url(),
			);
		}
		return $items;
	}
);

/** Jobs → Add-ons. */
function wpjc_licence_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$state  = wpjc_licence_get();
	$active = wpjc_licence_active();
	$news   = 'news' === wpjc_licence_source();
	$l      = wpjc_licence_meta();
	$hint   = $news && function_exists( 'news_licence_get' ) ? news_licence_get()['code_hint'] : $state['code_hint'];

	wpjc_admin_shell_open( 'licence', false );
	?>
	<div class="wpjc-addons">
		<?php if ( $state['error'] ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $state['error'] ); ?></p></div>
		<?php endif; ?>

		<section class="wpjc-addons__intro">
			<h2><?php esc_html_e( 'Earn more from your board', 'jobcore' ); ?></h2>
			<p><?php esc_html_e( 'JobCore is free and complete. Add-ons add ways to earn from your board and new looks. Buy them in our shop: after payment you come straight back here with your key filled in.', 'jobcore' ); ?></p>
			<p>
				<a class="button button-primary" href="<?php echo esc_url( wpjc_shop_buy_url( 'jobs' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get all add-ons — Jobs stack', 'jobcore' ); ?></a>
				<a class="button" href="<?php echo esc_url( wpjc_shop_url() . '/add-ons/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Compare in the shop', 'jobcore' ); ?></a>
			</p>
		</section>

		<ul class="wpjc-addons__grid">
			<?php
			foreach ( wpjc_addons() as $slug => $a ) :
				$installed = defined( $a[3] );
				$covered   = $active && wpjc_licence_covers( $slug );
				?>
				<li class="wpjc-addon<?php echo $installed ? ' is-installed' : ''; ?>">
					<span class="wpjc-addon__icon dashicons dashicons-<?php echo esc_attr( $a[2] ); ?>" aria-hidden="true"></span>
					<h3><?php echo esc_html( $a[0] ); ?></h3>
					<p><?php echo esc_html( $a[1] ); ?></p>
					<p class="wpjc-addon__state">
						<?php if ( $installed ) : ?>
							<span class="wpjc-addon__badge wpjc-addon__badge--on"><?php esc_html_e( 'Active', 'jobcore' ); ?></span>
							<?php if ( $covered ) : ?>
								<span class="wpjc-addon__badge"><?php esc_html_e( 'Updates on', 'jobcore' ); ?></span>
							<?php endif; ?>
						<?php else : ?>
							<a class="button button-primary" href="<?php echo esc_url( wpjc_shop_buy_url( $a[4] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get it', 'jobcore' ); ?></a>
							<a class="button-link" href="<?php echo esc_url( wpjc_shop_product_url( $a[4] ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'jobcore' ); ?></a>
						<?php endif; ?>
					</p>
				</li>
			<?php endforeach; ?>
		</ul>

		<section class="wpjc-addons__key" id="licence">
			<h2><?php esc_html_e( 'Licence key', 'jobcore' ); ?></h2>
			<?php if ( $active ) : ?>
				<?php if ( wpjc_licence_support() ) : ?>
					<p><span class="dashicons dashicons-yes-alt" style="color:#00a32a"></span> <strong><?php esc_html_e( 'Registered', 'jobcore' ); ?></strong> — <?php esc_html_e( 'automatic updates are on for the add-ons your key includes.', 'jobcore' ); ?></p>
				<?php else : ?>
					<div class="notice notice-warning inline"><p><?php esc_html_e( 'Updates for this key have ended. The add-ons keep working; renew in your shop account to get updates again.', 'jobcore' ); ?></p></div>
				<?php endif; ?>
				<?php if ( $news ) : ?>
					<p><?php esc_html_e( 'This site uses the All access key registered under News. It covers the Job add-ons too.', 'jobcore' ); ?></p>
				<?php endif; ?>
				<table class="form-table" role="presentation">
					<tr><th><?php esc_html_e( 'Licence key', 'jobcore' ); ?></th><td><code><?php echo esc_html( $hint ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Licence', 'jobcore' ); ?></th><td><?php echo esc_html( $l['type'] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Updates until', 'jobcore' ); ?></th><td><?php echo ! empty( $l['supported_until'] ) ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $l['supported_until'] ) ) ) : '—'; ?></td></tr>
					<tr><th><?php esc_html_e( 'Site', 'jobcore' ); ?></th><td><?php echo esc_html( wpjc_licence_domain() ); ?></td></tr>
				</table>
				<p>
					<a class="button" href="<?php echo esc_url( wpjc_shop_url() . '/my-account/licences/' ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Manage in your account', 'jobcore' ); ?></a>
				</p>
				<?php if ( ! $news ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="wpjc_licence"><input type="hidden" name="do" value="deactivate">
						<?php wp_nonce_field( 'wpjc_licence' ); ?>
						<p><button class="button"><?php esc_html_e( 'Deactivate on this site', 'jobcore' ); ?></button>
						<span class="description"><?php esc_html_e( 'Use this before moving to another domain.', 'jobcore' ); ?></span></p>
					</form>
				<?php endif; ?>
			<?php else : ?>
				<p><?php esc_html_e( 'Bought an add-on? Paste your key to switch on its automatic updates. The add-ons work without a key — the key is only for updates and support.', 'jobcore' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="wpjc_licence"><input type="hidden" name="do" value="activate">
					<?php wp_nonce_field( 'wpjc_licence' ); ?>
					<p><label for="wpjc-licence-code"><strong><?php esc_html_e( 'Licence key', 'jobcore' ); ?></strong></label><br>
					<input type="text" id="wpjc-licence-code" name="code" class="regular-text code" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" autocomplete="off" required pattern="[A-Fa-f0-9]{8}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{4}-[A-Fa-f0-9]{12}"></p>
					<p class="description"><?php esc_html_e( 'Your key is in the order e-mail and in your shop account under Licences. Local and staging sites are free.', 'jobcore' ); ?></p>
					<p><button class="button button-primary"><?php esc_html_e( 'Register', 'jobcore' ); ?></button></p>
				</form>
			<?php endif; ?>
			<p class="description" style="margin-top:20px"><?php esc_html_e( 'Privacy: registering sends only the licence key, your site address and an anonymous site ID to the Xolius licence server. Installed add-ons then check for their updates with that key. Nothing is sent before you register, and nothing about your visitors is sent ever.', 'jobcore' ); ?></p>
		</section>
	</div>
	<script>
	( function () {
		var m = window.location.hash.match( /jobcore-key=([0-9a-fA-F-]{36})/ );
		var field = document.getElementById( 'wpjc-licence-code' );
		if ( ! m || ! field ) {
			return;
		}
		field.value = m[1];
		field.focus();
		field.scrollIntoView( { block: 'center' } );
		if ( window.history.replaceState ) {
			window.history.replaceState( null, '', window.location.pathname + window.location.search );
		}
	}() );
	</script>
	<?php
	wpjc_admin_shell_close();
}

/* Reminder only when a paid add-on is installed without a key. */
add_action(
	'admin_notices',
	static function () {
		if ( ! wpjc_addon_active() || ! current_user_can( 'manage_options' ) || wpjc_licence_active() || get_user_meta( get_current_user_id(), 'wpjc_licence_notice_off', true ) ) {
			return;
		}
		if ( ! function_exists( 'wpjc_is_admin_screen' ) || ! wpjc_is_admin_screen() ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && 'wpjc_job_page_' . WPJC_LICENCE_PAGE === $screen->id ) {
			return;
		}
		printf(
			'<div class="notice notice-info is-dismissible wpjc-licence-notice"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'Register your licence key to get automatic updates for your JobCore add-ons.', 'jobcore' ),
			esc_url( wpjc_licence_url() . '#licence' ),
			esc_html__( 'Register now', 'jobcore' )
		);
		?>
		<script>
		document.addEventListener('click', function (ev) {
			if (!ev.target.closest('.wpjc-licence-notice .notice-dismiss')) {
				return;
			}
			var body = new FormData();
			body.append('action', 'wpjc_licence_notice_off');
			body.append('_wpnonce', <?php echo wp_json_encode( wp_create_nonce( 'wpjc_licence_notice' ) ); ?>);
			window.fetch(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, { method: 'POST', credentials: 'same-origin', body: body });
		});
		</script>
		<?php
	}
);

add_action(
	'wp_ajax_wpjc_licence_notice_off',
	static function () {
		check_ajax_referer( 'wpjc_licence_notice' );
		update_user_meta( get_current_user_id(), 'wpjc_licence_notice_off', 1 );
		wp_send_json_success();
	}
);
