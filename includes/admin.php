<?php
/**
 * Settings screen (tabs inside the admin shell), plus sample content.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'edit.php?post_type=wpjc_job',
			__( 'JobCore', 'jobcore' ),
			__( 'Settings', 'jobcore' ),
			'manage_options',
			WPJC_SETTINGS,
			'wpjc_render_settings_page'
		);
	}
);

/**
 * One settings row.
 *
 * @param string $key   Setting key.
 * @param string $label Label.
 * @param string $type  text|url|email|number|textarea|color|media (image URL with a Media Library picker).
 * @param string $help  Help text.
 * @param array  $opts  Current settings.
 * @param array  $attrs Extra input attributes (placeholder, min, max).
 */
function wpjc_admin_field( $key, $label, $type, $help, array $opts, array $attrs = array() ) {
	$id    = 'wpjc_' . $key;
	$name  = 'wpjc_settings[' . $key . ']';
	$value = (string) ( $opts[ $key ] ?? '' );
	if ( 'number' === $type && '0' === $value && isset( $attrs['placeholder'] ) ) {
		$value = '';
	}
	echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
	if ( 'textarea' === $type ) {
		printf( '<textarea class="large-text" rows="3" id="%1$s" name="%2$s">%3$s</textarea>', esc_attr( $id ), esc_attr( $name ), esc_textarea( $value ) );
	} elseif ( 'media' === $type ) {
		?>
		<div class="wpjc-media<?php echo '' !== $value ? ' has-image' : ''; ?>" data-wpjc-media>
			<button type="button" class="wpjc-media__preview" data-wpjc-media-select aria-label="<?php esc_attr_e( 'Select from Media Library', 'jobcore' ); ?>">
				<img src="<?php echo esc_url( $value ); ?>" alt=""<?php echo '' !== $value ? '' : ' hidden'; ?>>
				<span class="dashicons dashicons-format-image" aria-hidden="true"></span>
			</button>
			<div class="wpjc-media__side">
				<p class="wpjc-media__actions">
					<button type="button" class="button" data-wpjc-media-select><?php esc_html_e( 'Select from Media Library', 'jobcore' ); ?></button>
					<button type="button" class="button-link button-link-delete" data-wpjc-media-remove><?php esc_html_e( 'Remove', 'jobcore' ); ?></button>
				</p>
				<input class="regular-text" type="url" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php esc_attr_e( 'or paste an image URL', 'jobcore' ); ?>" data-wpjc-media-url>
			</div>
		</div>
		<?php
	} else {
		$extra = '';
		$attrs = wp_parse_args( $attrs, 'number' === $type ? array( 'min' => 0, 'step' => 1 ) : array() );
		foreach ( $attrs as $attr => $attr_value ) {
			$extra .= ' ' . esc_attr( $attr ) . '="' . esc_attr( (string) $attr_value ) . '"';
		}
		printf(
			'<input class="%1$s" type="%2$s" id="%3$s" name="%4$s" value="%5$s"%6$s>',
			esc_attr( 'number' === $type ? 'small-text' : 'regular-text' ),
			esc_attr( $type ),
			esc_attr( $id ),
			esc_attr( $name ),
			esc_attr( $value ),
			$extra // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
		);
	}
	if ( $help ) {
		echo '<p class="description">' . esc_html( $help ) . '</p>';
	}
	echo '</td></tr>';
}

/**
 * Settings screen.
 */
