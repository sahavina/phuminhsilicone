/**
 * Control đơn giản: text, textarea, number, select, toggle, align, htmlId, classList, size.
 */
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const common = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

export function TextField( { value, onChange, placeholder, def } ) {
	return (
		<TextControl
			{ ...common }
			label={ def.label }
			hideLabelFromVision
			value={ value ?? '' }
			placeholder={
				placeholder ??
				( 'string' === typeof def.default ? def.default : '' )
			}
			maxLength={ def.maxLength }
			onChange={ onChange }
		/>
	);
}

export function TextareaField( { value, onChange, def } ) {
	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ def.label }
			hideLabelFromVision
			value={ value ?? '' }
			rows={ 4 }
			onChange={ onChange }
		/>
	);
}

export function NumberField( { value, onChange, placeholder, def } ) {
	return (
		<TextControl
			{ ...common }
			type="number"
			label={ def.label }
			hideLabelFromVision
			value={ value ?? '' }
			min={ def.min }
			max={ def.max }
			placeholder={
				placeholder ??
				( undefined !== def.default && 'object' !== typeof def.default
					? String( def.default )
					: '' )
			}
			onChange={ ( v ) => onChange( '' === v ? null : Number( v ) ) }
		/>
	);
}

export function SelectField( { value, onChange, placeholder, def } ) {
	const options = [ ...( def.options || [] ) ];

	// Không có mặc định (hoặc đang kế thừa breakpoint lớn hơn) → cho chọn "mặc định".
	if ( undefined === def.default || def.responsive ) {
		const inherited = options.find( ( o ) => o.value === placeholder );
		options.unshift( {
			value: '',
			label: inherited
				? `— ${ inherited.label } (${ __( 'kế thừa', 'saha-builder' ) }) —`
				: __( '— Mặc định —', 'saha-builder' ),
		} );
	}

	return (
		<SelectControl
			{ ...common }
			label={ def.label }
			hideLabelFromVision
			value={ value ?? ( def.responsive ? '' : ( def.default ?? '' ) ) }
			options={ options }
			onChange={ ( v ) => onChange( '' === v ? null : v ) }
		/>
	);
}

export function ToggleField( { value, onChange, def } ) {
	const checked = value ?? def.default ?? false;

	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ def.label }
			checked={ !! checked }
			// Bằng mặc định → bỏ key (JSON gọn); khác mặc định → lưu tường minh.
			onChange={ ( v ) =>
				onChange( v === ( def.default ?? false ) ? null : v )
			}
		/>
	);
}

const ALIGN_ICONS = {
	left: 'editor-alignleft',
	center: 'editor-aligncenter',
	right: 'editor-alignright',
	justify: 'editor-justify',
};

const ALIGN_LABELS = {
	left: __( 'Trái', 'saha-builder' ),
	center: __( 'Giữa', 'saha-builder' ),
	right: __( 'Phải', 'saha-builder' ),
	justify: __( 'Đều hai bên', 'saha-builder' ),
};

export function AlignField( { value, onChange, placeholder, def } ) {
	return (
		<div className="saha-b-buttons" role="group" aria-label={ def.label }>
			{ ( def.options || [] ).map( ( option ) => (
				<Button
					key={ option }
					icon={ ALIGN_ICONS[ option ] }
					label={ ALIGN_LABELS[ option ] }
					size="compact"
					isPressed={ ( value ?? placeholder ) === option }
					className={
						! value && placeholder === option ? 'is-inherited' : ''
					}
					onClick={ () =>
						onChange( value === option ? null : option )
					}
				/>
			) ) }
		</div>
	);
}

/**
 * Kích thước CSS: nhập tự do, server kiểm (24px, 50%, 2rem, var(--saha-…)).
 *
 * @param {Object}                          props             Props.
 * @param {string}                          props.value       Giá trị.
 * @param {(...args: unknown[]) => unknown} props.onChange    Đổi giá trị.
 * @param {string}                          props.placeholder Giá trị kế thừa.
 * @param {Object}                          props.def         Định nghĩa control.
 */
export function SizeField( { value, onChange, placeholder, def } ) {
	const units = ( def.units || [] ).join( ', ' );

	return (
		<TextControl
			{ ...common }
			label={ def.label }
			hideLabelFromVision
			value={ value ?? '' }
			placeholder={
				placeholder ??
				( units
					? `${ __( 'ví dụ', 'saha-builder' ) } 24${ def.units?.[ 0 ] ?? 'px' }`
					: '' )
			}
			onChange={ ( v ) => onChange( v.trim() || null ) }
		/>
	);
}
