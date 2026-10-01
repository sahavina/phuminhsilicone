/**
 * SAHA Builder — ứng dụng.
 *
 * Định nghĩa element lấy từ server (GET /builder/elements); lưu qua POST
 * /builder/save (server sanitize lại toàn bộ). Canvas render bằng renderer PHP.
 */
import { Notice, Spinner } from '@wordpress/components';
import {
	useCallback,
	useEffect,
	useMemo,
	useReducer,
	useRef,
	useState,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

import { useActions } from './actions';
import { api, errorMessage } from './api';
import Canvas from './canvas/Canvas';
import { BuilderContext, config, useBuilder } from './context';
import Inserter from './panels/Inserter';
import Navigator from './panels/Navigator';
import Settings from './panels/Settings';
import { initialState, isDirty, reducer } from './store/reducer';
import { createNode, locate, setRootType } from './store/tree';
import Toolbar from './Toolbar';

/**
 * Tài liệu khởi đầu khi trang có nội dung cũ (chưa dùng builder): Section > Văn bản.
 *
 * @param {Object} defs Định nghĩa.
 * @param {string} html Nội dung cũ.
 * @return {Object} Tài liệu.
 */
function legacyDocument( defs, html ) {
	const taken = new Set();
	const section = createNode( defs, 'section', taken );
	const text = createNode( defs, 'text', taken );

	text.props = { content: html };
	section.children = [ text ];

	return { version: 1, elements: [ section ] };
}

/**
 * Đang gõ trong ô nhập → không bắt phím tắt.
 *
 * @param {EventTarget} target Target.
 * @return {boolean} Kết quả.
 */
function isTyping( target ) {
	return !! target?.closest?.(
		'input, textarea, select, [contenteditable="true"], .mce-container'
	);
}

export default function App() {
	const [ state, dispatch ] = useReducer( reducer, undefined, initialState );
	const [ elements, setElements ] = useState( null );
	const [ advanced, setAdvanced ] = useState( [] );
	const [ meta, setMeta ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ device, setDevice ] = useState( 'desktop' );
	const [ leftTab, setLeftTab ] = useState( 'insert' );
	const [ saving, setSaving ] = useState( false );
	const [ notices, setNotices ] = useState( [] );

	const defs = useMemo(
		() =>
			Object.fromEntries(
				( elements || [] ).map( ( e ) => [ e.type, e ] )
			),
		[ elements ]
	);
	const dirty = isDirty( state );

	const notify = useCallback( ( notice ) => {
		const id = notice.id || String( Date.now() + Math.random() );
		setNotices( ( list ) => [
			...list.filter( ( n ) => n.id !== id ),
			{ ...notice, id },
		] );

		if ( 'success' === notice.status ) {
			window.setTimeout(
				() =>
					setNotices( ( list ) =>
						list.filter( ( n ) => n.id !== id )
					),
				3000
			);
		}
	}, [] );

	/*
	 * Tải định nghĩa + layout.
	 */
	useEffect( () => {
		Promise.all( [ api.elements(), api.load( config.postId ) ] )
			.then( ( [ definitions, data ] ) => {
				setRootType( config.rootType );

				const map = Object.fromEntries(
					definitions.elements.map( ( e ) => [ e.type, e ] )
				);
				let doc =
					data.document && Array.isArray( data.document.elements )
						? data.document
						: { version: 1, elements: [] };
				const savedDoc = doc;

				if ( ! doc.elements.length && config.legacyContent ) {
					doc = legacyDocument( map, config.legacyContent );
					notify( {
						id: 'legacy',
						status: 'info',
						message: __(
							'Nội dung hiện có của trang đã được đưa vào một khối Văn bản. Bấm Lưu để bắt đầu dùng SAHA Builder cho trang này.',
							'saha-builder'
						),
					} );
				}

				setElements( definitions.elements );
				setAdvanced( definitions.advanced );
				setMeta( data );
				dispatch( {
					type: 'LOAD',
					doc,
					savedDoc,
					hash: data.hash,
					readOnly: !! data.lockedBy,
				} );

				if ( data.lockedBy ) {
					notify( {
						id: 'lock',
						status: 'warning',
						message: sprintf(
							/* translators: %s: tên người dùng */ __(
								'%s đang chỉnh sửa trang này. Bạn chỉ xem được, không lưu được.',
								'saha-builder'
							),
							data.lockedBy
						),
					} );
				}
			} )
			.catch( ( error ) =>
				setLoadError(
					errorMessage(
						error,
						__( 'Không tải được builder.', 'saha-builder' )
					)
				)
			);
	}, [ notify ] );

	/*
	 * Giữ khoá chỉnh sửa (hết hạn sau 150 giây nếu không gia hạn).
	 */
	useEffect( () => {
		if ( ! meta || state.readOnly ) {
			return undefined;
		}

		const hold = () =>
			api.lock( config.postId ).catch( ( error ) => {
				if ( 'locked' === error?.code ) {
					dispatch( { type: 'SET_READ_ONLY', readOnly: true } );
					notify( {
						id: 'lock',
						status: 'warning',
						message: errorMessage( error, '' ),
					} );
				}
			} );

		hold();
		const timer = window.setInterval(
			hold,
			( config.lockInterval || 60 ) * 1000
		);

		return () => window.clearInterval( timer );
	}, [ meta, state.readOnly, notify ] );

	/*
	 * Cảnh báo khi rời trang còn thay đổi chưa lưu.
	 */
	useEffect( () => {
		if ( ! dirty ) {
			return undefined;
		}

		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};

		window.addEventListener( 'beforeunload', warn );

		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ dirty ] );

	/*
	 * Lưu.
	 */
	const stateRef = useRef( state );
	stateRef.current = state;

	const save = useCallback(
		( { baseHash, enabled = true } = {} ) => {
			const current = stateRef.current;

			if ( saving || current.readOnly ) {
				return Promise.resolve( false );
			}

			setSaving( true );

			return api
				.save(
					config.postId,
					current.doc,
					baseHash ?? current.hash,
					enabled
				)
				.then( ( data ) => {
					dispatch( {
						type: 'SAVED',
						doc: data.document,
						hash: data.hash,
					} );
					setNotices( ( list ) =>
						list.filter(
							( n ) =>
								! [ 'save-error', 'legacy' ].includes( n.id )
						)
					);
					notify( {
						id: 'saved',
						status: 'success',
						message: __( 'Đã lưu.', 'saha-builder' ),
					} );
					return true;
				} )
				.catch( ( error ) => {
					if ( 'conflict' === error?.code ) {
						notify( {
							id: 'save-error',
							status: 'error',
							message: errorMessage( error, '' ),
							actions: [
								{
									label: __(
										'Tải lại bản mới (bỏ thay đổi của tôi)',
										'saha-builder'
									),
									onClick: () => window.location.reload(),
								},
								{
									label: __(
										'Ghi đè bằng bản của tôi',
										'saha-builder'
									),
									onClick: () =>
										save( {
											baseHash: error.data?.hash || '',
										} ),
								},
							],
						} );
					} else if ( 'locked' === error?.code ) {
						dispatch( { type: 'SET_READ_ONLY', readOnly: true } );
						notify( {
							id: 'lock',
							status: 'warning',
							message: errorMessage( error, '' ),
						} );
					} else if ( 'validation_failed' === error?.code ) {
						const errors = error.errors || {};
						dispatch( { type: 'SET_ERRORS', errors } );

						const first = Object.keys( errors )
							.map( ( key ) => key.split( '.' )[ 0 ] )
							.find( ( id ) => locate( current.doc, id ) );

						if ( first ) {
							dispatch( { type: 'SELECT', id: first } );
						}

						notify( {
							id: 'save-error',
							status: 'error',
							message: __(
								'Layout có giá trị không hợp lệ (đánh dấu ⚠). Sửa rồi lưu lại.',
								'saha-builder'
							),
						} );
					} else {
						notify( {
							id: 'save-error',
							status: 'error',
							message: errorMessage(
								error,
								__( 'Không lưu được. Thử lại.', 'saha-builder' )
							),
						} );
					}

					return false;
				} )
				.finally( () => setSaving( false ) );
		},
		[ saving, notify ]
	);

	const disableBuilder = useCallback( () => {
		if (
			// eslint-disable-next-line no-alert -- xác nhận đổi cách hiển thị trang (thao tác hiếm, cần chặn tay).
			! window.confirm(
				__(
					'Tắt SAHA Builder cho trang này? Trang sẽ hiển thị nội dung trong trình soạn thảo WordPress (bản HTML tĩnh của layout hiện tại). Layout vẫn được giữ để bật lại sau.',
					'saha-builder'
				)
			)
		) {
			return;
		}

		save( { enabled: false } ).then( ( ok ) => {
			if ( ok ) {
				window.location.href = config.editUrl || config.exitUrl;
			}
		} );
	}, [ save ] );

	if ( loadError ) {
		return (
			<div className="saha-b-fatal">
				<Notice status="error" isDismissible={ false }>
					{ loadError }
				</Notice>
				<a href={ config.exitUrl }>
					{ __( 'Quay lại', 'saha-builder' ) }
				</a>
			</div>
		);
	}

	if ( ! elements || ! meta ) {
		return (
			<div className="saha-b-fatal">
				<Spinner />
			</div>
		);
	}

	return (
		<BuilderContext.Provider
			value={ {
				state,
				dispatch,
				defs,
				elements,
				advanced,
				device,
				setDevice,
			} }
		>
			<Layout
				meta={ meta }
				dirty={ dirty }
				saving={ saving }
				save={ save }
				disableBuilder={ disableBuilder }
				notices={ notices }
				setNotices={ setNotices }
				leftTab={ leftTab }
				setLeftTab={ setLeftTab }
			/>
		</BuilderContext.Provider>
	);
}

