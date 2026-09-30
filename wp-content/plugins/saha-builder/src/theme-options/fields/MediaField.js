/**
 * Chọn ảnh từ Media Library — lưu attachment ID, không lưu URL (spec SCC §80).
 */
import { Button } from '@wordpress/components';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { MediaUpload } from '@wordpress/media-utils';

const canUpload = Boolean( window.sahaThemeOptions?.canUpload );

export default function MediaField( { id, value, onChange, describedBy } ) {
	const mediaId = Number( value ) || 0;

	const media = useSelect(
		( select ) =>
			mediaId ? select( coreStore ).getMedia( mediaId ) : null,
		[ mediaId ]
	);

	const preview =
		media?.media_details?.sizes?.medium?.source_url || media?.source_url;

	return (
		<div
			className="saha-to__media"
			id={ id }
			aria-describedby={ describedBy }
		>
			{ mediaId > 0 && (
				<div className="saha-to__media-preview">
					{ preview ? (
						<img src={ preview } alt={ media?.alt_text || '' } />
					) : (
						<span>{ __( 'Đang tải ảnh…', 'saha-builder' ) }</span>
					) }
				</div>
			) }
			{ canUpload ? (
				<MediaUpload
					allowedTypes={ [ 'image' ] }
					value={ mediaId }
					onSelect={ ( selected ) => onChange( selected.id ) }
					render={ ( { open } ) => (
						<Button variant="secondary" onClick={ open }>
							{ mediaId
								? __( 'Đổi ảnh', 'saha-builder' )
								: __( 'Chọn ảnh', 'saha-builder' ) }
						</Button>
					) }
				/>
			) : (
				<p>
					{ __( 'Bạn không có quyền tải ảnh lên.', 'saha-builder' ) }
				</p>
			) }
			{ mediaId > 0 && (
				<Button
					variant="link"
					isDestructive
					onClick={ () => onChange( 0 ) }
				>
					{ __( 'Bỏ ảnh', 'saha-builder' ) }
				</Button>
			) }
		</div>
	);
}
