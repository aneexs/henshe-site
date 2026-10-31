<?php
/**
 * Plugin Name: Henshe Site
 * Description: Site features that stay the same whatever theme is used: site styles, dark mode, phone menu, back-to-top, story links, reactions, Gmail sending and small fixes. Switch each part on or off under Settings > Henshe Site.
 * Version: 1.3.12
 * Author: Kaleo
 * Requires PHP: 8.0
 * Update URI: https://github.com/aneexs/henshe-site
 */

defined( 'ABSPATH' ) || exit;

const HENSHE_SITE_VERSION = '1.3.12';
define( 'HENSHE_SITE_FILE', __FILE__ );
define( 'HENSHE_SITE_URL', plugin_dir_url( __FILE__ ) );

/* ---------------------------------------------------------------
 * Switches (Settings > Henshe Site). Everything new starts OFF,
 * so updating the plugin changes nothing until a switch is turned on.
 * ------------------------------------------------------------- */
function henshe_site_features() {
	return array(
		'maintenance' => array( 'Maintenance mode', 'Visitors see only the page "Maintenance" (edit it under Pages; it stays a Draft). Google is told it is temporary. You stay logged in and see the whole site. Purge Breeze after turning it on or off.' ),
		'site_styles' => array( 'Site styles', 'Kaleo look for Twenty Twenty-Five: header, footer, homepage grid, posts, quotes, menu effects.' ),
		'dark_mode'   => array( 'Dark mode', 'Sun / moon button in the header. Turn off if the theme has its own dark mode.' ),
		'phone_menu'  => array( 'Phone menu', 'Menu button on phones and tablets that opens a card under the header.' ),
		'back_to_top' => array( 'Back to top', 'Round arrow button at the bottom right after scrolling down.' ),
		'story_links' => array( 'Story links', 'Breadcrumbs, reading progress bar, read time and text size button, share buttons (Facebook, X, Instagram), small photos on the previous / next story links, and More Stories shows the same category (never the story being read).' ),
		'reactions'   => array( 'Reactions', '"How would you rate this?" buttons (Love, Like, Keep It Up, Blast) at the end of each post.' ),
		'trending'    => array( 'Trending slider', 'Sliding banner of the 5 stories with the most reactions. Put [hs_trending] on a page.' ),
		'year_filter' => array( 'Year filter', 'Year buttons above the All Stories grid. Put [hs_year_filter] above the stories on that page.' ),
	);
}

function henshe_site_on( $key ) {
	$opts = get_option( 'henshe_site_features', array() );
	return ! empty( $opts[ $key ] );
}

add_action( 'init', function () {
	$props = array();
	foreach ( array_keys( henshe_site_features() ) as $key ) {
		$props[ $key ] = array( 'type' => 'boolean' );
	}
	register_setting( 'henshe_site', 'henshe_site_features', array(
		'type'              => 'object',
		'default'           => array(),
		'sanitize_callback' => function ( $value ) {
			$clean = array();
			foreach ( array_keys( henshe_site_features() ) as $key ) {
				$clean[ $key ] = ! empty( $value[ $key ] );
			}
			return $clean;
		},
		'show_in_rest'      => array( 'schema' => array( 'type' => 'object', 'properties' => $props ) ),
	) );
} );

