/**
 * Control mốc 1.4: icon, HTML, term (danh mục/thương hiệu), block dùng chung.
 */
import {
	Button,
	Notice,
	SelectControl,
	Spinner,
	TextareaControl,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api } from '../api';
import { config } from '../context';

const common = { __next40pxDefaultSize: true, __nextHasNoMarginBottom: true };

/**
 * Lưới icon (SVG do server gửi kèm định nghĩa — nguồn tin cậy).
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.value    Tên icon.
 * @param {(...args: unknown[]) => unknown} props.onChange Đổi giá trị.
 * @param {Object}                          props.def      Định nghĩa control.
 */
export function IconField( { value, onChange, def } ) {
	const current = value ?? def.default;

	return (
		<div
			className="saha-b-icons"
			role="radiogroup"
			aria-label={ def.label }
		>
			{ ( def.icons || [] ).map( ( icon ) => (
				<button
					key={ icon.value }
					type="button"
					role="radio"
					aria-checked={ current === icon.value }
					title={ icon.label }
					aria-label={ icon.label }
					className={
						'saha-b-icons__item' +
						( current === icon.value ? ' is-selected' : '' )
					}
					onClick={ () =>
						onChange(
							icon.value === def.default ? null : icon.value
						)
					}

					dangerouslySetInnerHTML={ { __html: icon.svg } }
				/>
			) ) }
		</div>
	);
}

/**
 * Mã HTML. Server lọc theo quyền unfiltered_html của người lưu.
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.value    HTML.
 * @param {(...args: unknown[]) => unknown} props.onChange Đổi giá trị.
 * @param {Object}                          props.def      Định nghĩa control.
 */
export function HtmlField( { value, onChange, def } ) {
	return (
		<>
			{ ! def.unfiltered && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Tài khoản của bạn không được lưu mã script/iframe — các thẻ này sẽ bị loại khi lưu.',
						'saha-builder'
					) }
				</Notice>
			) }
			<TextareaControl
				__nextHasNoMarginBottom
				label={ def.label }
				hideLabelFromVision
				className="saha-b-code"
				rows={ 10 }
				value={ value ?? '' }
				spellCheck={ false }
				onChange={ ( v ) => onChange( v || null ) }
			/>
		</>
	);
}

/**
 * Chọn term (danh sách do server gửi kèm định nghĩa).
 *
 * @param {Object}                          props          Props.
 * @param {string}                          props.value    Slug.
 * @param {(...args: unknown[]) => unknown} props.onChange Đổi giá trị.
 * @param {Object}                          props.def      Định nghĩa control.
 */
export function TermField( { value, onChange, def } ) {
	return (
		<SelectControl
			{ ...common }
			label={ def.label }
			hideLabelFromVision
			value={ value ?? '' }
			options={ [
				{ value: '', label: __( '— Không chọn —', 'saha-builder' ) },
				...( def.options || [] ),
			] }
			onChange={ ( v ) => onChange( v || null ) }
		/>
	);
}

/**
 * Chọn block dùng chung (danh sách luôn mới qua REST) + mở block để sửa.
 *
 * @param {Object}                          props          Props.
 * @param {number}                          props.value    Block ID.
 * @param {(...args: unknown[]) => unknown} props.onChange Đổi giá trị.
 * @param {Object}                          props.def      Định nghĩa control.
 */
export function BlockRefField( { value, onChange, def } ) {
	const [ blocks, setBlocks ] = useState( null );

	useEffect( () => {
		api.blocks()
			.then( setBlocks )
			.catch( () => setBlocks( [] ) );
	}, [] );

	if ( null === blocks ) {
		return <Spinner />;
	}

	const draft = __( '(nháp — chưa hiện ngoài website)', 'saha-builder' );

	return (
		<div className="saha-b-blockref">
			<SelectControl
				{ ...common }
				label={ def.label }
				hideLabelFromVision
				value={ value ? String( value ) : '' }
				options={ [
					{
						value: '',
						label: __( '— Chọn block —', 'saha-builder' ),
					},
					...blocks.map( ( b ) => ( {
						value: String( b.id ),
						label:
							b.title +
							( 'publish' === b.status ? '' : ' ' + draft ),
					} ) ),
				] }
				onChange={ ( v ) => onChange( v ? Number( v ) : null ) }
			/>
			<div className="saha-b-blockref__actions">
				{ value ? (
					<Button
						variant="secondary"
						size="compact"
						href={ config.builderUrl + value }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Sửa block (tab mới)', 'saha-builder' ) }
					</Button>
				) : null }
				<Button
					variant="link"
					href={ config.blocksUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Quản lý blocks', 'saha-builder' ) }
				</Button>
			</div>
		</div>
	);
}
