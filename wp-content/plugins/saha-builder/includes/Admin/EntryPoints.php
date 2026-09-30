<?php
/**
 * Lối vào SAHA Builder.
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder\Admin;

use Saha\Core\Builder\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * EntryPoints — link "Sửa bằng SAHA Builder" ở danh sách, admin bar, block editor.
 */
final class EntryPoints {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'page_row_actions', array( $this, 'rowActions' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'rowActions' ), 10, 2 );
		add_action( 'admin_bar_menu', array( $this, 'adminBar' ), 81 );
		add_action( 'enqueue_block_editor_assets', array( $this, 'blockEditorNotice' ) );
	}

	/**
	 * Link trong danh sách Trang / Bài viết.
	 *
	 * @param array<string, string> $actions Action.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function rowActions( $actions, $post ) {
		if ( $post instanceof \WP_Post && 'trash' !== $post->post_status && BuilderScreen::canEdit( $post->ID ) ) {
			$actions['saha_builder'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( BuilderScreen::url( $post->ID ) ),
				LayoutRepository::isEnabled( $post->ID ) ? esc_html__( 'Sửa bằng SAHA Builder', 'saha-builder' ) : esc_html__( 'Dựng bằng SAHA Builder', 'saha-builder' )
			);
		}

		return $actions;
	}

	/**
	 * Link trên admin bar ở frontend.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function adminBar( $bar ): void {
		if ( is_admin() || ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();

		if ( ! BuilderScreen::canEdit( $post_id ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'    => 'saha-builder',
				'title' => esc_html__( 'Sửa bằng SAHA Builder', 'saha-builder' ),
				'href'  => BuilderScreen::url( $post_id ),
			)
		);
	}

	/**
	 * Block editor: trang đang dùng builder → cảnh báo sửa ở đây không có tác dụng.
	 */
	public function blockEditorNotice(): void {
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ đọc ID.

		if ( ! BuilderScreen::canEdit( $post_id ) || ! LayoutRepository::isEnabled( $post_id ) ) {
			return;
		}

		wp_register_script( 'saha-builder-editor-notice', false, array( 'wp-data', 'wp-notices', 'wp-dom-ready' ), SAHA_BUILDER_VERSION, true );
		wp_enqueue_script( 'saha-builder-editor-notice' );
		wp_add_inline_script(
			'saha-builder-editor-notice',
			sprintf(
				'wp.domReady(function(){wp.data.dispatch("core/notices").createWarningNotice(%1$s,{isDismissible:false,actions:[{label:%2$s,url:%3$s}]});});',
				wp_json_encode( __( 'Trang này hiển thị bằng SAHA Builder. Nội dung trong trình soạn thảo này chỉ là bản dự phòng — sửa ở đây KHÔNG thay đổi trang ngoài website và sẽ bị ghi đè khi lưu bằng SAHA Builder.', 'saha-builder' ) ),
				wp_json_encode( __( 'Mở SAHA Builder', 'saha-builder' ) ),
				wp_json_encode( BuilderScreen::url( $post_id ) )
			)
		);
	}
}
