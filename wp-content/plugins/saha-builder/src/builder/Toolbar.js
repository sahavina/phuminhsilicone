/**
 * Thanh công cụ trên cùng.
 */
import {
	Button,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { DEVICES, config, useBuilder } from './context';

const DEVICE_LABELS = {
	desktop: __( 'Desktop', 'saha-builder' ),
	tablet: __( 'Tablet', 'saha-builder' ),
	mobile: __( 'Mobile', 'saha-builder' ),
};

export default function Toolbar( {
	title,
	permalink,
	dirty,
	saving,
	onSave,
	onDisable,
} ) {
	const { state, dispatch, device, setDevice } = useBuilder();

	return (
		<header className="saha-b-toolbar">
			<div className="saha-b-toolbar__start">
				<Button
					icon="arrow-left-alt2"
					label={ __( 'Thoát builder', 'saha-builder' ) }
					href={ config.exitUrl }
				/>
				<div className="saha-b-toolbar__title">
					<strong>SAHA Builder</strong>
					<span>{ title }</span>
				</div>
			</div>

			<div
				className="saha-b-toolbar__center"
				role="group"
				aria-label={ __( 'Xem theo thiết bị', 'saha-builder' ) }
			>
				{ DEVICES.map( ( d ) => (
					<Button
						key={ d.key }
						icon={ d.icon }
						label={ sprintf(
							/* translators: %s: thiết bị */ __(
								'Xem và chỉnh cho %s',
								'saha-builder'
							),
							DEVICE_LABELS[ d.key ]
						) }
						isPressed={ device === d.key }
						onClick={ () => setDevice( d.key ) }
					/>
				) ) }
			</div>

			<div className="saha-b-toolbar__end">
				<Button
					icon="undo"
					label={ __( 'Hoàn tác (Ctrl+Z)', 'saha-builder' ) }
					disabled={ ! state.past.length || state.readOnly }
					onClick={ () => dispatch( { type: 'UNDO' } ) }
				/>
				<Button
					icon="redo"
					label={ __( 'Làm lại (Ctrl+Shift+Z)', 'saha-builder' ) }
					disabled={ ! state.future.length || state.readOnly }
					onClick={ () => dispatch( { type: 'REDO' } ) }
				/>
				{ permalink && (
					<Button
						variant="tertiary"
						href={ permalink }
						target="_blank"
						rel="noopener noreferrer"
					>
						{ dirty
							? __( 'Xem trang (bản đã lưu)', 'saha-builder' )
							: __( 'Xem trang', 'saha-builder' ) }
					</Button>
				) }
				<Button
					variant="primary"
					isBusy={ saving }
					disabled={ ! dirty || saving || state.readOnly }
					aria-disabled={ ! dirty || saving || state.readOnly }
					onClick={ onSave }
				>
					{ saving
						? __( 'Đang lưu…', 'saha-builder' )
						: __( 'Lưu', 'saha-builder' ) }
				</Button>
				<DropdownMenu
					icon="ellipsis"
					label={ __( 'Tuỳ chọn khác', 'saha-builder' ) }
				>
					{ ( { onClose } ) => (
						<MenuGroup>
							{ config.editUrl && (
								<MenuItem href={ config.editUrl }>
									{ __(
										'Mở trình soạn thảo WordPress',
										'saha-builder'
									) }
								</MenuItem>
							) }
							<MenuItem
								isDestructive
								disabled={ state.readOnly }
								onClick={ () => {
									onClose();
									onDisable();
								} }
							>
								{ __(
									'Tắt SAHA Builder cho trang này',
									'saha-builder'
								) }
							</MenuItem>
						</MenuGroup>
					) }
				</DropdownMenu>
			</div>
		</header>
	);
}
