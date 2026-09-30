/**
 * Liên kết: URL + mở tab mới + nofollow.
 */
import { TextControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function LinkField( { value, onChange, def } ) {
	const current = value && 'object' === typeof value ? value : {};

	const update = ( patch ) => {
		const next = { ...current, ...patch };

		Object.keys( next ).forEach( ( key ) => {
			if ( ! next[ key ] ) {
				delete next[ key ];
			}
		} );

		onChange( next.url ? next : null );
	};

	return (
		<div className="saha-b-link">
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ def.label }
				hideLabelFromVision
				type="url"
				value={ current.url ?? '' }
				placeholder="https://… , /lien-he/ , tel:… , #neo"
				onChange={ ( url ) => update( { url: url.trim() } ) }
			/>
			{ current.url && (
				<>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Mở trong tab mới', 'saha-builder' ) }
						checked={ !! current.newTab }
						onChange={ ( newTab ) => update( { newTab } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'nofollow (liên kết không tin cậy / quảng cáo)',
							'saha-builder'
						) }
						checked={ !! current.nofollow }
						onChange={ ( nofollow ) => update( { nofollow } ) }
					/>
				</>
			) }
		</div>
	);
}
