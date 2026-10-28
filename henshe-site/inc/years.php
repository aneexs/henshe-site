<?php
/**
 * [hs_year_filter] — year buttons above the All Stories grid (All, 2025, 2024 …).
 * A button adds ?yr=2024 to the page address; the stories grid on that page
 * (a query with the class "hs-stories") then shows only that year's stories.
 */

defined( 'ABSPATH' ) || exit;

function hs_years_chosen() {
	return isset( $_GET['yr'] ) ? absint( $_GET['yr'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
}

add_shortcode( 'hs_year_filter', function () {
	global $wpdb;
	$years = $wpdb->get_col( "SELECT DISTINCT YEAR(post_date) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY 1 DESC" );
	if ( count( $years ) < 2 ) {
		return '';
	}
	wp_enqueue_style( 'henshe-years', HENSHE_SITE_URL . 'assets/years.css', array(), HENSHE_SITE_VERSION );

	$base   = get_permalink();
	$chosen = hs_years_chosen();
	$html   = '<nav class="hs-years" aria-label="Stories by year">';
	$html  .= '<a href="' . esc_url( $base ) . '"' . ( $chosen ? '' : ' aria-current="page"' ) . '>All</a>';
	foreach ( $years as $year ) {
		$html .= '<a href="' . esc_url( add_query_arg( 'yr', $year, $base ) ) . '"' . ( (int) $year === $chosen ? ' aria-current="page"' : '' ) . '>' . esc_html( $year ) . '</a>';
	}
	return $html . '</nav>';
} );

add_filter( 'pre_render_block', function ( $pre, $block ) {
	if ( 'core/query' === $block['blockName'] ) {
		$GLOBALS['henshe_site_years'] = str_contains( $block['attrs']['className'] ?? '', 'hs-stories' ) && is_page();
	}
	return $pre;
}, 10, 2 );

add_filter( 'query_loop_block_query_vars', function ( $query ) {
	if ( ! empty( $GLOBALS['henshe_site_years'] ) && hs_years_chosen() ) {
		$query['date_query'] = array( array( 'year' => hs_years_chosen() ) );
	}
	return $query;
} );
