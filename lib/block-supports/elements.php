<?php
/**
 * Elements styles block support.
 *
 * @package gutenberg
 */

/**
 * Render out the duotone stylesheet and SVG.
 *
 * @param  string $block_content Rendered block content.
 * @param  array  $block         Block object.
 * @return string                Filtered block content.
 */
function gutenberg_render_elements_support( $block_content, $block ) {
	$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $block['blockName'] );
	$link_color = _wp_array_get( $block['attrs'], array( 'style', 'elements', 'link', 'color', 'text' ), null );

	/*
	* For now we only care about link color.
	* This code in the future when we have a public API
	* should take advantage of WP_Theme_JSON::compute_style_properties
	* and work for any element and style.
	*/
	if ( null === $link_color ) {
		return $block_content;
	}
	$class_name = 'wp-block-elements-container-' . uniqid();

	if ( strpos( $link_color, 'var:preset|color|' ) !== false ) {
		// Get the name from the string and add proper styles.
		$index_to_splice = strrpos( $link_color, '|' ) + 1;
		$link_color_name = substr( $link_color, $index_to_splice );
		$link_color      = "var(--wp--preset--color--$link_color_name)";
	}

	$style      = implode(
		"\n",
		array(
			'<style>',
			".$class_name a{",
			"\tcolor: $link_color;",
			'}',
			"</style>\n",
		)
	);

	// Like the layout hook this assumes the hook only applies to blocks with a single wrapper.
	$content = preg_replace(
		'/' . preg_quote( 'class="', '/' ) . '/',
		'class="' . $class_name . ' ',
		$block_content,
		1
	);
	return $content . $style;

}


add_filter( 'render_block', 'gutenberg_render_elements_support', 10, 2 );
