<?php
/**
 * SVG uploads for administrators only. WordPress blocks SVG files by default because an SVG can
 * carry scripts. Every SVG is cleaned on upload: only drawing tags and attributes are kept, so
 * scripts, event handlers (onclick...), outside links and embedded HTML are removed.
 * Anything that can't be read as a clean SVG is refused.
 */

defined( 'ABSPATH' ) || exit;

function hs_svg_allowed() {
	return current_user_can( 'manage_options' );
}

// Add .svg to the allowed file types (admins only).
add_filter( 'upload_mimes', function ( $mimes ) {
	if ( hs_svg_allowed() ) {
		$mimes['svg'] = 'image/svg+xml';
	}
	return $mimes;
} );

// WordPress's own file-type check doesn't recognise SVG; confirm it ourselves.
add_filter( 'wp_check_filetype_and_ext', function ( $data, $file, $filename ) {
	if ( hs_svg_allowed() && 'svg' === strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) ) {
		$head = (string) file_get_contents( $file, false, null, 0, 4096 );
		if ( false !== stripos( $head, '<svg' ) ) {
			$data['ext']             = 'svg';
			$data['type']            = 'image/svg+xml';
			$data['proper_filename'] = false;
		}
	}
	return $data;
}, 10, 3 );

// Clean the file before it is saved.
add_filter( 'wp_handle_upload_prefilter', function ( $file ) {
	if ( 'svg' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) ) {
		return $file;
	}
	if ( ! hs_svg_allowed() ) {
		$file['error'] = 'Only administrators can upload SVG files.';
		return $file;
	}
	$clean = hs_svg_sanitize( (string) file_get_contents( $file['tmp_name'] ) );
	if ( null === $clean ) {
		$file['error'] = 'This SVG could not be cleaned safely, so it was not uploaded. Try exporting it again as a plain SVG.';
		return $file;
	}
	file_put_contents( $file['tmp_name'], $clean );
	return $file;
} );

/**
 * Returns the cleaned SVG, or null if the file isn't a usable SVG.
 */
function hs_svg_sanitize( $svg ) {
	if ( '' === trim( $svg ) || ! class_exists( 'DOMDocument' ) ) {
		return null;
	}
	if ( 0 === strpos( $svg, "\x1f\x8b" ) ) { // gzipped .svgz
		$svg = (string) @gzdecode( $svg );
	}
	// Entities and DOCTYPEs are never needed in an icon and are a known attack route.
	if ( preg_match( '/<!DOCTYPE|<!ENTITY/i', $svg ) ) {
		$svg = preg_replace( '/<!DOCTYPE[^>\[]*(\[[^\]]*\])?\s*>/is', '', $svg );
		if ( preg_match( '/<!ENTITY/i', $svg ) ) {
			return null;
		}
	}

	$elements = array(
		'svg', 'g', 'switch', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata', 'style',
		'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
		'text', 'tspan', 'textpath',
		'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask', 'marker', 'image',
		'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feflood',
		'fegaussianblur', 'femerge', 'femergenode', 'feoffset', 'femorphology', 'fedropshadow',
		'fefunca', 'fefuncr', 'fefuncg', 'fefuncb', 'feturbulence', 'fedisplacementmap',
	);

	$prev = libxml_use_internal_errors( true );
	$doc  = new DOMDocument();
	$ok   = $doc->loadXML( $svg, LIBXML_NONET | LIBXML_NOBLANKS );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	if ( ! $ok || ! $doc->documentElement || 'svg' !== strtolower( $doc->documentElement->localName ) ) {
		return null;
	}

	// Remove comments and processing instructions (keeps the file small too).
	$xpath = new DOMXPath( $doc );
	foreach ( iterator_to_array( $xpath->query( '//comment() | //processing-instruction()' ) ) as $node ) {
		$node->parentNode->removeChild( $node );
	}

	foreach ( iterator_to_array( $doc->getElementsByTagName( '*' ) ) as $el ) {
		if ( ! $el->parentNode ) {
			continue; // already removed with its parent
		}
		// Drop editor extras (Inkscape, Illustrator...) and anything not on the list, e.g. script, foreignObject.
		$is_svg_ns = in_array( $el->namespaceURI, array( null, '', 'http://www.w3.org/2000/svg' ), true );
		if ( ! $is_svg_ns || ! in_array( strtolower( $el->localName ), $elements, true ) ) {
			$el->parentNode->removeChild( $el );
			continue;
		}
		if ( 'style' === strtolower( $el->localName ) && ! hs_svg_safe_css( $el->textContent ) ) {
			$el->parentNode->removeChild( $el );
			continue;
		}
		foreach ( iterator_to_array( $el->attributes ) as $attr ) {
			$name  = strtolower( $attr->localName );
			$value = trim( $attr->value );
			$bad   = 0 === strpos( $name, 'on' )                                   // onclick, onload...
				|| ( $attr->namespaceURI && ! in_array( $attr->namespaceURI, array( 'http://www.w3.org/1999/xlink', 'http://www.w3.org/XML/1998/namespace' ), true ) )
				|| ( 'href' === $name && ! hs_svg_safe_href( $value ) )
				|| ( 'style' === $name && ! hs_svg_safe_css( $value ) )
				|| preg_match( '/url\(\s*+[\'"]?+\s*+(?!#)/i', $value );             // only url(#local) references
			if ( $bad ) {
				$el->removeAttributeNode( $attr );
			}
		}
	}

	$out = $doc->saveXML( $doc->documentElement );
	return $out ? '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $out : null;
}

