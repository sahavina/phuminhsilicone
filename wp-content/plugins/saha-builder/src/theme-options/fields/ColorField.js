/**
 * Màu: ô nhập mã màu + bảng chọn màu.
 *
 * Ô nhập chấp nhận cả `var(--saha-…)` để tham chiếu màu khác (server kiểm tra).
 */
import { Button, ColorPicker, Dropdown } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const HEX = /^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;

export default function ColorField( {
	id,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	const current = value ?? '';

	return (
		<div className="saha-to__color">
			<Dropdown
				popoverProps={ { placement: 'bottom-start' } }
				renderToggle={ ( { isOpen, onToggle } ) => (
					<Button
						className="saha-to__swatch"
						onClick={ onToggle }
						aria-expanded={ isOpen }
						aria-label={ __( 'Chọn màu', 'saha-builder' ) }
						style={ {
							background: HEX.test( current )
								? current
								: 'transparent',
						} }
					/>
				) }
				renderContent={ () => (
					<ColorPicker
						color={ HEX.test( current ) ? current : '#000000' }
						onChange={ onChange }
						enableAlpha
					/>
				) }
			/>
			<input
				id={ id }
				type="text"
				className="saha-to__input saha-to__input--short"
				value={ current }
				onChange={ ( event ) => onChange( event.target.value.trim() ) }
				placeholder="#0b5cab"
				spellCheck={ false }
				aria-describedby={ describedBy }
				aria-invalid={ invalid || undefined }
			/>
		</div>
	);
}
