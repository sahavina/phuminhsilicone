/**
 * Một control của bảng thiết lập, dựng từ định nghĩa server.
 *
 * Control `responsive` sửa giá trị cho thiết bị đang chọn trên thanh công cụ;
 * ô trống hiện giá trị kế thừa từ thiết bị lớn hơn.
 */
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useBuilder } from '../context';
import BackgroundField from './BackgroundField';
import {
	AlignField,
	NumberField,
	SelectField,
	SizeField,
	TextField,
	TextareaField,
	ToggleField,
} from './basic';
import ColorField from './ColorField';
import LinkField from './LinkField';
import MediaField from './MediaField';
import RichTextField from './RichTextField';
import SpacingField from './SpacingField';
import TypographyField from './TypographyField';
import { inheritedAt, setAt, valueAt } from './responsive';

const CONTROLS = {
	text: TextField,
	textarea: TextareaField,
	richtext: RichTextField,
	number: NumberField,
	select: SelectField,
	toggle: ToggleField,
	color: ColorField,
	size: SizeField,
	spacing: SpacingField,
	typography: TypographyField,
	align: AlignField,
	media: MediaField,
	link: LinkField,
	background: BackgroundField,
	htmlId: TextField,
	classList: TextField,
};

const DEVICE_ICON = {
	desktop: 'desktop',
	tablet: 'tablet',
	mobile: 'smartphone',
};

export default function Field( { nodeId, def, value, onChange, error } ) {
	const { device } = useBuilder();
	const Control = CONTROLS[ def.type ];

	if ( ! Control ) {
		return null;
	}

	const responsive = !! def.responsive;
	const shown = responsive ? valueAt( value, device ) : value;
	const placeholder = responsive ? inheritedAt( value, device ) : undefined;
	const id = `${ nodeId }-${ def.key }`;
	const selfLabelled = [ 'toggle' ].includes( def.type );

	return (
		<div
			className={
				'saha-b-field saha-b-field--' +
				def.type +
				( error ? ' has-error' : '' )
			}
		>
			{ ! selfLabelled && (
				<div className="saha-b-field__label">
					<span>{ def.label }</span>
					{ responsive && (
						<span
							className="saha-b-field__device"
							title={ __(
								'Giá trị riêng cho thiết bị đang xem',
								'saha-builder'
							) }
						>
							<Icon icon={ DEVICE_ICON[ device ] } size={ 14 } />
						</span>
					) }
				</div>
			) }
			<Control
				id={ id }
				def={ def }
				value={ shown }
				placeholder={ placeholder }
				onChange={ ( next ) =>
					onChange( responsive ? setAt( value, device, next ) : next )
				}
			/>
			{ def.help && <p className="saha-b-field__help">{ def.help }</p> }
			{ error && (
				<p className="saha-b-field__error" role="alert">
					{ error }
				</p>
			) }
		</div>
	);
}