function wpjc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- navigation and display-only flags.
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'look';
	$seeded = isset( $_GET['wpjc_seeded'] ) ? sanitize_key( wp_unslash( $_GET['wpjc_seeded'] ) ) : '';
	// phpcs:enable
	$tab     = isset( wpjc_settings_tabs()[ $tab ] ) ? $tab : 'look';
	$is_form = in_array( $tab, array( 'look', 'board', 'contact', 'feeds' ), true );
	$opts    = wp_parse_args( (array) get_option( 'wpjc_settings', array() ), wpjc_default_settings() );

	wpjc_admin_shell_open( $tab, $is_form );
	settings_errors();

	if ( 'sample' === $tab ) {
		wpjc_render_sample_tab( $seeded );
		wpjc_admin_shell_close();
		return;
	}
	if ( 'status' === $tab ) {
		wpjc_render_status_tab();
		wpjc_admin_shell_close();
		return;
	}
	?>
	<form method="post" action="options.php" id="wpjc-opt-form">
		<?php settings_fields( 'wpjc_settings_group' ); ?>
		<table class="form-table" role="presentation">
			<?php
			if ( 'look' === $tab ) {
				wpjc_render_look_tab( $opts );
			} elseif ( 'board' === $tab ) {
				wpjc_admin_field( 'list_title', __( 'Hero title', 'jobcore' ), 'text', '', $opts );
				wpjc_admin_field( 'list_intro', __( 'Hero intro', 'jobcore' ), 'textarea', '', $opts );
				wpjc_admin_field( 'search_hint', __( 'Search placeholder', 'jobcore' ), 'text', '', $opts );
				wpjc_admin_field( 'per_column', __( 'Jobs per tier column', 'jobcore' ), 'number', __( 'Premium Plus, Premium and Basic columns on the board home.', 'jobcore' ), $opts );
				wpjc_admin_field( 'employers_n', __( 'Featured employers', 'jobcore' ), 'number', __( '0 hides the row.', 'jobcore' ), $opts );
				wpjc_admin_field( 'per_page', __( 'Jobs per page', 'jobcore' ), 'number', __( 'All jobs and search results.', 'jobcore' ), $opts );
				?>
				<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Jobs account', 'jobcore' ); ?></h3></td></tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'New job ads', 'jobcore' ); ?></th>
					<td>
						<label><input type="checkbox" name="wpjc_settings[fe_review]" value="1" <?php checked( (int) $opts['fe_review'], 1 ); ?>> <?php esc_html_e( 'Review job ads posted in the jobs account before they go live', 'jobcore' ); ?></label>
						<p class="description">
							<?php
							/* translators: %s: link to the jobs account */
							printf( esc_html__( 'Signed-in users post jobs and add companies in the %s. You get an e-mail for each ad waiting for review.', 'jobcore' ), '<a href="' . esc_url( wpjc_account_url() ) . '" target="_blank">' . esc_html__( 'jobs account', 'jobcore' ) . '</a>' );
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Sign in and sign up', 'jobcore' ); ?></th>
					<td>
						<label style="display:block;margin:0 0 8px"><input type="radio" name="wpjc_settings[login_page]" value="board" <?php checked( 'site' !== wpjc_opt( 'login_page' ) ); ?>> <strong><?php esc_html_e( 'On the job board, in its own look (recommended)', 'jobcore' ); ?></strong><span class="description" style="display:block;margin:2px 0 0 24px"><?php esc_html_e( 'Sign-up asks whether someone is looking for a job or hiring.', 'jobcore' ); ?></span></label>
						<label style="display:block"><input type="radio" name="wpjc_settings[login_page]" value="site" <?php checked( 'site' === wpjc_opt( 'login_page' ) ); ?>> <strong><?php echo esc_html( function_exists( 'news_core_account_url' ) ? __( 'The News. login page', 'jobcore' ) : __( 'The WordPress login page', 'jobcore' ) ); ?></strong></label>
						<p class="description">
							<?php
							echo esc_html(
								wpjc_signup_open()
									? __( 'New accounts are open.', 'jobcore' )
									: __( 'New accounts are closed: switch on “Anyone can register” under Settings → General (or the sign-up setting of the News. accounts).', 'jobcore' )
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Job seekers and employers', 'jobcore' ); ?></th>
					<td>
						<fieldset>
							<?php
							$wpjc_modes = array(
								'soft'   => array( __( 'Account type, easy to switch (recommended)', 'jobcore' ), __( 'Each account is for job seeking or for hiring; the menu shows only what fits. People who need both switch with one click.', 'jobcore' ) ),
								'strict' => array( __( 'Separate accounts', 'jobcore' ), __( 'Job seekers cannot add companies or post jobs; employers cannot use job seeker tools. Only you can change the type, in the user’s profile.', 'jobcore' ) ),
								'off'    => array( __( 'One account for everything', 'jobcore' ), __( 'Every account sees every screen.', 'jobcore' ) ),
							);
							foreach ( $wpjc_modes as $wpjc_key => $wpjc_mode ) :
								?>
								<label style="display:block;margin:0 0 10px"><input type="radio" name="wpjc_settings[accounts]" value="<?php echo esc_attr( $wpjc_key ); ?>" <?php checked( wpjc_accounts_mode(), $wpjc_key ); ?>> <strong><?php echo esc_html( $wpjc_mode[0] ); ?></strong><span class="description" style="display:block;margin:2px 0 0 24px"><?php echo esc_html( $wpjc_mode[1] ); ?></span></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'The type is set from where people sign up (e.g. “Post a job” makes an employer account), or they pick it the first time they open their jobs account.', 'jobcore' ); ?></p>
					</td>
				</tr>
				<?php
				wpjc_admin_field( 'fe_days', __( 'Max listing days', 'jobcore' ), 'number', __( 'Longest period a user can choose for a job ad (7–120).', 'jobcore' ), $opts, array( 'min' => 7, 'max' => 120 ) );
				?>
				<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Applications', 'jobcore' ); ?></h3></td></tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'CVs and photos', 'jobcore' ); ?></th>
					<td>
						<label><input type="checkbox" name="wpjc_settings[app_files]" value="1" <?php checked( (int) $opts['app_files'], 1 ); ?>> <?php esc_html_e( 'Keep application files on the site so employers can download them', 'jobcore' ); ?></label>
						<p class="description"><?php esc_html_e( 'Files are stored in a protected folder and only the employer of the job ad and site editors can download them. Every application is also listed under “Applications received” in the employer’s jobs account.', 'jobcore' ); ?></p>
					</td>
				</tr>
				<?php
				wpjc_admin_field( 'app_files_days', __( 'Delete files after', 'jobcore' ), 'number', __( 'Days (7–365). The application record stays; only the CV and photo are deleted.', 'jobcore' ), $opts, array( 'min' => 7, 'max' => 365 ) );
			} elseif ( 'feeds' === $tab ) {
				wpjc_render_feeds_tab( $opts );
			} else {
				wpjc_admin_field( 'contact_email', __( 'Contact e-mail', 'jobcore' ), 'email', __( 'Shown under the hero; receives the “waiting for review” e-mails (else the site admin e-mail).', 'jobcore' ), $opts );
				wpjc_admin_field( 'contact_phone', __( 'Contact phone', 'jobcore' ), 'text', '', $opts );
				wpjc_admin_field( 'help_title', __( 'Help box title', 'jobcore' ), 'text', __( 'The box with your e-mail and phone on employer pages. Empty = “Questions about an ad?”', 'jobcore' ), $opts, array( 'placeholder' => __( 'Questions about an ad?', 'jobcore' ) ) );
				wpjc_admin_field( 'help_text', __( 'Help box text', 'jobcore' ), 'text', __( 'Empty = “The {board} team is happy to help.” — {board} is replaced with the board name.', 'jobcore' ), $opts, array( 'placeholder' => __( 'The {board} team is happy to help.', 'jobcore' ) ) );
				wpjc_admin_field( 'social_facebook', __( 'Facebook', 'jobcore' ), 'url', '', $opts );
				wpjc_admin_field( 'social_instagram', __( 'Instagram', 'jobcore' ), 'url', '', $opts );
				wpjc_admin_field( 'social_linkedin', __( 'LinkedIn', 'jobcore' ), 'url', '', $opts );
			}
			?>
		</table>
		<div class="wpjc-opt__foot"><?php submit_button( __( 'Save Changes', 'jobcore' ), 'primary', 'submit', false ); ?></div>
	</form>
	<?php
	if ( 'feeds' === $tab ) {
		wpjc_render_indexing_box();
	}
	wpjc_admin_shell_close();
}

/**
 * How ready the open jobs are for Google's job search: [label, done, total, tip].
 *
 * @return array<int, array{0:string,1:int,2:int,3:string}>
 */
function wpjc_google_checks() {
	$jobs    = wpjc_query_jobs( array( 'per_page' => 300 ) )->posts;
	$total   = count( $jobs );
	$country = wpjc_board_country();
	$count   = array_fill_keys( array( 'place', 'company', 'logo', 'type', 'salary', 'expires', 'text' ), 0 );
	foreach ( $jobs as $job ) {
		$company = wpjc_job_company( $job );
		$place   = (string) wpjc_meta( $job, 'location' );
		$count['place']   += ( '' !== $place || wpjc_meta( $job, 'remote' ) ) ? 1 : 0;
		$count['company'] += '' !== $company['name'] ? 1 : 0;
		$count['logo']    += '' !== wpjc_logo_url( $company['logo'] ) ? 1 : 0;
		$count['type']    += '' !== wpjc_feed_jobtype( $job ) ? 1 : 0;
		$count['salary']  += wpjc_parse_salary( wpjc_job_salary_text( $job ) ) ? 1 : 0;
		$count['expires'] += '' !== (string) wpjc_meta( $job, 'expires' ) ? 1 : 0;
		$count['text']    += str_word_count( wp_strip_all_tags( $job->post_content ) ) >= 40 ? 1 : 0;
	}
	return array(
		array( __( 'Board country set', 'jobcore' ), '' !== (string) wpjc_opt( 'country' ) ? 1 : 0, 1, '' !== $country ? sprintf( /* translators: %s: country code */ __( 'Google needs the country of every job place. Now guessed from the site language: %s. Set it below.', 'jobcore' ), $country ) : __( 'Google needs the country of every job place. Set it below.', 'jobcore' ) ),
		array( __( 'Place or remote', 'jobcore' ), $count['place'], $total, __( 'Required. Add a city, or tick “Remote-friendly” for jobs without one.', 'jobcore' ) ),
		array( __( 'Employer name', 'jobcore' ), $count['company'], $total, __( 'Required. Link the job to a company.', 'jobcore' ) ),
		array( __( 'Full job description (40+ words)', 'jobcore' ), $count['text'], $total, __( 'Google asks for the full description of the job — tasks, requirements and what you offer.', 'jobcore' ) ),
		array( __( 'Closing date', 'jobcore' ), $count['expires'], $total, __( 'Recommended. Google removes the job on that day.', 'jobcore' ) ),
		array( __( 'Job type', 'jobcore' ), $count['type'], $total, __( 'Recommended (full time, part time…).', 'jobcore' ) ),
		array( __( 'Company logo', 'jobcore' ), $count['logo'], $total, __( 'Recommended. Shown next to the job in Google.', 'jobcore' ) ),
		array( __( 'Salary Google can read', 'jobcore' ), $count['salary'], $total, function_exists( 'wpjcf_field' ) ? __( 'Recommended. Write it like “€2,700 – €2,900 / month” in the Salary field.', 'jobcore' ) : __( 'Recommended. Add a “salary” field with the Field Editor add-on and write it like “€2,700 – €2,900 / month”.', 'jobcore' ) ),
	);
}