add_action( 'admin_menu', function () {
	add_options_page( 'Henshe Site', 'Henshe Site', 'manage_options', 'henshe-site', function () {
		?>
		<div class="wrap">
			<h1>Henshe Site</h1>
			<p>Turn each part of the site on or off. Purge the Breeze cache after a change so visitors see it.</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'henshe_site' ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( henshe_site_features() as $key => $f ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $f[0] ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="henshe_site_features[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( henshe_site_on( $key ) ); ?>>
									On
								</label>
								<p class="description"><?php echo esc_html( $f[1] ); ?></p>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php do_action( 'henshe_site_settings_after' ); ?>
		</div>
		<?php
	} );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=henshe-site' ) ) . '">Settings</a>' );
	return $links;
} );

/* ---------------------------------------------------------------
 * Styles and the small site script
 * ------------------------------------------------------------- */
add_action( 'wp_enqueue_scripts', function () {
	$url = HENSHE_SITE_URL . 'assets/';
	if ( henshe_site_on( 'site_styles' ) ) {
		wp_enqueue_style( 'henshe-site', $url . 'site.css', array(), HENSHE_SITE_VERSION );
	}
	if ( henshe_site_on( 'phone_menu' ) ) {
		wp_enqueue_style( 'henshe-site-menu', $url . 'menu.css', array(), HENSHE_SITE_VERSION );
	}
	if ( henshe_site_on( 'dark_mode' ) || henshe_site_on( 'phone_menu' ) || henshe_site_on( 'back_to_top' ) || henshe_site_on( 'story_links' ) || henshe_site_on( 'site_styles' ) ) {
		wp_enqueue_script( 'henshe-site', $url . 'site.js', array(), HENSHE_SITE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	}
}, 30 );

/* Dark mode: switch it on before the page draws, so there is no white flash */
add_action( 'wp_head', function () {
	if ( henshe_site_on( 'dark_mode' ) ) {
		echo "<script>try{if(localStorage.getItem('hs-dark')==='1')document.documentElement.classList.add('hs-dark')}catch(e){}</script>\n";
	}
}, 1 );

/* [hs_dark_mode] — the sun / moon button (place it in the header) */
add_shortcode( 'hs_dark_mode', function () {
	if ( ! henshe_site_on( 'dark_mode' ) ) {
		return '';
	}
	return '<button type="button" class="hs-mode" aria-label="Dark mode" aria-pressed="false">'
		. '<svg class="hs-sun" viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 2v2.2M12 19.8V22M2 12h2.2M19.8 12H22M4.9 4.9l1.6 1.6M17.5 17.5l1.6 1.6M4.9 19.1l1.6-1.6M17.5 6.5l1.6-1.6"/></g></svg>'
		. '<svg class="hs-moon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="M20.5 14.3A8.6 8.6 0 0 1 9.7 3.5a8.6 8.6 0 1 0 10.8 10.8Z"/></svg>'
		. '</button>';
} );

/* [hs_menu_button] — the phone menu button and its card (place it in the header) */
add_shortcode( 'hs_menu_button', function () {
	if ( ! henshe_site_on( 'phone_menu' ) ) {
		return '';
	}
	return '<button type="button" class="hs-burger" aria-label="Menu" aria-expanded="false" aria-controls="hs-drop"><span></span><span></span><span></span></button>'
		. '<nav id="hs-drop" class="hs-drop" aria-label="Menu" hidden><ul></ul></nav>';
} );

/* With our phone menu on, the theme's own phone menu is not needed */
add_filter( 'render_block_data', function ( $block ) {
	if ( 'core/navigation' === $block['blockName'] && henshe_site_on( 'phone_menu' ) ) {
		$block['attrs']['overlayMenu'] = 'never';
	}
	return $block;
} );

/* Back to top button */
add_action( 'wp_footer', function () {
	if ( henshe_site_on( 'back_to_top' ) ) {
		echo '<button type="button" class="hs-top" aria-label="Back to top"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' . "\n";
	}
} );

/* ---------------------------------------------------------------
 * Story links
 * ------------------------------------------------------------- */
/* [hs_read_time] — "(Estimated Read Time: 5 Minutes)" under the cover photo (about 200 words a minute, never less than 1) */
add_shortcode( 'hs_read_time', function () {
	if ( ! henshe_site_on( 'story_links' ) || ! is_singular( 'post' ) ) {
		return '';
	}
	$words   = str_word_count( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', get_the_ID() ) ) ) );
	$minutes = max( 1, (int) ceil( $words / 200 ) );
	return '<span class="hs-read-time">' . esc_html( '(Estimated Read Time: ' . $minutes . ( 1 === $minutes ? ' Minute)' : ' Minutes)' ) ) . '</span>';
} );

/* [hs_share] — share buttons under a story. Instagram has no share link for websites,
   so its button opens the phone's share sheet (where Instagram is listed), or copies the link on a computer. */
add_shortcode( 'hs_share', function () {
	if ( ! henshe_site_on( 'story_links' ) || ! is_singular() ) {
		return '';
	}
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( html_entity_decode( get_the_title(), ENT_QUOTES ) );
	$icons = array(
		'facebook'  => '<path fill="currentColor" d="M13.5 21v-7.5H16l.5-3h-3V8.6c0-.9.3-1.6 1.6-1.6h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4V10.5H8v3h2.5V21z"/>',
		'x'         => '<path fill="currentColor" d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.2-8.3L1.8 3h6.4l4.4 5.8zm-1.1 16.2h1.7L7.4 4.7H5.6z"/>',
		'instagram' => '<g fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4.2"/></g><circle cx="17.3" cy="6.7" r="1.2" fill="currentColor"/>',
	);
	$svg = function ( $k ) use ( $icons ) {
		return '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">' . $icons[ $k ] . '</svg>';
	};
	return '<div class="hs-share"><span class="hs-share__label">Share</span>'
		. '<a class="hs-share__btn" href="https://www.facebook.com/sharer/sharer.php?u=' . $url . '" target="_blank" rel="noopener" aria-label="Share on Facebook">' . $svg( 'facebook' ) . '</a>'
		. '<a class="hs-share__btn" href="https://x.com/intent/post?text=' . $title . '&amp;url=' . $url . '" target="_blank" rel="noopener" aria-label="Share on X (Twitter)">' . $svg( 'x' ) . '</a>'
		. '<button type="button" class="hs-share__btn hs-share__ig" data-url="' . esc_url( get_permalink() ) . '" aria-label="Share on Instagram">' . $svg( 'instagram' ) . '</button>'
		. '<span class="hs-share__note" role="status"></span></div>'
		. "<script>document.querySelectorAll('.hs-share__ig').forEach(function(b){b.onclick=function(){var u=b.dataset.url,n=b.parentNode.querySelector('.hs-share__note');"
		. "if(navigator.share&&matchMedia('(pointer:coarse)').matches){navigator.share({title:document.title,url:u}).catch(function(){});return;}"
		. "(navigator.clipboard?navigator.clipboard.writeText(u):Promise.reject()).then(function(){n.textContent='Link copied, paste it in Instagram';setTimeout(function(){n.textContent=''},3000)},function(){prompt('Copy this link for Instagram:',u)});};});</script>";
} );

/* Previous / next story: add a small photo */
add_filter( 'render_block_core/post-navigation-link', function ( $html, $block ) {
	if ( ! henshe_site_on( 'story_links' ) || '' === trim( $html ) ) {
		return $html;
	}
	$post = ( isset( $block['attrs']['type'] ) && 'previous' === $block['attrs']['type'] ) ? get_previous_post() : get_next_post();
	if ( ! $post || ! has_post_thumbnail( $post ) ) {
		return $html;
	}
	$img = get_the_post_thumbnail( $post, 'thumbnail', array( 'class' => 'hs-nav-thumb', 'alt' => '', 'loading' => 'lazy' ) );
	return preg_replace( '/^(\s*<div[^>]*>)/', '$1' . $img, $html, 1 );
}, 10, 2 );

/* More Stories (a query with the class "hs-related"): leave out the story being read */
add_filter( 'pre_render_block', function ( $pre, $block ) {
	if ( 'core/query' === $block['blockName'] ) {
		$GLOBALS['henshe_site_related'] = str_contains( $block['attrs']['className'] ?? '', 'hs-related' );
	}
	return $pre;
}, 10, 2 );
add_filter( 'query_loop_block_query_vars', function ( $query ) {
	if ( ! empty( $GLOBALS['henshe_site_related'] ) && henshe_site_on( 'story_links' ) && is_singular( 'post' ) ) {
		$query['post__not_in'] = array_merge( $query['post__not_in'] ?? array(), array( get_queried_object_id() ) );
		$query['post__in']     = hs_related_ids( get_queried_object_id() );
		$query['orderby']      = 'post__in';
	}
	return $query;
} );

/* ---------------------------------------------------------------
 * Reactions (needs the old "Kaleo Reactions" plugin to be removed)
 * ------------------------------------------------------------- */
if ( henshe_site_on( 'reactions' ) ) {
	add_action( 'plugins_loaded', function () {
		if ( function_exists( 'kaleo_reactions_list' ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-warning"><p><strong>Henshe Site:</strong> reactions are now part of this plugin. Deactivate and delete the old "Kaleo Reactions" plugin; the counts are kept.</p></div>';
			} );
			return;
		}
		require __DIR__ . '/inc/reactions.php';
	} );
}

/* Breadcrumbs, text size, collections, related stories, font fix */
require __DIR__ . '/inc/extras.php';

/* Updates from GitHub (Plugins shows "Update available") */
require __DIR__ . '/inc/updates.php';

/* SEO fields of The SEO Framework in the REST API */
require __DIR__ . '/inc/seo-rest.php';

/* Gmail sending (Settings > Henshe Site > Email) */
require __DIR__ . '/inc/gmail.php';

/* SVG uploads for administrators, cleaned of scripts on upload */
require __DIR__ . '/inc/svg-uploads.php';

/* Maintenance mode: "Back soon" page for visitors */
if ( henshe_site_on( 'maintenance' ) ) {
	require __DIR__ . '/inc/maintenance.php';
}

/* Trending slider: [hs_trending] */
if ( henshe_site_on( 'trending' ) ) {
	require __DIR__ . '/inc/trending.php';
} else {
	add_shortcode( 'hs_trending', '__return_empty_string' );
}

/* Year filter on All Stories: [hs_year_filter] */
if ( henshe_site_on( 'year_filter' ) ) {
	require __DIR__ . '/inc/years.php';
} else {
	add_shortcode( 'hs_year_filter', '__return_empty_string' );
}

/* ---------------------------------------------------------------
 * Small fixes (always on, same as version 1)
 * ------------------------------------------------------------- */
// Comments are turned off site-wide: hide Comments from the admin menu and admin bar.
// If comments are ever turned back on (Settings > Discussion), delete these two hooks.
add_action( 'admin_menu', function () {
	remove_menu_page( 'edit-comments.php' );
} );
add_action( 'admin_bar_menu', function ( $bar ) {
	$bar->remove_node( 'comments' );
}, 999 );

// Load Contact Form 7's files only on the Contact page (the only page with a form).
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_page( 'contact' ) ) {
		wp_dequeue_style( 'contact-form-7' );
		wp_dequeue_script( 'contact-form-7' );
		wp_dequeue_script( 'swv' );
	}
}, 20 );

