<?php
/**
 * Process of structures that adhere to the theme.json schema.
 *
 * @package gutenberg
 */

/**
 * Class that encapsulates the processing of
 * structures that adhere to the theme.json spec.
 */
class WP_Theme_JSON_Gutenberg {

	/**
	 * Container of data in theme.json format.
	 *
	 * @var array
	 */
	private $theme_json = null;

	/**
	 * Holds block metadata extracted from block.json
	 * to be shared among all instances so we don't
	 * process it twice.
	 *
	 * @var array
	 */
	private static $blocks_metadata = null;

	/**
	 * The CSS selector for the root block.
	 *
	 * @var string
	 */
	const ROOT_BLOCK_SELECTOR = 'body';

	const VALID_ORIGINS = array(
		'core',
		'theme',
		'user',
	);

	const VALID_TOP_LEVEL_KEYS = array(
		'customTemplates',
		'templateParts',
		'styles',
		'settings',
		'version',
	);

	const VALID_STYLES = array(
		'border'     => array(
			'color'  => null,
			'radius' => null,
			'style'  => null,
			'width'  => null,
		),
		'color'      => array(
			'background' => null,
			'gradient'   => null,
			'text'       => null,
		),
		'filter'     => array(
			'duotone' => null,
		),
		'spacing'    => array(
			'margin'   => null,
			'padding'  => null,
			'blockGap' => null,
		),
		'typography' => array(
			'fontFamily'     => null,
			'fontSize'       => null,
			'fontStyle'      => null,
			'fontWeight'     => null,
			'letterSpacing'  => null,
			'lineHeight'     => null,
			'textDecoration' => null,
			'textTransform'  => null,
		),
	);

	const VALID_SETTINGS = array(
		'border'     => array(
			'customColor'  => null,
			'customRadius' => null,
			'customStyle'  => null,
			'customWidth'  => null,
		),
		'color'      => array(
			'background'     => null,
			'custom'         => null,
			'customDuotone'  => null,
			'customGradient' => null,
			'duotone'        => null,
			'gradients'      => null,
			'link'           => null,
			'palette'        => null,
			'text'           => null,
		),
		'custom'     => null,
		'layout'     => array(
			'contentSize' => null,
			'wideSize'    => null,
		),
		'spacing'    => array(
			'blockGap'      => null,
			'customMargin'  => null,
			'customPadding' => null,
			'units'         => null,
		),
		'typography' => array(
			'customFontSize'        => null,
			'customFontStyle'       => null,
			'customFontWeight'      => null,
			'customLetterSpacing'   => null,
			'customLineHeight'      => null,
			'customTextDecorations' => null,
			'customTextTransforms'  => null,
			'dropCap'               => null,
			'fontFamilies'          => null,
			'fontSizes'             => null,
		),
	);

	/**
	 * Presets are a set of values that serve
	 * to bootstrap some styles: colors, font sizes, etc.
	 *
	 * They are a unkeyed array of values such as:
	 *
	 * ```php
	 * array(
	 *   array(
	 *     'slug'      => 'unique-name-within-the-set',
	 *     'name'      => 'Name for the UI',
	 *     <value_key> => 'value'
	 *   ),
	 * )
	 * ```
	 *
	 * This contains the necessary metadata to process them:
	 *
	 * - path       => where to find the preset within the settings section
	 *
	 * - value_key  => the key that represents the value
	 *
	 * - value_func => optionally, instead of value_key, a function to generate
	 *                 the value that takes a preset as an argument
	 *
	 * - css_var    => name of the var to generate. The "$slug" substring will be
	 *                 replaced by the slug of each preset. For example,
	 *                 given a preset for color with two values whose slugs are "black" and "white",
	 *                 the string "--wp--preset--color--$slug" will generate two variables:
	 *                 "--wp--preset--color--black" and "--wp--preset--color--white".
	 *
	 * - classes    => array containing a structure with the classes to
	 *                 generate for the presets, where for each array item
	 *                 the key is the class name and the value the property name.
	 *                 The "$slug" substring will be replaced by the slug of each preset.
	 *                 For example:
	 *                 'classes' => array(
	 *                   '.has-$slug-color'            => 'color',
	 *                   '.has-$slug-background-color' => 'background-color',
	 *                   '.has-$slug-border-color'     => 'border-color',
	 *                 )
	 * - properties => array of CSS properties to be used by kses to
	 *                 validate the content of each preset
	 *                 by means of the remove_insecure_properties method.
	 */
	const PRESETS_METADATA = array(
		array(
			'path'       => array( 'color', 'palette' ),
			'value_key'  => 'color',
			'css_vars'   => '--wp--preset--color--$slug',
			'classes'    => array(
				'.has-$slug-color'            => 'color',
				'.has-$slug-background-color' => 'background-color',
				'.has-$slug-border-color'     => 'border-color',
			),
			'properties' => array( 'color', 'background-color', 'border-color' ),
		),
		array(
			'path'       => array( 'color', 'gradients' ),
			'value_key'  => 'gradient',
			'css_vars'   => '--wp--preset--gradient--$slug',
			'classes'    => array( '.has-$slug-gradient-background' => 'background' ),
			'properties' => array( 'background' ),
		),
		array(
			'path'       => array( 'color', 'duotone' ),
			'value_func' => 'gutenberg_render_duotone_filter_preset',
			'css_vars'   => '--wp--preset--duotone--$slug',
			'classes'    => array(),
			'properties' => array( 'filter' ),
		),
		array(
			'path'       => array( 'typography', 'fontSizes' ),
			'value_key'  => 'size',
			'css_vars'   => '--wp--preset--font-size--$slug',
			'classes'    => array( '.has-$slug-font-size' => 'font-size' ),
			'properties' => array( 'font-size' ),
		),
		array(
			'path'       => array( 'typography', 'fontFamilies' ),
			'value_key'  => 'fontFamily',
			'css_vars'   => '--wp--preset--font-family--$slug',
			'classes'    => array(),
			'properties' => array( 'font-family' ),
		),
	);

