<?php
/**
 * Job feeds and Google job search helpers.
 *
 * - XML job feed (/feed/jobs-xml/) in the Indeed-style <source><job> format that many job
 *   aggregators read, and an RSS feed (/feed/jobs-rss/) for readers, Slack, Zapier and partner sites.
 *   Both take the board's search parameters, e.g. /feed/jobs-xml/?wpjc_cat=12 for one field.
 * - The job sitemap leaves out filled and expired jobs.
 * - Shared helpers: the board's country, salary text → numbers, logo URL.
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	static function () {
		if ( ! (int) wpjc_opt( 'feeds_on' ) ) {
			return;
		}
		add_feed( 'jobs-xml', 'wpjc_render_xml_feed' );
		add_feed( 'jobs-rss', 'wpjc_render_rss_feed' );
	},
	20
);

/**
 * Feed address.
 *
 * @param string $kind xml|rss.
 * @param array  $args Search parameters to add.
 * @return string
 */
function wpjc_feed_url( $kind = 'xml', array $args = array() ) {
	$url = get_feed_link( 'xml' === $kind ? 'jobs-xml' : 'jobs-rss' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Two-letter country of the board: the setting, else the site language's region (de_AT → AT).
 *
 * @return string
 */
function wpjc_board_country() {
	$code = strtoupper( (string) wpjc_opt( 'country' ) );
	if ( preg_match( '/^[A-Z]{2}$/', $code ) ) {
		return $code;
	}
	if ( preg_match( '/^[a-z]{2,3}_([A-Z]{2})/', get_locale(), $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Three-letter currency used when a salary has no symbol.
 *
 * @return string
 */
function wpjc_board_currency() {
	$code = strtoupper( (string) wpjc_opt( 'currency' ) );
	return preg_match( '/^[A-Z]{3}$/', $code ) ? $code : 'EUR';
}

/**
 * Salary text of a job (the Field Editor's "salary" field), filterable.
 *
 * @param WP_Post $job Job.
 * @return string
 */
function wpjc_job_salary_text( WP_Post $job ) {
	$text = trim( (string) get_post_meta( $job->ID, '_wpjc_cf_salary', true ) );
	return (string) apply_filters( 'wpjc_job_salary_text', $text, $job );
}

/**
 * Read a salary written by people: "€4,200 – €5,000 / month", "60k–75k € a year",
 * "18 € per hour", "1.500 KM". Returns null when there is no number.
 *
 * @param string $text Salary text.
 * @return array{currency:string,min:float,max:float,unit:string}|null Unit: HOUR, DAY, WEEK, MONTH or YEAR.
 */
function wpjc_parse_salary( $text ) {
	$text = trim( wp_strip_all_tags( (string) $text ) );
	if ( '' === $text ) {
		return null;
	}
	$low = mb_strtolower( $text );

	$currencies = array(
		'€'   => 'EUR',
		'eur' => 'EUR',
		'£'   => 'GBP',
		'gbp' => 'GBP',
		'chf' => 'CHF',
		'usd' => 'USD',
		'$'   => 'USD',
		'bam' => 'BAM',
		'km'  => 'BAM',
		'rsd' => 'RSD',
		'din' => 'RSD',
		'pln' => 'PLN',
		'zł'  => 'PLN',
		'czk' => 'CZK',
		'kč'  => 'CZK',
		'huf' => 'HUF',
		'ft'  => 'HUF',
		'sek' => 'SEK',
		'nok' => 'NOK',
		'dkk' => 'DKK',
		'ron' => 'RON',
		'lei' => 'RON',
		'mkd' => 'MKD',
		'ден' => 'MKD',
	);
	$currency   = '';
	foreach ( $currencies as $sign => $code ) {
		$pattern = preg_match( '/^[a-zа-я]+$/u', $sign ) ? '/(?<![a-zа-я])' . preg_quote( $sign, '/' ) . '(?![a-zа-я])/u' : '/' . preg_quote( $sign, '/' ) . '/u';
		if ( preg_match( $pattern, $low ) ) {
			$currency = $code;
			break;
		}
	}

	// Numbers like 4,200 / 4.200 / 4 200 / 60k / 2,5.
	if ( ! preg_match_all( '/(\d[\d.,\s\x{00A0}\x{202F}\'’]*\d|\d)\s*(k|tsd|tausend|hiljad\w*)?(?![a-z])/iu', $low, $m, PREG_SET_ORDER ) ) {
		return null;
	}
	$values = array();
	foreach ( $m as $hit ) {
		$num = preg_replace( '/[\s\x{00A0}\x{202F}\'’]/u', '', $hit[1] );
		if ( preg_match( '/^\d{1,3}([.,]\d{3})+$/', $num ) ) {
			$num = preg_replace( '/[.,]/', '', $num ); // Thousands separators.
		} elseif ( preg_match( '/^\d{1,3}([.,]\d{3})+[.,]\d{1,2}$/', $num ) ) {
			$num = preg_replace( '/[.,](\d{1,2})$/', '.$1', preg_replace( '/[.,](?=\d{3})/', '', $num ) );
		} else {
			$num = str_replace( ',', '.', $num ); // Decimal comma.
		}
		$value = (float) $num;
		if ( ! empty( $hit[2] ) ) {
			$value *= 1000;
		}
		if ( $value > 0 ) {
			$values[] = $value;
		}
		if ( count( $values ) >= 2 ) {
			break;
		}
	}
	if ( ! $values ) {
		return null;
	}
	$min = min( $values );
	$max = max( $values );

	$units = array(
		'HOUR'  => '/\b(hour|hourly|hr|h)\b|stunde|std\b|\bsat\b|po satu|\/h\b/u',
		'DAY'   => '/\b(day|daily)\b|\btag\b|täglich|\bdan\b|dnevn/u',
		'WEEK'  => '/\b(week|weekly|wk)\b|woche|sedmic|tjedn/u',
		'MONTH' => '/\b(month|monthly|mo|pm)\b|monat|mjese|mesec|\bmj\b|brutto\/m|netto\/m/u',
		'YEAR'  => '/\b(year|yearly|annual|annually|yr|pa|p\.a)\b|jahr|godi[sš]nj|godina|\bgod\b/u',
	);
	$unit  = '';
	foreach ( $units as $key => $pattern ) {
		if ( preg_match( $pattern, $low ) ) {
			$unit = $key;
			break;
		}
	}
	if ( '' === $unit ) {
		$unit = $max >= 15000 ? 'YEAR' : ( $max >= 300 ? 'MONTH' : 'HOUR' );
	}
	return array(
		'currency' => '' !== $currency ? $currency : wpjc_board_currency(),
		'min'      => $min,
		'max'      => $max,
		'unit'     => $unit,
	);
}

/**
 * Logo address from the employer's logo field (URL or media ID).
 *
 * @param string|int $logo Logo field.
 * @return string
 */
function wpjc_logo_url( $logo ) {
	if ( is_numeric( $logo ) ) {
		$url = wp_get_attachment_image_url( (int) $logo, 'medium' );
		return $url ? (string) $url : '';
	}
	return (string) $logo;
}

/**
 * Whether a job is gone from the board: filled, expired, or not published.
 *
 * @param WP_Post $job Job.
 * @return bool
 */
function wpjc_job_is_closed( WP_Post $job ) {
	if ( 'publish' !== $job->post_status || wpjc_meta( $job, 'filled' ) ) {
		return true;
	}
	$expires = (string) wpjc_meta( $job, 'expires' );
	return '' !== $expires && $expires < wp_date( 'Y-m-d' );
}

/**
 * Jobs for a feed: the board's open jobs, newest first, with the request's search parameters.
 *
 * @return WP_Post[]
 */
function wpjc_feed_jobs() {
	$filters = wpjc_search_filters();
	$args    = array_merge(
		wpjc_filters_query_args( $filters ),
		array(
			'per_page' => (int) apply_filters( 'wpjc_feed_limit', 500 ),
			'sort'     => 'new',
		)
	);
	return wpjc_query_jobs( $args )->posts;
}

/**
 * Text inside <![CDATA[ … ]]>.
 *
 * @param string $text Text.
 * @return string
 */
function wpjc_cdata( $text ) {
	return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', (string) $text ) . ']]>';
}

/**
 * Feed words for a job type (fulltime, parttime, contract, temporary, internship, volunteer).
 *
 * @param WP_Post $job Job.
 * @return string
 */
function wpjc_feed_jobtype( WP_Post $job ) {
	$types = get_the_terms( $job, 'wpjc_job_type' );
	if ( ! $types || is_wp_error( $types ) ) {
		return '';
	}
	$map = array(
		'full'      => 'fulltime',
		'part'      => 'parttime',
		'contract'  => 'contract',
		'freelance' => 'contract',
		'temp'      => 'temporary',
		'intern'    => 'internship',
		'volunt'    => 'volunteer',
	);
	$out = array();
	foreach ( $types as $t ) {
		$hit = '';
		foreach ( $map as $needle => $value ) {
			if ( false !== strpos( $t->slug, $needle ) ) {
				$hit = $value;
				break;
			}
		}
		$out[] = '' !== $hit ? $hit : wpjc_term_text( $t );
	}
	return implode( ', ', array_unique( $out ) );
}

/** XML job feed. */
function wpjc_render_xml_feed() {
	$jobs    = wpjc_feed_jobs();
	$brand   = wpjc_brand();
	$country = wpjc_board_country();
	$last    = $jobs ? get_post_modified_time( 'U', true, $jobs[0] ) : time();
	header( 'Content-Type: application/xml; charset=UTF-8' );
	header( 'X-Robots-Tag: noindex' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo "<source>\n";
	echo '<publisher>' . esc_xml( $brand['name'] ) . "</publisher>\n";
	echo '<publisherurl>' . esc_url( wpjc_home_url() ) . "</publisherurl>\n";
	echo '<lastBuildDate>' . esc_xml( gmdate( 'D, d M Y H:i:s', (int) $last ) . ' GMT' ) . "</lastBuildDate>\n";
	foreach ( $jobs as $job ) {
		$company = wpjc_job_company( $job );
		$place   = (string) wpjc_meta( $job, 'location' );
		$remote  = (bool) wpjc_meta( $job, 'remote' );
		$expires = (string) wpjc_meta( $job, 'expires' );
		$cats    = get_the_terms( $job, 'wpjc_job_category' );
		$salary  = wpjc_job_salary_text( $job );
		$logo    = wpjc_logo_url( $company['logo'] );
		$fields  = array(
			'title'           => wp_strip_all_tags( get_the_title( $job ) ),
			'date'            => get_post_time( 'D, d M Y H:i:s', true, $job ) . ' GMT',
			'referencenumber' => (string) $job->ID,
			'url'             => get_permalink( $job ),
			'company'         => $company['name'],
			'city'            => $place,
			'country'         => $country,
			'description'     => wpautop( wp_kses_post( $job->post_content ) ),
			'salary'          => $salary,
			'jobtype'         => wpjc_feed_jobtype( $job ),
			'category'        => ( $cats && ! is_wp_error( $cats ) ) ? implode( ', ', array_map( 'wpjc_term_text', $cats ) ) : '',
			'expirationdate'  => $expires ? gmdate( 'D, d M Y', (int) strtotime( $expires ) ) : '',
			'remotetype'      => $remote ? ( '' === $place ? 'Fully remote' : 'Hybrid remote' ) : '',
			'logo'            => $logo,
		);
		$fields  = (array) apply_filters( 'wpjc_xml_feed_job', $fields, $job );
		echo "<job>\n";
		foreach ( $fields as $tag => $value ) {
			if ( '' === (string) $value ) {
				continue;
			}
			$tag = preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $tag ) );
			echo '<' . $tag . '>' . wpjc_cdata( (string) $value ) . '</' . $tag . ">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CDATA, tag name is a-z0-9.
		}
		echo "</job>\n";
	}
	echo "</source>\n";
}

/** RSS 2.0 job feed. */
function wpjc_render_rss_feed() {
	$jobs  = wpjc_feed_jobs();
	$brand = wpjc_brand();
	header( 'Content-Type: application/rss+xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n";
	echo '<title>' . esc_xml( $brand['name'] ) . "</title>\n";
	echo '<link>' . esc_url( wpjc_home_url() ) . "</link>\n";
	echo '<atom:link href="' . esc_url( wpjc_current_url() ) . '" rel="self" type="application/rss+xml"/>' . "\n";
	echo '<description>' . esc_xml( (string) wpjc_opt( 'list_intro' ) ) . "</description>\n";
	echo '<language>' . esc_xml( get_bloginfo( 'language' ) ) . "</language>\n";
	echo '<lastBuildDate>' . esc_xml( gmdate( 'D, d M Y H:i:s', $jobs ? (int) get_post_modified_time( 'U', true, $jobs[0] ) : time() ) . ' +0000' ) . "</lastBuildDate>\n";
	foreach ( $jobs as $job ) {
		$company = wpjc_job_company( $job );
		$place   = (string) wpjc_meta( $job, 'location' );
		$bits    = array_filter( array( $company['name'], $place, wpjc_job_salary_text( $job ) ) );
		echo "<item>\n";
		echo '<title>' . esc_xml( wp_strip_all_tags( get_the_title( $job ) ) . ( $company['name'] ? ' — ' . $company['name'] : '' ) ) . "</title>\n";
		echo '<link>' . esc_url( get_permalink( $job ) ) . "</link>\n";
		echo '<guid isPermaLink="false">' . esc_xml( 'wpjc-job-' . $job->ID ) . "</guid>\n";
		echo '<pubDate>' . esc_xml( get_post_time( 'D, d M Y H:i:s', true, $job ) . ' +0000' ) . "</pubDate>\n";
		$cats = get_the_terms( $job, 'wpjc_job_category' );
		foreach ( ( $cats && ! is_wp_error( $cats ) ) ? $cats : array() as $cat ) {
			echo '<category>' . esc_xml( wpjc_term_text( $cat ) ) . "</category>\n";
		}
		echo '<description>' . wpjc_cdata( ( $bits ? '<p><strong>' . esc_html( implode( ' · ', $bits ) ) . '</strong></p>' : '' ) . wpautop( wp_kses_post( wp_trim_words( wp_strip_all_tags( $job->post_content ), 80 ) ) ) ) . "</description>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CDATA of escaped HTML.
		echo "</item>\n";
	}
	echo "</channel></rss>\n";
}

// Feed reader discovery on board pages.
add_action(
	'wp_head',
	static function () {
		if ( ! (int) wpjc_opt( 'feeds_on' ) || ! function_exists( 'wpjc_is_jobs_view' ) || ! wpjc_is_jobs_view() ) {
			return;
		}
		/* translators: %s: board name */
		$title = sprintf( __( '%s — new jobs', 'jobcore' ), wpjc_brand()['name'] );
		echo '<link rel="alternate" type="application/rss+xml" title="' . esc_attr( $title ) . '" href="' . esc_url( wpjc_feed_url( 'rss' ) ) . '">' . "\n";
	}
);

// Sitemap: only jobs people can still apply to.
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $post_type ) {
		if ( 'wpjc_job' !== $post_type ) {
			return $args;
		}
		$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'AND',
			array(
				'relation' => 'OR',
				array(
					'key'     => '_wpjc_filled',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_wpjc_filled',
					'value'   => array( '', '0' ),
					'compare' => 'IN',
				),
			),
			array(
				'relation' => 'OR',
				array(
					'key'     => '_wpjc_expires',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => '_wpjc_expires',
					'value' => '',
				),
				array(
					'key'     => '_wpjc_expires',
					'value'   => wp_date( 'Y-m-d' ),
					'compare' => '>=',
					'type'    => 'DATE',
				),
			),
		);
		return $args;
	},
	10,
	2
);