// Allow pinch zoom on phones (the Liondar theme adds maximum-scale=1, which blocks it).
add_action( 'template_redirect', function () {
	ob_start( function ( $html ) {
		return str_replace( 'initial-scale=1, maximum-scale=1', 'initial-scale=1', $html );
	} );
} );

// Hide the public list of user accounts (login names) from visitors.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );

// Stop loading the 525 KB IcoFont icon font and draw the icons we use as small SVGs instead.
// Only matters while a Liondar theme is active (it registers 'icon-font' and uses the icofont classes).
add_action( 'wp_enqueue_scripts', function () {
	if ( 'liondar' !== get_template() ) {
		return;
	}
	wp_dequeue_style( 'icon-font' );
	wp_register_style( 'kaleo-svg-icons', false );
	wp_enqueue_style( 'kaleo-svg-icons' );
	wp_add_inline_style( 'kaleo-svg-icons', <<<'CSS'
/* ----- Icons: small built-in SVGs instead of the 525 KB IcoFont download ----- */
:root {
  --ic-search: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2.2' stroke-linecap='round'%3E%3Ccircle cx='10.5' cy='10.5' r='7'/%3E%3Cpath d='m20.5 20.5-5-5'/%3E%3C/svg%3E");
  --ic-sun: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='12' cy='12' r='4' fill='%23000'/%3E%3Cpath d='M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4'/%3E%3C/svg%3E");
  --ic-moon: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z'/%3E%3C/svg%3E");
  --ic-facebook: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath d='M13.5 22v-8.2h2.8l.4-3.2h-3.2V8.5c0-.9.3-1.6 1.6-1.6h1.7V4.1c-.3 0-1.3-.1-2.5-.1-2.5 0-4.2 1.5-4.2 4.3v2.3H7.3v3.2h2.8V22z'/%3E%3C/svg%3E");
  --ic-instagram: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2'%3E%3Crect x='3' y='3' width='18' height='18' rx='5'/%3E%3Ccircle cx='12' cy='12' r='4'/%3E%3Ccircle cx='17.3' cy='6.7' r='.6' fill='%23000'/%3E%3C/svg%3E");
  --ic-email: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2' stroke-linejoin='round'%3E%3Crect x='3' y='5' width='18' height='14' rx='2'/%3E%3Cpath d='m3.5 6.5 8.5 6.5 8.5-6.5'/%3E%3C/svg%3E");
  --ic-arrow-up: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M12 20V4M6 10l6-6 6 6'/%3E%3C/svg%3E");
  --ic-home: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23000' stroke-width='2' stroke-linejoin='round'%3E%3Cpath d='M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'/%3E%3C/svg%3E");
}
.icofont-search-1::before, .icofont-sun-alt::before, .icofont-moon::before,
.icofont-facebook::before, .icofont-instagram::before, .icofont-email::before,
.icofont-long-arrow-up::before, .icofont-home::before,
.blog-sidebar .widget_search form button::before, .not-found form button::before {
  content: "" !important;
  display: inline-block;
  width: 1em; height: 1em;
  vertical-align: -0.125em;
  background-color: currentColor;
  -webkit-mask: var(--ic) center / contain no-repeat;
  mask: var(--ic) center / contain no-repeat;
}
.icofont-search-1, .blog-sidebar .widget_search form button, .not-found form button { --ic: var(--ic-search); }
.icofont-sun-alt { --ic: var(--ic-sun); }
.icofont-moon { --ic: var(--ic-moon); }
.icofont-facebook { --ic: var(--ic-facebook); }
.icofont-instagram { --ic: var(--ic-instagram); }
.icofont-email { --ic: var(--ic-email); }
.icofont-long-arrow-up { --ic: var(--ic-arrow-up); }
.icofont-home { --ic: var(--ic-home); }
.blog-sidebar .widget_search form button::before, .not-found form button::before { width: 15px; height: 15px; }
/* Match the old icon sizes */
.icofont-search-1::before, .icofont-facebook::before { width: 1.25em; height: 1.25em; vertical-align: -0.2em; }
.icofont-instagram::before, .icofont-email::before { width: 1.1em; height: 1.1em; vertical-align: -0.15em; }
CSS
	);
}, 100 );
