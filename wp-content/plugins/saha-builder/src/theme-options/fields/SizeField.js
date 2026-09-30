/**
 * Kích thước CSS, có thể responsive (desktop / tablet / mobile).
 *
 * Tablet / mobile để trống = kế thừa từ breakpoint lớn hơn (spec SCC §55).
 */
import { __ } from '@wordpress/i18n';

import { toResponsive } from '../utils';

const DEVICES = [
	{ key: 'desktop', label: __( 'Desktop', 'saha-builder' ) },
	{ key: 'tablet', label: __( 'Tablet', 'saha-builder' ) },
	{ key: 'mobile', label: __( 'Mobile', 'saha-builder' ) },
];

export default function SizeField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	const placeholder = `${ field.min ?? '' }${ ( field.units || [ 'px' ] )[ 0 ] }`;

	if ( ! field.responsive ) {
		return (
			<input
				id={ id }
				type="text"
				className="saha-to__input saha-to__input--short"
				value={ value ?? '' }
				onChange={ ( event ) => onChange( event.target.value.trim() ) }
				placeholder={ placeholder }
				aria-describedby={ describedBy }
				aria-invalid={ invalid || undefined }
			/>
		);
	}

	const current = toResponsive( value );

	return (
		<div
			className="saha-to__responsive"
			role="group"
			aria-describedby={ describedBy }
		>
			{ DEVICES.map( ( device, index ) => {
				// Nhãn chính của field (BaseControl) trỏ vào ô desktop.
				const inputId = index === 0 ? id : `${ id }-${ device.key }`;

				return (
					<label
						key={ device.key }
						className="saha-to__device"
						htmlFor={ inputId }
					>
						<span>{ device.label }</span>
						<input
							id={ inputId }
							type="text"
							className="saha-to__input saha-to__input--short"
							value={ current[ device.key ] ?? '' }
							placeholder={
								index === 0
									? placeholder
									: __( 'kế thừa', 'saha-builder' )
							}
							onChange={ ( event ) =>
								onChange( {
									...current,
									[ device.key ]: event.target.value.trim(),
								} )
							}
							aria-invalid={ invalid || undefined }
						/>
					</label>
				);
			} ) }
		</div>
	);
}