	/**
	 * Metadata for style properties.
	 *
	 * Each element is a direct mapping from the CSS property name to the
	 * path to the value in theme.json & block attributes.
	 */
	const PROPERTIES_METADATA = array(
		'background'                 => array( 'color', 'gradient' ),
		'background-color'           => array( 'color', 'background' ),
		'border-radius'              => array( 'border', 'radius' ),
		'border-top-left-radius'     => array( 'border', 'radius', 'topLeft' ),
		'border-top-right-radius'    => array( 'border', 'radius', 'topRight' ),
		'border-bottom-left-radius'  => array( 'border', 'radius', 'bottomLeft' ),
		'border-bottom-right-radius' => array( 'border', 'radius', 'bottomRight' ),
		'border-color'               => array( 'border', 'color' ),
		'border-width'               => array( 'border', 'width' ),
		'border-style'               => array( 'border', 'style' ),
		'color'                      => array( 'color', 'text' ),
		'font-family'                => array( 'typography', 'fontFamily' ),
		'font-size'                  => array( 'typography', 'fontSize' ),
		'font-style'                 => array( 'typography', 'fontStyle' ),
		'font-weight'                => array( 'typography', 'fontWeight' ),
		'letter-spacing'             => array( 'typography', 'letterSpacing' ),
		'line-height'                => array( 'typography', 'lineHeight' ),
		'margin'                     => array( 'spacing', 'margin' ),
		'margin-top'                 => array( 'spacing', 'margin', 'top' ),
		'margin-right'               => array( 'spacing', 'margin', 'right' ),
		'margin-bottom'              => array( 'spacing', 'margin', 'bottom' ),
		'margin-left'                => array( 'spacing', 'margin', 'left' ),
		'padding'                    => array( 'spacing', 'padding' ),
		'padding-top'                => array( 'spacing', 'padding', 'top' ),
		'padding-right'              => array( 'spacing', 'padding', 'right' ),
		'padding-bottom'             => array( 'spacing', 'padding', 'bottom' ),
		'padding-left'               => array( 'spacing', 'padding', 'left' ),
		'--wp--style--block-gap'     => array( 'spacing', 'blockGap' ),
		'text-decoration'            => array( 'typography', 'textDecoration' ),
		'text-transform'             => array( 'typography', 'textTransform' ),
		'filter'                     => array( 'filter', 'duotone' ),
	);

	/**
	 * Protected style properties.
	 *
	 * These style properties are only rendered if a setting enables it
	 * via a value other than `null`.
	 *
	 * Each element maps the style property to the corresponding theme.json
	 * setting key.
	 */
	const PROTECTED_PROPERTIES = array(
		'spacing.blockGap' => array( 'spacing', 'blockGap' ),
	);

	const ELEMENTS = array(
		'link' => 'a',
		'h1'   => 'h1',
		'h2'   => 'h2',
		'h3'   => 'h3',
		'h4'   => 'h4',
		'h5'   => 'h5',
		'h6'   => 'h6',
	);

	const LATEST_SCHEMA = 1;

	/**
	 * Constructor.
	 *
	 * @param array  $theme_json A structure that follows the theme.json schema.
	 * @param string $origin What source of data this object represents. One of core, theme, or user. Default: theme.
	 */
	public function __construct( $theme_json = array(), $origin = 'theme' ) {
		if ( ! in_array( $origin, self::VALID_ORIGINS, true ) ) {
			$origin = 'theme';
		}

		// The old format is not meant to be ported to core.
		// We can remove it at that point.
		if ( ! isset( $theme_json['version'] ) || 0 === $theme_json['version'] ) {
			$theme_json = WP_Theme_JSON_Schema_V0::parse( $theme_json );
		}

		$valid_block_names   = array_keys( self::get_blocks_metadata() );
		$valid_element_names = array_keys( self::ELEMENTS );
		$this->theme_json    = self::sanitize( $theme_json, $valid_block_names, $valid_element_names );

		// Internally, presets are keyed by origin.
		$nodes = self::get_setting_nodes( $this->theme_json );
		foreach ( $nodes as $node ) {
			foreach ( self::PRESETS_METADATA as $preset_metadata ) {
				$path   = array_merge( $node['path'], $preset_metadata['path'] );
				$preset = _wp_array_get( $this->theme_json, $path, null );
				if ( null !== $preset ) {
					gutenberg_experimental_set( $this->theme_json, $path, array( $origin => $preset ) );
				}
			}
		}
	}

	/**
	 * Sanitizes the input according to the schemas.
	 *
	 * @param array $input Structure to sanitize.
	 * @param array $valid_block_names List of valid block names.
	 * @param array $valid_element_names List of valid element names.
	 *
	 * @return array The sanitized output.
	 */
	private static function sanitize( $input, $valid_block_names, $valid_element_names ) {
		$output = array();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output = array_intersect_key( $input, array_flip( self::VALID_TOP_LEVEL_KEYS ) );

		// Build the schema based on valid block & element names.
		$schema                 = array();
		$schema_styles_elements = array();
		foreach ( $valid_element_names as $element ) {
			$schema_styles_elements[ $element ] = self::VALID_STYLES;
		}
		$schema_styles_blocks   = array();
		$schema_settings_blocks = array();
		foreach ( $valid_block_names as $block ) {
			$schema_settings_blocks[ $block ]           = self::VALID_SETTINGS;
			$schema_styles_blocks[ $block ]             = self::VALID_STYLES;
			$schema_styles_blocks[ $block ]['elements'] = $schema_styles_elements;
		}
		$schema['styles']             = self::VALID_STYLES;
		$schema['styles']['blocks']   = $schema_styles_blocks;
		$schema['styles']['elements'] = $schema_styles_elements;
		$schema['settings']           = self::VALID_SETTINGS;
		$schema['settings']['blocks'] = $schema_settings_blocks;

		// Remove anything that's not present in the schema.
		foreach ( array( 'styles', 'settings' ) as $subtree ) {
			if ( ! isset( $input[ $subtree ] ) ) {
				continue;
			}

			if ( ! is_array( $input[ $subtree ] ) ) {
				unset( $output[ $subtree ] );
				continue;
			}

			$result = self::remove_keys_not_in_schema( $input[ $subtree ], $schema[ $subtree ] );

			if ( empty( $result ) ) {
				unset( $output[ $subtree ] );
			} else {
				$output[ $subtree ] = $result;
			}
		}

		return $output;
	}

