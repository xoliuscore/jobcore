<?php
/**
 * Settings API.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_init',
	static function () {
		register_setting(
			'wpjc_settings_group',
			'wpjc_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => 'wpjc_sanitize_settings',
				'default'           => wpjc_default_settings(),
			)
		);
	}
);

/**
 * @param mixed $in Raw.
 * @return array<string, mixed>
 */
function wpjc_sanitize_settings( $in ) {
	$in = is_array( $in ) ? wp_unslash( $in ) : array();
	// Each settings tab posts only its own fields; the rest keep their stored value.
	$out = array_intersect_key(
		wp_parse_args( (array) get_option( 'wpjc_settings', array() ), wpjc_default_settings() ),
		wpjc_default_settings()
	);

	foreach ( array( 'list_title', 'brand_name', 'brand_tag', 'contact_phone', 'search_hint', 'help_title', 'help_text' ) as $key ) {
		if ( isset( $in[ $key ] ) ) {
			$out[ $key ] = sanitize_text_field( (string) $in[ $key ] );
		}
	}
	if ( isset( $in['list_intro'] ) ) {
		$out['list_intro'] = sanitize_textarea_field( (string) $in['list_intro'] );
	}
	foreach ( array( 'brand_logo', 'brand_logo_light', 'hero_image', 'social_facebook', 'social_instagram', 'social_linkedin' ) as $key ) {
		if ( isset( $in[ $key ] ) ) {
			$out[ $key ] = esc_url_raw( trim( (string) $in[ $key ] ) );
		}
	}
	if ( isset( $in['header_style'] ) ) {
		$out['header_style'] = 'news' === $in['header_style'] ? 'news' : 'board';
	}
	$ranges = array(
		'per_column'  => array( 1, 30 ),
		'employers_n' => array( 0, 48 ),
		'per_page'    => array( 6, 96 ),
		'fe_days'        => array( 7, 120 ),
		'app_files_days' => array( 7, 365 ),
	);
	// The Board tab posts fe_days; an unticked box sends nothing.
	if ( isset( $in['fe_days'] ) ) {
		$out['fe_review'] = empty( $in['fe_review'] ) ? 0 : 1;
		$out['app_files'] = empty( $in['app_files'] ) ? 0 : 1;
	}
	// Logo size: 0 = follow the News. theme (heights) or auto (width).
	$logo = array(
		'logo_height'   => array( 16, 80 ),
		'logo_height_m' => array( 16, 60 ),
		'logo_width'    => array( 40, 400 ),
	);
	foreach ( $logo as $key => $range ) {
		if ( isset( $in[ $key ] ) ) {
			$value       = absint( $in[ $key ] );
			$out[ $key ] = $value ? min( $range[1], max( $range[0], $value ) ) : 0;
		}
	}
	foreach ( $ranges as $key => $range ) {
		if ( isset( $in[ $key ] ) ) {
			$out[ $key ] = min( $range[1], max( $range[0], absint( $in[ $key ] ) ) );
		}
	}
	if ( isset( $in['contact_email'] ) ) {
		$out['contact_email'] = sanitize_email( (string) $in['contact_email'] );
	}
	if ( isset( $in['country'] ) ) {
		$code           = strtoupper( sanitize_text_field( (string) $in['country'] ) );
		$out['country'] = preg_match( '/^[A-Z]{2}$/', $code ) ? $code : '';
		$code           = strtoupper( sanitize_text_field( (string) ( $in['currency'] ?? '' ) ) );
		$out['currency'] = preg_match( '/^[A-Z]{3}$/', $code ) ? $code : '';
		$out['feeds_on']    = empty( $in['feeds_on'] ) ? 0 : 1;
		$out['indexing_on'] = empty( $in['indexing_on'] ) ? 0 : 1;
	}
	if ( isset( $in['scheme'] ) ) {
		$out['scheme'] = in_array( $in['scheme'], array( 'light', 'dark', 'auto' ), true ) ? (string) $in['scheme'] : 'light';
	}
	if ( isset( $in['header_style'] ) ) {
		$out['scheme_switch'] = empty( $in['scheme_switch'] ) ? 0 : 1;
	}
	if ( isset( $in['login_page'] ) ) {
		$out['login_page'] = 'site' === $in['login_page'] ? 'site' : 'board';
	}
	if ( isset( $in['accounts'] ) ) {
		$out['accounts'] = in_array( $in['accounts'], array( 'off', 'soft', 'strict' ), true ) ? (string) $in['accounts'] : 'soft';
	}
	if ( isset( $in['layout'] ) ) {
		$out['layout'] = 'standalone' === $in['layout'] ? 'standalone' : 'theme';
	}
	if ( isset( $in['board_skin'] ) ) {
		$skin             = sanitize_key( (string) $in['board_skin'] );
		$out['board_skin'] = array_key_exists( $skin, wpjc_board_skins() ) ? $skin : 'default';
	}
	if ( isset( $in['demo_switcher'] ) ) {
		$mode                 = sanitize_key( (string) $in['demo_switcher'] );
		$out['demo_switcher'] = in_array( $mode, array( 'off', 'admins', 'everyone' ), true ) ? $mode : 'off';
	}
	if ( isset( $in['accent_pick'] ) ) {
		$pick         = (string) $in['accent_pick'];
		$in['accent'] = 'custom' === $pick ? (string) ( $in['accent_custom'] ?? '' ) : $pick;
	}
	if ( isset( $in['accent'] ) ) {
		$accent        = sanitize_hex_color( (string) $in['accent'] );
		$out['accent'] = $accent ? $accent : '';
	}
	if ( isset( $in['brand_tag_pick'] ) ) {
		$pick = (string) $in['brand_tag_pick'];
		$hex  = 'custom' === $pick ? sanitize_hex_color( (string) ( $in['brand_tag_custom'] ?? '' ) ) : '';
		$out['brand_tag_color'] = $hex ? $hex : '';
	}
	return $out;
}
