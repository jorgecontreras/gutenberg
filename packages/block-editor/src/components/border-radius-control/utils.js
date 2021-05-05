/**
 * External dependencies
 */
import { isEmpty, isNumber } from 'lodash';

/**
 * WordPress dependencies
 */
import { Platform } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const isWeb = Platform.OS === 'web';

export const CSS_UNITS = [
	{
		value: 'px',
		label: isWeb ? 'px' : __( 'Pixels (px)' ),
		default: '',
	},
	{
		value: 'em',
		label: isWeb ? 'em' : __( 'Relative to parent font size (em)' ),
		default: '',
	},
	{
		value: 'rem',
		label: isWeb ? 'rem' : __( 'Relative to root font size (rem)' ),
		default: '',
	},
];

export const LABELS = {
	all: __( 'All' ),
	topLeft: __( 'Top left' ),
	topRight: __( 'Top right' ),
	bottomLeft: __( 'Bottom left' ),
	bottomRight: __( 'Bottom right' ),
	mixed: __( 'Mixed' ),
};

export const DEFAULT_VALUES = {
	topLeft: null,
	topRight: null,
	bottomLeft: null,
	bottomRight: null,
};

/**
 * Parses a number and unit from a value.
 *
 * @param {string} initialValue Value to parse
 * @return {Array<number, string>} The extracted number and unit.
 */
export function parseUnit( initialValue ) {
	const value = String( initialValue ).trim();

	let num = parseFloat( value, 10 );
	num = isNaN( num ) ? '' : num;

	const unitMatch = value.match( /[\d.\-\+]*\s*(.*)/ )[ 1 ];

	let unit = unitMatch !== undefined ? unitMatch : '';
	unit = unit.toLowerCase();

	const match = CSS_UNITS.find( ( item ) => item.value === unit );
	unit = match?.value;

	return [ num, unit ];
}

/**
 * Gets an items with the most occurrence within an array
 * https://stackoverflow.com/a/20762713
 *
 * @param {Array<any>} arr Array of items to check.
 * @return {any} The item with the most occurrences.
 */
function mode( arr ) {
	return arr
		.sort(
			( a, b ) =>
				arr.filter( ( v ) => v === a ).length -
				arr.filter( ( v ) => v === b ).length
		)
		.pop();
}

/**
 * Returns the most common CSS unit in the radius values.
 *
 * @param  {Object|string} values Radius values.
 * @return {string}               Most common CSS unit in values.
 */
export function getAllUnit( values = {} ) {
	if ( typeof values === 'string' ) {
		const [ , unit ] = parseUnit( values );
		return unit || 'px';
	}

	const allUnits = Object.values( values ).map( ( value ) => {
		const [ , unit ] = parseUnit( value );
		return unit;
	} );

	return mode( allUnits );
}

/**
 * Gets the 'all' input value and unit from values data.
 *
 * @param  {Object|string} values Radius values.
 * @return {string}               A value + unit for the 'all' input.
 */
export function getAllValue( values = {} ) {
	/**
	 * Border radius support was originally a single pixel value.
	 *
	 * To maintain backwards compatibility treat this case as the all value.
	 */
	if ( typeof values === 'string' ) {
		return values;
	}

	const parsedValues = Object.values( values ).map( ( value ) =>
		parseUnit( value )
	);

	const allValues = parsedValues.map( ( value ) => value[ 0 ] );
	const allUnits = parsedValues.map( ( value ) => value[ 1 ] );

	const value = allValues.every( ( v ) => v === allValues[ 0 ] )
		? allValues[ 0 ]
		: '';
	const unit = mode( allUnits );

	/**
	 * The isNumber check is important. On reset actions, the incoming value
	 * may be null or an empty string.
	 *
	 * Also, the value may also be zero (0), which is considered a valid unit value.
	 *
	 * isNumber() is more specific for these cases, rather than relying on a
	 * simple truthy check.
	 */
	const allValue = isNumber( value ) ? `${ value }${ unit }` : null;

	return allValue;
}

/**
 * Checks to determine if values are mixed.
 *
 * @param  {Object} values Radius values.
 * @return {boolean}       Whether values are mixed.
 */
export function isValuesMixed( values = {} ) {
	const allValue = getAllValue( values );
	const isMixed = isNaN( parseFloat( allValue ) );

	return isMixed;
}

/**
 * Checks to determine if values are defined.
 *
 * @param  {Object} values Radius values.
 * @return {boolean}       Whether values are mixed.
 */
export function isValuesDefined( values ) {
	return (
		values !== undefined &&
		( typeof values === 'string' ||
			! isEmpty( Object.values( values ).filter( Boolean ) ) )
	);
}
