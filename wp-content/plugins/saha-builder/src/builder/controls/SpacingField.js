/**
 * Khoảng cách 4 cạnh (margin/padding) cho thiết bị đang chọn.
 */
import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

const SIDES = [
	[ 'top', __( 'Trên', 'saha-builder' ) ],
	[ 'right', __( 'Phải', 'saha-builder' ) ],
	[ 'bottom', __( 'Dưới', 'saha-builder' ) ],
	[ 'left', __( 'Trái', 'saha-builder' ) ],
];

export default function SpacingField( { value, onChange, placeholder } ) {
	const [ linked, setLinked ] = useState( false );
	const current = value && 'object' === typeof value ? value : {};
	const inherited =
		placeholder && 'object' === typeof placeholder ? placeholder : {};

	const set = ( side, raw ) => {
		const v = raw.trim();
		const next = { ...current };

		( linked ? SIDES.map( ( [ s ] ) => s ) : [ side ] ).forEach( ( s ) => {
			if ( v ) {
				next[ s ] = v;
			} else {
				delete next[ s ];
			}
		} );

		onChange( Object.keys( next ).length ? next : null );
	};

	return (
		<div className="saha-b-spacing">
			{ SIDES.map( ( [ side, label ] ) => (
				<TextControl
					key={ side }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ label }
					value={ current[ side ] ?? '' }
					placeholder={ inherited[ side ] ?? '' }
					onChange={ ( v ) => set( side, v ) }
				/>
			) ) }
			<Button
				icon={ linked ? 'admin-links' : 'editor-unlink' }
				label={
					linked
						? __( 'Đang đặt 4 cạnh cùng lúc', 'saha-builder' )
						: __( 'Đặt 4 cạnh cùng lúc', 'saha-builder' )
				}
				isPressed={ linked }
				size="compact"
				onClick={ () => setLinked( ! linked ) }
			/>
		</div>
	);
}
