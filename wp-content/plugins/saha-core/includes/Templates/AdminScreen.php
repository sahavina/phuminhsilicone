<?php
/**
 * Quản trị SAHA → Header & Footer.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * AdminScreen — nút tạo header/footer, "Dùng cho toàn site", cột loại / đang dùng.
 */
final class AdminScreen {

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		$type = Repository::POST_TYPE;

		add_filter( "views_edit-{$type}", array( $this, 'actions' ) );
		add_filter( "manage_{$type}_posts_columns", array( $this, 'columns' ) );
		add_action( "manage_{$type}_posts_custom_column", array( $this, 'column' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'rowActions' ), 10, 2 );
		add_action( 'admin_post_saha_template_new', array( $this, 'handleNew' ) );
		add_action( 'admin_post_saha_template_defaults', array( $this, 'handleDefaults' ) );
		add_action( 'admin_post_saha_template_activate', array( $this, 'handleActivate' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * URL thao tác (có nonce).
	 *
	 * @param string               $action Hành động.
	 * @param array<string, mixed> $args   Tham số thêm.
	 */
	private static function actionUrl( string $action, array $args = array() ): string {
		return wp_nonce_url( add_query_arg( array( 'action' => $action ) + $args, admin_url( 'admin-post.php' ) ), $action );
	}

	/**
	 * Nút phía trên danh sách.
	 *
	 * @param array<string, string> $views View.
	 * @return array<string, string>
	 */
	public function actions( $views ): array {
		$views = (array) $views;

		if ( ! current_user_can( Roles::CAP_TEMPLATES ) ) {
			return $views;
		}

		echo '<p class="saha-template-actions">';
		foreach ( Repository::types() as $type => $label ) {
			printf(
				'<a class="button button-primary" href="%1$s">%2$s</a> ',
				esc_url( self::actionUrl( 'saha_template_new', array( 'type' => $type ) ) ),
				/* translators: %s: Header | Footer */
				esc_html( sprintf( __( 'Thêm %s', 'saha-core' ), strtolower( $label ) ) )
			);
		}
		printf(
			'<a class="button" href="%1$s">%2$s</a></p>',
			esc_url( self::actionUrl( 'saha_template_defaults' ) ),
			esc_html__( 'Tạo header & footer mặc định', 'saha-core' )
		);

		return $views;
	}

	/**
	 * Cột.
	 *
	 * @param array<string, string> $columns Cột.
	 * @return array<string, string>
	 */
	public function columns( $columns ): array {
		$columns = (array) $columns;
		$date    = $columns['date'] ?? null;

		unset( $columns['date'] );

		$columns['saha_type']   = __( 'Loại', 'saha-core' );
		$columns['saha_active'] = __( 'Dùng cho toàn site', 'saha-core' );

		if ( null !== $date ) {
			$columns['date'] = $date;
		}

		return $columns;
	}

	/**
	 * Nội dung cột.
	 *
	 * @param string $column  Cột.
	 * @param int    $post_id Template ID.
	 */
	public function column( $column, $post_id ): void {
		$post_id = (int) $post_id;

		if ( 'saha_type' === $column ) {
			echo esc_html( Repository::types()[ Repository::typeOf( $post_id ) ] ?? '—' );
		}

		if ( 'saha_active' === $column ) {
			$active = Repository::isSiteWide( $post_id ) && 'publish' === get_post_status( $post_id );
			echo $active ? '<strong>✓ ' . esc_html__( 'Đang dùng', 'saha-core' ) . '</strong>' : '—';
		}
	}

	/**
	 * Hành động trên từng dòng.
	 *
	 * @param array<string, string> $actions Hành động.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function rowActions( $actions, $post ) {
		if ( ! $post instanceof \WP_Post || Repository::POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		if ( ! ( Repository::isSiteWide( $post->ID ) && 'publish' === $post->post_status ) ) {
			$actions['saha_activate'] = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( self::actionUrl( 'saha_template_activate', array( 'post' => $post->ID ) ) ),
				esc_html__( 'Dùng cho toàn site', 'saha-core' )
			);
		}

		return $actions;
	}

	/**
	 * Kiểm quyền + nonce cho admin-post.
	 *
	 * @param string $action Hành động.
	 */
	private function guard( string $action ): void {
		if ( ! current_user_can( Roles::CAP_TEMPLATES ) || ! current_user_can( Roles::CAP_BUILDER ) ) {
			wp_die( esc_html__( 'Bạn không có quyền quản lý header/footer.', 'saha-core' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Thêm header/footer trống → mở builder.
	 */
	public function handleNew(): void {
		$this->guard( 'saha_template_new' );

		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- đã check_admin_referer.

		if ( ! isset( Repository::types()[ $type ] ) ) {
			wp_die( esc_html__( 'Loại template không hợp lệ.', 'saha-core' ), 400 );
		}

		/* translators: %s: Header | Footer */
		$id = Defaults::create( $type, sprintf( __( '%s mới', 'saha-core' ), Repository::types()[ $type ] ), Defaults::starter( $type ) );

		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'saha-builder', 'post' => $id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Tạo header & footer mặc định.
	 */
	public function handleDefaults(): void {
		$this->guard( 'saha_template_defaults' );

		$result = Defaults::install();
		$ok     = ! array_filter( $result, 'is_wp_error' );

		wp_safe_redirect( add_query_arg( 'saha_notice', $ok ? 'defaults' : 'error', admin_url( 'edit.php?post_type=' . Repository::POST_TYPE ) ) );
		exit;
	}

	/**
	 * Dùng template cho toàn site.
	 */
	public function handleActivate(): void {
		$this->guard( 'saha_template_activate' );

		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- đã check_admin_referer.

		if ( '' === Repository::typeOf( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_die( esc_html__( 'Template không hợp lệ.', 'saha-core' ), 400 );
		}

		Repository::activate( $post_id );

		wp_safe_redirect( add_query_arg( 'saha_notice', 'activated', admin_url( 'edit.php?post_type=' . Repository::POST_TYPE ) ) );
		exit;
	}

	/**
	 * Thông báo sau thao tác.
	 */
	public function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$notice = isset( $_GET['saha_notice'] ) ? sanitize_key( wp_unslash( $_GET['saha_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ hiển thị.

		if ( ! $screen || Repository::POST_TYPE !== $screen->post_type || '' === $notice ) {
			return;
		}

		$messages = array(
			'defaults'  => array( 'success', __( 'Đã tạo header & footer mặc định. Loại nào chưa có template đang dùng thì được dùng cho toàn site ngay.', 'saha-core' ) ),
			'activated' => array( 'success', __( 'Đã đổi template dùng cho toàn site.', 'saha-core' ) ),
			'error'     => array( 'error', __( 'Không tạo được template — xem SAHA → Kiểm tra hệ thống.', 'saha-core' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $messages[ $notice ][0] ), esc_html( $messages[ $notice ][1] ) );
		}
	}
}