	/**
	 * Returns the metadata for each block.
	 *
	 * Example:
	 *
	 * {
	 *   'core/paragraph': {
	 *     'selector': 'p'
	 *   },
	 *   'core/heading': {
	 *     'selector': 'h1'
	 *   },
	 *   'core/group': {
	 *     'selector': '.wp-block-group'
	 *   },
	 *   'core/cover': {
	 *     'selector': '.wp-block-cover',
	 *     'duotone': '> .wp-block-cover__image-background, > .wp-block-cover__video-background'
	 *   }
	 * }
	 *
	 * @return array Block metadata.
	 */
	private static function get_blocks_metadata() {
		if ( null !== self::$blocks_metadata ) {
			return self::$blocks_metadata;
		}

		self::$blocks_metadata = array();

		$registry = WP_Block_Type_Registry::get_instance();
		$blocks   = $registry->get_all_registered();
		foreach ( $blocks as $block_name => $block_type ) {
			if (
				isset( $block_type->supports['__experimentalSelector'] ) &&
				is_string( $block_type->supports['__experimentalSelector'] )
			) {
				self::$blocks_metadata[ $block_name ]['selector'] = $block_type->supports['__experimentalSelector'];
			} else {
				self::$blocks_metadata[ $block_name ]['selector'] = '.wp-block-' . str_replace( '/', '-', str_replace( 'core/', '', $block_name ) );
			}

			if (
				isset( $block_type->supports['color']['__experimentalDuotone'] ) &&
				is_string( $block_type->supports['color']['__experimentalDuotone'] )
			) {
				self::$blocks_metadata[ $block_name ]['duotone'] = $block_type->supports['color']['__experimentalDuotone'];
			}

			// Assign defaults, then overwrite those that the block sets by itself.
			// If the block selector is compounded, will append the element to each
			// individual block selector.
			$block_selectors = explode( ',', self::$blocks_metadata[ $block_name ]['selector'] );
			foreach ( self::ELEMENTS as $el_name => $el_selector ) {
				$element_selector = array();
				foreach ( $block_selectors as $selector ) {
					$element_selector[] = $selector . ' ' . $el_selector;
				}
				self::$blocks_metadata[ $block_name ]['elements'][ $el_name ] = implode( ',', $element_selector );
			}
		}

		return self::$blocks_metadata;
	}

	/**
	 * Given a tree, removes the keys that are not present in the schema.
	 *
	 * It is recursive and modifies the input in-place.
	 *
	 * @param array $tree Input to process.
	 * @param array $schema Schema to adhere to.
	 *
	 * @return array Returns the modified $tree.
	 */
	private static function remove_keys_not_in_schema( $tree, $schema ) {
		$tree = array_intersect_key( $tree, $schema );

		foreach ( $schema as $key => $data ) {
			if ( ! isset( $tree[ $key ] ) ) {
				continue;
			}

			if ( is_array( $schema[ $key ] ) && is_array( $tree[ $key ] ) ) {
				$tree[ $key ] = self::remove_keys_not_in_schema( $tree[ $key ], $schema[ $key ] );

				if ( empty( $tree[ $key ] ) ) {
					unset( $tree[ $key ] );
				}
			} elseif ( is_array( $schema[ $key ] ) && ! is_array( $tree[ $key ] ) ) {
				unset( $tree[ $key ] );
			}
		}

		return $tree;
	}

	/**
	 * Given a tree, it creates a flattened one
	 * by merging the keys and binding the leaf values
	 * to the new keys.
	 *
	 * It also transforms camelCase names into kebab-case
	 * and substitutes '/' by '-'.
	 *
	 * This is thought to be useful to generate
	 * CSS Custom Properties from a tree,
	 * although there's nothing in the implementation
	 * of this function that requires that format.
	 *
	 * For example, assuming the given prefix is '--wp'
	 * and the token is '--', for this input tree:
	 *
	 * {
	 *   'some/property': 'value',
	 *   'nestedProperty': {
	 *     'sub-property': 'value'
	 *   }
	 * }
	 *
	 * it'll return this output:
	 *
	 * {
	 *   '--wp--some-property': 'value',
	 *   '--wp--nested-property--sub-property': 'value'
	 * }
	 *
	 * @param array  $tree Input tree to process.
	 * @param string $prefix Prefix to prepend to each variable. '' by default.
	 * @param string $token Token to use between levels. '--' by default.
	 *
	 * @return array The flattened tree.
	 */
	private static function flatten_tree( $tree, $prefix = '', $token = '--' ) {
		$result = array();
		foreach ( $tree as $property => $value ) {
			$new_key = $prefix . str_replace(
				'/',
				'-',
				strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $property ) ) // CamelCase to kebab-case.
			);