// Links may only point inside the file (#id) or be an embedded PNG/JPEG/GIF/WebP picture.
function hs_svg_safe_href( $value ) {
	return '' === $value
		|| '#' === $value[0]
		|| preg_match( '#^data:image/(png|jpe?g|gif|webp);base64,[a-z0-9+/=\s]+$#i', $value );
}

function hs_svg_safe_css( $css ) {
	return ! preg_match( '/@import|expression\s*\(|javascript:|behavior\s*:|-moz-binding|url\(\s*+[\'"]?+\s*+(?!#)/i', $css );
}

/* ---- Make SVGs behave like normal images in the Media Library and the editor ---- */

// Record the width and height from the SVG, so the image block knows its size.
add_filter( 'wp_generate_attachment_metadata', function ( $meta, $id ) {
	if ( 'image/svg+xml' !== get_post_mime_type( $id ) ) {
		return $meta;
	}
	$file = get_attached_file( $id );
	$size = hs_svg_size( $file );
	$meta = is_array( $meta ) ? $meta : array();
	return array_merge( $meta, $size, array( 'file' => _wp_relative_upload_path( $file ) ) );
}, 10, 2 );

function hs_svg_size( $file ) {
	$w = $h = 0;
	$svg = @simplexml_load_file( $file, 'SimpleXMLElement', LIBXML_NONET );
	if ( $svg ) {
		$a = $svg->attributes();
		$w = (float) $a->width;
		$h = (float) $a->height;
		if ( ( ! $w || ! $h ) && isset( $a->viewBox ) ) {
			$box = preg_split( '/[\s,]+/', trim( (string) $a->viewBox ) );
			if ( 4 === count( $box ) ) {
				$w = (float) $box[2];
				$h = (float) $box[3];
			}
		}
	}
	return array( 'width' => (int) round( $w ?: 100 ), 'height' => (int) round( $h ?: 100 ) );
}

// Show a preview of the SVG in the Media Library grid and the image picker.
add_filter( 'wp_prepare_attachment_for_js', function ( $response, $attachment ) {
	if ( 'image/svg+xml' !== $response['mime'] ) {
		return $response;
	}
	$size = hs_svg_size( get_attached_file( $attachment->ID ) );
	$full = array( 'url' => $response['url'], 'width' => $size['width'], 'height' => $size['height'], 'orientation' => $size['width'] >= $size['height'] ? 'landscape' : 'portrait' );
	$response['image'] = array( 'src' => $response['url'], 'width' => $size['width'], 'height' => $size['height'] );
	$response['icon']  = $response['url'];
	$response['sizes'] = array( 'full' => $full, 'medium' => $full, 'thumbnail' => $full );
	return $response;
}, 10, 2 );

add_action( 'admin_head', function () {
	echo '<style>.attachment-preview img[src$=".svg"],.media-icon img[src$=".svg"]{width:100%;height:auto;padding:8px;box-sizing:border-box}</style>' . "\n";
} );
