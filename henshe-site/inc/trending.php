<?php
/**
 * [hs_trending] — sliding banner of the stories with the most reactions.
 * Totals are the sum of the four reaction counts (_kaleo_r_* post meta).
 * If fewer stories have reactions than asked for, the newest stories fill the rest.
 */

defined( 'ABSPATH' ) || exit;

function hs_trending_posts( $count ) {
	global $wpdb;
	$keys = array( '_kaleo_r_love', '_kaleo_r_like', '_kaleo_r_keep-it-up', '_kaleo_r_blast' );
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT m.post_id AS id, SUM(CAST(m.meta_value AS UNSIGNED)) AS total
		 FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
		 WHERE m.meta_key IN (%s, %s, %s, %s) AND p.post_type = 'post' AND p.post_status = 'publish'
		 GROUP BY m.post_id HAVING total > 0 ORDER BY total DESC, p.post_date DESC LIMIT %d",
		array_merge( $keys, array( $count ) )
	) );
	$posts = array();
	foreach ( $rows as $row ) {
		$posts[ (int) $row->id ] = (int) $row->total;
	}
	if ( count( $posts ) < $count ) {
		$more = get_posts( array(
			'numberposts' => $count - count( $posts ),
			'exclude'     => array_keys( $posts ),
			'fields'      => 'ids',
		) );
		foreach ( $more as $id ) {
			$posts[ $id ] = 0;
		}
	}
	return $posts;
}

add_shortcode( 'hs_trending', function ( $atts ) {
	$atts  = shortcode_atts( array( 'count' => 5 ), $atts );
	$posts = hs_trending_posts( max( 1, (int) $atts['count'] ) );
	if ( ! $posts ) {
		return '';
	}

	wp_enqueue_style( 'henshe-trending', HENSHE_SITE_URL . 'assets/trending.css', array(), HENSHE_SITE_VERSION );
	wp_enqueue_script( 'henshe-trending', HENSHE_SITE_URL . 'assets/trending.js', array(), HENSHE_SITE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	// A smiley: the count is all four reactions together, so no single reaction's icon.
	$icon  = '<svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="9.2"/><path d="M8.2 14.2a4.6 4.6 0 0 0 7.6 0"/><circle cx="9" cy="9.8" r=".6" fill="currentColor"/><circle cx="15" cy="9.8" r=".6" fill="currentColor"/></svg>';
	$arrow = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

	$cards = '';
	foreach ( $posts as $id => $total ) {
		$link   = esc_url( get_permalink( $id ) );
		$label  = sprintf( _n( '%s reaction', '%s reactions', $total ), number_format_i18n( $total ) );
		$cards .= '<li class="hs-trend__card">'
			. '<a class="hs-trend__photo" href="' . $link . '" tabindex="-1" aria-hidden="true">' . get_the_post_thumbnail( $id, 'medium_large', array( 'alt' => '' ) ) . '</a>'
			. '<h3 class="hs-trend__title"><a href="' . $link . '">' . esc_html( get_the_title( $id ) ) . '</a></h3>'
			. '<p class="hs-trend__count" aria-label="' . esc_attr( $label ) . '">' . $icon . '<span>' . number_format_i18n( $total ) . '</span></p>'
			. '</li>';
	}
	// Endless loop: the list is drawn three times and the script keeps the view on the middle copy.
	$copy = str_replace(
		array( '<li class="hs-trend__card">', '<h3 class="hs-trend__title"><a href' ),
		array( '<li class="hs-trend__card" aria-hidden="true">', '<h3 class="hs-trend__title"><a tabindex="-1" href' ),
		$cards
	);

	return '<div class="hs-trend" data-count="' . count( $posts ) . '">'
		. '<button type="button" class="hs-trend__arrow hs-trend__prev" aria-label="Previous stories">' . $arrow . '</button>'
		. '<ol class="hs-trend__track">' . $copy . $cards . $copy . '</ol>'
		. '<button type="button" class="hs-trend__arrow hs-trend__next" aria-label="Next stories">' . $arrow . '</button>'
		. '</div>';
} );
