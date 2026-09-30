/**
 * SAHA Theme Options — ứng dụng.
 *
 * Toàn bộ form dựng từ schema do REST trả về (saha-core ThemeOptions\Schema):
 * thêm field ở PHP là UI tự có, không phải sửa JS. Server sanitize lại mọi
 * giá trị — UI chỉ giúp nhập đúng, không phải lớp bảo vệ.
 */
import apiFetch from '@wordpress/api-fetch';
import { Button, Card, CardBody, Notice, Spinner } from '@wordpress/components';
import { useCallback, useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import Field from './fields/Field';
import GroupTabs from './GroupTabs';
import { diffValues, getValue, setValue } from './utils';

const API_PATH = '/saha/v1/settings';
const config = window.sahaThemeOptions || {};

export default function App() {
	const [ schema, setSchema ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ saved, setSaved ] = useState( {} );
	const [ errors, setErrors ] = useState( {} );
	const [ notice, setNotice ] = useState( null );
	const [ loadError, setLoadError ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ activeGroup, setActiveGroup ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: API_PATH } )
			.then( ( response ) => {
				setSchema( response.data.schema );
				setValues( response.data.values );
				setSaved( response.data.values );
			} )
			.catch( ( error ) =>
				setLoadError(
					error?.message ||
						__( 'Không tải được Theme Options.', 'saha-builder' )
				)
			);
	}, [] );

	const changes = useMemo(
		() => diffValues( values, saved ),
		[ values, saved ]
	);
	const dirty = Object.keys( changes ).length > 0;

	// Cảnh báo khi rời trang còn thay đổi chưa lưu.
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

	const onChange = useCallback( ( group, key, value ) => {
		setValues( ( current ) => setValue( current, group, key, value ) );
		setErrors( ( current ) => {
			const next = { ...current };
			delete next[ `${ group }.${ key }` ];
			return next;
		} );
	}, [] );

	const save = async () => {
		setSaving( true );
		setNotice( null );

		try {
			const response = await apiFetch( {
				path: API_PATH,
				method: 'POST',
				data: { values: changes },
			} );

			setValues( response.data.values );
			setSaved( response.data.values );
			setErrors( {} );
			setNotice( { status: 'success', message: response.message } );
		} catch ( error ) {
			// 422: field hợp lệ đã được lưu, field lỗi giữ giá trị cũ ở server.
			if ( error?.code === 'validation_failed' && error?.data?.values ) {
				const serverValues = error.data.values;

				setSaved( serverValues );
				setErrors( error.errors || {} );
				// Giữ nguyên giá trị người dùng đang nhập ở field lỗi để họ sửa.
				setValues( ( current ) => {
					let next = serverValues;

					Object.keys( error.errors || {} ).forEach( ( path ) => {
						const [ group, key ] = path.split( '.' );
						next = setValue(
							next,
							group,
							key,
							getValue( current, group, key )
						);
					} );

					return next;
				} );
			}

			setNotice( {
				status: 'error',
				message:
					error?.message ||
					__( 'Không lưu được. Vui lòng thử lại.', 'saha-builder' ),
			} );
		} finally {
			setSaving( false );
		}
	};

	if ( loadError ) {
		return (
			<Notice status="error" isDismissible={ false }>
				{ loadError }
			</Notice>
		);
	}

	if ( ! schema ) {
		return <Spinner />;
	}

	const errorCount = Object.keys( errors ).length;
	const group =
		schema.find( ( item ) => item.key === activeGroup ) || schema[ 0 ];

	return (
		<div className="saha-to">
			{ ! config.themeActive && (
				<Notice status="warning" isDismissible={ false }>
					{ __(
						'Theme đang dùng không phải SAHA Theme — thay đổi ở đây chưa có tác dụng trên website.',
						'saha-builder'
					) }
				</Notice>
			) }

			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.message }
				</Notice>
			) }

			<div className="saha-to__toolbar">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ ! dirty || saving }
					aria-disabled={ ! dirty || saving }
				>
					{ saving
						? __( 'Đang lưu…', 'saha-builder' )
						: __( 'Lưu thay đổi', 'saha-builder' ) }
				</Button>
				{ config.homeUrl && (
					<Button
						variant="secondary"
						href={ config.homeUrl }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Xem website', 'saha-builder' ) }
					</Button>
				) }
				<span className="saha-to__status" role="status">
					{ dirty && __( 'Có thay đổi chưa lưu', 'saha-builder' ) }
					{ errorCount > 0 &&
						` · ${ errorCount } ${ __( 'lỗi', 'saha-builder' ) }` }
				</span>
			</div>

			<div className="saha-to__tabs">
				<GroupTabs
					idPrefix="saha-to"
					selected={ group.key }
					onSelect={ setActiveGroup }
					tabs={ schema.map( ( item ) => ( {
						name: item.key,
						title:
							item.label +
							( Object.keys( errors ).some( ( path ) =>
								path.startsWith( `${ item.key }.` )
							)
								? ' ⚠'
								: '' ),
					} ) ) }
				/>
				<Card
					className="saha-to__panel"
					id="saha-to-panel"
					role="tabpanel"
					aria-labelledby={ `saha-to-tab-${ group.key }` }
				>
					<CardBody>
						<h2 className="saha-to__heading">{ group.label }</h2>
						{ group.fields.map( ( field ) => (
							<Field
								key={ field.key }
								field={ field }
								value={ getValue(
									values,
									group.key,
									field.key
								) }
								error={
									errors[ `${ group.key }.${ field.key }` ]
								}
								onChange={ ( value ) =>
									onChange( group.key, field.key, value )
								}
							/>
						) ) }
					</CardBody>
				</Card>
			</div>
		</div>
	);
}
