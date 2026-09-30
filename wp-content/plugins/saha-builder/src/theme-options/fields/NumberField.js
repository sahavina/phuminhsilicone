/**
 * Số nguyên trong khoảng min–max.
 * @param {Object}                  props             Props của field.
 * @param {string}                  props.id
 * @param {Object}                  props.field
 * @param {string|number}           props.value
 * @param {(value: number) => void} props.onChange
 * @param {string}                  props.describedBy
 * @param {boolean}                 props.invalid
 */
export default function NumberField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	return (
		<input
			id={ id }
			type="number"
			className="saha-to__input saha-to__input--short"
			min={ field.min }
			max={ field.max }
			step={ 1 }
			value={ value ?? '' }
			onChange={ ( event ) =>
				onChange(
					event.target.value === ''
						? ''
						: Number( event.target.value )
				)
			}
			aria-describedby={ describedBy }
			aria-invalid={ invalid || undefined }
		/>
	);
}
