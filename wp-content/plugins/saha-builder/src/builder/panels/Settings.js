/**
 * Bảng thiết lập của element đang chọn — tab Nội dung / Kiểu / Nâng cao.
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { useActions } from '../actions';
import { useBuilder } from '../context';
import Field from '../controls/Field';
import { findNode } from '../store/tree';

const TABS = [
	[ 'content', __( 'Nội dung', 'saha-builder' ) ],
	[ 'style', __( 'Kiểu', 'saha-builder' ) ],
	[ 'advanced', __( 'Nâng cao', 'saha-builder' ) ],
];

export default function Settings() {
	const { state, dispatch, defs, advanced } = useBuilder();
	const { copy, paste, hasClipboard } = useActions();
	const [ tab, setTab ] = useState( 'content' );
	const node = state.selectedId
		? findNode( state.doc, state.selectedId )
		: null;

	if ( ! node ) {
		return (
			<div className="saha-b-settings saha-b-settings--empty">
				<p>
					{ __(
						'Chọn một element trên trang hoặc trong Cấu trúc để chỉnh.',
						'saha-builder'
					) }
				</p>
			</div>
		);
	}

	const def = defs[ node.type ];

	if ( ! def ) {
		return (
			<div className="saha-b-settings">
				<p>
					{ __(
						'Element này thuộc tiện ích mở rộng chưa được bật — dữ liệu vẫn được giữ nguyên.',
						'saha-builder'
					) }
				</p>
			</div>
		);
	}

	const controls =
		'advanced' === tab
			? advanced
			: def.controls.filter(
					( c ) => ( c.section || 'content' ) === tab
				);
	const scope = 'advanced' === tab ? 'advanced' : 'props';
	const bag = ( 'advanced' === tab ? node.advanced : node.props ) || {};
	const tabErrors = ( key ) =>
		( 'advanced' === key
			? advanced
			: def.controls.filter( ( c ) => ( c.section || 'content' ) === key )
		).some( ( c ) => state.errors[ `${ node.id }.${ c.key }` ] );

	return (
		<div className="saha-b-settings">
			<header className="saha-b-settings__head">
				<h2>{ def.name }</h2>
				<div className="saha-b-settings__actions">
					<Button
						icon="arrow-up-alt2"
						label={ __( 'Lên trên', 'saha-builder' ) }
						size="compact"
						disabled={ state.readOnly }
						onClick={ () =>
							dispatch( {
								type: 'MOVE_SIBLING',
								id: node.id,
								delta: -1,
							} )
						}
					/>
					<Button
						icon="arrow-down-alt2"
						label={ __( 'Xuống dưới', 'saha-builder' ) }
						size="compact"
						disabled={ state.readOnly }
						onClick={ () =>
							dispatch( {
								type: 'MOVE_SIBLING',
								id: node.id,
								delta: 1,
							} )
						}
					/>
					<Button
						icon="admin-page"
						label={ __( 'Nhân bản (Ctrl+D)', 'saha-builder' ) }
						size="compact"
						disabled={ state.readOnly }
						onClick={ () =>
							dispatch( { type: 'DUPLICATE', id: node.id } )
						}
					/>
					<Button
						icon="clipboard"
						label={ __( 'Sao chép (Ctrl+C)', 'saha-builder' ) }
						size="compact"
						onClick={ () => copy( node.id ) }
					/>
					<Button
						icon="editor-paste-text"
						label={ __( 'Dán (Ctrl+V)', 'saha-builder' ) }
						size="compact"
						disabled={ state.readOnly || ! hasClipboard() }
						onClick={ paste }
					/>
					<Button
						icon="trash"
						label={ __( 'Xoá (Delete)', 'saha-builder' ) }
						size="compact"
						isDestructive
						disabled={ state.readOnly }
						onClick={ () =>
							dispatch( { type: 'REMOVE', id: node.id } )
						}
					/>
				</div>
			</header>

			<div className="saha-b-tabs" role="tablist">
				{ TABS.map( ( [ key, label ] ) => (
					<button
						key={ key }
						type="button"
						role="tab"
						aria-selected={ tab === key }
						className={
							'saha-b-tabs__tab' +
							( tab === key ? ' is-active' : '' )
						}
						onClick={ () => setTab( key ) }
					>
						{ label }
						{ tabErrors( key ) && (
							<span
								className="saha-b-tabs__error"
								aria-label={ __( 'có lỗi', 'saha-builder' ) }
							>
								{ ' ' }
								⚠
							</span>
						) }
					</button>
				) ) }
			</div>

			<fieldset
				className="saha-b-settings__body"
				role="tabpanel"
				disabled={ state.readOnly }
			>
				{ controls.length ? (
					controls.map( ( control ) => (
						<Field
							// Đổi element → dựng lại control (editor rich text khởi tạo lại).
							key={ `${ node.id }-${ scope }-${ control.key }` }
							nodeId={ node.id }
							def={ control }
							value={ bag[ control.key ] }
							error={
								state.errors[ `${ node.id }.${ control.key }` ]
							}
							onChange={ ( value ) =>
								dispatch( {
									type: 'UPDATE',
									id: node.id,
									scope,
									key: control.key,
									value,
								} )
							}
						/>
					) )
				) : (
					<p className="saha-b-hint">
						{ __(
							'Element này không có thiết lập ở tab này.',
							'saha-builder'
						) }
					</p>
				) }
			</fieldset>
		</div>
	);
}
