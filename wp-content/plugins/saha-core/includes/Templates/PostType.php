<?php
/**
 * Post type saha_template.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Admin;
use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * PostType — header/footer (Phase 2 thêm template trang, sản phẩm…).
 *
 * Header/footer ảnh hưởng toàn site → mọi thao tác cần `manage_saha_templates`
 * (administrator), không chỉ `edit_saha_builder` như trang thường.
 */
final class PostType {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'registerType' ), 5 );
		add_filter( 'saha_builder_post_types', array( $this, 'enableBuilder' ) );
		add_filter( 'saha_builder_root_type', array( $this, 'rootType' ), 10, 2 );
		add_action( 'saha_core_admin_menu', array( $this, 'addMenu' ) );
		add_filter( 'parent_file', array( $this, 'parentFile' ) );

		// Chỉ mục điều kiện luôn khớp dữ liệu.
		add_action( 'save_post_' . Repository::POST_TYPE, array( Repository::class, 'compile' ) );
		add_action( 'deleted_post', array( $this, 'maybeCompile' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'maybeCompile' ) );
		add_action( 'untrashed_post', array( $this, 'maybeCompile' ) );
	}

	/**
	 * Đăng ký post type.
	 */
	public function registerType(): void {
		$cap = Roles::CAP_TEMPLATES;

		register_post_type(
			Repository::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Header & Footer', 'saha-core' ),
					'singular_name' => __( 'Template', 'saha-core' ),
					'edit_item'     => __( 'Đổi tên template', 'saha-core' ),
					'search_items'  => __( 'Tìm template', 'saha-core' ),
					'not_found'     => __( 'Chưa có header/footer nào.', 'saha-core' ),
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'map_meta_cap'        => true,
				'capabilities'        => array(
					'edit_posts'             => $cap,
					'edit_others_posts'      => $cap,
					'edit_published_posts'   => $cap,
					'edit_private_posts'     => $cap,
					'publish_posts'          => $cap,
					'read_private_posts'     => $cap,
					'delete_posts'           => $cap,
					'delete_others_posts'    => $cap,
					'delete_published_posts' => $cap,
					'delete_private_posts'   => $cap,
					// Tạo qua nút "Thêm header / Thêm footer" (có loại sẵn), không qua post-new.php.
					'create_posts'           => 'do_not_allow',
				),
				'supports'            => array( 'title', 'revisions' ),
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * Template dựng bằng builder.
	 *
	 * @param string[] $types Post type.
	 * @return string[]
	 */
	public function enableBuilder( $types ): array {
		$types   = (array) $types;
		$types[] = Repository::POST_TYPE;

		return array_values( array_unique( $types ) );
	}

	/**
	 * Header có gốc riêng: chỉ nhận element Header.
	 *
	 * @param string $root    Gốc.
	 * @param int    $post_id Post ID.
	 */
	public function rootType( $root, $post_id ): string {
		return 'header' === Repository::typeOf( (int) $post_id ) ? 'header-root' : (string) $root;
	}

	/**
	 * Menu SAHA → Header & Footer.
	 *
	 * @param string $parent Slug menu SAHA.
	 */
	public function addMenu( string $parent ): void {
		add_submenu_page( $parent, __( 'Header & Footer', 'saha-core' ), __( 'Header & Footer', 'saha-core' ), Roles::CAP_TEMPLATES, 'edit.php?post_type=' . Repository::POST_TYPE, '', 2 );
	}

	/**
	 * Tô sáng menu SAHA.
	 *
	 * @param string $parent_file Menu cha.
	 */
	public function parentFile( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && Repository::POST_TYPE === $screen->post_type ? Admin::MENU_SLUG : $parent_file;
	}

	/**
	 * Biên dịch lại khi xoá/khôi phục template.
	 *
	 * @param int           $post_id Post ID.
	 * @param \WP_Post|null $post    Post (deleted_post truyền vào vì lúc đó bài đã bị xoá).
	 */
	public function maybeCompile( $post_id, $post = null ): void {
		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( (int) $post_id );

		if ( Repository::POST_TYPE === $type ) {
			Repository::compile();
		}
	}
}
