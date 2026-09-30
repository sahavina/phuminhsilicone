/**
 * Bật / tắt.
 */
import { ToggleControl } from '@wordpress/components';

export default function ToggleField( { field, value, onChange } ) {
	return (
		<ToggleControl
			__nextHasNoMarginBottom
			label={ field.label }
			help={ field.help || undefined }
			checked={ Boolean( value ) }
			onChange={ onChange }
		/>
	);
}
