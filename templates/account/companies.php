<?php
/**
 * Jobs account: my companies.
 *
 * @package JobCore
 * @var WP_Term[]  $companies Companies.
 * @var array|null $notice    Notice.
 */

defined( 'ABSPATH' ) || exit;

wpjc_template(
	'account/head',
	array(
		'title'  => __( 'My companies', 'jobcore' ),
		'dek'    => __( 'Job ads are published under a company. Candidates see its name, logo and description with every ad.', 'jobcore' ),
		'action' => array( __( 'Add a company', 'jobcore' ), wpjc_account_url( 'companies', 'new' ), 'plus' ),
		'notice' => $notice,
	)
);

if ( ! $companies ) {
	wpjc_template(
		'account/empty',
		array(
			'icon'   => 'building',
			'title'  => __( 'No companies yet', 'jobcore' ),
			'text'   => __( 'Add your company first — then you can post job ads for it.', 'jobcore' ),
			'button' => array( __( 'Add a company', 'jobcore' ), wpjc_account_url( 'companies', 'new' ), 'plus' ),
		)
	);
	return;
}
?>
<div class="wpjc-acc-card">
	<ul class="wpjc-acc-list">
		<?php
		foreach ( $companies as $wpjc_term ) :
			$wpjc_active = wpjc_company_is_active( $wpjc_term->term_id );
			$wpjc_link   = get_term_link( $wpjc_term );
			$wpjc_city   = wpjc_company_meta( $wpjc_term->term_id, 'city' );
			?>
			<li class="wpjc-acc-row<?php echo $wpjc_active ? '' : ' is-off'; ?>">
				<?php
				echo wpjc_logo_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
					array(
						'name' => $wpjc_term->name,
						'logo' => wpjc_company_meta( $wpjc_term->term_id, 'logo' ),
					),
					'wpjc-acc-row__logo'
				);
				?>
				<div class="wpjc-acc-row__body">
					<p class="wpjc-acc-row__title"><a href="<?php echo esc_url( wpjc_account_url( 'companies', $wpjc_term->term_id ) ); ?>"><?php echo esc_html( $wpjc_term->name ); ?></a></p>
					<p class="wpjc-acc-row__meta">
						<?php if ( $wpjc_city ) : ?>
							<span><?php echo wpjc_icon( 'pin', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $wpjc_city ); ?></span>
						<?php endif; ?>
						<span>
							<?php
							/* translators: %s: number of open jobs */
							echo esc_html( sprintf( _n( '%s open job', '%s open jobs', (int) $wpjc_term->count, 'jobcore' ), number_format_i18n( (int) $wpjc_term->count ) ) );
							?>
						</span>
					</p>
				</div>
				<span class="wpjc-acc-badge wpjc-acc-badge--<?php echo $wpjc_active ? 'live' : 'closed'; ?>"><?php echo $wpjc_active ? esc_html__( 'Active', 'jobcore' ) : esc_html__( 'Inactive', 'jobcore' ); ?></span>
				<div class="wpjc-acc-row__actions">
					<?php if ( $wpjc_active && ! is_wp_error( $wpjc_link ) && $wpjc_term->count ) : ?>
						<a class="wpjc-acc-iconbtn" href="<?php echo esc_url( $wpjc_link ); ?>" title="<?php esc_attr_e( 'View company page', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'View company page', 'jobcore' ); ?>"><?php echo wpjc_icon( 'eye', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endif; ?>
					<a class="wpjc-acc-iconbtn" href="<?php echo esc_url( wpjc_account_url( 'companies', $wpjc_term->term_id ) ); ?>" title="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>" aria-label="<?php esc_attr_e( 'Edit', 'jobcore' ); ?>"><?php echo wpjc_icon( 'edit', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
				</div>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
