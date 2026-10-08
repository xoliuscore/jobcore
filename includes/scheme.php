<?php
/**
 * Light and dark mode for the standalone board with the Jobs portal header.
 *
 * The board's default comes from JobCore → Look. Visitors can pick Light, Dark or Auto in the
 * profile menu; the choice is kept in a cookie (this browser) and, for signed-in people, in
 * their account so it follows them to other devices. With the News. site header the board
 * follows the theme's own dark mode instead.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the board handles light and dark itself on this request.
 *
 * @return bool
 */
function wpjc_scheme_on() {
	return wpjc_is_standalone() && 'board' === wpjc_header_style();
}

/**
 * Whether visitors get the Light / Dark / Auto switch.
 *
 * @return bool
 */
function wpjc_scheme_switch_on() {
	return wpjc_scheme_on() && (bool) wpjc_opt( 'scheme_switch' );
}

/**
 * The visitor's choice: light, dark or auto.
 *
 * @return string
 */
function wpjc_scheme_choice() {
	$ok = array( 'light', 'dark', 'auto' );
	if ( wpjc_scheme_switch_on() ) {
		$cookie = isset( $_COOKIE['wpjc_scheme'] ) ? sanitize_key( wp_unslash( $_COOKIE['wpjc_scheme'] ) ) : '';
		if ( in_array( $cookie, $ok, true ) ) {
			return $cookie;
		}
		if ( is_user_logged_in() ) {
			$meta = (string) get_user_meta( get_current_user_id(), 'wpjc_scheme', true );
			if ( in_array( $meta, $ok, true ) ) {
				return $meta;
			}
		}
	}
	$default = (string) wpjc_opt( 'scheme' );
	return in_array( $default, $ok, true ) ? $default : 'light';
}

// <html data-wpjc-scheme="light|dark" data-wpjc-scheme-pref="light|dark|auto">.
add_filter(
	'language_attributes',
	static function ( $output ) {
		if ( is_admin() || ! wpjc_scheme_on() ) {
			return $output;
		}
		$pref = wpjc_scheme_choice();
		return $output . ' data-wpjc-scheme="' . esc_attr( 'dark' === $pref ? 'dark' : 'light' ) . '" data-wpjc-scheme-pref="' . esc_attr( $pref ) . '"';
	}
);

// Auto: settle light or dark before the page paints, and follow the device when it changes.
add_action(
	'wp_head',
	static function () {
		if ( ! wpjc_scheme_on() ) {
			return;
		}
		echo '<meta name="color-scheme" content="' . ( 'light' === wpjc_scheme_choice() ? 'light' : 'light dark' ) . '">' . "\n";
		$js = "(function(){var h=document.documentElement,m=window.matchMedia&&matchMedia('(prefers-color-scheme: dark)');function s(){if(h.getAttribute('data-wpjc-scheme-pref')==='auto'){h.setAttribute('data-wpjc-scheme',m&&m.matches?'dark':'light');}}s();if(m&&m.addEventListener){m.addEventListener('change',s);}window.wpjcScheme=s;})();";
		wp_print_inline_script_tag( $js, array( 'id' => 'wpjc-scheme' ) );
	},
	1
);

// Save the choice to the account (the cookie is set in the browser).
add_action(
	'wp_ajax_wpjc_scheme',
	static function () {
		check_ajax_referer( 'wpjc_scheme', 'nonce' );
		$pref = isset( $_POST['scheme'] ) ? sanitize_key( wp_unslash( $_POST['scheme'] ) ) : '';
		if ( ! in_array( $pref, array( 'light', 'dark', 'auto' ), true ) ) {
			wp_send_json_error( null, 400 );
		}
		update_user_meta( get_current_user_id(), 'wpjc_scheme', $pref );
		wp_send_json_success();
	}
);

/**
 * The Light / Dark / Auto switch for the profile menu.
 *
 * @return string Safe HTML.
 */
function wpjc_scheme_switch_html() {
	if ( ! wpjc_scheme_switch_on() ) {
		return '';
	}
	$now  = wpjc_scheme_choice();
	$opts = array(
		'light' => array( __( 'Light', 'jobcore' ), 'sun' ),
		'dark'  => array( __( 'Dark', 'jobcore' ), 'moon' ),
		'auto'  => array( __( 'Auto', 'jobcore' ), 'monitor' ),
	);
	$data = is_user_logged_in() ? ' data-nonce="' . esc_attr( wp_create_nonce( 'wpjc_scheme' ) ) . '" data-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"' : '';
	$html = '<div class="wpjc-scheme" role="group" aria-label="' . esc_attr__( 'Colours', 'jobcore' ) . '"' . $data . '>';
	foreach ( $opts as $key => $o ) {
		$html .= sprintf(
			'<button type="button" class="wpjc-scheme__btn" data-wpjc-scheme-set="%1$s" aria-pressed="%2$s" title="%3$s">%4$s<span>%3$s</span></button>',
			esc_attr( $key ),
			$key === $now ? 'true' : 'false',
			esc_attr( $o[0] ),
			wpjc_icon( $o[1], 15 )
		);
	}
	return $html . '</div>';
}
