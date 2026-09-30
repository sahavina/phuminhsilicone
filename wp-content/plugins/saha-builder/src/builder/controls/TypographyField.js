/**
 * Kiểu chữ. Cỡ chữ, giãn dòng, giãn chữ theo thiết bị đang chọn.
 */
import { SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useBuilder } from '../context';
import { inheritedAt, setAt, valueAt } from './responsive';

const common = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

export default function TypographyField( { value, onChange, def } ) {
	const { device } = useBuilder();
	const current = value && 'object' === typeof value ? value : {};

	const update = ( key, next ) => {
		const copy = { ...current };

		if ( null === next || undefined === next || '' === next ) {
			delete copy[ key ];
		} else {
			copy[ key ] = next;
		}

		onChange( Object.keys( copy ).length ? copy : null );
	};

	const responsive = ( key, label, hint ) => (
		<TextControl
			{ ...common }
			label={ label }
			value={ valueAt( current[ key ], device ) ?? '' }
			placeholder={ inheritedAt( current[ key ], device ) ?? hint }
			onChange={ ( v ) =>
				update( key, setAt( current[ key ], device, v.trim() ) )
			}
		/>
	);

	const select = ( key, label, options ) => (
		<SelectControl
			{ ...common }
			label={ label }
			value={ current[ key ] ?? '' }
			options={ [
				{ value: '', label: __( 'Mặc định', 'saha-builder' ) },
				...options,
			] }
			onChange={ ( v ) => update( key, v ) }
		/>
	);

	return (
		<div className="saha-b-typography">
			{ select(
				'fontFamily',
				__( 'Font', 'saha-builder' ),
				( def.fonts || [] ).filter( ( f ) => '' !== f.value )
			) }
			<div className="saha-b-grid-2">
				{ responsive(
					'fontSize',
					__( 'Cỡ chữ', 'saha-builder' ),
					'16px'
				) }
				{ select(
					'fontWeight',
					__( 'Độ đậm', 'saha-builder' ),
					( def.weights || [] ).map( ( w ) => ( {
						value: w,
						label: w,
					} ) )
				) }
				{ responsive(
					'lineHeight',
					__( 'Giãn dòng', 'saha-builder' ),
					'1.5'
				) }
				{ responsive(
					'letterSpacing',
					__( 'Giãn chữ', 'saha-builder' ),
					'0px'
				) }
				{ select(
					'textTransform',
					__( 'Chữ hoa/thường', 'saha-builder' ),
					[
						{
							value: 'none',
							label: __( 'Giữ nguyên', 'saha-builder' ),
						},
						{
							value: 'uppercase',
							label: __( 'IN HOA', 'saha-builder' ),
						},
						{
							value: 'lowercase',
							label: __( 'thường', 'saha-builder' ),
						},
						{
							value: 'capitalize',
							label: __( 'Viết Hoa Đầu Từ', 'saha-builder' ),
						},
					]
				) }
				{ select( 'fontStyle', __( 'Kiểu', 'saha-builder' ), [
					{ value: 'normal', label: __( 'Thường', 'saha-builder' ) },
					{ value: 'italic', label: __( 'Nghiêng', 'saha-builder' ) },
				] ) }
			</div>
		</div>
	);
}
