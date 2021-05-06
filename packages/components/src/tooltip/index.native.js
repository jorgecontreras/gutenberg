/**
 * External dependencies
 */
import {
	Animated,
	Easing,
	StyleSheet,
	PanResponder,
	Text,
	TouchableWithoutFeedback,
	View,
} from 'react-native';

/**
 * WordPress dependencies
 */
import { Children, useState, useRef, useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { createSlotFill } from '../slot-fill';
import styles from './style.scss';

const { Fill, Slot } = createSlotFill( 'Tooltip' );

const Tooltip = ( {
	children,
	position,
	text,
	visible: initialVisible = false,
} ) => {
	const [ visible, setVisible ] = useState( initialVisible );

	return (
		<>
			{ visible && (
				<Fill>
					<Label visible={ visible } text={ text } />
				</Fill>
			) }
			{ Children.only( children ) }
		</>
	);
};

function Label( { children, position, visible, text } ) {
	const animationValue = useRef( new Animated.Value( 0 ) ).current;
	const [ dimensions, setDimensions ] = useState( null );
	// const visible = useContext( TooltipContext );

	// if ( typeof visible === 'undefined' ) {
	// 	throw new Error(
	// 		'Tooltip.Label cannot be rendered outside of the Tooltip component'
	// 	);
	// }

	useEffect( () => {
		startAnimation();
	}, [ visible ] );

	const startAnimation = () => {
		Animated.timing( animationValue, {
			toValue: visible ? 1 : 0,
			duration: visible ? 300 : 150,
			useNativeDriver: true,
			delay: visible ? 500 : 0,
			easing: Easing.out( Easing.quad ),
		} ).start();
	};

	// Transforms rely upon onLayout to enable custom offsets additions
	let tooltipTransforms;
	// if ( dimensions ) {
	// 	tooltipTransforms = [
	// 		{
	// 			translateX:
	// 				( align === 'center' ? -dimensions.width / 2 : 0 ) +
	// 				xOffset,
	// 		},
	// 		{ translateY: -dimensions.height + yOffset },
	// 	];
	// }

	const tooltipStyles = [
		styles.tooltip,
		{
			shadowColor: styles.tooltip__shadow?.color,
			shadowOffset: {
				width: 0,
				height: 2,
			},
			shadowOpacity: 0.25,
			shadowRadius: 2,
			elevation: 2,
			transform: tooltipTransforms,
		},
		// align === 'left' && styles.tooltipLeftAlign,
	];
	const arrowStyles = [
		styles.tooltip__arrow,
		// align === 'left' && styles.arrowLeftAlign,
	];

	return (
		<Animated.View
			style={ {
				opacity: animationValue,
				transform: [
					{
						translateY: animationValue.interpolate( {
							inputRange: [ 0, 1 ],
							outputRange: [ visible ? 4 : -8, -8 ],
						} ),
					},
				],
			} }
		>
			<View
				onLayout={ ( { nativeEvent } ) => {
					const { height, width } = nativeEvent.layout;
					setDimensions( { height, width } );
				} }
				style={ tooltipStyles }
			>
				<Text style={ styles.tooltip__text }>{ text }</Text>
				<View style={ arrowStyles } />
			</View>
		</Animated.View>
	);
}

Tooltip.Slot = ( { children, ...rest } ) => {
	const panResponder = useRef(
		PanResponder.create( {
			/**
			 * To allow dimissing the tooltip on press while also avoiding blocking
			 * interactivity within the child context, we place this `onPress` side
			 * effect within the `onStartShouldSetPanResponderCapture` callback.
			 *
			 * This is a bit unorthodox, but may be the simplest approach to achieving
			 * this outcome. This is effectively a gesture responder that never
			 * becomes the controlling responder. https://bit.ly/2J3ugKF
			 */
			onStartShouldSetPanResponderCapture: () => {
				console.log( '> hello' );
				// if ( onPress ) {
				// 	onPress();
				// }
				return false;
			},
		} )
	).current;
	return (
		<View
			{ ...( true ? panResponder.panHandlers : {} ) }
			style={ [
				// StyleSheet.absoluteFill,
				{ flex: 1, borderColor: 'red', borderWidth: 2 },
			] }
		>
			{ children }
			<Slot { ...rest } />
		</View>
	);
};

export default Tooltip;
