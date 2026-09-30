/**
 * Danh sách nhóm dạng tab dọc (WAI-ARIA tabs, kích hoạt thủ công).
 *
 * Không dùng TabPanel của `@wordpress/components`: bản Ariakit chọn tab theo
 * focus, nên khi cửa sổ lấy lại focus tab có thể tự đổi giữa lúc đang nhập.
 * Ở đây tab chỉ đổi khi bấm chuột hoặc Enter/Space; mũi tên chỉ di chuyển focus.
 */
import { useRef } from '@wordpress/element';

export default function GroupTabs( { tabs, selected, onSelect, idPrefix } ) {
	const refs = useRef( [] );

	const onKeyDown = ( event, index ) => {
		const last = tabs.length - 1;
		let next = null;

		switch ( event.key ) {
			case 'ArrowDown':
				next = index === last ? 0 : index + 1;
				break;
			case 'ArrowUp':
				next = index === 0 ? last : index - 1;
				break;
			case 'Home':
				next = 0;
				break;
			case 'End':
				next = last;
				break;
			default:
				return;
		}

		event.preventDefault();
		refs.current[ next ]?.focus();
	};

	return (
		<div
			className="saha-to__tablist"
			role="tablist"
			aria-orientation="vertical"
		>
			{ tabs.map( ( tab, index ) => {
				const isSelected = tab.name === selected;

				return (
					<button
						key={ tab.name }
						ref={ ( el ) => ( refs.current[ index ] = el ) }
						type="button"
						role="tab"
						id={ `${ idPrefix }-tab-${ tab.name }` }
						aria-controls={ `${ idPrefix }-panel` }
						aria-selected={ isSelected }
						tabIndex={ isSelected ? 0 : -1 }
						className={
							'saha-to__tab' +
							( isSelected ? ' is-selected' : '' )
						}
						onClick={ () => onSelect( tab.name ) }
						onKeyDown={ ( event ) => onKeyDown( event, index ) }
					>
						{ tab.title }
					</button>
				);
			} ) }
		</div>
	);
}
