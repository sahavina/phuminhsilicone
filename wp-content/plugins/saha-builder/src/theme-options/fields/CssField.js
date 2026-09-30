/**
 * CSS tuỳ chỉnh. Khoá nếu người dùng thiếu quyền `edit_css` (server cũng bỏ qua).
 */
import { __ } from '@wordpress/i18n';

export default function CssField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	if ( field.editable === false ) {
		return (
			<p className="saha-to__muted">
				{ __(
					'Bạn không có quyền sửa CSS (cần quyền edit_css).',
					'saha-builder'
				) }
			</p>
		);
	}

	return (
		<textarea
			id={ id }
			className="saha-to__input saha-to__code"
			rows={ 14 }
			spellCheck={ false }
			value={ value ?? '' }
			onChange={ ( event ) => onChange( event.target.value ) }
			aria-describedby={ describedBy }
			aria-invalid={ invalid || undefined }
		/>
	);
}
