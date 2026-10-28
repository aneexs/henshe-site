<?php
/**
 * Maintenance mode: visitors get the "Maintenance" page (Pages > Maintenance) with a 503 status and
 * Retry-After, so Google treats the break as temporary and keeps the site's rankings.
 * Logged-in admins and editors still see the whole site; wp-admin, login and the REST API keep working.
 */

defined( 'ABSPATH' ) || exit;

/* The text comes from a page with the slug "maintenance" (kept as a Draft, so it's never public).
   Edit it like any page: Pages > Maintenance. Created once, with the word "Maintenance", if it's missing. */
function hs_maintenance_page() {
	return get_page_by_path( 'maintenance', OBJECT, 'page' );
}

add_action( 'admin_init', function () {
	if ( hs_maintenance_page() || ! current_user_can( 'edit_pages' ) ) {
		return;
	}
	wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => 'Maintenance',
		'post_name'    => 'maintenance',
		'post_content' => "<!-- wp:paragraph -->\n<p>Maintenance</p>\n<!-- /wp:paragraph -->",
	) );
} );

add_action( 'template_redirect', function () {
	if ( current_user_can( 'edit_posts' ) ) {
		return;
	}
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // Breeze: never cache the maintenance page
	}
	nocache_headers();
	status_header( 503 );
	header( 'Retry-After: 86400' );
	header( 'Content-Type: text/html; charset=utf-8' );

	$page = hs_maintenance_page();
	$body = $page && trim( $page->post_content ) ? apply_filters( 'the_content', $page->post_content ) : '<p>Maintenance</p>';
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?php echo esc_html( $page ? get_the_title( $page ) : 'Maintenance' ); ?></title>
<?php wp_print_styles( 'wp-block-library' ); ?>
<style>
	html, body { height: 100%; margin: 0; }
	body { display: grid; place-items: center; padding: 24px; box-sizing: border-box; background: #fff; color: #111; text-align: center;
		font: 600 28px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
	main { max-width: 720px; }
	main > :first-child { margin-top: 0; }
	main > :last-child { margin-bottom: 0; }
	main img { max-width: 100%; height: auto; }
</style>
</head>
<body><main><?php echo $body; // phpcs:ignore WordPress.Security.EscapeOutput -- the page's own blocks ?></main></body>
</html>
	<?php
	exit;
}, 0 );

/* A clear reminder in the admin bar while it's on */
add_action( 'admin_bar_menu', function ( $bar ) {
	$bar->add_node( array(
		'id'    => 'hs-maintenance',
		'title' => '<span style="background:#e10600;color:#fff;padding:2px 8px;border-radius:3px">Maintenance mode ON</span>',
		'href'  => admin_url( 'options-general.php?page=henshe-site' ),
	) );
}, 100 );
