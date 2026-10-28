<?php
/**
 * Reading and navigation extras (1.2.0): breadcrumbs, text size button, collections,
 * More Stories from the same category, and the web font fix.
 */

defined( 'ABSPATH' ) || exit;

/* [hs_breadcrumbs] — Home › Category › Story (on stories) or Home › Category (on category, tag and writer pages) */
add_shortcode( 'hs_breadcrumbs', function () {
	if ( ! henshe_site_on( 'story_links' ) ) {
		return '';
	}
	$items = array( '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>' );
	if ( is_singular( 'post' ) ) {
		$cats = get_the_category();
		if ( $cats ) {
			$items[] = '<a href="' . esc_url( get_category_link( $cats[0] ) ) . '">' . esc_html( $cats[0]->name ) . '</a>';
		}
		$items[] = '<span aria-current="page">' . esc_html( get_the_title() ) . '</span>';
	} elseif ( is_category() || is_tag() || is_author() ) {
		$items[] = '<span aria-current="page">' . esc_html( is_author() ? get_the_author_meta( 'display_name', get_queried_object_id() ) : single_term_title( '', false ) ) . '</span>';
	} else {
		return '';
	}
	return '<nav class="hs-crumbs" aria-label="Breadcrumb"><ol><li>' . implode( '</li><li>', $items ) . '</li></ol></nav>';
} );

/* [hs_text_size] — "Font Size" + "Aa" button under the cover photo: normal, larger, largest (remembered in this browser) */
add_shortcode( 'hs_text_size', function () {
	if ( ! henshe_site_on( 'story_links' ) || ! is_singular( 'post' ) ) {
		return '';
	}
	return '<span class="hs-text-size-wrap"><span class="hs-text-size-label" aria-hidden="true">Font Size</span>'
		. '<button type="button" class="hs-text-size" aria-label="Change font size">A<span>A</span></button></span>';
} );

/* [hs_collection tag="maldivian-makers"] — banner for a hand-picked group of stories (a tag with a description) */
add_shortcode( 'hs_collection', function ( $atts ) {
	$atts = shortcode_atts( array( 'tag' => '' ), $atts );
	$tag  = get_term_by( 'slug', sanitize_title( $atts['tag'] ), 'post_tag' );
	if ( ! $tag || ! henshe_site_on( 'site_styles' ) ) {
		return '';
	}
	$posts = get_posts( array( 'tag_id' => $tag->term_id, 'numberposts' => 4 ) );
	if ( ! $posts ) {
		return '';
	}
	$thumbs = '';
	foreach ( $posts as $p ) {
		$thumbs .= '<a href="' . esc_url( get_permalink( $p ) ) . '" title="' . esc_attr( get_the_title( $p ) ) . '">' . get_the_post_thumbnail( $p, 'medium', array( 'alt' => '', 'loading' => 'lazy' ) ) . '</a>';
	}
	return '<section class="hs-collection">'
		. '<div class="hs-collection__text"><p class="hs-collection__kicker">Collection</p>'
		. '<h2 class="hs-collection__title"><a href="' . esc_url( get_tag_link( $tag ) ) . '">' . esc_html( $tag->name ) . '</a></h2>'
		. ( $tag->description ? '<p class="hs-collection__desc">' . esc_html( $tag->description ) . '</p>' : '' )
		. '<a class="hs-collection__more" href="' . esc_url( get_tag_link( $tag ) ) . '">See all ' . (int) $tag->count . ' stories <span aria-hidden="true">→</span></a></div>'
		. '<div class="hs-collection__photos">' . $thumbs . '</div></section>';
} );

/* More Stories under a story: same category first, topped up with the newest stories */
function hs_related_ids( $id ) {
	$cats = wp_get_post_categories( $id );
	$ids  = $cats ? get_posts( array( 'category__in' => $cats, 'post__not_in' => array( $id ), 'numberposts' => 3, 'fields' => 'ids' ) ) : array();
	if ( count( $ids ) < 3 ) {
		$ids = array_merge( $ids, get_posts( array( 'post__not_in' => array_merge( $ids, array( $id ) ), 'numberposts' => 3 - count( $ids ), 'fields' => 'ids' ) ) );
	}
	return $ids;
}

/* Web font: load Manrope early, and size the stand-in font to match so headlines don't jump when it arrives */
add_action( 'wp_head', function () {
	if ( ! henshe_site_on( 'site_styles' ) || ! file_exists( get_theme_file_path( 'assets/fonts/manrope/Manrope-VariableFont_wght.woff2' ) ) ) {
		return;
	}
	echo '<link rel="preload" href="' . esc_url( get_theme_file_uri( 'assets/fonts/manrope/Manrope-VariableFont_wght.woff2' ) ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	echo '<style id="hs-font-fallback">@font-face{font-family:"Manrope Fallback";src:local("Arial"),local("Liberation Sans"),local("Helvetica");size-adjust:101%;ascent-override:105.5%;descent-override:29.7%;line-gap-override:0%}'
		. 'body{--wp--preset--font-family--manrope:Manrope,"Manrope Fallback",sans-serif}</style>' . "\n";
}, 2 );

/* Text size: apply the reader's choice before the story draws */
add_action( 'wp_head', function () {
	if ( henshe_site_on( 'story_links' ) && is_singular( 'post' ) ) {
		echo "<script>try{var t=localStorage.getItem('hs-text');if(t)document.documentElement.dataset.hsText=t}catch(e){}</script>\n";
	}
}, 1 );
