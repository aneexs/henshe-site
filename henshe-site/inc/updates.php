<?php
/**
 * Updates from GitHub: WordPress checks the latest release of
 * github.com/aneexs/henshe-site and offers it under Plugins like
 * any store plugin (auto-updates work too).
 * Hooked through the "Update URI" header in henshe-site.php.
 */

defined( 'ABSPATH' ) || exit;

const HENSHE_SITE_REPO = 'aneexs/henshe-site';

/* Latest release from GitHub, cached for an hour (GitHub allows 60 calls/hour) */
function henshe_site_release( $fresh = false ) {
	$release = $fresh ? false : get_site_transient( 'henshe_site_release' );
	if ( false !== $release ) {
		return $release ?: null;
	}
	$release  = array();
	$response = wp_remote_get( 'https://api.github.com/repos/' . HENSHE_SITE_REPO . '/releases/latest', array(
		'timeout' => 10,
		'headers' => array( 'Accept' => 'application/vnd.github+json' ),
	) );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( 200 === wp_remote_retrieve_response_code( $response ) && ! empty( $data['tag_name'] ) ) {
		foreach ( $data['assets'] ?? array() as $asset ) {
			if ( 'henshe-site.zip' === $asset['name'] ) {
				$release = array(
					'version' => ltrim( $data['tag_name'], 'vV' ),
					'package' => $asset['browser_download_url'],
					'url'     => $data['html_url'],
					'notes'   => $data['body'] ?? '',
					'date'    => $data['published_at'] ?? '',
				);
			}
		}
	}
	set_site_transient( 'henshe_site_release', $release, HOUR_IN_SECONDS );
	return $release ?: null;
}

/* Tell WordPress about the newest version */
add_filter( 'update_plugins_github.com', function ( $update, $plugin_data, $plugin_file ) {
	if ( plugin_basename( HENSHE_SITE_FILE ) !== $plugin_file ) {
		return $update;
	}
	$release = henshe_site_release();
	if ( ! $release ) {
		return $update;
	}
	return array(
		'id'           => 'github.com/' . HENSHE_SITE_REPO,
		'slug'         => 'henshe-site',
		'version'      => $release['version'],
		'url'          => 'https://github.com/' . HENSHE_SITE_REPO,
		'package'      => $release['package'],
		'requires_php' => '8.0',
	);
}, 10, 3 );

/* "View details" window under Plugins */
add_filter( 'plugins_api', function ( $result, $action, $args ) {
	if ( 'plugin_information' !== $action || 'henshe-site' !== ( $args->slug ?? '' ) ) {
		return $result;
	}
	$release = henshe_site_release();
	if ( ! $release ) {
		return $result;
	}
	return (object) array(
		'name'          => 'Henshe Site',
		'slug'          => 'henshe-site',
		'version'       => $release['version'],
		'author'        => 'Kaleo',
		'homepage'      => 'https://github.com/' . HENSHE_SITE_REPO,
		'download_link' => $release['package'],
		'last_updated'  => $release['date'],
		'requires_php'  => '8.0',
		'sections'      => array(
			'changelog' => wpautop( esc_html( $release['notes'] ?: 'See ' . $release['url'] ) ),
		),
	);
}, 10, 3 );

/* "Check again" under Dashboard > Updates asks GitHub straight away
   (priority 1: before WordPress runs its own plugin check on this page) */
add_action( 'load-update-core.php', function () {
	if ( ! empty( $_GET['force-check'] ) ) {
		delete_site_transient( 'henshe_site_release' );
	}
}, 1 );

/* "Update now" for admins over the REST API: GET shows installed and
   latest version, POST installs the latest release straight away.
   /wp-json/henshe/v1/update (needs the update_plugins capability) */
add_action( 'rest_api_init', function () {
	register_rest_route( 'henshe/v1', '/update', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => fn() => current_user_can( 'update_plugins' ),
			'callback'            => function () {
				$release = henshe_site_release( true );
				return array(
					'installed' => HENSHE_SITE_VERSION,
					'latest'    => $release['version'] ?? null,
				);
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => fn() => current_user_can( 'update_plugins' ),
			'callback'            => 'henshe_site_update_now',
		),
	) );
} );

function henshe_site_update_now() {
	$release = henshe_site_release( true );
	if ( ! $release ) {
		return new WP_Error( 'henshe_no_release', 'Could not read the latest release from GitHub.', array( 'status' => 502 ) );
	}
	if ( ! version_compare( $release['version'], HENSHE_SITE_VERSION, '>' ) ) {
		return array( 'updated' => false, 'installed' => HENSHE_SITE_VERSION, 'message' => 'Already up to date.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	if ( ! WP_Filesystem() ) {
		return new WP_Error( 'henshe_filesystem', 'WordPress cannot write plugin files on this server.', array( 'status' => 500 ) );
	}

	// Fresh update list so the upgrader sees the new release.
	delete_site_transient( 'update_plugins' );
	wp_update_plugins();

	$file       = plugin_basename( HENSHE_SITE_FILE );
	$was_active = is_plugin_active( $file );
	$skin       = new WP_Ajax_Upgrader_Skin();
	$upgrader   = new Plugin_Upgrader( $skin );
	$result     = $upgrader->upgrade( $file );

	// The upgrader switches the plugin off while copying; switch it back on.
	if ( $was_active && ! is_plugin_active( $file ) ) {
		update_option( 'active_plugins', array_values( array_unique( array_merge( get_option( 'active_plugins', array() ), array( $file ) ) ) ) );
	}

	if ( true !== $result ) {
		$error = is_wp_error( $result ) ? $result : $skin->get_errors();
		return new WP_Error( 'henshe_update_failed', $error->get_error_message() ?: 'Update failed.', array( 'status' => 500 ) );
	}
	$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );
	return array( 'updated' => true, 'installed' => $data['Version'], 'active' => is_plugin_active( $file ) );
}