			if ( is_array( $value ) ) {
				$new_prefix = $new_key . $token;
				$result     = array_merge(
					$result,
					self::flatten_tree( $value, $new_prefix, $token )
				);
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Returns the style property for the given path.
	 *
	 * It also converts CSS Custom Property stored as
	 * "var:preset|color|secondary" to the form
	 * "--wp--preset--color--secondary".
	 *
	 * @param array $styles Styles subtree.
	 * @param array $path Which property to process.
	 *
	 * @return string Style property value.
	 */
	private static function get_property_value( $styles, $path ) {
		$value = _wp_array_get( $styles, $path, '' );

		if ( '' === $value || is_array( $value ) ) {
			return $value;
		}

		$prefix     = 'var:';
		$prefix_len = strlen( $prefix );
		$token_in   = '|';
		$token_out  = '--';
		if ( 0 === strncmp( $value, $prefix, $prefix_len ) ) {
			$unwrapped_name = str_replace(
				$token_in,
				$token_out,
				substr( $value, $prefix_len )
			);
			$value          = "var(--wp--$unwrapped_name)";
		}

		return $value;
	}

	/**
	 * Given a styles array, it extracts the style properties
	 * and adds them to the $declarations array following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $styles Styles to process.
	 * @param array $settings Theme settings.
	 * @param array $properties Properties metadata.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_style_properties( $styles, $settings = array(), $properties = self::PROPERTIES_METADATA ) {
		$declarations = array();
		if ( empty( $styles ) ) {
			return $declarations;
		}

		foreach ( $properties as $css_property => $value_path ) {
			$value = self::get_property_value( $styles, $value_path );

			// Look up protected properties, keyed by value path.
			// Skip protected properties that are explicitly set to `null`.
			if ( is_array( $value_path ) ) {
				$path_string = implode( '.', $value_path );
				if (
					isset( self::PROTECTED_PROPERTIES[ $path_string ] ) &&
					_wp_array_get( $settings, self::PROTECTED_PROPERTIES[ $path_string ], null ) === null
				) {
					continue;
				}
			}

			// Skip if empty and not "0" or value represents array of longhand values.
			$has_missing_value = empty( $value ) && ! is_numeric( $value );
			if ( $has_missing_value || is_array( $value ) ) {
				continue;
			}

			$declarations[] = array(
				'name'  => $css_property,
				'value' => $value,
			);
		}

		return $declarations;
	}

	/**
	 * Function that appends a sub-selector to a existing one.
	 *
	 * Given the compounded $selector "h1, h2, h3"
	 * and the $to_append selector ".some-class" the result will be
	 * "h1.some-class, h2.some-class, h3.some-class".
	 *
	 * @param string $selector Original selector.
	 * @param string $to_append Selector to append.
	 *
	 * @return string
	 */
	private static function append_to_selector( $selector, $to_append ) {
		$new_selectors = array();
		$selectors     = explode( ',', $selector );
		foreach ( $selectors as $sel ) {
			$new_selectors[] = $sel . $to_append;
		}

		return implode( ',', $new_selectors );
	}

	/**
	 * Function that scopes a selector with another one. This works a bit like
	 * SCSS nesting except the `&` operator isn't supported.
	 *
	 * <code>
	 * $scope = '.a, .b .c';
	 * $selector = '> .x, .y';
	 * $merged = scope_selector( $scope, $selector );
	 * // $merged is '.a > .x, .a .y, .b .c > .x, .b .c .y'
	 * </code>
	 *
	 * @param string $scope    Selector to scope to.
	 * @param string $selector Original selector.
	 *
	 * @return string Scoped selector.
	 */
	private static function scope_selector( $scope, $selector ) {
		$scopes    = explode( ',', $scope );
		$selectors = explode( ',', $selector );

		$selectors_scoped = array();
		foreach ( $scopes as $outer ) {
			foreach ( $selectors as $inner ) {
				$selectors_scoped[] = trim( $outer ) . ' ' . trim( $inner );
			}
		}

		return implode( ', ', $selectors_scoped );
	}

	/**
	 * Gets preset values keyed by slugs based on settings and metadata.
	 *
	 * <code>
	 * $settings = array(
	 *     'typography' => array(
	 *         'fontFamilies' => array(
	 *             array(
	 *                 'slug'       => 'sansSerif',
	 *                 'fontFamily' => '"Helvetica Neue", sans-serif',
	 *             ),
	 *             array(
	 *                 'slug'   => 'serif',
	 *                 'colors' => 'Georgia, serif',
	 *             )
	 *         ),
	 *     ),
	 * );
	 * $meta = array(
	 *    'path'      => array( 'typography', 'fontFamilies' ),
	 *    'value_key' => 'fontFamily',
	 * );
	 * $values_by_slug = get_settings_values_by_slug();
	 * // $values_by_slug === array(
	 * //   'sans-serif' => '"Helvetica Neue", sans-serif',
	 * //   'serif'      => 'Georgia, serif',
	 * // );
	 * </code>
	 *
	 * @param array $settings Settings to process.
	 * @param array $preset_metadata One of the PRESETS_METADATA values.
	 * @param array $origins List of origins to process.
	 *
	 * @return array Array of presets where each key is a slug and each value is the preset value.
	 */
	private static function get_settings_values_by_slug( $settings, $preset_metadata, $origins ) {
		$preset_per_origin = _wp_array_get( $settings, $preset_metadata['path'], array() );

		$result = array();
		foreach ( $origins as $origin ) {
			if ( ! isset( $preset_per_origin[ $origin ] ) ) {
				continue;
			}
			foreach ( $preset_per_origin[ $origin ] as $preset ) {
				$slug = gutenberg_experimental_to_kebab_case( $preset['slug'] );

				$value = '';
				if ( isset( $preset_metadata['value_key'] ) ) {
					$value_key = $preset_metadata['value_key'];
					$value     = $preset[ $value_key ];
				} elseif (
					isset( $preset_metadata['value_func'] ) &&
					is_callable( $preset_metadata['value_func'] )
				) {
					$value_func = $preset_metadata['value_func'];
					$value      = call_user_func( $value_func, $preset );
				} else {
					// If we don't have a value, then don't add it to the result.
					continue;
				}

				$result[ $slug ] = $value;
			}
		}
		return $result;
	}

	/**
	 * Similar to get_settings_values_by_slug, but doesn't compute the value.
	 *
	 * @param array $settings Settings to process.
	 * @param array $preset_metadata One of the PRESETS_METADATA values.
	 * @param array $origins List of origins to process.
	 *
	 * @return array Array of presets where the key and value are both the slug.
	 */
	private static function get_settings_slugs( $settings, $preset_metadata, $origins = self::VALID_ORIGINS ) {
		$preset_per_origin = _wp_array_get( $settings, $preset_metadata['path'], array() );

		$result = array();
		foreach ( $origins as $origin ) {
			if ( ! isset( $preset_per_origin[ $origin ] ) ) {
				continue;
			}
			foreach ( $preset_per_origin[ $origin ] as $preset ) {
				$slug = gutenberg_experimental_to_kebab_case( $preset['slug'] );

				// Use the array as a set so we don't get duplicates.
				$result[ $slug ] = $slug;
			}
		}
		return $result;
	}

	/**
	 * Given a settings array, it returns the generated rulesets
	 * for the preset classes.
	 *
	 * @param array  $settings Settings to process.
	 * @param string $selector Selector wrapping the classes.
	 * @param array  $origins  List of origins to process.
	 *
	 * @return string The result of processing the presets.
	 */
	private static function compute_preset_classes( $settings, $selector, $origins ) {
		if ( self::ROOT_BLOCK_SELECTOR === $selector ) {
			// Classes at the global level do not need any CSS prefixed,
			// and we don't want to increase its specificity.
			$selector = '';
		}

		$stylesheet = '';
		foreach ( self::PRESETS_METADATA as $preset_metadata ) {
			$slugs = self::get_settings_slugs( $settings, $preset_metadata, $origins );
			foreach ( $preset_metadata['classes'] as $class => $property ) {
				foreach ( $slugs as $slug ) {
					$css_var     = self::replace_slug_in_string( $preset_metadata['css_vars'], $slug );
					$class_name  = self::replace_slug_in_string( $class, $slug );
					$stylesheet .= self::to_ruleset(
						self::append_to_selector( $selector, $class_name ),
						array(
							array(
								'name'  => $property,
								'value' => 'var(' . $css_var . ') !important',
							),
						)
					);
				}
			}
		}

		return $stylesheet;
	}

	/**
	 * Transform a slug into a CSS Custom Property.
	 *
	 * @param string $input String to replace.
	 * @param string $slug The slug value to use to generate the custom property.
	 *
	 * @return string The CSS Custom Property. Something along the lines of --wp--preset--color--black.
	 */
	private static function replace_slug_in_string( $input, $slug ) {
		return strtr( $input, array( '$slug' => $slug ) );
	}

	/**
	 * Given the block settings, it extracts the CSS Custom Properties
	 * for the presets and adds them to the $declarations array
	 * following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $settings Settings to process.
	 * @param array $origins  List of origins to process.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_preset_vars( $settings, $origins ) {
		$declarations = array();
		foreach ( self::PRESETS_METADATA as $preset_metadata ) {
			$values_by_slug = self::get_settings_values_by_slug( $settings, $preset_metadata, $origins );
			foreach ( $values_by_slug as $slug => $value ) {
				$declarations[] = array(
					'name'  => self::replace_slug_in_string( $preset_metadata['css_vars'], $slug ),
					'value' => $value,
				);
			}
		}

		return $declarations;
	}

	/**
	 * Given an array of settings, it extracts the CSS Custom Properties
	 * for the custom values and adds them to the $declarations
	 * array following the format:
	 *
	 * ```php
	 * array(
	 *   'name'  => 'property_name',
	 *   'value' => 'property_value,
	 * )
	 * ```
	 *
	 * @param array $settings Settings to process.
	 *
	 * @return array Returns the modified $declarations.
	 */
	private static function compute_theme_vars( $settings ) {
		$declarations  = array();
		$custom_values = _wp_array_get( $settings, array( 'custom' ), array() );
		$css_vars      = self::flatten_tree( $custom_values );
		foreach ( $css_vars as $key => $value ) {
			$declarations[] = array(
				'name'  => '--wp--custom--' . $key,
				'value' => $value,
			);
		}

		return $declarations;
	}

	/**
	 * Given a selector and a declaration list,
	 * creates the corresponding ruleset.
	 *
	 * To help debugging, will add some space
	 * if SCRIPT_DEBUG is defined and true.
	 *
	 * @param string $selector CSS selector.
	 * @param array  $declarations List of declarations.
	 *
	 * @return string CSS ruleset.
	 */
	private static function to_ruleset( $selector, $declarations ) {
		if ( empty( $declarations ) ) {
			return '';
		}
		$ruleset = '';

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$declaration_block = array_reduce(
				$declarations,
				function ( $carry, $element ) {
					return $carry .= "\t" . $element['name'] . ': ' . $element['value'] . ";\n"; },
				''
			);
			$ruleset          .= $selector . " {\n" . $declaration_block . "}\n";
		} else {
			$declaration_block = array_reduce(
				$declarations,
				function ( $carry, $element ) {
					return $carry .= $element['name'] . ': ' . $element['value'] . ';'; },
				''
			);
			$ruleset          .= $selector . '{' . $declaration_block . '}';
		}

		return $ruleset;
	}

