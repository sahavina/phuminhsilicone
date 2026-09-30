/**
 * Nền: màu, ảnh (vị trí, cỡ, lặp, cố định), lớp phủ.
 */
import { RangeControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import ColorField from './ColorField';
import MediaField from './MediaField';

const common = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

const LABELS = {
	cover: __( 'Phủ kín (cover)', 'saha-builder' ),
	contain: __( 'Vừa khung (contain)', 'saha-builder' ),
	auto: __( 'Kích thước gốc', 'saha-builder' ),
	'no-repeat': __( 'Không lặp', 'saha-builder' ),
	repeat: __( 'Lặp', 'saha-builder' ),
	'repeat-x': __( 'Lặp ngang', 'saha-builder' ),
	'repeat-y': __( 'Lặp dọc', 'saha-builder' ),
	scroll: __( 'Cuộn theo trang', 'saha-builder' ),
	fixed: __( 'Cố định (parallax)', 'saha-builder' ),
};

export default function BackgroundField( { value, onChange, def } ) {
	const current = value && 'object' === typeof value ? value : {};

	const update = ( key, next ) => {
		const copy = { ...current };

		if ( null === next || undefined === next || '' === next ) {
			delete copy[ key ];
		} else {
			copy[ key ] = next;
		}

		if ( ! copy.image ) {
			[ 'position', 'size', 'repeat', 'attachment' ].forEach(
				( k ) => delete copy[ k ]
			);
		}

		onChange( Object.keys( copy ).length ? copy : null );
	};

	const select = ( key, label, list, fallback ) => (
		<SelectControl
			{ ...common }
			label={ label }
			value={ current[ key ] ?? fallback }
			options={ list.map( ( v ) => ( {
				value: v,
				label: LABELS[ v ] || v,
			} ) ) }
			onChange={ ( v ) => update( key, v === fallback ? null : v ) }
		/>
	);

	const overlay = current.overlay || {};

	return (
		<div className="saha-b-background">
			<div
				className="saha-b-subfield"
				role="group"
				aria-label={ __( 'Màu nền', 'saha-builder' ) }
			>
				<div className="saha-b-subfield__label">
					{ __( 'Màu nền', 'saha-builder' ) }
				</div>
				<ColorField
					value={ current.color }
					onChange={ ( v ) => update( 'color', v ) }
					def={ { label: __( 'Màu nền', 'saha-builder' ) } }
				/>
			</div>

			<div
				className="saha-b-subfield"
				role="group"
				aria-label={ __( 'Ảnh nền', 'saha-builder' ) }
			>
				<div className="saha-b-subfield__label">
					{ __( 'Ảnh nền', 'saha-builder' ) }
				</div>
				<MediaField
					value={ current.image }
					onChange={ ( v ) => update( 'image', v ) }
					def={ { sizes: def.imageSizes, defaultSize: 'full' } }
				/>
			</div>

			{ current.image && (
				<div className="saha-b-grid-2">
					{ select(
						'position',
						__( 'Vị trí', 'saha-builder' ),
						def.positions || [ 'center center' ],
						'center center'
					) }
					{ select(
						'size',
						__( 'Cỡ', 'saha-builder' ),
						def.sizes || [ 'cover' ],
						'cover'
					) }
					{ select(
						'repeat',
						__( 'Lặp', 'saha-builder' ),
						def.repeats || [ 'no-repeat' ],
						'no-repeat'
					) }
					{ select(
						'attachment',
						__( 'Khi cuộn', 'saha-builder' ),
						def.attachments || [ 'scroll' ],
						'scroll'
					) }
				</div>
			) }

			<div
				className="saha-b-subfield"
				role="group"
				aria-label={ __(
					'Lớp phủ (làm tối ảnh để chữ dễ đọc)',
					'saha-builder'
				) }
			>
				<div className="saha-b-subfield__label">
					{ __(
						'Lớp phủ (làm tối ảnh để chữ dễ đọc)',
						'saha-builder'
					) }
				</div>
				<ColorField
					value={ overlay.color }
					onChange={ ( color ) =>
						update(
							'overlay',
							color
								? { color, opacity: overlay.opacity ?? 0.5 }
								: null
						)
					}
					def={ { label: __( 'Màu lớp phủ', 'saha-builder' ) } }
				/>
			</div>

			{ overlay.color && (
				<RangeControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Độ đậm lớp phủ', 'saha-builder' ) }
					value={ overlay.opacity ?? 0.5 }
					min={ 0 }
					max={ 1 }
					step={ 0.05 }
					onChange={ ( opacity ) =>
						update( 'overlay', { ...overlay, opacity } )
					}
				/>
			) }
		</div>
	);
}
