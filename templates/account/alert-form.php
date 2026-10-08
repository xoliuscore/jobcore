<?php
/**
 * Jobs account: new / edit job alert.
 *
 * @package JobCore
 * @var string     $item   'new' or alert key.
 * @var array      $alerts Alerts keyed by ID.
 * @var array|null $notice Notice.
 */

defined( 'ABSPATH' ) || exit;

$wpjc_is_new = 'new' === $item;
if ( ! $wpjc_is_new && ! isset( $alerts[ $item ] ) ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'bell-off',
			'title'  => __( 'Job alert not found', 'jobcore' ),
			'text'   => __( 'It may have been deleted.', 'jobcore' ),
			'button' => array( __( 'Back to job alerts', 'jobcore' ), wpjc_account_url( 'alerts' ), 'arrow' ),
		)
	);
	return;
}
$wpjc_alert = $wpjc_is_new ? wpjc_alert_defaults() : $alerts[ $item ];
if ( $wpjc_is_new ) {
	// "Get these jobs by e-mail" from a search: start from that search.
	$wpjc_from = wpjc_search_filters();
	$wpjc_alert['keywords'] = $wpjc_from['keywords'];
	$wpjc_alert['category'] = $wpjc_from['category'];
	$wpjc_alert['type']     = $wpjc_from['type'];
	$wpjc_alert['location'] = '' !== $wpjc_from['location'] ? $wpjc_from['location'] : (string) ( $wpjc_from['places'][0] ?? '' );
	$wpjc_alert['remote']   = $wpjc_from['remote'];
}
$wpjc_title = wpjc_account_title( 'alerts', $item );

wpjc_template(
	'account/head',
	array(
		'title'  => $wpjc_title,
		'dek'    => __( 'Choose what you are looking for. Leave a field empty to match any value.', 'jobcore' ),
		'crumbs' => array( array( __( 'Job alerts', 'jobcore' ), wpjc_account_url( 'alerts' ) ) ),
		'notice' => $notice,
	)
);

$wpjc_dropdown = static function ( $taxonomy, $name, $selected, $none ) {
	return wp_dropdown_categories(
		array(
			'taxonomy'          => $taxonomy,
			'name'              => $name,
			'id'                => $name,
			'selected'          => (int) $selected,
			'hide_empty'        => 0,
			'hierarchical'      => 1,
			'show_option_none'  => $none,
			'option_none_value' => '0',
			'orderby'           => 'name',
			'echo'              => 0,
			'class'             => 'wpjc-input',
		)
	);
};
?>
<form class="wpjc-acc-card wpjc-acc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<?php wpjc_account_form_fields( 'alert_save' ); ?>
	<input type="hidden" name="alert_id" value="<?php echo esc_attr( $wpjc_is_new ? '' : $item ); ?>">

	<div class="wpjc-acc-fields">
		<p class="wpjc-acc-field wpjc-acc-field--wide">
			<label for="alert_keywords"><?php esc_html_e( 'Job title or keywords', 'jobcore' ); ?></label>
			<input class="wpjc-input" type="text" id="alert_keywords" name="alert_keywords" value="<?php echo esc_attr( $wpjc_alert['keywords'] ); ?>" placeholder="<?php echo esc_attr( (string) wpjc_opt( 'search_hint' ) ); ?>" maxlength="80">
		</p>
		<p class="wpjc-acc-field">
			<label for="alert_category"><?php esc_html_e( 'Category', 'jobcore' ); ?></label>
			<?php echo $wpjc_dropdown( 'wpjc_job_category', 'alert_category', $wpjc_alert['category'], __( 'Any category', 'jobcore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?>
		</p>
		<p class="wpjc-acc-field">
			<label for="alert_type"><?php esc_html_e( 'Job type', 'jobcore' ); ?></label>
			<?php echo $wpjc_dropdown( 'wpjc_job_type', 'alert_type', $wpjc_alert['type'], __( 'Any type', 'jobcore' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup. ?>
		</p>
		<p class="wpjc-acc-field">
			<label for="alert_location"><?php esc_html_e( 'City', 'jobcore' ); ?></label>
			<input class="wpjc-input" type="text" id="alert_location" name="alert_location" value="<?php echo esc_attr( $wpjc_alert['location'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Berlin', 'jobcore' ); ?>" maxlength="80">
		</p>
		<p class="wpjc-acc-field wpjc-acc-field--check">
			<label><input type="checkbox" name="alert_remote" value="1" <?php checked( (bool) $wpjc_alert['remote'] ); ?>> <?php esc_html_e( 'Remote jobs only', 'jobcore' ); ?></label>
		</p>
		<fieldset class="wpjc-acc-field wpjc-acc-field--wide">
			<legend><?php esc_html_e( 'Send me new jobs', 'jobcore' ); ?></legend>
			<div class="wpjc-acc-seg">
				<?php foreach ( wpjc_alert_frequencies() as $wpjc_key => $wpjc_label ) : ?>
					<label><input type="radio" name="alert_frequency" value="<?php echo esc_attr( $wpjc_key ); ?>" <?php checked( $wpjc_alert['frequency'], $wpjc_key ); ?>><span><?php echo esc_html( $wpjc_label ); ?></span></label>
				<?php endforeach; ?>
			</div>
		</fieldset>
		<p class="wpjc-acc-field wpjc-acc-field--wide">
			<label for="alert_name"><?php esc_html_e( 'Alert name', 'jobcore' ); ?> <span class="wpjc-acc-opt"><?php esc_html_e( '(optional)', 'jobcore' ); ?></span></label>
			<input class="wpjc-input" type="text" id="alert_name" name="alert_name" value="<?php echo esc_attr( $wpjc_alert['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Developer jobs in Berlin', 'jobcore' ); ?>" maxlength="80">
		</p>
	</div>

	<div class="wpjc-acc-form__foot">
		<a class="wpjc-btn wpjc-btn--ghost wpjc-btn--sm" href="<?php echo esc_url( wpjc_account_url( 'alerts' ) ); ?>"><?php esc_html_e( 'Cancel', 'jobcore' ); ?></a>
		<button class="wpjc-btn wpjc-btn--sm" type="submit"><?php echo wpjc_icon( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo $wpjc_is_new ? esc_html__( 'Create alert', 'jobcore' ) : esc_html__( 'Save alert', 'jobcore' ); ?></button>
	</div>
</form>
