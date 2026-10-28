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
