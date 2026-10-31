<?php
/**
 * REST access to The SEO Framework's fields, so stories uploaded
 * through the REST API can carry their own SEO title and description.
 * Nothing here changes what visitors see; it only opens the fields.
 */

defined( 'ABSPATH' ) || exit;

/* Per post / page: title, description, "don't add site name" */
add_action( 'init', function () {
	$fields = array(
		'_genesis_title'         => 'string',
		'_genesis_description'   => 'string',
		'_tsf_title_no_blogname' => 'integer',
	);
	foreach ( array( 'post', 'page' ) as $type ) {
		foreach ( $fields as $key => $kind ) {
			register_post_meta( $type, $key, array(
				'type'          => $kind,
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			) );
		}
	}
} );

/* Site-wide settings of The SEO Framework (admins only).
 * GET returns them; POST merges the given keys into them. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'henshe/v1', '/seo-settings', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'callback'            => fn() => get_option( 'autodescription-site-settings', array() ),
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
			'callback'            => function ( WP_REST_Request $request ) {
				$changes = $request->get_json_params();
				if ( ! is_array( $changes ) || ! $changes ) {
					return new WP_Error( 'henshe_empty', 'Send a JSON object of settings.', array( 'status' => 400 ) );
				}
				$settings = get_option( 'autodescription-site-settings', array() );
				$settings = array_merge( is_array( $settings ) ? $settings : array(), $changes );
				update_option( 'autodescription-site-settings', $settings );
				return get_option( 'autodescription-site-settings', array() );
			},
		),
	) );
} );