	/**
	 * Converts each styles section into a list of rulesets
	 * to be appended to the stylesheet.
	 * These rulesets contain all the css variables (custom variables and preset variables).
	 *
	 * See glossary at https://developer.mozilla.org/en-US/docs/Web/CSS/Syntax
	 *
	 * For each section this creates a new ruleset such as:
	 *
	 *   block-selector {
	 *     --wp--preset--category--slug: value;
	 *     --wp--custom--variable: value;
	 *   }
	 *
	 * @param array $nodes Nodes with settings.
	 * @param array $origins List of origins to process.
	 *
	 * @return string The new stylesheet.
	 */
	private function get_css_variables( $nodes, $origins ) {
		$stylesheet = '';
		foreach ( $nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector = $metadata['selector'];

			$node         = _wp_array_get( $this->theme_json, $metadata['path'], array() );
			$declarations = array_merge( self::compute_preset_vars( $node, $origins ), self::compute_theme_vars( $node ) );

			$stylesheet .= self::to_ruleset( $selector, $declarations );
		}

		return $stylesheet;
	}

	/**
	 * Converts each style section into a list of rulesets
	 * containing the block styles to be appended to the stylesheet.
	 *
	 * See glossary at https://developer.mozilla.org/en-US/docs/Web/CSS/Syntax
	 *
	 * For each section this creates a new ruleset such as:
	 *
	 *   block-selector {
	 *     style-property-one: value;
	 *   }
	 *
	 * @param array $style_nodes Nodes with styles.
	 *
	 * @return string The new stylesheet.
	 */
	private function get_block_classes( $style_nodes ) {
		$block_rules = '';

		foreach ( $style_nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$node         = _wp_array_get( $this->theme_json, $metadata['path'], array() );
			$selector     = $metadata['selector'];
			$settings     = _wp_array_get( $this->theme_json, array( 'settings' ) );
			$declarations = self::compute_style_properties( $node, $settings );

			// 1. Separate the ones who use the general selector
			// and the ones who use the duotone selector.
			$declarations_duotone = array();
			foreach ( $declarations as $index => $declaration ) {
				if ( 'filter' === $declaration['name'] ) {
					unset( $declarations[ $index ] );
					$declarations_duotone[] = $declaration;
				}
			}

			// 2. Generate the rules that use the general selector.
			$block_rules .= self::to_ruleset( $selector, $declarations );

			// 3. Generate the rules that use the duotone selector.
			if ( isset( $metadata['duotone'] ) && ! empty( $declarations_duotone ) ) {
				$selector_duotone = self::scope_selector( $metadata['selector'], $metadata['duotone'] );
				$block_rules     .= self::to_ruleset( $selector_duotone, $declarations_duotone );
			}

			if ( self::ROOT_BLOCK_SELECTOR === $selector ) {
				$block_rules .= 'body { margin: 0; }';
				$block_rules .= '.wp-site-blocks > .alignleft { float: left; margin-right: 2em; }';
				$block_rules .= '.wp-site-blocks > .alignright { float: right; margin-left: 2em; }';
				$block_rules .= '.wp-site-blocks > .aligncenter { justify-content: center; margin-left: auto; margin-right: auto; }';

				$has_block_gap_support = _wp_array_get( $this->theme_json, array( 'settings', 'spacing', 'blockGap' ) ) !== null;
				if ( $has_block_gap_support ) {
					$block_rules .= '.wp-site-blocks > * { margin-top: 0; margin-bottom: 0; }';
					$block_rules .= '.wp-site-blocks > * + * { margin-top: var( --wp--style--block-gap ); }';
				}
			}
		}

		return $block_rules;
	}

