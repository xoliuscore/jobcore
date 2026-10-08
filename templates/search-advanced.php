<?php
/**
 * Advanced search under the hero search: category, location, posted within, job type and remote.
 * The fields belong to the hero form through their form="" attribute.
 *
 * Override in a theme: wp-job-core/search-advanced.php.
 *
 * @package JobCore
 * @var string $form_id Hero search form ID.
 * @var array  $values  Current filters (wpjc_search_filters()).
 * @var bool   $open    Start expanded.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_facets = wpjc_search_facets();
$wpjc_panel  = $form_id . '-adv';
$wpjc_active = wpjc_filters_advanced_count( $values );

/*
 * Terms with open jobs (plus the current choice), as [ term_id => [ name, count ] ].
 */
$wpjc_choices = static function ( $taxonomy, array $counts, $current ) {
	$out   = array();
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'orderby'    => 'name',
		)
	);
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		$n = (int) ( $counts[ $term->term_id ] ?? 0 );
		if ( $n || (int) $current === $term->term_id ) {
			$out[ $term->term_id ] = array( wpjc_term_text( $term ), $n );
		}
	}
	return $out;
};
$wpjc_cats  = $wpjc_choices( 'wpjc_job_category', $wpjc_facets['categories'], $values['category'] );
$wpjc_types = $wpjc_choices( 'wpjc_job_type', $wpjc_facets['types'], $values['type'] );
$wpjc_locs  = $wpjc_facets['locations'];
if ( '' !== $values['location'] && ! isset( $wpjc_locs[ $values['location'] ] ) ) {
	$wpjc_locs[ $values['location'] ] = 0;
}
?>
<div class="wpjc-adv<?php echo $open ? ' is-open' : ''; ?>" data-wpjc-adv data-form="<?php echo esc_attr( $form_id ); ?>">
	<button type="button" class="wpjc-adv__toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $wpjc_panel ); ?>">
		<?php echo wpjc_icon( 'sliders', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
		<span><?php esc_html_e( 'Advanced search', 'jobcore' ); ?></span>
		<span class="wpjc-adv__badge" data-wpjc-adv-badge<?php echo $wpjc_active ? '' : ' hidden'; ?>><?php echo esc_html( number_format_i18n( $wpjc_active ) ); ?></span>
		<span class="wpjc-adv__chev"><?php echo wpjc_icon( 'chevron', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
	</button>

	<div class="wpjc-adv__panel" id="<?php echo esc_attr( $wpjc_panel ); ?>" role="group" aria-label="<?php esc_attr_e( 'Advanced search', 'jobcore' ); ?>"<?php echo $open ? '' : ' hidden'; ?>>
		<div class="wpjc-adv__grid">
			<p class="wpjc-adv__field">
				<label for="<?php echo esc_attr( $wpjc_panel ); ?>-cat"><?php echo wpjc_icon( 'tag', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Category', 'jobcore' ); ?></label>
				<span class="wpjc-adv__select">
					<select id="<?php echo esc_attr( $wpjc_panel ); ?>-cat" name="wpjc_cat" form="<?php echo esc_attr( $form_id ); ?>">
						<option value=""><?php esc_html_e( 'All categories', 'jobcore' ); ?></option>
						<?php foreach ( $wpjc_cats as $wpjc_id => $wpjc_c ) : ?>
							<option value="<?php echo esc_attr( (string) $wpjc_id ); ?>" <?php selected( (int) $values['category'], $wpjc_id ); ?>><?php echo esc_html( $wpjc_c[0] . ' (' . number_format_i18n( $wpjc_c[1] ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			</p>
			<p class="wpjc-adv__field">
				<label for="<?php echo esc_attr( $wpjc_panel ); ?>-loc"><?php echo wpjc_icon( 'pin', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Location', 'jobcore' ); ?></label>
				<span class="wpjc-adv__select">
					<select id="<?php echo esc_attr( $wpjc_panel ); ?>-loc" name="wpjc_loc" form="<?php echo esc_attr( $form_id ); ?>">
						<option value=""><?php esc_html_e( 'All locations', 'jobcore' ); ?></option>
						<?php foreach ( $wpjc_locs as $wpjc_place => $wpjc_n ) : ?>
							<option value="<?php echo esc_attr( (string) $wpjc_place ); ?>" <?php selected( $values['location'], (string) $wpjc_place ); ?>><?php echo esc_html( $wpjc_place . ' (' . number_format_i18n( $wpjc_n ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			</p>
			<p class="wpjc-adv__field">
				<label for="<?php echo esc_attr( $wpjc_panel ); ?>-days"><?php echo wpjc_icon( 'clock', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Posted', 'jobcore' ); ?></label>
				<span class="wpjc-adv__select">
					<select id="<?php echo esc_attr( $wpjc_panel ); ?>-days" name="wpjc_days" form="<?php echo esc_attr( $form_id ); ?>">
						<option value=""><?php esc_html_e( 'Any time', 'jobcore' ); ?></option>
						<?php foreach ( wpjc_posted_options() as $wpjc_days => $wpjc_label ) : ?>
							<option value="<?php echo esc_attr( (string) $wpjc_days ); ?>" <?php selected( (int) $values['days'], $wpjc_days ); ?>><?php echo esc_html( $wpjc_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</span>
			</p>
		</div>

		<div class="wpjc-adv__row">
			<?php if ( $wpjc_types ) : ?>
				<fieldset class="wpjc-adv__types">
					<legend><?php echo wpjc_icon( 'briefcase', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Job type', 'jobcore' ); ?></legend>
					<div class="wpjc-adv__seg">
						<label>
							<input type="radio" name="wpjc_type" value="" form="<?php echo esc_attr( $form_id ); ?>" <?php checked( 0, (int) $values['type'] ); ?>>
							<span><?php esc_html_e( 'All types', 'jobcore' ); ?></span>
						</label>
						<?php foreach ( $wpjc_types as $wpjc_id => $wpjc_t ) : ?>
							<label>
								<input type="radio" name="wpjc_type" value="<?php echo esc_attr( (string) $wpjc_id ); ?>" form="<?php echo esc_attr( $form_id ); ?>" <?php checked( (int) $values['type'], $wpjc_id ); ?>>
								<span><?php echo esc_html( $wpjc_t[0] ); ?> <small><?php echo esc_html( number_format_i18n( $wpjc_t[1] ) ); ?></small></span>
							</label>
						<?php endforeach; ?>
					</div>
				</fieldset>
			<?php endif; ?>
			<label class="wpjc-adv__switch">
				<input type="checkbox" role="switch" name="wpjc_remote" value="1" form="<?php echo esc_attr( $form_id ); ?>" <?php checked( $values['remote'] ); ?>>
				<span class="wpjc-adv__track" aria-hidden="true"></span>
				<span><?php esc_html_e( 'Remote possible', 'jobcore' ); ?> <small><?php echo esc_html( number_format_i18n( $wpjc_facets['remote'] ) ); ?></small></span>
			</label>
		</div>

		<div class="wpjc-adv__foot">
			<button type="button" class="wpjc-adv__clear" data-wpjc-adv-clear>
				<?php echo wpjc_icon( 'close', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Clear filters', 'jobcore' ); ?>
			</button>
			<button type="submit" class="wpjc-btn wpjc-adv__go" form="<?php echo esc_attr( $form_id ); ?>">
				<?php echo wpjc_icon( 'search', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<span data-wpjc-adv-label aria-live="polite"><?php esc_html_e( 'Show jobs', 'jobcore' ); ?></span>
			</button>
		</div>
	</div>
</div>
