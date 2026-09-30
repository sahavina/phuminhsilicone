/**
 * Typography: font, cỡ chữ, độ đậm, giãn dòng, giãn chữ (spec SCC §29).
 */
import { __ } from '@wordpress/i18n';

const WEIGHTS = [
	'100',
	'200',
	'300',
	'400',
	'500',
	'600',
	'700',
	'800',
	'900',
];

export default function TypographyField( {
	id,
	field,
	value,
	onChange,
	describedBy,
	invalid,
} ) {
	const current = value || {};
	const set = ( key ) => ( event ) =>
		onChange( { ...current, [ key ]: event.target.value.trim() } );

	return (
		<div
			className="saha-to__typography"
			role="group"
			aria-describedby={ describedBy }
		>
			<label className="saha-to__sub" htmlFor={ id }>
				<span>{ __( 'Font', 'saha-builder' ) }</span>
				<select
					id={ id }
					className="saha-to__input"
					value={ current.fontFamily || 'system' }
					onChange={ set( 'fontFamily' ) }
					aria-invalid={ invalid || undefined }
				>
					{ ( field.fonts || [] ).map( ( font ) => (
						<option key={ font.value } value={ font.value }>
							{ font.label }
						</option>
					) ) }
				</select>
			</label>
			<label className="saha-to__sub" htmlFor={ `${ id }-size` }>
				<span>{ __( 'Cỡ chữ', 'saha-builder' ) }</span>
				<input
					id={ `${ id }-size` }
					type="text"
					className="saha-to__input saha-to__input--short"
					value={ current.fontSize || '' }
					placeholder="16px"
					onChange={ set( 'fontSize' ) }
				/>
			</label>
			<label className="saha-to__sub" htmlFor={ `${ id }-weight` }>
				<span>{ __( 'Độ đậm', 'saha-builder' ) }</span>
				<select
					id={ `${ id }-weight` }
					className="saha-to__input saha-to__input--short"
					value={ current.fontWeight || '400' }
					onChange={ set( 'fontWeight' ) }
				>
					{ WEIGHTS.map( ( weight ) => (
						<option key={ weight } value={ weight }>
							{ weight }
						</option>
					) ) }
				</select>
			</label>
			<label className="saha-to__sub" htmlFor={ `${ id }-line-height` }>
				<span>{ __( 'Giãn dòng', 'saha-builder' ) }</span>
				<input
					id={ `${ id }-line-height` }
					type="text"
					className="saha-to__input saha-to__input--short"
					value={ current.lineHeight || '' }
					placeholder="1.5"
					onChange={ set( 'lineHeight' ) }
				/>
			</label>
			<label
				className="saha-to__sub"
				htmlFor={ `${ id }-letter-spacing` }
			>
				<span>{ __( 'Giãn chữ', 'saha-builder' ) }</span>
				<input
					id={ `${ id }-letter-spacing` }
					type="text"
					className="saha-to__input saha-to__input--short"
					value={ current.letterSpacing || '' }
					placeholder="0"
					onChange={ set( 'letterSpacing' ) }
				/>
			</label>
		</div>
	);
}