	/**
	 * Creates new rulesets as classes for each preset value such as:
	 *
	 *   .has-value-color {
	 *     color: value;
	 *   }
	 *
	 *   .has-value-background-color {
	 *     background-color: value;
	 *   }
	 *
	 *   .has-value-font-size {
	 *     font-size: value;
	 *   }
	 *
	 *   .has-value-gradient-background {
	 *     background: value;
	 *   }
	 *
	 *   p.has-value-gradient-background {
	 *     background: value;
	 *   }

	 * @param array $setting_nodes Nodes with settings.
	 * @param array $origins       List of origins to process presets from.
	 *
	 * @return string The new stylesheet.
	 */
	private function get_preset_classes( $setting_nodes, $origins ) {
		$preset_rules = '';

		foreach ( $setting_nodes as $metadata ) {
			if ( null === $metadata['selector'] ) {
				continue;
			}

			$selector      = $metadata['selector'];
			$node          = _wp_array_get( $this->theme_json, $metadata['path'], array() );
			$preset_rules .= self::compute_preset_classes( $node, $selector, $origins );
		}

		return $preset_rules;
	}

	/**
	 * Returns the existing settings for each block.
	 *
	 * Example:
	 *
	 * {
	 *   'root': {
	 *     'color': {
	 *       'custom': true
	 *     }
	 *   },
	 *   'core/paragraph': {
	 *     'spacing': {
	 *       'customPadding': true
	 *     }
	 *   }
	 * }
	 *
	 * @return array Settings per block.
	 */
	public function get_settings() {
		if ( ! isset( $this->theme_json['settings'] ) ) {
			return array();
		} else {
			return $this->theme_json['settings'];
		}
	}

	/**
	 * Returns the page templates of the current theme.
	 *
	 * @return array
	 */
	public function get_custom_templates() {
		$custom_templates = array();
		if ( ! isset( $this->theme_json['customTemplates'] ) ) {
			return $custom_templates;
		}

		foreach ( $this->theme_json['customTemplates'] as $item ) {
			if ( isset( $item['name'] ) ) {
				$custom_templates[ $item['name'] ] = array(
					'title'     => isset( $item['title'] ) ? $item['title'] : '',
					'postTypes' => isset( $item['postTypes'] ) ? $item['postTypes'] : array( 'page' ),
				);
			}
		}
		return $custom_templates;
	}

	/**
	 * Returns the template part data of current theme.
	 *
	 * @return array
	 */
	public function get_template_parts() {
		$template_parts = array();
		if ( ! isset( $this->theme_json['templateParts'] ) ) {
			return $template_parts;
		}

		foreach ( $this->theme_json['templateParts'] as $item ) {
			if ( isset( $item['name'] ) ) {
				$template_parts[ $item['name'] ] = array(
					'title' => isset( $item['title'] ) ? $item['title'] : '',
					'area'  => isset( $item['area'] ) ? $item['area'] : '',
				);
			}
		}
		return $template_parts;
	}

	/**
	 * Builds metadata for the style nodes, which returns in the form of:
	 *
	 * [
	 *   [
	 *     'path'     => [ 'path', 'to', 'some', 'node' ],
	 *     'selector' => 'CSS selector for some node',
	 *     'duotone'  => 'CSS selector for duotone for some node'
	 *   ],
	 *   [
	 *     'path'     => ['path', 'to', 'other', 'node' ],
	 *     'selector' => 'CSS selector for other node',
	 *     'duotone'  => null
	 *   ],
	 * ]
	 *
	 * @param array $theme_json The tree to extract style nodes from.
	 * @param array $selectors List of selectors per block.
	 *
	 * @return array
	 */
	private static function get_style_nodes( $theme_json, $selectors = array() ) {
		$nodes = array();
		if ( ! isset( $theme_json['styles'] ) ) {
			return $nodes;
		}

		// Top-level.
		$nodes[] = array(
			'path'     => array( 'styles' ),
			'selector' => self::ROOT_BLOCK_SELECTOR,
		);

		if ( isset( $theme_json['styles']['elements'] ) ) {
			foreach ( $theme_json['styles']['elements'] as $element => $node ) {
				$nodes[] = array(
					'path'     => array( 'styles', 'elements', $element ),
					'selector' => self::ELEMENTS[ $element ],
				);
			}
		}

		// Blocks.
		if ( ! isset( $theme_json['styles']['blocks'] ) ) {
			return $nodes;
		}

		foreach ( $theme_json['styles']['blocks'] as $name => $node ) {
			$selector = null;
			if ( isset( $selectors[ $name ]['selector'] ) ) {
				$selector = $selectors[ $name ]['selector'];
			}

			$duotone_selector = null;
			if ( isset( $selectors[ $name ]['duotone'] ) ) {
				$duotone_selector = $selectors[ $name ]['duotone'];
			}

			$nodes[] = array(
				'path'     => array( 'styles', 'blocks', $name ),
				'selector' => $selector,
				'duotone'  => $duotone_selector,
			);

			if ( isset( $theme_json['styles']['blocks'][ $name ]['elements'] ) ) {
				foreach ( $theme_json['styles']['blocks'][ $name ]['elements'] as $element => $node ) {
					$nodes[] = array(
						'path'     => array( 'styles', 'blocks', $name, 'elements', $element ),
						'selector' => $selectors[ $name ]['elements'][ $element ],
					);
				}
			}
		}

		return $nodes;
	}

