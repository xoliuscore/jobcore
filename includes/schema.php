<?php
/**
 * Google Jobs: JobPosting structured data on single job pages.
 * Turn it off with add_filter( 'wpjc_job_schema', '__return_false' ).
 *
 * @package JobCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * JobPosting data for a job (null when the job should not be marked up).
 *
 * @param WP_Post $job Job.
 * @return array|null
 */
function wpjc_job_schema( WP_Post $job ) {
	if ( wpjc_meta( $job, 'filled' ) ) {
		return null;
	}
	$company = wpjc_job_company( $job );
	$data    = array(
		'@context'      => 'https://schema.org/',
		'@type'         => 'JobPosting',
		'title'         => wp_strip_all_tags( get_the_title( $job ) ),
		'description'   => wpautop( wp_kses_post( $job->post_content ) ),
		'datePosted'    => get_post_time( 'c', true, $job ),
		'url'           => get_permalink( $job ),
		'identifier'    => array(
			'@type' => 'PropertyValue',
			'name'  => '' !== $company['name'] ? $company['name'] : get_bloginfo( 'name' ),
			'value' => (string) $job->ID,
		),
	);
	$expires = wpjc_meta( $job, 'expires' );
	if ( $expires && strtotime( $expires ) ) {
		$data['validThrough'] = gmdate( 'c', strtotime( $expires . ' 23:59:59' ) );
	}
	if ( '' !== $company['name'] ) {
		$org = array(
			'@type' => 'Organization',
			'name'  => $company['name'],
		);
		if ( $company['website'] ) {
			$org['sameAs'] = esc_url_raw( $company['website'] );
		}
		$logo = wpjc_logo_url( $company['logo'] );
		if ( $logo ) {
			$org['logo'] = esc_url_raw( $logo );
		}
		$data['hiringOrganization'] = $org;
	}
	$location = (string) wpjc_meta( $job, 'location' );
	$remote   = (bool) wpjc_meta( $job, 'remote' );
	$country  = wpjc_board_country();
	if ( '' !== $location ) {
		$address = array(
			'@type'           => 'PostalAddress',
			'addressLocality' => $location,
		);
		if ( '' !== $country ) {
			$address['addressCountry'] = $country;
		}
		$data['jobLocation'] = array(
			'@type'   => 'Place',
			'address' => $address,
		);
	}
	// Fully remote (no place): Google wants TELECOMMUTE plus where applicants may live.
	if ( $remote && '' === $location ) {
		$data['jobLocationType'] = 'TELECOMMUTE';
		if ( '' !== $country ) {
			$data['applicantLocationRequirements'] = array(
				'@type' => 'Country',
				'name'  => $country,
			);
		}
	}
	$salary = wpjc_parse_salary( wpjc_job_salary_text( $job ) );
	if ( $salary ) {
		$value = array(
			'@type'    => 'QuantitativeValue',
			'unitText' => $salary['unit'],
		);
		if ( $salary['min'] < $salary['max'] ) {
			$value['minValue'] = $salary['min'];
			$value['maxValue'] = $salary['max'];
		} else {
			$value['value'] = $salary['min'];
		}
		$data['baseSalary'] = array(
			'@type'    => 'MonetaryAmount',
			'currency' => $salary['currency'],
			'value'    => $value,
		);
	}
	// Applying happens on this page (our form) rather than on another site.
	$data['directApply'] = '' === (string) wpjc_meta( $job, 'apply_url' ) && '' !== (string) wpjc_meta( $job, 'apply_email' );
	$types = get_the_terms( $job, 'wpjc_job_type' );
	if ( $types && ! is_wp_error( $types ) ) {
		$map = array(
			'full'      => 'FULL_TIME',
			'part'      => 'PART_TIME',
			'contract'  => 'CONTRACTOR',
			'freelance' => 'CONTRACTOR',
			'temp'      => 'TEMPORARY',
			'intern'    => 'INTERN',
			'volunt'    => 'VOLUNTEER',
		);
		$out = array();
		foreach ( $types as $t ) {
			foreach ( $map as $needle => $value ) {
				if ( false !== strpos( $t->slug, $needle ) ) {
					$out[] = $value;
				}
			}
		}
		if ( $out ) {
			$data['employmentType'] = array_values( array_unique( $out ) );
		}
	}
	return (array) apply_filters( 'wpjc_job_schema_data', $data, $job );
}

add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'wpjc_job' ) || ! apply_filters( 'wpjc_job_schema', true ) ) {
			return;
		}
		$data = wpjc_job_schema( get_post() );
		if ( $data ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
		}
	}
);
