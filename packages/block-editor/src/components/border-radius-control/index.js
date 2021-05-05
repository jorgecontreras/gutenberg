/**
 * WordPress dependencies
 */
import { RangeControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import AllInputControl from './all-input-control';
import InputControls from './input-controls';
import LinkedButton from './linked-button';
import {
	CSS_UNITS,
	DEFAULT_VALUES,
	getAllValue,
	getAllUnit,
	isValuesDefined,
	isValuesMixed,
	parseUnit,
} from './utils';

export const MIN_BORDER_RADIUS_VALUE = 0;

/**
 * Control to display border radius options.
 *
 * @param  {Object}    props Component props.
 * @return {WPElement}       Custom border radius control.
 */

export default function BorderRadiusControl( { onChange, values } ) {
	const [ isLinked, setIsLinked ] = useState(
		! isValuesDefined( values ) || ! isValuesMixed( values )
	);

	const [ allValue ] = parseUnit( getAllValue( values ) );
	const unit = getAllUnit( values );
	const step = unit === 'px' ? 1 : 0.25;

	const toggleLinked = () => setIsLinked( ! isLinked );

	const handleSliderChange = ( next ) => {
		onChange( next !== undefined ? `${ next }${ unit }` : undefined );
	};

	return (
		<fieldset className="components-border-radius-control">
			<legend>{ __( 'Radius' ) }</legend>
			<div className="components-border-radius-control__wrapper">
				{ isLinked ? (
					<>
						<AllInputControl
							className="components-border-radius-control__unit-control"
							values={ values }
							min={ MIN_BORDER_RADIUS_VALUE }
							onChange={ onChange }
							unit={ unit }
							units={ CSS_UNITS }
							step={ step }
						/>
						<RangeControl
							className="components-border-radius-control__range-control"
							value={ allValue }
							min={ MIN_BORDER_RADIUS_VALUE }
							initialPosition={ 0 }
							withInputField={ false }
							onChange={ handleSliderChange }
							step={ step }
						/>
					</>
				) : (
					<InputControls
						min={ MIN_BORDER_RADIUS_VALUE }
						onChange={ onChange }
						values={ values || DEFAULT_VALUES }
						units={ CSS_UNITS }
					/>
				) }
				<LinkedButton onClick={ toggleLinked } isLinked={ isLinked } />
			</div>
		</fieldset>
	);
}