	/**
	 * Builds metadata for the setting nodes, which returns in the form of:
	 *
	 * [
	 *   [
	 *     'path'     => ['path', 'to', 'some', 'node' ],
	 *     'selector' => 'CSS selector for some node'
	 *   ],
	 *   [
	 *     'path'     => [ 'path', 'to', 'other', 'node' ],
	 *     'selector' => 'CSS selector for other node'
	 *   ],
	 * ]
	 *
	 * @param array $theme_json The tree to extract setting nodes from.
	 * @param array $selectors List of selectors per block.
	 *
	 * @return array
	 */
	private static function get_setting_nodes( $theme_json, $selectors = array() ) {
		$nodes = array();
		if ( ! isset( $theme_json['settings'] ) ) {
			return $nodes;
		}

		// Top-level.
		$nodes[] = array(
			'path'     => array( 'settings' ),
			'selector' => self::ROOT_BLOCK_SELECTOR,
		);

		// Calculate paths for blocks.
		if ( ! isset( $theme_json['settings']['blocks'] ) ) {
			return $nodes;
		}

		foreach ( $theme_json['settings']['blocks'] as $name => $node ) {
			$selector = null;
			if ( isset( $selectors[ $name ]['selector'] ) ) {
				$selector = $selectors[ $name ]['selector'];
			}

			$nodes[] = array(
				'path'     => array( 'settings', 'blocks', $name ),
				'selector' => $selector,
			);
		}

		return $nodes;
	}

	/**
	 * Returns the stylesheet that results of processing
	 * the theme.json structure this object represents.
	 *
	 * @param string $type   Type of stylesheet. It accepts:
	 *                         'all': css variables, block classes, preset classes. The default.
	 *                         'block_styles': only block & preset classes.
	 *                         'css_variables': only css variables.
	 *                         'presets': only css variables and preset classes.
	 * @param array  $origins A list of origins to include. By default it includes 'core', 'theme', and 'user'.
	 *
	 * @return string Stylesheet.
	 */
	public function get_stylesheet( $type = 'all', $origins = self::VALID_ORIGINS ) {
		$blocks_metadata = self::get_blocks_metadata();
		$style_nodes     = self::get_style_nodes( $this->theme_json, $blocks_metadata );
		$setting_nodes   = self::get_setting_nodes( $this->theme_json, $blocks_metadata );

		switch ( $type ) {
			case 'block_styles':
				return $this->get_block_classes( $style_nodes ) . $this->get_preset_classes( $setting_nodes, $origins );
			case 'css_variables':
				return $this->get_css_variables( $setting_nodes, $origins );
			case 'presets':
				return $this->get_css_variables( $setting_nodes, $origins ) . $this->get_preset_classes( $setting_nodes, $origins );
			default:
				return $this->get_css_variables( $setting_nodes, $origins ) . $this->get_block_classes( $style_nodes ) . $this->get_preset_classes( $setting_nodes, $origins );
		}
	}

	/**
	 * Merge new incoming data.
	 *
	 * @param WP_Theme_JSON $incoming Data to merge.
	 */
	public function merge( $incoming ) {
		$incoming_data    = $incoming->get_raw_data();
		$this->theme_json = array_replace_recursive( $this->theme_json, $incoming_data );

		// The array_replace_recursive algorithm merges at the leaf level.
		// For leaf values that are arrays it will use the numeric indexes for replacement.
		// In those cases, we want to replace the existing with the incoming value, if it exists.
		$to_replace   = array();
		$to_replace[] = array( 'spacing', 'units' );
		foreach ( self::VALID_ORIGINS as $origin ) {
			$to_replace[] = array( 'color', 'duotone', $origin );
			$to_replace[] = array( 'color', 'palette', $origin );
			$to_replace[] = array( 'color', 'gradients', $origin );
			$to_replace[] = array( 'typography', 'fontSizes', $origin );
			$to_replace[] = array( 'typography', 'fontFamilies', $origin );
		}

		$nodes = self::get_setting_nodes( $this->theme_json );
		foreach ( $nodes as $metadata ) {
			foreach ( $to_replace as $property_path ) {
				$path = array_merge( $metadata['path'], $property_path );
				$node = _wp_array_get( $incoming_data, $path, null );
				if ( isset( $node ) ) {
					gutenberg_experimental_set( $this->theme_json, $path, $node );
				}
			}
		}

	}

	/**
	 * Processes a setting node and returns the same node
	 * without the insecure settings.
	 *
	 * @param array $input Node to process.
	 *
	 * @return array
	 */
	private static function remove_insecure_settings( $input ) {
		$output = array();
		foreach ( self::PRESETS_METADATA as $preset_metadata ) {
			$presets = _wp_array_get( $input, $preset_metadata['path'], null );
			if ( null === $presets ) {
				continue;
			}

			$escaped_preset = array();
			foreach ( $presets as $preset ) {
				if (
					esc_attr( esc_html( $preset['name'] ) ) === $preset['name'] &&
					sanitize_html_class( $preset['slug'] ) === $preset['slug']
				) {
					$value = null;
					if ( isset( $preset_metadata['value_key'] ) ) {
						$value = $preset[ $preset_metadata['value_key'] ];
					} elseif (
						isset( $preset_metadata['value_func'] ) &&
						is_callable( $preset_metadata['value_func'] )
					) {
						$value = call_user_func( $preset_metadata['value_func'], $preset );
					}

					$preset_is_valid = true;
					foreach ( $preset_metadata['properties'] as $property ) {
						if ( ! self::is_safe_css_declaration( $property, $value ) ) {
							$preset_is_valid = false;
							break;
						}
					}

					if ( $preset_is_valid ) {
						$escaped_preset[] = $preset;
					}
				}
			}

			if ( ! empty( $escaped_preset ) ) {
				gutenberg_experimental_set( $output, $preset_metadata['path'], $escaped_preset );
			}
		}

		return $output;
	}

	/**
	 * Processes a style node and returns the same node
	 * without the insecure styles.
	 *
	 * @param array $input Node to process.
	 *
	 * @return array
	 */
	private static function remove_insecure_styles( $input ) {
		$output       = array();
		$declarations = self::compute_style_properties( $input );

		foreach ( $declarations as $declaration ) {
			if ( self::is_safe_css_declaration( $declaration['name'], $declaration['value'] ) ) {
				$path = self::PROPERTIES_METADATA[ $declaration['name'] ];

				// Check the value isn't an array before adding so as to not
				// double up shorthand and longhand styles.
				$value = _wp_array_get( $input, $path, array() );
				if ( ! is_array( $value ) ) {
					gutenberg_experimental_set( $output, $path, $value );
				}
			}
		}
		return $output;
	}

