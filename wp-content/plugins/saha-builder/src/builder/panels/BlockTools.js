/**
 * Công cụ Block dùng chung trong bảng thiết lập:
 * - Section (element cấp gốc) → "Lưu thành block": tạo block và thay bằng tham chiếu.
 * - Element Block ở cấp gốc → "Tách khỏi block": chép nội dung block vào trang để sửa riêng.
 */
import { Button, TextControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { api, errorMessage } from '../api';
import { config, useBuilder } from '../context';
import {
	cloneWithNewIds,
	collectIds,
	locate,
	newId,
	replaceNode,
} from '../store/tree';

/**
 * @param {Object} props      Props.
 * @param {Object} props.node Node đang chọn.
 */
export default function BlockTools( { node } ) {
	const { state, dispatch, defs } = useBuilder();
	const [ title, setTitle ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ message, setMessage ] = useState( '' );

	const where = locate( state.doc, node.id );
	const atRoot = where && ! where.parent;
	const canSave =
		config.canCreateBlock &&
		atRoot &&
		'block' !== node.type &&
		( defs[ node.type ]?.allowedParents || [] ).includes( 'root' );
	const canDetach = atRoot && 'block' === node.type && node.props?.blockId;

	if ( state.readOnly || ( ! canSave && ! canDetach ) ) {
		return null;
	}

	const saveAsBlock = () => {
		setBusy( true );
		setMessage( '' );

		api.createBlock( title.trim(), node )
			.then( ( block ) => {
				const ref = {
					id: newId( collectIds( state.doc ) ),
					type: 'block',
					props: { blockId: block.id },
				};
				dispatch( {
					type: 'APPLY',
					doc: replaceNode( state.doc, node.id, [ ref ] ),
					selectId: ref.id,
				} );
				setTitle( '' );
			} )
			.catch( ( error ) =>
				setMessage(
					errorMessage(
						error,
						__( 'Không tạo được block.', 'saha-builder' )
					)
				)
			)
			.finally( () => setBusy( false ) );
	};

	const detach = () => {
		setBusy( true );
		setMessage( '' );

		api.load( node.props.blockId )
			.then( ( data ) => {
				const taken = collectIds( state.doc );
				const copies = ( data.document?.elements || [] ).map( ( n ) =>
					cloneWithNewIds( n, taken )
				);

				if ( ! copies.length ) {
					setMessage(
						__( 'Block chưa có nội dung.', 'saha-builder' )
					);
					return;
				}

				dispatch( {
					type: 'APPLY',
					doc: replaceNode( state.doc, node.id, copies ),
					selectId: copies[ 0 ].id,
				} );
			} )
			.catch( ( error ) =>
				setMessage(
					errorMessage(
						error,
						__( 'Không đọc được block.', 'saha-builder' )
					)
				)
			)
			.finally( () => setBusy( false ) );
	};

	return (
		<div className="saha-b-blocktools">
			{ canSave && (
				<>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __(
							'Lưu thành block dùng chung',
							'saha-builder'
						) }
						help={ __(
							'Block dùng lại được ở nhiều trang; sửa block một chỗ, mọi trang đổi theo.',
							'saha-builder'
						) }
						placeholder={ __(
							'Tên block, ví dụ: USP 4 cam kết',
							'saha-builder'
						) }
						value={ title }
						onChange={ setTitle }
					/>
					<Button
						variant="secondary"
						size="compact"
						isBusy={ busy }
						disabled={ busy || ! title.trim() }
						onClick={ saveAsBlock }
					>
						{ __( 'Lưu thành block', 'saha-builder' ) }
					</Button>
				</>
			) }
			{ canDetach && (
				<Button
					variant="secondary"
					size="compact"
					isBusy={ busy }
					disabled={ busy }
					onClick={ detach }
					title={ __(
						'Chép nội dung block vào trang này để sửa riêng (block gốc không đổi).',
						'saha-builder'
					) }
				>
					{ __( 'Tách khỏi block', 'saha-builder' ) }
				</Button>
			) }
			{ message && (
				<p className="saha-b-field__error" role="alert">
					{ message }
				</p>
			) }
		</div>
	);
}
