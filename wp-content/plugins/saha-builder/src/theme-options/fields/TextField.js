/**
 * Text / textarea.
 * @param {Object}                  props             Props của field.
 * @param {string}                  props.id
 * @param {Object}                  props.field
 * @param {string|number}           props.value
 * @param {(value: string) => void} props.onChange
 * @param {string}                  props.describedBy
 * @param {boolean}                 props.invalid
 */
export default function TextField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	const common = {
		id,
		className: 'saha-to__input',
		value: value ?? '',
		onChange: ( event ) => onChange( event.target.value ),
		'aria-describedby': describedBy,
		'aria-invalid': invalid || undefined,
	};

	if ( field.type === 'textarea' ) {
		return <textarea rows={ 4 } { ...common } />;
	}

	return <input type="text" { ...common } />;
}