	/**
	 * Checks that a declaration provided by the user is safe.
	 *
	 * @param string $property_name Property name in a CSS declaration, i.e. the `color` in `color: red`.
	 * @param string $property_value Value in a CSS declaration, i.e. the `red` in `color: red`.
	 * @return boolean
	 */
	private static function is_safe_css_declaration( $property_name, $property_value ) {
		$style_to_validate = $property_name . ': ' . $property_value;
		$filtered          = esc_html( safecss_filter_attr( $style_to_validate ) );
		return ! empty( trim( $filtered ) );
	}

	/**
	 * Removes insecure data from theme.json.
	 *
	 * @param array $theme_json Structure to sanitize.
	 *
	 * @return array Sanitized structure.
	 */
	public static function remove_insecure_properties( $theme_json ) {
		$sanitized = array();

		if ( ! isset( $theme_json['version'] ) || 0 === $theme_json['version'] ) {
			$theme_json = WP_Theme_JSON_Schema_V0::parse( $theme_json );
		}

		$valid_block_names   = array_keys( self::get_blocks_metadata() );
		$valid_element_names = array_keys( self::ELEMENTS );
		$theme_json          = self::sanitize( $theme_json, $valid_block_names, $valid_element_names );

		$blocks_metadata = self::get_blocks_metadata();
		$style_nodes     = self::get_style_nodes( $theme_json, $blocks_metadata );
		foreach ( $style_nodes as $metadata ) {
			$input = _wp_array_get( $theme_json, $metadata['path'], array() );
			if ( empty( $input ) ) {
				continue;
			}

			$output = self::remove_insecure_styles( $input );
			if ( ! empty( $output ) ) {
				gutenberg_experimental_set( $sanitized, $metadata['path'], $output );
			}
		}

		$setting_nodes = self::get_setting_nodes( $theme_json );
		foreach ( $setting_nodes as $metadata ) {
			$input = _wp_array_get( $theme_json, $metadata['path'], array() );
			if ( empty( $input ) ) {
				continue;
			}

			$output = self::remove_insecure_settings( $input );
			if ( ! empty( $output ) ) {
				gutenberg_experimental_set( $sanitized, $metadata['path'], $output );
			}
		}

		if ( empty( $sanitized['styles'] ) ) {
			unset( $theme_json['styles'] );
		} else {
			$theme_json['styles'] = $sanitized['styles'];
		}

		if ( empty( $sanitized['settings'] ) ) {
			unset( $theme_json['settings'] );
		} else {
			$theme_json['settings'] = $sanitized['settings'];
		}

		return $theme_json;
	}

	/**
	 * Returns the raw data.
	 *
	 * @return array Raw data.
	 */
	public function get_raw_data() {
		return $this->theme_json;
	}

	/**
	 *
	 * Transforms the given editor settings according the
	 * add_theme_support format to the theme.json format.
	 *
	 * @param array $settings Existing editor settings.
	 *
	 * @return array Config that adheres to the theme.json schema.
	 */
	public static function get_from_editor_settings( $settings ) {
		$theme_settings = array(
			'version'  => self::LATEST_SCHEMA,
			'settings' => array(),
		);

		// Deprecated theme supports.
		if ( isset( $settings['disableCustomColors'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['custom'] = ! $settings['disableCustomColors'];
		}

		if ( isset( $settings['disableCustomGradients'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['customGradient'] = ! $settings['disableCustomGradients'];
		}

		if ( isset( $settings['disableCustomFontSizes'] ) ) {
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['customFontSize'] = ! $settings['disableCustomFontSizes'];
		}

		if ( isset( $settings['enableCustomLineHeight'] ) ) {
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['customLineHeight'] = $settings['enableCustomLineHeight'];
		}

		if ( isset( $settings['enableCustomUnits'] ) ) {
			if ( ! isset( $theme_settings['settings']['spacing'] ) ) {
				$theme_settings['settings']['spacing'] = array();
			}
			$theme_settings['settings']['spacing']['units'] = ( true === $settings['enableCustomUnits'] ) ?
				array( 'px', 'em', 'rem', 'vh', 'vw', '%' ) :
				$settings['enableCustomUnits'];
		}

		if ( isset( $settings['colors'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['palette'] = $settings['colors'];
		}

		if ( isset( $settings['gradients'] ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['gradients'] = $settings['gradients'];
		}

		if ( isset( $settings['fontSizes'] ) ) {
			$font_sizes = $settings['fontSizes'];
			// Back-compatibility for presets without units.
			foreach ( $font_sizes as $key => $font_size ) {
				if ( is_numeric( $font_size['size'] ) ) {
					$font_sizes[ $key ]['size'] = $font_size['size'] . 'px';
				}
			}
			if ( ! isset( $theme_settings['settings']['typography'] ) ) {
				$theme_settings['settings']['typography'] = array();
			}
			$theme_settings['settings']['typography']['fontSizes'] = $font_sizes;
		}

		// This allows to make the plugin work with WordPress 5.7 beta
		// as well as lower versions. The second check can be removed
		// as soon as the minimum WordPress version for the plugin
		// is bumped to 5.7.
		if ( isset( $settings['enableCustomSpacing'] ) ) {
			if ( ! isset( $theme_settings['settings']['spacing'] ) ) {
				$theme_settings['settings']['spacing'] = array();
			}
			$theme_settings['settings']['spacing']['customPadding'] = $settings['enableCustomSpacing'];
		}

		// Things that didn't land in core yet, so didn't have a setting assigned.
		// This should be removed when the plugin minimum WordPress version
		// is bumped to 5.8.
		//
		// Do not port this to WordPress core.
		if ( current( (array) get_theme_support( 'experimental-link-color' ) ) ) {
			if ( ! isset( $theme_settings['settings']['color'] ) ) {
				$theme_settings['settings']['color'] = array();
			}
			$theme_settings['settings']['color']['link'] = true;
		}

		return $theme_settings;
	}

}
