<?php
/**
 * Job listing meta fields.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Field definitions.
 *
 * @return array<string, array{label:string,type:string,help?:string}>
 */
function wpjc_meta_fields() {
	$fields = array(
		'company'         => array( 'label' => __( 'Company name', 'jobcore' ), 'type' => 'text' ),
		'company_website' => array( 'label' => __( 'Company website', 'jobcore' ), 'type' => 'url' ),
		'location'        => array( 'label' => __( 'Location', 'jobcore' ), 'type' => 'text' ),
		'remote'          => array( 'label' => __( 'Remote-friendly', 'jobcore' ), 'type' => 'checkbox' ),
		'apply_email'     => array( 'label' => __( 'Application e-mail', 'jobcore' ), 'type' => 'email', 'help' => __( 'Candidates can apply by e-mail when no external URL is set.', 'jobcore' ) ),
		'apply_url'       => array( 'label' => __( 'External apply URL', 'jobcore' ), 'type' => 'url' ),
		'expires'         => array( 'label' => __( 'Expires on', 'jobcore' ), 'type' => 'date' ),
		'tier'            => array( 'label' => __( 'Listing tier', 'jobcore' ), 'type' => 'select', 'help' => __( 'Premium Plus and Premium show in their own columns on the board home.', 'jobcore' ) ),
		'filled'          => array( 'label' => __( 'Position filled', 'jobcore' ), 'type' => 'checkbox' ),
	);
	return (array) apply_filters( 'wpjc_meta_fields', $fields );
}

add_action(
	'init',
	static function () {
		foreach ( array_keys( wpjc_meta_fields() ) as $key ) {
			register_post_meta(
				'wpjc_job',
				'_wpjc_' . $key,
				array(
					'type'              => in_array( $key, array( 'remote', 'filled' ), true ) ? 'boolean' : 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function () {
						return current_user_can( 'edit_posts' );
					},
					'sanitize_callback' => static function ( $value ) use ( $key ) {
						return wpjc_sanitize_meta_value( $key, $value );
					},
				)
			);
		}
	}
);

/**
 * @param string $key   Field key.
 * @param mixed  $value Raw.
 * @return mixed
 */
function wpjc_sanitize_meta_value( $key, $value ) {
	switch ( $key ) {
		case 'remote':
		case 'filled':
			return (bool) $value;
		case 'tier':
			$value = sanitize_key( (string) $value );
			return isset( wpjc_tiers()[ $value ] ) ? $value : 'basic';
		case 'company_website':
		case 'apply_url':
			return esc_url_raw( (string) $value );
		case 'apply_email':
			return sanitize_email( (string) $value );
		case 'expires':
			$value = sanitize_text_field( (string) $value );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
		default:
			return sanitize_text_field( (string) $value );
	}
}

add_action(
	'add_meta_boxes',
	static function () {
		add_meta_box( 'wpjc_job_details', __( 'Job details', 'jobcore' ), 'wpjc_render_meta_box', 'wpjc_job', 'normal', 'high' );
	}
);

/**
 * @param WP_Post $post Post.
 */
function wpjc_render_meta_box( $post ) {
	wp_nonce_field( 'wpjc_save_meta', 'wpjc_meta_nonce' );
	echo '<table class="form-table"><tbody>';
	foreach ( wpjc_meta_fields() as $key => $field ) {
		$id    = 'wpjc_' . $key;
		$value = get_post_meta( $post->ID, '_wpjc_' . $key, true );
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label></th><td>';
		if ( 'checkbox' === $field['type'] ) {
			printf(
				'<label><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> %3$s</label>',
				esc_attr( $id ),
				checked( ! empty( $value ), true, false ),
				esc_html( $field['label'] )
			);
		} elseif ( 'select' === $field['type'] ) {
			$current = isset( wpjc_tiers()[ $value ] ) ? $value : 'basic';
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '">';
			foreach ( wpjc_tiers() as $tier => $def ) {
				echo '<option value="' . esc_attr( $tier ) . '"' . selected( $current, $tier, false ) . '>' . esc_html( $def['label'] ) . '</option>';
			}
			echo '</select>';
		} else {
			printf(
				'<input class="regular-text" type="%1$s" id="%2$s" name="%2$s" value="%3$s">',
				esc_attr( $field['type'] ),
				esc_attr( $id ),
				esc_attr( (string) $value )
			);
		}
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

add_action(
	'save_post_wpjc_job',
	static function ( $post_id ) {
		if ( ! isset( $_POST['wpjc_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpjc_meta_nonce'] ) ), 'wpjc_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		foreach ( wpjc_meta_fields() as $key => $field ) {
			$name = 'wpjc_' . $key;
			if ( 'checkbox' === $field['type'] ) {
				$val = isset( $_POST[ $name ] ) ? 1 : 0;
			} else {
				$val = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}
			update_post_meta( $post_id, '_wpjc_' . $key, wpjc_sanitize_meta_value( $key, $val ) );
		}
	}
);
