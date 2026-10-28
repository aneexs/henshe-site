<?php
/**
 * Reactions (Love, Like, Keep It Up, Blast) at the end of each post.
 * Moved here from the separate Kaleo Reactions plugin. Counts, the REST address and saved choices are unchanged,
 * so nothing is lost when switching over.
 */

defined( 'ABSPATH' ) || exit;

function hs_reactions_list() {
	return array(
		'love'       => 'Love',
		'like'       => 'Like',
		'keep-it-up' => 'Keep It Up',
		'blast'      => 'Blast',
	);
}

/* Line icons from Phosphor (MIT): outline and filled shapes. */
function hs_reactions_icons() {
	return array(
		'love' => array( '<path fill="currentColor" d="M178 40c-20.65 0-38.73 8.88-50 23.89C116.73 48.88 98.65 40 78 40a62.07 62.07 0 0 0-62 62c0 70 103.79 126.66 108.21 129a8 8 0 0 0 7.58 0C136.21 228.66 240 172 240 102a62.07 62.07 0 0 0-62-62m-50 174.8c-18.26-10.64-96-59.11-96-112.8a46.06 46.06 0 0 1 46-46c19.45 0 35.78 10.36 42.6 27a8 8 0 0 0 14.8 0c6.82-16.67 23.15-27 42.6-27a46.06 46.06 0 0 1 46 46c0 53.61-77.76 102.15-96 112.8"/>', '<path fill="currentColor" d="M240 102c0 70-103.79 126.66-108.21 129a8 8 0 0 1-7.58 0C119.79 228.66 16 172 16 102a62.07 62.07 0 0 1 62-62c20.65 0 38.73 8.88 50 23.89C139.27 48.88 157.35 40 178 40a62.07 62.07 0 0 1 62 62"/>' ),
		'like' => array( '<path fill="currentColor" d="M234 80.12A24 24 0 0 0 216 72h-56V56a40 40 0 0 0-40-40a8 8 0 0 0-7.16 4.42L75.06 96H32a16 16 0 0 0-16 16v88a16 16 0 0 0 16 16h172a24 24 0 0 0 23.82-21l12-96A24 24 0 0 0 234 80.12M32 112h40v88H32Zm191.94-15l-12 96a8 8 0 0 1-7.94 7H88v-94.11l36.71-73.43A24 24 0 0 1 144 56v24a8 8 0 0 0 8 8h64a8 8 0 0 1 7.94 9"/>', '<path fill="currentColor" d="M234 80.12A24 24 0 0 0 216 72h-56V56a40 40 0 0 0-40-40a8 8 0 0 0-7.16 4.42L75.06 96H32a16 16 0 0 0-16 16v88a16 16 0 0 0 16 16h172a24 24 0 0 0 23.82-21l12-96A24 24 0 0 0 234 80.12M32 112h40v88H32Z"/>' ),
		'keep-it-up' => array( '<path fill="currentColor" d="M200 80h-16V64a32 32 0 0 0-56-21.13a32 32 0 0 0-55.79 17.55A32 32 0 0 0 24 88v40a104 104 0 0 0 208 0v-16a32 32 0 0 0-32-32m-48-32a16 16 0 0 1 16 16v16h-32V64a16 16 0 0 1 16-16M88 64a16 16 0 0 1 32 0v40a16 16 0 0 1-32 0ZM40 88a16 16 0 0 1 32 0v16a16 16 0 0 1-32 0Zm176 40a88 88 0 0 1-175.92 3.75A31.93 31.93 0 0 0 80 125.13a31.93 31.93 0 0 0 44.58 3.35a32.2 32.2 0 0 0 11.8 11.44A47.88 47.88 0 0 0 120 176a8 8 0 0 0 16 0a32 32 0 0 1 32-32a8 8 0 0 0 0-16h-16a16 16 0 0 1-16-16V96h64a16 16 0 0 1 16 16Z"/>', '<path fill="currentColor" d="M232 120v8a104 104 0 0 1-104.37 104c-54-.19-98-42.06-103.12-94.78a4 4 0 0 1 5.56-4A35.94 35.94 0 0 0 72 122.59a35.92 35.92 0 0 0 53.94 2.33a40.36 40.36 0 0 0 12.87 13A47.94 47.94 0 0 0 120 176a8 8 0 0 0 8.67 8a8.21 8.21 0 0 0 7.33-8.26A32 32 0 0 1 168 144a8 8 0 0 0 8-8.53a8.18 8.18 0 0 0-8.25-7.47H160a24 24 0 0 1-24-24V88h64a32 32 0 0 1 32 32m-187.27 0C55.57 119.6 64 110.37 64 99.52v-23C64 65.63 55.57 56.4 44.73 56A20 20 0 0 0 24 76v24a20 20 0 0 0 20.73 20m56 0c10.84-.39 19.27-9.62 19.27-20.47v-47c0-10.85-8.43-20.08-19.27-20.47A20 20 0 0 0 80 52v48a20 20 0 0 0 20.73 20M176 52a20 20 0 0 0-20.73-20c-10.84.4-19.27 9.63-19.27 20.48V72h36a4 4 0 0 0 4-4Z"/>' ),
		'blast' => array( '<path fill="currentColor" d="M183.89 153.34a57.6 57.6 0 0 1-46.56 46.55a9 9 0 0 1-1.33.11a8 8 0 0 1-1.32-15.89c16.57-2.79 30.63-16.85 33.44-33.45a8 8 0 0 1 15.78 2.68ZM216 144a88 88 0 0 1-176 0c0-27.92 11-56.47 32.66-84.85a8 8 0 0 1 11.93-.89l24.12 23.41l22-60.41a8 8 0 0 1 12.63-3.41C165.21 36 216 84.55 216 144m-16 0c0-46.09-35.79-85.92-58.21-106.33l-22.27 61.07a8 8 0 0 1-13.09 3L80.06 76.16C64.09 99.21 56 122 56 144a72 72 0 0 0 144 0"/>', '<path fill="currentColor" d="M143.38 17.85a8 8 0 0 0-12.63 3.41l-22 60.41l-24.16-23.41a8 8 0 0 0-11.93.89C51 87.53 40 116.08 40 144a88 88 0 0 0 176 0c0-59.45-50.79-108-72.62-126.15m40.51 135.49a57.6 57.6 0 0 1-46.56 46.55a7.7 7.7 0 0 1-1.33.11a8 8 0 0 1-1.32-15.89c16.57-2.79 30.63-16.85 33.44-33.45a8 8 0 0 1 15.78 2.68Z"/>' ),
	);
}

