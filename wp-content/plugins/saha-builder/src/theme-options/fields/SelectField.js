/**
 * Chọn một giá trị trong danh sách.
 * @param {Object}                  props             Props của field.
 * @param {string}                  props.id
 * @param {Object}                  props.field
 * @param {string|number}           props.value
 * @param {(value: string) => void} props.onChange
 * @param {string}                  props.describedBy
 * @param {boolean}                 props.invalid
 */
export default function SelectField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	return (
		<select
			id={ id }
			className="saha-to__input saha-to__input--short"
			value={ value ?? '' }
			onChange={ ( event ) => onChange( event.target.value ) }
			aria-describedby={ describedBy }
			aria-invalid={ invalid || undefined }
		>
			{ ( field.options || [] ).map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	);
}
