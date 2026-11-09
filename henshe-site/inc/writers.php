<?php
/**
 * Writers: credit a story to a writer who has no user account.
 *
 * Writers are a list like Categories (Posts > Writers): a name and a short bio.
 * Tick one or more writers in the post sidebar. The byline, the writer's own page
 * (/writer/name/), the RSS feed and Google's article data then show the writer
 * instead of the account that posted. Stories with no writer ticked show the
 * account's name, same as before.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
	register_taxonomy( 'hs_writer', 'post', array(
		'labels'            => array(
			'name'                   => 'Writers',
			'singular_name'          => 'Writer',
			'menu_name'              => 'Writers',
			'all_items'              => 'All writers',
			'edit_item'              => 'Edit writer',
			'view_item'              => 'View writer',
			'update_item'            => 'Update writer',
			'add_new_item'           => 'Add new writer',
			'new_item_name'          => 'New writer name',
			'search_items'           => 'Search writers',
			'not_found'              => 'No writers found.',
			'no_terms'               => 'No writers',
			'back_to_items'          => '← Go to Writers',
			'name_field_description' => 'The name shown on stories.',
			'desc_field_description' => 'A short bio, shown on the writer\'s page.',
		),
		'public'            => true,
		'hierarchical'      => true, // tick boxes in the post sidebar, like Categories
		'show_admin_column' => true,
		'show_in_rest'      => true,
		'rest_base'         => 'writers',
		'rewrite'           => array( 'slug' => 'writer', 'with_front' => false ),
	) );
} );

/* Writers have no parent writer: hide that field on the Writers screens */
add_action( 'admin_head', function () {
	$screen = get_current_screen();
	if ( $screen && 'hs_writer' === $screen->taxonomy ) {
		echo '<style>.term-parent-wrap{display:none}</style>';
	}
} );

/** The writers ticked on a story (empty when none). */
function hs_writers_of( $post = null ) {
	$terms = get_the_terms( $post, 'hs_writer' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms : array();
}

/** "Jumana", "Jumana & Ayesha" or "Jumana, Ayesha & Imala", linked to their pages when $link is true. */
function hs_writer_names( $writers, $link = false, $target = '_self' ) {
	$names = array();
	foreach ( $writers as $w ) {
		$names[] = $link
			? '<a href="' . esc_url( get_term_link( $w ) ) . '" target="' . esc_attr( $target ) . '" class="wp-block-post-author-name__link">' . esc_html( $w->name ) . '</a>'
			: esc_html( $w->name );
	}
	$last = array_pop( $names );
	return $names ? implode( ', ', $names ) . ' &amp; ' . $last : $last;
}

/* Byline (the "Author name" block): show the writers */
add_filter( 'render_block_core/post-author-name', function ( $html, $block, $instance ) {
	$writers = hs_writers_of( $instance->context['postId'] ?? get_the_ID() );
	if ( ! $writers || '' === trim( $html ) ) {
		return $html;
	}
	$names = hs_writer_names( $writers, ! empty( $block['attrs']['isLink'] ), $block['attrs']['linkTarget'] ?? '_self' );
	return preg_replace( '#^(\s*<div[^>]*>).*(</div>\s*)$#s', '${1}' . $names . '${2}', $html );
}, 10, 3 );

/* Everywhere else the author's name is printed (RSS feed, older themes) */
add_filter( 'the_author', function ( $name ) {
	if ( is_admin() || ( ! in_the_loop() && ! is_feed() ) ) {
		return $name;
	}
	$writers = hs_writers_of( get_post() );
	return $writers ? wp_specialchars_decode( hs_writer_names( $writers ) ) : $name;
} );

/* Google's article data (The SEO Framework): the writers are the authors */
add_filter( 'the_seo_framework_schema_graph_data', function ( $graph ) {
	if ( ! is_singular( 'post' ) ) {
		return $graph;
	}
	$writers = hs_writers_of( get_queried_object_id() );
	if ( ! $writers ) {
		return $graph;
	}
	$people = array();
	foreach ( $writers as $w ) {
		$person = array( '@type' => 'Person', '@id' => home_url( '/#/schema/Person/writer-' . $w->slug ), 'name' => $w->name, 'url' => get_term_link( $w ) );
		if ( '' !== trim( $w->description ) ) {
			$person['description'] = wp_trim_words( wp_strip_all_tags( $w->description ), 40 );
		}
		$people[] = $person;
	}
	// Swap the account's Person (wherever it sits in the data, and any reference to it) for the writers.
	$old_id = '';
	$find   = function ( $node ) use ( &$find, &$old_id ) {
		if ( is_array( $node ) ) {
			if ( 'Person' === ( $node['@type'] ?? '' ) ) {
				$old_id = $node['@id'] ?? '';
				return;
			}
			array_map( $find, $node );
		}
	};
	$find( $graph );
	$swap = function ( $node ) use ( &$swap, $old_id, $people ) {
		if ( ! is_array( $node ) ) {
			return $node;
		}
		if ( 'Person' === ( $node['@type'] ?? '' ) || ( $old_id && array( '@id' => $old_id ) === $node ) ) {
			return 1 === count( $people ) ? $people[0] : $people;
		}
		return array_map( $swap, $node );
	};
	$out = array();
	foreach ( $graph as $entity ) {
		if ( 'Person' === ( $entity['@type'] ?? '' ) ) {
			array_push( $out, ...$people ); // a Person listed on its own in the data
		} else {
			$out[] = $swap( $entity );
		}
	}
	return $out;
} );

/* Old writer addresses (/author/jumana/) go to the writer's page when there is one */
add_action( 'template_redirect', function () {
	$slug = get_query_var( 'author_name' );
	if ( ! $slug && is_author() ) {
		$user = get_queried_object();
		$slug = $user->user_nicename ?? '';
	}
	if ( ! $slug ) {
		return;
	}
	$writer = get_term_by( 'slug', $slug, 'hs_writer' );
	if ( $writer ) {
		wp_safe_redirect( get_term_link( $writer ), 301 );
		exit;
	}
} );
