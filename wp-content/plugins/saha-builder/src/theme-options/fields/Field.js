/**
 * Chọn control theo `field.type` và bọc nhãn / trợ giúp / lỗi.
 */
import { BaseControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';

import ColorField from './ColorField';
import CssField from './CssField';
import MediaField from './MediaField';
import NumberField from './NumberField';
import SelectField from './SelectField';
import SizeField from './SizeField';
import TextField from './TextField';
import ToggleField from './ToggleField';
import TypographyField from './TypographyField';

const CONTROLS = {
	color: ColorField,
	css: CssField,
	media: MediaField,
	number: NumberField,
	select: SelectField,
	size: SizeField,
	text: TextField,
	textarea: TextField,
	toggle: ToggleField,
	typography: TypographyField,
};

export default function Field( { field, value, error, onChange } ) {
	const id = useInstanceId( Field, 'saha-to-field' );
	const Control = CONTROLS[ field.type ] || TextField;
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const describedBy =
		[ field.help && helpId, error && errorId ]
			.filter( Boolean )
			.join( ' ' ) || undefined;

	// Toggle tự có nhãn của nó; các control khác dùng BaseControl làm nhãn chung.
	if ( field.type === 'toggle' ) {
		return (
			<div className="saha-to__field">
				<ToggleField
					field={ field }
					value={ value }
					onChange={ onChange }
				/>
				{ error && (
					<p id={ errorId } className="saha-to__error" role="alert">
						{ error }
					</p>
				) }
			</div>
		);
	}

	return (
		<BaseControl
			__nextHasNoMarginBottom
			id={ id }
			label={ field.label }
			help={
				field.help ? <span id={ helpId }>{ field.help }</span> : null
			}
			className={ `saha-to__field${ error ? ' has-error' : '' }` }
		>
			<Control
				id={ id }
				field={ field }
				value={ value }
				onChange={ onChange }
				describedBy={ describedBy }
				invalid={ Boolean( error ) }
			/>
			{ error && (
				<p id={ errorId } className="saha-to__error" role="alert">
					{ error }
				</p>
			) }
		</BaseControl>
	);
}