/**
 * Feeds & Google rows (inside the settings form).
 *
 * @param array $opts Current settings.
 */
function wpjc_render_feeds_tab( array $opts ) {
	$checks = wpjc_google_checks();
	$sample = wpjc_query_jobs( array( 'per_page' => 1 ) )->posts;
	$cats   = get_terms(
		array(
			'taxonomy' => 'wpjc_job_category',
			'number'   => 1,
			'orderby'  => 'count',
			'order'    => 'DESC',
		)
	);
	?>
	<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Google job search', 'jobcore' ); ?></h3></td></tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'How your jobs look to Google', 'jobcore' ); ?></th>
		<td>
			<p class="description" style="margin-top:0"><?php esc_html_e( 'Every job page tells Google its title, employer, place, dates and salary, so jobs can show in Google’s job search. Open jobs right now:', 'jobcore' ); ?></p>
			<table class="widefat striped" style="max-width:720px;margin-top:8px">
				<tbody>
					<?php foreach ( $checks as $c ) : ?>
						<?php $wpjc_full = $c[2] > 0 && $c[1] >= $c[2]; ?>
						<tr>
							<td style="width:24px"><span class="dashicons dashicons-<?php echo $wpjc_full ? 'yes-alt' : 'warning'; ?>" style="color:<?php echo $wpjc_full ? '#16a34a' : '#d97706'; ?>"></span></td>
							<td><strong><?php echo esc_html( $c[0] ); ?></strong><?php echo $wpjc_full ? '' : '<br><span class="description">' . esc_html( $c[3] ) . '</span>'; ?></td>
							<td style="width:90px;text-align:right"><?php echo 1 === $c[2] && 1 >= $c[1] ? ( $wpjc_full ? esc_html__( 'Yes', 'jobcore' ) : esc_html__( 'No', 'jobcore' ) ) : esc_html( number_format_i18n( $c[1] ) . ' / ' . number_format_i18n( $c[2] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $sample ) : ?>
				<p><a class="button" href="<?php echo esc_url( 'https://search.google.com/test/rich-results?url=' . rawurlencode( get_permalink( $sample[0] ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Test a job in Google’s Rich Results Test', 'jobcore' ); ?></a> <span class="description"><?php esc_html_e( 'Works once the site is online.', 'jobcore' ); ?></span></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="wpjc_country"><?php esc_html_e( 'Country of the jobs', 'jobcore' ); ?></label></th>
		<td>
			<input type="text" id="wpjc_country" name="wpjc_settings[country]" value="<?php echo esc_attr( (string) ( $opts['country'] ?? '' ) ); ?>" maxlength="2" size="4" style="text-transform:uppercase" placeholder="<?php echo esc_attr( wpjc_board_country() ); ?>">
			<label style="margin-left:16px"><?php esc_html_e( 'Currency', 'jobcore' ); ?> <input type="text" name="wpjc_settings[currency]" value="<?php echo esc_attr( (string) ( $opts['currency'] ?? '' ) ); ?>" maxlength="3" size="5" style="text-transform:uppercase" placeholder="EUR"></label>
			<p class="description"><?php esc_html_e( 'Two-letter country code (DE, AT, BA, HR…) and three-letter currency (EUR, CHF, BAM…). Empty country = taken from the site language. The currency is used when a salary has no € or other sign. Fully remote jobs are offered to people in this country.', 'jobcore' ); ?></p>
		</td>
	</tr>

	<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Job feeds', 'jobcore' ); ?></h3></td></tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Feeds', 'jobcore' ); ?></th>
		<td>
			<label><input type="checkbox" name="wpjc_settings[feeds_on]" value="1" <?php checked( ! empty( $opts['feeds_on'] ) ); ?>> <?php esc_html_e( 'Publish job feeds', 'jobcore' ); ?></label>
			<?php if ( ! empty( $opts['feeds_on'] ) ) : ?>
				<table class="widefat" style="max-width:720px;margin-top:10px">
					<tbody>
						<tr>
							<td style="width:150px"><strong><?php esc_html_e( 'XML job feed', 'jobcore' ); ?></strong></td>
							<td><code><a href="<?php echo esc_url( wpjc_feed_url( 'xml' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wpjc_feed_url( 'xml' ) ); ?></a></code><br><span class="description"><?php esc_html_e( 'For job aggregators and job search sites that take an XML feed. Send them this address.', 'jobcore' ); ?></span></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'RSS feed', 'jobcore' ); ?></strong></td>
							<td><code><a href="<?php echo esc_url( wpjc_feed_url( 'rss' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wpjc_feed_url( 'rss' ) ); ?></a></code><br><span class="description"><?php esc_html_e( 'For feed readers, Slack, Zapier, newsletters and partner sites.', 'jobcore' ); ?></span></td>
						</tr>
					</tbody>
				</table>
				<p class="description">
					<?php
					$wpjc_eg = ( $cats && ! is_wp_error( $cats ) ) ? wpjc_feed_url( 'xml', array( 'wpjc_cat' => $cats[0]->term_id ) ) : wpjc_feed_url( 'xml', array( 'wpjc_q' => 'developer' ) );
					/* translators: %s: example feed address */
					printf( esc_html__( 'Both feeds take the board’s search filters, so a partner can get only some jobs, for example %s', 'jobcore' ), '<code>' . esc_html( $wpjc_eg ) . '</code>' );
					?>
				</p>
				<p class="description"><?php esc_html_e( 'Only open jobs are listed: no filled, expired or unpublished ones. The site map for search engines leaves them out too.', 'jobcore' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>

	<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Instant indexing', 'jobcore' ); ?></h3></td></tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Google Indexing API', 'jobcore' ); ?></th>
		<td>
			<label><input type="checkbox" name="wpjc_settings[indexing_on]" value="1" <?php checked( ! empty( $opts['indexing_on'] ) ); ?>> <?php esc_html_e( 'Tell Google when a job goes live, changes or closes', 'jobcore' ); ?></label>
			<p class="description"><?php esc_html_e( 'New jobs then appear in Google within hours instead of days, and closed ones disappear. Needs a Google service account key — see below.', 'jobcore' ); ?></p>
		</td>
	</tr>
	<?php
}

