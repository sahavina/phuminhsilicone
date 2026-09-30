/**
 * Ảnh từ Media Library: lưu { id, size }, không lưu URL.
 */
import { Button, SelectControl } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MediaUpload } from '@wordpress/media-utils';

import { config } from '../context';

export default function MediaField( { value, onChange, def } ) {
	const current = value && 'object' === typeof value ? value : null;
	const id = current?.id || 0;

	const media = useSelect(
		( select ) => ( id ? select( coreStore ).getMedia( id ) : null ),
		[ id ]
	);
	const thumb =
		media?.media_details?.sizes?.medium?.source_url || media?.source_url;
	const sizes = def.sizes || def.imageSizes || [ 'large', 'full' ];
	const defaultSize = def.defaultSize || 'large';

	return (
		<div className="saha-b-media">
			{ id > 0 && (
				<div className="saha-b-media__preview">
					{ thumb ? (
						<img src={ thumb } alt={ media?.alt_text || '' } />
					) : (
						<span>{ __( 'Đang tải ảnh…', 'saha-builder' ) }</span>
					) }
				</div>
			) }
			<div className="saha-b-media__actions">
				{ config.canUpload ? (
					<MediaUpload
						allowedTypes={ [ 'image' ] }
						value={ id }
						onSelect={ ( selected ) =>
							onChange( {
								id: selected.id,
								size: current?.size || defaultSize,
							} )
						}
						render={ ( { open } ) => (
							<Button
								variant="secondary"
								size="compact"
								onClick={ open }
							>
								{ id
									? __( 'Đổi ảnh', 'saha-builder' )
									: __( 'Chọn ảnh', 'saha-builder' ) }
							</Button>
						) }
					/>
				) : (
					<p>
						{ __( 'Bạn không có quyền chọn ảnh.', 'saha-builder' ) }
					</p>
				) }
				{ id > 0 && (
					<Button
						variant="link"
						isDestructive
						onClick={ () => onChange( null ) }
					>
						{ __( 'Bỏ ảnh', 'saha-builder' ) }
					</Button>
				) }
			</div>
			{ id > 0 && (
				<SelectControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Kích thước ảnh', 'saha-builder' ) }
					value={ current.size || defaultSize }
					options={ sizes.map( ( s ) => ( { value: s, label: s } ) ) }
					onChange={ ( size ) => onChange( { ...current, size } ) }
				/>
			) }
		</div>
	);
}
