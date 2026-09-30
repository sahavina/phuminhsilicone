/**
 * Chọn màu: bảng màu Theme Options (lưu var(--saha-*)) hoặc màu tuỳ chọn.
 */
import {
	Button,
	ColorPicker,
	Dropdown,
	TextControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { config } from '../context';

/**
 * Màu hiển thị của giá trị (biến Theme Options → màu hiện tại).
 *
 * @param {string} value Giá trị.
 * @return {string} Màu CSS.
 */
function preview( value ) {
	const swatch = ( config.palette || [] ).find( ( p ) => p.value === value );
	return swatch ? swatch.color : value;
}

export default function ColorField( { value, onChange, placeholder, def } ) {
	const shown = value || placeholder || '';

	return (
		<div className="saha-b-color">
			<div
				className="saha-b-color__palette"
				role="group"
				aria-label={ __( 'Màu Theme Options', 'saha-builder' ) }
			>
				{ ( config.palette || [] ).map( ( swatch ) => (
					<button
						key={ swatch.value }
						type="button"
						className={
							'saha-b-color__swatch' +
							( value === swatch.value ? ' is-selected' : '' )
						}
						style={ { background: swatch.color } }
						title={ swatch.name }
						aria-label={ swatch.name }
						aria-pressed={ value === swatch.value }
						onClick={ () =>
							onChange(
								value === swatch.value ? null : swatch.value
							)
						}
					/>
				) ) }
			</div>
			<div className="saha-b-color__row">
				<Dropdown
					popoverProps={ { placement: 'left-start' } }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<button
							type="button"
							className="saha-b-color__current"
							style={ {
								background: preview( shown ) || 'transparent',
							} }
							aria-expanded={ isOpen }
							aria-label={ __(
								'Chọn màu tuỳ ý',
								'saha-builder'
							) }
							onClick={ onToggle }
						/>
					) }
					renderContent={ () => (
						<ColorPicker
							color={
								/^#|^rgb/.test( value || '' )
									? value
									: '#000000'
							}
							enableAlpha
							onChange={ ( color ) => onChange( color ) }
						/>
					) }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ def.label }
					hideLabelFromVision
					value={ value ?? '' }
					placeholder={
						placeholder || __( 'Mặc định', 'saha-builder' )
					}
					onChange={ ( v ) => onChange( v.trim() || null ) }
				/>
				{ value && (
					<Button
						size="compact"
						variant="tertiary"
						onClick={ () => onChange( null ) }
					>
						{ __( 'Xoá', 'saha-builder' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