/** Indexing API key and log (own forms, after the settings form). */
function wpjc_render_indexing_box() {
	$acc  = wpjc_index_account();
	$msg  = get_transient( 'wpjc_index_msg_' . get_current_user_id() );
	$log  = (array) get_option( WPJC_INDEX_LOG, array() );
	$form = static function ( $do, $label, $class = 'button' ) {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 6px 6px 0">
			<input type="hidden" name="action" value="wpjc_index">
			<input type="hidden" name="wpjc_do" value="<?php echo esc_attr( $do ); ?>">
			<?php wp_nonce_field( 'wpjc_index' ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	};
	if ( $msg ) {
		delete_transient( 'wpjc_index_msg_' . get_current_user_id() );
	}
	?>
	<div class="wpjc-opt" style="margin-top:20px">
		<h3 style="margin-top:0"><?php esc_html_e( 'Google service account key', 'jobcore' ); ?></h3>
		<?php if ( is_array( $msg ) ) : ?>
			<div class="notice notice-<?php echo esc_attr( $msg[0] ); ?> inline"><p><?php echo esc_html( $msg[1] ); ?></p></div>
		<?php endif; ?>
		<?php if ( ! function_exists( 'openssl_sign' ) ) : ?>
			<p><?php esc_html_e( 'This server’s PHP has no OpenSSL, which the Indexing API needs. Ask your host to turn it on.', 'jobcore' ); ?></p>
		<?php elseif ( $acc ) : ?>
			<p>
				<?php
				/* translators: %s: service account e-mail */
				printf( esc_html__( 'Key saved for %s. The key itself is not shown again.', 'jobcore' ), '<code>' . esc_html( $acc['client_email'] ) . '</code>' );
				?>
			</p>
			<?php
			$form( 'test', __( 'Test connection', 'jobcore' ), 'button button-primary' );
			$form( 'all', __( 'Send all open jobs now', 'jobcore' ) );
			$form( 'forget', __( 'Remove key', 'jobcore' ), 'button button-link-delete' );
			?>
			<?php if ( ! wpjc_opt( 'indexing_on' ) ) : ?>
				<p class="description"><?php esc_html_e( 'Pings are off: tick “Tell Google when a job goes live” above and save.', 'jobcore' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<ol style="max-width:720px">
				<li><?php echo wp_kses( __( 'In <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud</a>, create a project and turn on the “Web Search Indexing API”.', 'jobcore' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ) ) ); ?></li>
				<li><?php esc_html_e( 'Under IAM → Service accounts, create a service account, then Keys → Add key → JSON. A file downloads.', 'jobcore' ); ?></li>
				<li><?php echo wp_kses( __( 'In <a href="https://search.google.com/search-console" target="_blank" rel="noopener">Search Console</a> → Settings → Users and permissions, add the service account’s e-mail as an <strong>Owner</strong> of this site.', 'jobcore' ), array( 'a' => array( 'href' => true, 'target' => true, 'rel' => true ), 'strong' => array() ) ); ?></li>
				<li><?php esc_html_e( 'Open the downloaded file in a text editor, copy everything and paste it here.', 'jobcore' ); ?></li>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpjc_index">
				<input type="hidden" name="wpjc_do" value="save">
				<?php wp_nonce_field( 'wpjc_index' ); ?>
				<textarea name="wpjc_key" rows="6" class="large-text code" style="max-width:720px" placeholder='{ "type": "service_account", … }' autocomplete="off" spellcheck="false"></textarea>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Save key', 'jobcore' ); ?></button> <span class="description"><?php esc_html_e( 'Stored only in this site’s database and used only to talk to Google.', 'jobcore' ); ?></span></p>
			</form>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Google allows 200 notifications a day by default; one job uses one when it goes live and one when it closes.', 'jobcore' ); ?></p>

		<?php if ( $log ) : ?>
			<h4><?php esc_html_e( 'Last notifications', 'jobcore' ); ?></h4>
			<table class="widefat striped" style="max-width:900px">
				<tbody>
					<?php foreach ( array_slice( $log, 0, 15 ) as $row ) : ?>
						<tr>
							<td style="width:140px"><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' H:i', (int) $row['time'] ) ); ?></td>
							<td style="width:110px"><?php echo 'URL_DELETED' === $row['type'] ? esc_html__( 'Closed', 'jobcore' ) : esc_html__( 'Live / changed', 'jobcore' ); ?></td>
							<td><code><?php echo esc_html( (string) wp_parse_url( $row['url'], PHP_URL_PATH ) ); ?></code></td>
							<td><?php echo '' === $row['error'] ? '<span style="color:#16a34a">' . esc_html__( 'Sent', 'jobcore' ) . '</span>' : '<span style="color:#b91c1c">' . esc_html( $row['error'] ) . '</span>'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Look & colours rows.
 *
 * @param array $opts Current settings.
 */
