<?php
/**
 * Post type saha_block — block tái sử dụng.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Blocks;

use Saha\Core\Admin;
use Saha\Core\Builder\LayoutRepository;

defined( 'ABSPATH' ) || exit;

/**
 * PostType (TECHNICAL-DESIGN §5.1).
 *
 * Không public (không có URL riêng), chỉ hiển thị qua element Block. Nội dung dựng
 * bằng SAHA Builder; màn hình sửa của WordPress chỉ dùng để đặt tên và xuất bản.
 */
final class PostType {

	public const NAME = 'saha_block';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'registerType' ), 5 );
		add_filter( 'saha_builder_post_types', array( $this, 'enableBuilder' ) );
		add_filter( 'redirect_post_location', array( $this, 'openBuilderAfterCreate' ), 10, 2 );
		add_action( 'saha_core_admin_menu', array( $this, 'addMenu' ) );
		add_filter( 'parent_file', array( $this, 'parentFile' ) );
		add_filter( 'manage_' . self::NAME . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::NAME . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Đăng ký post type.
	 */
	public function registerType(): void {
		register_post_type(
			self::NAME,
			array(
				'labels'              => array(
					'name'          => __( 'Blocks', 'saha-core' ),
					'singular_name' => __( 'Block', 'saha-core' ),
					'add_new'       => __( 'Thêm block', 'saha-core' ),
					'add_new_item'  => __( 'Thêm block mới', 'saha-core' ),
					'edit_item'     => __( 'Đổi tên / xuất bản block', 'saha-core' ),
					'search_items'  => __( 'Tìm block', 'saha-core' ),
					'not_found'     => __( 'Chưa có block nào.', 'saha-core' ),
					'menu_name'     => __( 'Blocks', 'saha-core' ),
				),
				'description'         => __( 'Khối nội dung dùng chung (USP, CTA, khuyến mại, cột footer…): sửa một chỗ, mọi trang cập nhật.', 'saha-core' ),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				// Menu thêm tay ngay sau Dashboard (show_in_menu sẽ chèn lên đầu menu SAHA).
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'capability_type'     => 'page',
				'map_meta_cap'        => true,
				'hierarchical'        => false,
				'supports'            => array( 'title', 'revisions' ),
				'rewrite'             => false,
				'query_var'           => false,
			)
		);
	}

	/**
	 * Menu SAHA → Blocks (vị trí thứ 2, sau Dashboard).
	 *
	 * @param string $parent Slug menu SAHA.
	 */
	public function addMenu( string $parent ): void {
		$type = get_post_type_object( self::NAME );

		if ( $type ) {
			add_submenu_page( $parent, (string) $type->labels->name, (string) $type->labels->menu_name, (string) $type->cap->edit_posts, 'edit.php?post_type=' . self::NAME, '', 1 );
		}
	}

	/**
	 * Tô sáng menu SAHA khi đang ở màn hình Blocks.
	 *
	 * @param string $parent_file Menu cha.
	 */
	public function parentFile( $parent_file ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		return $screen && self::NAME === $screen->post_type ? Admin::MENU_SLUG : $parent_file;
	}

	/**
	 * Block dựng bằng builder.
	 *
	 * @param string[] $types Post type.
	 * @return string[]
	 */
	public function enableBuilder( $types ): array {
		$types   = (array) $types;
		$types[] = self::NAME;

		return array_values( array_unique( $types ) );
	}

	/**
	 * Tạo block mới (đặt tên → Xuất bản) → mở thẳng SAHA Builder.
	 *
	 * @param string $location URL chuyển hướng.
	 * @param int    $post_id  Post ID.
	 */
	public function openBuilderAfterCreate( $location, $post_id ) {
		$post_id = (int) $post_id;

		if ( self::NAME !== get_post_type( $post_id ) || null !== LayoutRepository::raw( $post_id ) || ! current_user_can( 'edit_saha_builder' ) ) {
			return $location;
		}

		return add_query_arg(
			array(
				'page' => 'saha-builder',
				'post' => $post_id,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Cột "Dùng ở" trong danh sách.
	 *
	 * @param array<string, string> $columns Cột.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns = (array) $columns;
		$date    = $columns['date'] ?? null;

		unset( $columns['date'] );

		$columns['saha_usage'] = __( 'Đang dùng ở', 'saha-core' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Nội dung cột.
	 *
	 * @param string $column  Cột.
	 * @param int    $post_id Block ID.
	 */
	public function column( $column, $post_id ): void {
		if ( 'saha_usage' !== $column ) {
			return;
		}

		$users = Usage::pagesUsing( (int) $post_id );

		if ( ! $users ) {
			echo '—';
			return;
		}

		$links = array();

		foreach ( array_slice( $users, 0, 5 ) as $id ) {
			$links[] = '<a href="' . esc_url( (string) get_edit_post_link( $id ) ) . '">' . esc_html( get_the_title( $id ) ?: '#' . $id ) . '</a>';
		}

		echo wp_kses_post( implode( ', ', $links ) . ( count( $users ) > 5 ? ' …' : '' ) );
	}
}