function hs_reactions_counts( $post_id ) {
	$counts = array();
	foreach ( array_keys( hs_reactions_list() ) as $key ) {
		$counts[ $key ] = max( 0, (int) get_post_meta( $post_id, '_kaleo_r_' . $key, true ) );
	}
	return $counts;
}

/* Show the reaction bar at the end of single posts. */
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$post_id = get_the_ID();
	$counts  = hs_reactions_counts( $post_id );

	$html = '<div class="kaleo-reactions" data-post="' . esc_attr( $post_id ) . '">';
	$html .= '<p class="kaleo-reactions__title">How would you rate this?</p>';
	$html .= '<div class="kaleo-reactions__row">';
	$icons = hs_reactions_icons();
	foreach ( hs_reactions_list() as $key => $label ) {
		$html .= sprintf(
			'<button type="button" class="kaleo-reactions__btn" data-reaction="%1$s" aria-pressed="false" aria-label="%2$s"><svg class="kaleo-reactions__icon" viewBox="0 0 256 256" aria-hidden="true" focusable="false"><g class="kaleo-reactions__line">%3$s</g><g class="kaleo-reactions__fill">%5$s</g></svg><span class="kaleo-reactions__label">%2$s</span><span class="kaleo-reactions__count">%4$s</span></button>',
			esc_attr( $key ),
			esc_html( $label ),
			$icons[ $key ][0],
			$counts[ $key ] ? esc_html( number_format_i18n( $counts[ $key ] ) ) : '',
			$icons[ $key ][1]
		);
	}
	$html .= '</div></div>';

	return $content . $html;
}, 20 );

/* Load the small CSS and JS on single posts only. */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$url = HENSHE_SITE_URL . 'assets/';
	wp_enqueue_style( 'kaleo-reactions', $url . 'reactions.css', array(), HENSHE_SITE_VERSION );
	wp_enqueue_script( 'kaleo-reactions', $url . 'reactions.js', array(), HENSHE_SITE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_localize_script( 'kaleo-reactions', 'kaleoReactions', array( 'api' => esc_url_raw( rest_url( 'kaleo/v1/reactions/' ) ) ) );
} );

/* REST: read counts, and change a visitor's reaction. */
add_action( 'rest_api_init', function () {
	$id_arg = array(
		'id' => array(
			'validate_callback' => function ( $id ) {
				return 'post' === get_post_type( (int) $id ) && 'publish' === get_post_status( (int) $id );
			},
		),
	);

	register_rest_route( 'kaleo/v1', '/reactions/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => '__return_true',
			'args'                => $id_arg,
			'callback'            => function ( WP_REST_Request $req ) {
				return hs_reactions_response( hs_reactions_counts( (int) $req['id'] ) );
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => '__return_true',
			'args'                => $id_arg,
			'callback'            => 'hs_reactions_change',
		),
	) );
} );

function hs_reactions_response( $counts ) {
	$res = rest_ensure_response( array( 'counts' => $counts ) );
	$res->header( 'Cache-Control', 'no-store, max-age=0' );
	return $res;
}

function hs_reactions_change( WP_REST_Request $req ) {
	$post_id = (int) $req['id'];
	$valid   = array_keys( hs_reactions_list() );
	$add     = in_array( $req['add'], $valid, true ) ? $req['add'] : null;
	$remove  = in_array( $req['remove'], $valid, true ) ? $req['remove'] : null;

	if ( ! $add && ! $remove ) {
		return new WP_Error( 'hs_reactions_bad', 'Unknown reaction.', array( 'status' => 400 ) );
	}

	// Light spam guard: at most 30 changes per visitor IP per hour.
	$ip_key = 'kaleo_r_ip_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
	$hits   = (int) get_transient( $ip_key );
	if ( $hits >= 30 ) {
		return new WP_Error( 'hs_reactions_slow', 'Too many reactions, try again later.', array( 'status' => 429 ) );
	}
	set_transient( $ip_key, $hits + 1, HOUR_IN_SECONDS );

	if ( $add ) {
		hs_reactions_bump( $post_id, $add, 1 );
	}
	if ( $remove && $remove !== $add ) {
		hs_reactions_bump( $post_id, $remove, -1 );
	}

	return hs_reactions_response( hs_reactions_counts( $post_id ) );
}

/* Change a count in one SQL statement so taps at the same moment don't get lost. */
function hs_reactions_bump( $post_id, $key, $delta ) {
	global $wpdb;
	$meta_key = '_kaleo_r_' . $key;

	if ( '' === get_post_meta( $post_id, $meta_key, true ) ) {
		add_post_meta( $post_id, $meta_key, 0, true );
	}
	$wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->postmeta} SET meta_value = GREATEST(0, CAST(meta_value AS SIGNED) + %d) WHERE post_id = %d AND meta_key = %s",
		$delta,
		$post_id,
		$meta_key
	) );
	wp_cache_delete( $post_id, 'post_meta' );
}