function wpjc_render_look_tab( array $opts ) {
	$news = wpjc_has_news_theme();
	$size = array(
		'height' => function_exists( 'news_logo_height' ) ? (int) news_logo_height() : 32,
		'mobile' => function_exists( 'news_logo_height' ) ? (int) news_logo_height( true ) : 26,
	);
	?>
	<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Layout', 'jobcore' ); ?></h3></td></tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Board look', 'jobcore' ); ?></th>
		<td><?php wpjc_render_layout_choices( $opts ); ?></td>
	</tr>
	<?php if ( count( wpjc_board_skins() ) > 1 ) : ?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Board style', 'jobcore' ); ?></th>
		<td><?php wpjc_render_skin_choices( $opts ); ?></td>
	</tr>
	<?php endif; ?>
	<tr>
		<th scope="row"><label for="wpjc_demo_switcher"><?php esc_html_e( 'Demo look switcher', 'jobcore' ); ?></label></th>
		<td>
			<select id="wpjc_demo_switcher" name="wpjc_settings[demo_switcher]">
				<option value="off" <?php selected( 'off', $opts['demo_switcher'] ?? 'off' ); ?>><?php esc_html_e( 'Off', 'jobcore' ); ?></option>
				<option value="admins" <?php selected( 'admins', $opts['demo_switcher'] ?? '' ); ?>><?php esc_html_e( 'Admins only (try looks on this site)', 'jobcore' ); ?></option>
				<option value="everyone" <?php selected( 'everyone', $opts['demo_switcher'] ?? '' ); ?>><?php esc_html_e( 'Everyone (demo site)', 'jobcore' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'A “Looks” tab on the right edge of the jobs board lets people switch between board styles. Each choice only changes what that person sees, in their own browser; your settings stay as they are. Links like /jobs/?wpjc-look=market open a look directly. Turn Off before shipping.', 'jobcore' ); ?></p>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Header (standalone)', 'jobcore' ); ?></th>
		<td>
			<fieldset class="wpjc-choices">
				<label class="wpjc-choice"><input type="radio" name="wpjc_settings[header_style]" value="board" <?php checked( 'board', $opts['header_style'] ); ?>><span class="dashicons dashicons-menu-alt" aria-hidden="true"></span><b><?php esc_html_e( 'Jobs portal header', 'jobcore' ); ?></b><small><?php esc_html_e( 'Logo, icon menu, bookmarks and profile.', 'jobcore' ); ?></small></label>
				<label class="wpjc-choice<?php echo $news ? '' : ' is-disabled'; ?>"><input type="radio" name="wpjc_settings[header_style]" value="news" <?php checked( 'news', $opts['header_style'] ); ?> <?php disabled( ! $news ); ?>><span class="dashicons dashicons-align-wide" aria-hidden="true"></span><b><?php esc_html_e( 'News. site header', 'jobcore' ); ?></b><small><?php echo $news ? esc_html__( 'The theme’s header with the jobs menu.', 'jobcore' ) : esc_html__( 'Needs the News. theme.', 'jobcore' ); ?></small></label>
			</fieldset>
		</td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Light and dark', 'jobcore' ); ?></th>
		<td>
			<?php $wpjc_scheme = (string) ( $opts['scheme'] ?? 'light' ); ?>
			<select name="wpjc_settings[scheme]" aria-label="<?php esc_attr_e( 'Colours for new visitors', 'jobcore' ); ?>">
				<option value="light" <?php selected( 'light', $wpjc_scheme ); ?>><?php esc_html_e( 'Light', 'jobcore' ); ?></option>
				<option value="dark" <?php selected( 'dark', $wpjc_scheme ); ?>><?php esc_html_e( 'Dark', 'jobcore' ); ?></option>
				<option value="auto" <?php selected( 'auto', $wpjc_scheme ); ?>><?php esc_html_e( 'Follow the visitor’s device', 'jobcore' ); ?></option>
			</select>
			<label style="display:block;margin-top:8px"><input type="checkbox" name="wpjc_settings[scheme_switch]" value="1" <?php checked( ! empty( $opts['scheme_switch'] ) ); ?>> <?php esc_html_e( 'Let visitors switch between Light, Dark and Auto in the profile menu', 'jobcore' ); ?></label>
			<p class="description"><?php echo $news ? esc_html__( 'For the Jobs portal header. With the News. site header, the board follows the theme’s own dark mode.', 'jobcore' ) : esc_html__( 'For the Jobs portal header.', 'jobcore' ); ?></p>
		</td>
	</tr>

	<tr class="wpjc-opt-heading"><td colspan="2"><h3><?php esc_html_e( 'Brand', 'jobcore' ); ?></h3></td></tr>
	<?php
	wpjc_admin_field( 'brand_name', __( 'Board name', 'jobcore' ), 'text', __( 'Empty = “{Site name} Jobs”.', 'jobcore' ), $opts );
	wpjc_admin_field(
		'brand_logo',
		__( 'Board logo (light backgrounds)', 'jobcore' ),
		'media',
		$news
			? __( 'For a white header. Empty = the News. dark logo (Theme Options → Brand).', 'jobcore' )
			: __( 'For a white header. Empty = the board name as text.', 'jobcore' ),
		$opts
	);
	wpjc_admin_field(
		'brand_logo_light',
		__( 'Board logo (dark backgrounds)', 'jobcore' ),
		'media',
		$news
			? __( 'White or light wordmark for Marketplace and other dark headers. Empty = the News. logo for dark backgrounds (Theme Options → Brand).', 'jobcore' )
			: __( 'White or light wordmark for Marketplace and other dark headers. Empty = the light-background logo.', 'jobcore' ),
		$opts
	);
	/* translators: %d: pixels */
	$inherit = $news ? __( 'Empty = same as the News. theme logo (%d px).', 'jobcore' ) : __( 'Empty = %d px.', 'jobcore' );
	wpjc_admin_field(
		'logo_height',
		__( 'Logo height on computers (px)', 'jobcore' ),
		'number',
		sprintf( $inherit, $size['height'] ) . ' ' . __( '16 to 80. The width follows the logo proportions.', 'jobcore' ),
		$opts,
		array(
			'min'         => 16,
			'max'         => 80,
			'placeholder' => $size['height'],
		)
	);
	wpjc_admin_field(
		'logo_height_m',
		__( 'Logo height on phones (px)', 'jobcore' ),
		'number',
		sprintf( $inherit, $size['mobile'] ) . ' ' . __( '16 to 60.', 'jobcore' ),
		$opts,
		array(
			'min'         => 16,
			'max'         => 60,
			'placeholder' => $size['mobile'],
		)
	);
	wpjc_admin_field(
		'logo_width',
		__( 'Maximum logo width (px)', 'jobcore' ),
		'number',
		__( 'Empty = automatic. Keeps wide logos from pushing the menu (40 to 400).', 'jobcore' ),
		$opts,
		array(
			'min'         => 40,
			'max'         => 400,
			'placeholder' => 240,
		)
	);
	wpjc_admin_field(
		'brand_tag',
		__( 'Logo label', 'jobcore' ),
		'text',
		__( 'A short word next to the logo, like “Jobs”. Empty = hidden.', 'jobcore' ),
		$opts,
		array(
			'placeholder' => __( 'Jobs', 'jobcore' ),
		)
	);
	?>
	<tr>
		<th scope="row"><?php esc_html_e( 'Logo label colour', 'jobcore' ); ?></th>
		<td><?php wpjc_render_tag_color_choices( $opts ); ?></td>
	</tr>
	<tr>
		<th scope="row"><?php esc_html_e( 'Accent colour', 'jobcore' ); ?></th>
		<td><?php wpjc_render_accent_choices( $opts ); ?></td>
	</tr>
	<?php
	wpjc_admin_field( 'hero_image', __( 'Hero image', 'jobcore' ), 'media', __( 'Optional cut-out on the right of the hero. Use a transparent PNG of a person or object, about 700 × 900 px (shown at 300 px tall). Hidden on small screens.', 'jobcore' ), $opts );
}

/**
 * Premium board skins (only when an add-on registered extra looks).
 *
 * @param array $opts Current settings.
 */
function wpjc_render_skin_choices( array $opts ) {
	$current = wpjc_board_skin();
	?>
	<fieldset class="wpjc-choices">
		<?php foreach ( wpjc_board_skins() as $slug => $skin ) : ?>
			<label class="wpjc-choice">
				<input type="radio" name="wpjc_settings[board_skin]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $current ); ?>>
				<span class="dashicons dashicons-<?php echo 'default' === $slug ? 'admin-customizer' : 'awards'; ?>" aria-hidden="true"></span>
				<b><?php echo esc_html( $skin['label'] ); ?></b>
				<small><?php echo esc_html( $skin['description'] ); ?></small>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<p class="description"><?php esc_html_e( 'Premium looks come from the Looks add-on. The default look stays available.', 'jobcore' ); ?></p>
	<?php
}

/**
 * "Inside the theme" / "Standalone portal" choice cards.
 *
 * @param array $opts Current settings.
 */
function wpjc_render_layout_choices( array $opts ) {
	?>
	<fieldset class="wpjc-choices">
		<label class="wpjc-choice"><input type="radio" name="wpjc_settings[layout]" value="theme" <?php checked( 'theme', $opts['layout'] ); ?>><span class="dashicons dashicons-admin-appearance" aria-hidden="true"></span><b><?php esc_html_e( 'Inside the theme', 'jobcore' ); ?></b><small><?php esc_html_e( 'Job pages use the site header and footer.', 'jobcore' ); ?></small></label>
		<label class="wpjc-choice"><input type="radio" name="wpjc_settings[layout]" value="standalone" <?php checked( 'standalone', $opts['layout'] ); ?>><span class="dashicons dashicons-businessman" aria-hidden="true"></span><b><?php esc_html_e( 'Standalone portal', 'jobcore' ); ?></b><small><?php esc_html_e( 'Own header, footer and styles, like a separate jobs site.', 'jobcore' ); ?></small></label>
	</fieldset>
	<?php
}