/**
 * Bố cục + phím tắt (cần context nên tách khỏi App).
 *
 * @param {Object}                          props                Props.
 * @param {Object}                          props.meta           Dữ liệu trang (title, permalink…).
 * @param {boolean}                         props.dirty          Có thay đổi chưa lưu.
 * @param {boolean}                         props.saving         Đang lưu.
 * @param {(...args: unknown[]) => unknown} props.save           Lưu.
 * @param {(...args: unknown[]) => unknown} props.disableBuilder Tắt builder cho trang.
 * @param {Object[]}                        props.notices        Thông báo.
 * @param {(...args: unknown[]) => unknown} props.setNotices     Đặt thông báo.
 * @param {string}                          props.leftTab        Tab bên trái.
 * @param {(...args: unknown[]) => unknown} props.setLeftTab     Đổi tab bên trái.
 */
function Layout( {
	meta,
	dirty,
	saving,
	save,
	disableBuilder,
	notices,
	setNotices,
	leftTab,
	setLeftTab,
} ) {
	const { state, dispatch } = useBuilder();
	const actions = useActions();
	const latest = useRef( {} );
	latest.current = { state, dispatch, actions, save };

	const onShortcut = useCallback( ( event ) => {
		const {
			state: s,
			dispatch: d,
			actions: a,
			save: doSave,
		} = latest.current;
		const mod = event.ctrlKey || event.metaKey;
		const key = event.key.toLowerCase();

		if ( mod && 's' === key ) {
			event.preventDefault();
			doSave();
			return;
		}

		if ( isTyping( event.target ) ) {
			return;
		}

		if ( mod && 'z' === key ) {
			event.preventDefault();
			d( { type: event.shiftKey ? 'REDO' : 'UNDO' } );
		} else if ( mod && 'y' === key ) {
			event.preventDefault();
			d( { type: 'REDO' } );
		} else if ( s.selectedId && mod && 'c' === key ) {
			a.copy( s.selectedId );
		} else if ( mod && 'v' === key ) {
			event.preventDefault();
			a.paste();
		} else if ( s.selectedId && mod && 'd' === key ) {
			event.preventDefault();
			d( { type: 'DUPLICATE', id: s.selectedId } );
		} else if (
			s.selectedId &&
			( 'delete' === key || 'backspace' === key )
		) {
			event.preventDefault();
			d( { type: 'REMOVE', id: s.selectedId } );
		} else if ( 'escape' === key ) {
			d( { type: 'SELECT', id: null } );
		}
	}, [] );

	useEffect( () => {
		document.addEventListener( 'keydown', onShortcut );
		return () => document.removeEventListener( 'keydown', onShortcut );
	}, [ onShortcut ] );

	return (
		<div className="saha-b-app">
			<Toolbar
				title={ meta.title }
				permalink={ meta.permalink }
				dirty={ dirty }
				saving={ saving }
				onSave={ () => save() }
				onDisable={ disableBuilder }
			/>

			{ notices.length > 0 && (
				<div className="saha-b-notices">
					{ notices.map( ( notice ) => (
						<Notice
							key={ notice.id }
							status={ notice.status }
							actions={ notice.actions }
							onRemove={ () =>
								setNotices( ( list ) =>
									list.filter( ( n ) => n.id !== notice.id )
								)
							}
						>
							{ notice.message }
						</Notice>
					) ) }
				</div>
			) }

			<div className="saha-b-body">
				<aside
					className="saha-b-sidebar saha-b-sidebar--left"
					aria-label={ __(
						'Thêm element và cấu trúc',
						'saha-builder'
					) }
				>
					<div className="saha-b-tabs" role="tablist">
						<button
							type="button"
							role="tab"
							aria-selected={ 'insert' === leftTab }
							className={
								'saha-b-tabs__tab' +
								( 'insert' === leftTab ? ' is-active' : '' )
							}
							onClick={ () => setLeftTab( 'insert' ) }
						>
							{ __( 'Thêm', 'saha-builder' ) }
						</button>
						<button
							type="button"
							role="tab"
							aria-selected={ 'tree' === leftTab }
							className={
								'saha-b-tabs__tab' +
								( 'tree' === leftTab ? ' is-active' : '' )
							}
							onClick={ () => setLeftTab( 'tree' ) }
						>
							{ __( 'Cấu trúc', 'saha-builder' ) }
						</button>
					</div>
					<div className="saha-b-sidebar__scroll">
						{ 'insert' === leftTab ? (
							<Inserter />
						) : (
							<Navigator onAdd={ () => setLeftTab( 'insert' ) } />
						) }
					</div>
				</aside>

				<main className="saha-b-main">
					<Canvas onShortcut={ onShortcut } />
				</main>

				<aside
					className="saha-b-sidebar saha-b-sidebar--right"
					aria-label={ __( 'Thiết lập element', 'saha-builder' ) }
				>
					<div className="saha-b-sidebar__scroll">
						<Settings />
					</div>
				</aside>
			</div>
		</div>
	);
}
