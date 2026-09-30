/**
 * Bảng "Thêm": kéo element vào canvas, hoặc bấm để thêm cạnh/vào element đang chọn.
 */
import { Icon } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { useActions } from '../actions';
import { drag, useBuilder } from '../context';

const CATEGORIES = [
	[ 'layout', __( 'Bố cục', 'saha-builder' ) ],
	[ 'content', __( 'Nội dung', 'saha-builder' ) ],
	[ 'marketing', __( 'Marketing', 'saha-builder' ) ],
	[ 'woocommerce', __( 'Sản phẩm', 'saha-builder' ) ],
	[ 'blog', __( 'Blog', 'saha-builder' ) ],
];

export default function Inserter() {
	const { elements, state } = useBuilder();
	const { insertType } = useActions();

	const known = CATEGORIES.map( ( [ key ] ) => key );
	const groups = [
		...CATEGORIES,
		...[ ...new Set( elements.map( ( e ) => e.category ) ) ]
			.filter( ( c ) => ! known.includes( c ) )
			.map( ( c ) => [ c, c ] ),
	];

	return (
		<div className="saha-b-inserter">
			<p className="saha-b-hint">
				{ __(
					'Kéo vào trang, hoặc bấm để thêm vào/cạnh element đang chọn.',
					'saha-builder'
				) }
			</p>
			{ groups.map( ( [ key, label ] ) => {
				const items = elements.filter( ( e ) => e.category === key );

				if ( ! items.length ) {
					return null;
				}

				return (
					<section key={ key } className="saha-b-inserter__group">
						<h3>{ label }</h3>
						<div className="saha-b-inserter__grid">
							{ items.map( ( element ) => (
								<button
									key={ element.type }
									type="button"
									className="saha-b-inserter__item"
									draggable={ ! state.readOnly }
									disabled={ state.readOnly }
									onDragStart={ ( event ) => {
										drag.payload = {
											kind: 'new',
											type: element.type,
										};
										event.dataTransfer.effectAllowed =
											'copy';
										event.dataTransfer.setData(
											'text/plain',
											element.type
										);
									} }
									onDragEnd={ () => {
										drag.payload = null;
									} }
									onClick={ () => insertType( element.type ) }
								>
									<Icon
										icon={ element.icon || 'block-default' }
									/>
									<span>{ element.name }</span>
								</button>
							) ) }
						</div>
					</section>
				);
			} ) }
		</div>
	);
}