/**
 * Accent colour swatches (theme colour, presets, custom picker).
 *
 * @param array $opts Current settings.
 */
function wpjc_render_accent_choices( array $opts ) {
	$news    = wpjc_has_news_theme();
	$saved   = (string) sanitize_hex_color( (string) $opts['accent'] );
	$presets = wpjc_accent_presets();
	$preset  = array_change_key_case( array_flip( array_keys( $presets ) ) );
	$pick    = '' === $saved ? '' : ( isset( $preset[ strtolower( $saved ) ] ) ? strtolower( $saved ) : 'custom' );
	$base    = $news ? sanitize_hex_color( (string) get_theme_mod( 'brand_color', '#D7141B' ) ) : '#2563eb';
	?>
			<fieldset class="wpjc-swatches">
				<label class="wpjc-swatch">
					<input type="radio" name="wpjc_settings[accent_pick]" value="" <?php checked( '', $pick ); ?>>
					<span class="wpjc-swatch__dot" style="--c:<?php echo esc_attr( $base ); ?>"></span>
					<span><?php echo $news ? esc_html__( 'Theme colour', 'jobcore' ) : esc_html__( 'Default', 'jobcore' ); ?></span>
				</label>
				<?php foreach ( $presets as $hex => $label ) : ?>
					<label class="wpjc-swatch">
						<input type="radio" name="wpjc_settings[accent_pick]" value="<?php echo esc_attr( $hex ); ?>" <?php checked( $hex, $pick ); ?>>
						<span class="wpjc-swatch__dot" style="--c:<?php echo esc_attr( $hex ); ?>"></span>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
				<label class="wpjc-swatch">
					<input type="radio" name="wpjc_settings[accent_pick]" value="custom" id="wpjc-accent-custom" <?php checked( 'custom', $pick ); ?>>
					<input type="color" class="wpjc-swatch__picker" name="wpjc_settings[accent_custom]" id="wpjc-accent-picker" value="<?php echo esc_attr( 'custom' === $pick ? $saved : $base ); ?>" aria-label="<?php esc_attr_e( 'Custom colour', 'jobcore' ); ?>">
					<span><?php esc_html_e( 'Custom', 'jobcore' ); ?></span>
				</label>
			</fieldset>
			<p class="description"><?php esc_html_e( 'Hero, buttons, links, Premium Plus badges and highlights. “Theme colour” follows the News. brand colour.', 'jobcore' ); ?></p>
			<script>
			document.getElementById('wpjc-accent-picker').addEventListener('input', () => { document.getElementById('wpjc-accent-custom').checked = true; });
			</script>
	<?php
}

/**
 * Logo-label colour: theme / accent, or a custom hex.
 *
 * @param array $opts Current settings.
 */
function wpjc_render_tag_color_choices( array $opts ) {
	$saved = (string) sanitize_hex_color( (string) ( $opts['brand_tag_color'] ?? '' ) );
	$pick  = '' === $saved ? '' : 'custom';
	$base  = wpjc_accent();
	?>
			<fieldset class="wpjc-swatches">
				<label class="wpjc-swatch">
					<input type="radio" name="wpjc_settings[brand_tag_pick]" value="" <?php checked( '', $pick ); ?>>
					<span class="wpjc-swatch__dot" style="--c:<?php echo esc_attr( $base ); ?>"></span>
					<span><?php esc_html_e( 'Theme colour', 'jobcore' ); ?></span>
				</label>
				<label class="wpjc-swatch">
					<input type="radio" name="wpjc_settings[brand_tag_pick]" value="custom" id="wpjc-tag-custom" <?php checked( 'custom', $pick ); ?>>
					<input type="color" class="wpjc-swatch__picker" name="wpjc_settings[brand_tag_custom]" id="wpjc-tag-picker" value="<?php echo esc_attr( $saved ? $saved : $base ); ?>" aria-label="<?php esc_attr_e( 'Custom colour', 'jobcore' ); ?>">
					<span><?php esc_html_e( 'Custom colour', 'jobcore' ); ?></span>
				</label>
			</fieldset>
			<p class="description"><?php esc_html_e( '“Theme colour” uses the board accent (or the News. brand colour).', 'jobcore' ); ?></p>
			<script>
			document.getElementById('wpjc-tag-picker').addEventListener('input', () => { document.getElementById('wpjc-tag-custom').checked = true; });
			</script>
	<?php
}

/**
 * Number of sample jobs on the site.
 */
