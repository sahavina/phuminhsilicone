/**
 * Rich text bằng trình soạn thảo cổ điển của WordPress (TinyMCE) — quen thuộc với
 * người quản trị, không phải gõ HTML. Server luôn lọc lại bằng wp_kses_post.
 */
import { useEffect, useRef } from '@wordpress/element';

const TOOLBAR =
	'formatselect,bold,italic,underline,link,bullist,numlist,alignleft,aligncenter,alignright,removeformat,undo,redo';

/**
 * API editor cổ điển (wp.oldEditor khi có block editor, ngược lại wp.editor).
 *
 * @return {Object|null} API.
 */
function classicEditor() {
	const wp = window.wp || {};
	const api = wp.oldEditor || wp.editor;

	return api && 'function' === typeof api.initialize ? api : null;
}

export default function RichTextField( { id, value, onChange } ) {
	const editorId = 'saha-rt-' + id.replace( /[^a-z0-9-]/gi, '-' );
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	useEffect( () => {
		const api = classicEditor();
		const textarea = document.getElementById( editorId );

		const emit = ( html ) => onChangeRef.current( html );
		const onText = () => emit( textarea.value );

		// Tab "Văn bản" (HTML) của editor cổ điển.
		textarea?.addEventListener( 'input', onText );

		if ( api ) {
			api.initialize( editorId, {
				tinymce: {
					toolbar1: TOOLBAR,
					toolbar2: '',
					height: 220,
					wpautop: true,
					setup( editor ) {
						editor.on(
							'input change keyup undo redo ExecCommand',
							() => emit( editor.getContent() )
						);
					},
				},
				quicktags: { buttons: 'strong,em,link,ul,ol,li' },
				mediaButtons: false,
			} );
		}

		return () => {
			textarea?.removeEventListener( 'input', onText );
			api?.remove( editorId );
		};
	}, [ editorId ] );

	// Giá trị đổi từ ngoài (undo/redo, lưu server) → cập nhật editor.
	useEffect( () => {
		const editor = window.tinymce?.get( editorId );

		if (
			editor &&
			! editor.isHidden() &&
			editor.getContent() !== ( value || '' )
		) {
			editor.setContent( value || '' );
		}

		const textarea = document.getElementById( editorId );

		if (
			textarea &&
			textarea.ownerDocument.activeElement !== textarea &&
			textarea.value !== ( value || '' ) &&
			( ! editor || editor.isHidden() )
		) {
			textarea.value = value || '';
		}
	}, [ value, editorId ] );

	return (
		<div className="saha-b-richtext">
			<textarea
				id={ editorId }
				defaultValue={ value || '' }
				rows={ 8 }
				className="wp-editor-area"
			/>
		</div>
	);
}