function wpjc_sample_count() {
	return count(
		get_posts(
			array(
				'post_type'      => 'wpjc_job',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_wpjc_sample', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		)
	);
}

/**
 * Sample content tab.
 *
 * @param string $seeded Result flag (added|removed).
 */
function wpjc_render_sample_tab( $seeded ) {
	$count = wpjc_sample_count();
	?>
	<?php if ( 'added' === $seeded ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Sample jobs created.', 'jobcore' ); ?></p></div>
	<?php elseif ( 'removed' === $seeded ) : ?>
		<div class="notice notice-success"><p><?php esc_html_e( 'Sample jobs removed.', 'jobcore' ); ?></p></div>
	<?php endif; ?>
	<div class="wpjc-dash-box wpjc-sample">
		<p><?php esc_html_e( 'Creates 5 demo employers and 12 jobs (Premium Plus, Premium and Basic, some with photos) so you can preview the board. They are marked as samples and can be removed again in one click, photos included — your own jobs are never touched.', 'jobcore' ); ?></p>
		<p><strong>
			<?php
			/* translators: %d: number of sample jobs */
			echo esc_html( sprintf( _n( '%d sample job on this site.', '%d sample jobs on this site.', $count, 'jobcore' ), $count ) );
			?>
		</strong></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpjc_seed">
			<input type="hidden" name="mode" value="add">
			<?php wp_nonce_field( 'wpjc_seed' ); ?>
			<?php submit_button( __( 'Create sample jobs', 'jobcore' ), 'primary wpjc-opt__save', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="wpjc_seed">
			<input type="hidden" name="mode" value="remove">
			<?php wp_nonce_field( 'wpjc_seed' ); ?>
			<?php submit_button( __( 'Remove sample jobs', 'jobcore' ), 'delete', 'submit', false, $count ? array() : array( 'disabled' => 'disabled' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * System status tab.
 */
function wpjc_render_status_tab() {
	$page = (int) get_option( 'wpjc_jobs_page_id' );
	$rows = array(
		__( 'JobCore', 'jobcore' )      => array( WPJC_VERSION, true ),
		__( 'WordPress', 'jobcore' )        => array( get_bloginfo( 'version' ), version_compare( get_bloginfo( 'version' ), '6.5', '>=' ) ),
		__( 'PHP', 'jobcore' )              => array( PHP_VERSION, version_compare( PHP_VERSION, '8.0', '>=' ) ),
		__( 'Theme', 'jobcore' )            => array( wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ), true ),
		__( 'Jobs page', 'jobcore' )        => array( $page ? get_the_title( $page ) . ' (#' . $page . ')' : __( 'missing', 'jobcore' ), $page && 'publish' === get_post_status( $page ) ),
		__( 'Permalinks', 'jobcore' )       => array( get_option( 'permalink_structure' ) ? get_option( 'permalink_structure' ) : __( 'Plain', 'jobcore' ), (bool) get_option( 'permalink_structure' ) ),
		__( 'Max upload size', 'jobcore' )  => array(
			sprintf(
				/* translators: 1: server limit, 2: documents MB, 3: photo MB */
				__( 'Server %1$s · documents %2$d MB · photo %3$d MB', 'jobcore' ),
				size_format( wp_max_upload_size() ),
				wpjc_apply_limits()['docs_mb'],
				wpjc_apply_limits()['photo_mb']
			),
			wp_max_upload_size() >= 8 * MB_IN_BYTES,
		),
		__( 'E-mail sending', 'jobcore' )   => array( function_exists( 'wp_mail' ) ? __( 'wp_mail available', 'jobcore' ) : __( 'missing', 'jobcore' ), function_exists( 'wp_mail' ) ),
	);
	echo '<table class="widefat striped wpjc-status"><tbody>';
	foreach ( $rows as $label => $row ) {
		printf(
			'<tr><th scope="row">%1$s</th><td>%2$s</td><td>%3$s</td></tr>',
			esc_html( $label ),
			esc_html( (string) $row[0] ),
			$row[1] ? '<span class="dashicons dashicons-yes" style="color:#00a32a"></span>' : '<span class="dashicons dashicons-warning" style="color:#dba617"></span>'
		);
	}
	echo '</tbody></table>';
}

add_action( 'admin_post_wpjc_seed', 'wpjc_handle_seed' );

/**
 * Create or remove sample employers and jobs (admin-post).
 */
function wpjc_handle_seed() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Not allowed.', 'jobcore' ), 403 );
	}
	check_admin_referer( 'wpjc_seed' );
	$remove = isset( $_POST['mode'] ) && 'remove' === $_POST['mode'];
	if ( $remove ) {
		wpjc_seed_remove();
	} else {
		wpjc_seed_create();
	}
	wp_safe_redirect( add_query_arg( 'wpjc_seeded', $remove ? 'removed' : 'added', wpjc_settings_url( 'sample' ) ) );
	exit;
}

/**
 * Delete sample jobs, their photos and the sample employers.
 */
function wpjc_seed_remove() {
	foreach ( array( 'wpjc_job', 'attachment' ) as $type ) {
		$ids = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => '_wpjc_sample', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( $ids as $id ) {
			if ( 'attachment' === $type ) {
				wp_delete_attachment( $id, true );
			} else {
				wp_delete_post( $id, true );
			}
		}
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'wpjc_employer',
			'hide_empty' => false,
			'meta_key'   => 'wpjc_sample', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
			'fields'     => 'ids',
		)
	);
	if ( ! is_wp_error( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( (int) $term_id, 'wpjc_employer' );
		}
	}
}

/**
 * Import one bundled demo photo into the media library (once per seed run).
 *
 * @param string $file File name in assets/demo.
 * @return int Attachment ID, 0 on failure.
 */
function wpjc_seed_photo( $file ) {
	static $done = array();
	if ( isset( $done[ $file ] ) ) {
		return $done[ $file ];
	}
	$done[ $file ] = 0;
	$path          = WPJC_DIR . 'assets/demo/' . $file;
	if ( ! is_readable( $path ) ) {
		return 0;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( $file );
	if ( ! $tmp || ! copy( $path, $tmp ) ) {
		return 0;
	}
	$id = media_handle_sideload(
		array(
			'name'     => 'wpjc-sample-' . $file,
			'tmp_name' => $tmp,
		),
		0
	);
	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}
	update_post_meta( $id, '_wpjc_sample', '1' );
	$done[ $file ] = (int) $id;
	return $done[ $file ];
}

/**
 * Demo company details for the sample employers (employer page "Company details").
 *
 * @return array<string, array<string, string>>
 */
function wpjc_seed_profiles() {
	return array(
		'Nordwind Logistics' => array(
			'description' => "Nordwind Logistics moves goods across Europe by road, rail and sea. Our 1,200 people run warehouses in Hamburg, Berlin and Munich and keep 400 trucks on the road every day.\n\nWe invest in modern fleets, fair shifts and training: most of our team leads started as drivers or warehouse staff.",
			'legal_name'  => 'Nordwind Logistics GmbH',
			'reg_no'      => 'HRB 145872',
			'vat'         => 'DE 298 451 736',
			'address'     => 'Hafenstraße 18',
			'postcode'    => '20457',
			'city'        => 'Hamburg',
			'country'     => 'Germany',
			'website'     => 'https://nordwind-logistics.example',
			'linkedin'    => 'https://www.linkedin.com/company/nordwind-logistics-example',
		),
		'Bright Bank'        => array(
			'description' => "Bright Bank is a digital-first bank for people and small businesses, with branches in six German cities. We make banking simple: clear fees, a great app and advisors who take their time.\n\nWe offer hybrid work, a learning budget and 30 days of holiday.",
			'legal_name'  => 'Bright Bank AG',
			'reg_no'      => 'HRB 98312',
			'vat'         => 'DE 314 220 981',
			'address'     => 'Friedrichstraße 120',
			'postcode'    => '10117',
			'city'        => 'Berlin',
			'country'     => 'Germany',
			'website'     => 'https://brightbank.example',
			'linkedin'    => 'https://www.linkedin.com/company/bright-bank-example',
		),
		'Atlas Software'     => array(
			'description' => "Atlas Software builds planning tools used by 3,000 construction and engineering companies. Our product teams work in Vienna and Munich, close to the people who use what we build.\n\nSmall teams, modern stack (PHP, TypeScript, React), and a four-day focus week every quarter.",
			'legal_name'  => 'Atlas Software GmbH',
			'reg_no'      => 'FN 512843 k',
			'vat'         => 'ATU 74125890',
			'address'     => 'Mariahilfer Straße 47',
			'postcode'    => '1060',
			'city'        => 'Vienna',
			'country'     => 'Austria',
			'website'     => 'https://atlas-software.example',
			'linkedin'    => 'https://www.linkedin.com/company/atlas-software-example',
		),
		'Hotel Riverside'    => array(
			'description' => "Hotel Riverside is a four-star hotel on the banks of the Isar with 180 rooms, two restaurants and a riverside terrace. We host guests from all over the world and look for people who love good service.\n\nStaff meals, a free monthly night for family and real chances to grow into lead roles.",
			'legal_name'  => 'Riverside Hospitality GmbH',
			'reg_no'      => 'HRB 230114',
			'vat'         => 'DE 276 908 445',
			'address'     => 'Isarufer 5',
			'postcode'    => '80538',
			'city'        => 'Munich',
			'country'     => 'Germany',
			'website'     => 'https://hotel-riverside.example',
			'linkedin'    => '',
		),
		'GreenMart'          => array(
			'description' => "GreenMart runs 85 neighbourhood supermarkets in Germany and Switzerland with a focus on fresh, local and organic food. Our stores are small, friendly and part of the street they are in.\n\nStaff discount, planned rotas four weeks ahead and a clear path from assistant to store manager.",
			'legal_name'  => 'GreenMart Retail AG',
			'reg_no'      => 'CHE-412.338.905',
			'vat'         => 'CHE-412.338.905 MWST',
			'address'     => 'Bahnhofstrasse 64',
			'postcode'    => '8001',
			'city'        => 'Zurich',
			'country'     => 'Switzerland',
			'website'     => 'https://greenmart.example',
			'linkedin'    => 'https://www.linkedin.com/company/greenmart-example',
		),
	);
}

/**
 * Fill empty company details of an employer.
 *
 * @param int                  $term_id Employer term ID.
 * @param array<string,string> $profile From wpjc_seed_profiles().
 */
function wpjc_seed_apply_profile( $term_id, array $profile ) {
	$term = get_term( (int) $term_id, 'wpjc_employer' );
	if ( ! $term instanceof WP_Term ) {
		return;
	}
	if ( '' === trim( $term->description ) && ! empty( $profile['description'] ) ) {
		wp_update_term( $term->term_id, 'wpjc_employer', array( 'description' => $profile['description'] ) );
	}
	unset( $profile['description'] );
	foreach ( $profile as $key => $value ) {
		if ( '' !== $value && '' === (string) get_term_meta( $term->term_id, 'wpjc_' . $key, true ) ) {
			update_term_meta( $term->term_id, 'wpjc_' . $key, $value );
		}
	}
}

/**
 * Create sample employers and jobs (with photos for some of them).
 */
function wpjc_seed_create() {
	$employers = array( 'Nordwind Logistics', 'Bright Bank', 'Atlas Software', 'Hotel Riverside', 'GreenMart' );
	$emp_ids   = array();
	foreach ( $employers as $name ) {
		$term = term_exists( $name, 'wpjc_employer' );
		if ( ! $term ) {
			$term = wp_insert_term( $name, 'wpjc_employer' );
			if ( ! is_wp_error( $term ) ) {
				update_term_meta( (int) $term['term_id'], 'wpjc_sample', '1' );
			}
		}
		if ( ! is_wp_error( $term ) ) {
			$emp_ids[ $name ] = (int) $term['term_id'];
			wpjc_seed_apply_profile( (int) $term['term_id'], wpjc_seed_profiles()[ $name ] ?? array() );
		}
	}

	$ensure = static function ( $name, $taxonomy ) {
		$term = term_exists( $name, $taxonomy );
		if ( ! $term ) {
			$term = wp_insert_term( $name, $taxonomy );
		}
		return is_wp_error( $term ) ? 0 : (int) $term['term_id'];
	};

	// Title, employer, city, category, type, tier, photo.
	$jobs = array(
		array( 'Business Administration Specialist (m/f)', 'Nordwind Logistics', 'Berlin', 'Administration', 'Full time', 'premium_plus', 'accounting-desk.webp' ),
		array( 'Logistics Coordinator (m/f)', 'Nordwind Logistics', 'Hamburg', 'Transport & Logistics', 'Full time', 'premium_plus', 'logistics-yard.webp' ),
		array( 'Senior PHP Developer', 'Atlas Software', 'Vienna', 'IT', 'Full time', 'premium_plus', '' ),
		array( 'Truck Driver C+E (m/f)', 'Nordwind Logistics', 'Munich', 'Transport & Logistics', 'Full time', 'premium', 'truck-driver.webp' ),
		array( 'Front-end Developer (React)', 'Atlas Software', 'Munich', 'IT', 'Full time', 'premium', '' ),
		array( 'Customer Advisor', 'Bright Bank', 'Berlin', 'Finance', 'Full time', 'premium', 'call-centre.webp' ),
		array( 'Store Manager', 'GreenMart', 'Zurich', 'Sales', 'Full time', 'premium', 'warehouse.webp' ),
		array( 'Credit Analyst', 'Bright Bank', 'Hamburg', 'Finance', 'Full time', 'basic', 'office-phone.webp' ),
		array( 'Receptionist', 'Hotel Riverside', 'Munich', 'Hospitality', 'Part time', 'basic', '' ),
		array( 'Chef de Partie', 'Hotel Riverside', 'Berlin', 'Hospitality', 'Full time', 'basic', '' ),
		array( 'Sales Assistant', 'GreenMart', 'Berlin', 'Sales', 'Part time', 'basic', '' ),
		array( 'Marketing Intern', 'Nordwind Logistics', 'Remote', 'Marketing', 'Internship', 'basic', '' ),
	);

	$content = '<p>' . esc_html__( 'We are looking for a motivated colleague to join our growing team. This is a sample job created by JobCore.', 'jobcore' ) . '</p>'
		. '<h3>' . esc_html__( 'Your tasks', 'jobcore' ) . '</h3><ul><li>' . esc_html__( 'Own your daily work and deliver on time', 'jobcore' ) . '</li><li>' . esc_html__( 'Work closely with the team and clients', 'jobcore' ) . '</li><li>' . esc_html__( 'Help improve our processes', 'jobcore' ) . '</li></ul>'
		. '<h3>' . esc_html__( 'What we expect', 'jobcore' ) . '</h3><ul><li>' . esc_html__( 'Relevant education or experience', 'jobcore' ) . '</li><li>' . esc_html__( 'Good communication skills', 'jobcore' ) . '</li><li>' . esc_html__( 'English at a working level', 'jobcore' ) . '</li></ul>'
		. '<h3>' . esc_html__( 'We offer', 'jobcore' ) . '</h3><ul><li>' . esc_html__( 'Permanent contract and a competitive salary', 'jobcore' ) . '</li><li>' . esc_html__( 'Training and room to grow', 'jobcore' ) . '</li></ul>';

	foreach ( $jobs as $i => $job ) {
		list( $title, $employer, $city, $category, $type, $tier, $photo ) = $job;
		$id = wp_insert_post(
			array(
				'post_type'    => 'wpjc_job',
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $content,
				'post_date'    => wp_date( 'Y-m-d H:i:s', time() - $i * DAY_IN_SECONDS ),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			continue;
		}
		$remote = 'Remote' === $city;
		update_post_meta( $id, '_wpjc_sample', '1' );
		update_post_meta( $id, '_wpjc_location', $remote ? '' : $city );
		update_post_meta( $id, '_wpjc_remote', $remote ? 1 : 0 );
		update_post_meta( $id, '_wpjc_tier', $tier );
		update_post_meta( $id, '_wpjc_filled', 0 );
		update_post_meta( $id, '_wpjc_apply_email', get_option( 'admin_email' ) );
		update_post_meta( $id, '_wpjc_expires', wp_date( 'Y-m-d', time() + ( 30 - $i ) * DAY_IN_SECONDS ) );
		if ( isset( $emp_ids[ $employer ] ) ) {
			wp_set_object_terms( $id, $emp_ids[ $employer ], 'wpjc_employer' );
		}
		$cat_id  = $ensure( $category, 'wpjc_job_category' );
		$type_id = $ensure( $type, 'wpjc_job_type' );
		if ( $cat_id ) {
			wp_set_object_terms( $id, $cat_id, 'wpjc_job_category' );
		}
		if ( $type_id ) {
			wp_set_object_terms( $id, $type_id, 'wpjc_job_type' );
		}
		$photo_id = $photo ? wpjc_seed_photo( $photo ) : 0;
		if ( $photo_id ) {
			set_post_thumbnail( $id, $photo_id );
		}
	}
}
