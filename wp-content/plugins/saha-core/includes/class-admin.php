<?php
/**
 * Admin menu & pages.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Admin: tạo menu SAHA và trang Dashboard / Cấu hình.
 *
 * Các trang nghiệp vụ (Báo giá, Lead, Liên hệ, Báo cáo) do module tương ứng
 * tự đăng ký vào hook `saha_core_admin_menu` ở các phase sau.
 */
final class Admin {

	/**
	 * Slug menu gốc.
	 */
	public const MENU_SLUG = 'saha-core';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'add_dashboard_widget' ) );
		add_filter( 'plugin_action_links_' . SAHA_CORE_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Menu SAHA.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'SAHA', 'saha-core' ),
			__( 'SAHA', 'saha-core' ),
			Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' ),
			'dashicons-archive',
			26
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Dashboard', 'saha-core' ),
			__( 'Dashboard', 'saha-core' ),
			Roles::CAP_MANAGE,
			self::MENU_SLUG,
			array( $this, 'render_dashboard' )
		);

		/**
		 * Cho module khác thêm submenu.
		 *
		 * @param string $parent_slug Slug menu gốc.
		 */
		do_action( 'saha_core_admin_menu', self::MENU_SLUG );

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Cấu hình', 'saha-core' ),
			__( 'Cấu hình', 'saha-core' ),
			Roles::CAP_SETTINGS,
			self::MENU_SLUG . '-settings',
			array( $this, 'render_settings' )
		);
	}

	/**
	 * Asset admin, chỉ trên trang của plugin.
	 *
	 * @param string $hook_suffix Hook hiện tại.
	 */
	public function enqueue( string $hook_suffix ): void {
		// toplevel_page_saha-core cho Dashboard, saha_page_* cho mọi submenu (CRM, cấu hình…).
		$on_saha_page = 0 === strpos( $hook_suffix, 'toplevel_page_' . self::MENU_SLUG )
			|| 0 === strpos( $hook_suffix, 'saha_page_' );
		$needs_editor = $this->screen_needs_field_ui();

		if ( ! $on_saha_page && ! $needs_editor ) {
			return;
		}

		wp_enqueue_style(
			'saha-core-admin',
			SAHA_CORE_URL . 'admin/assets/admin.css',
			array(),
			self::asset_version( 'admin/assets/admin.css' )
		);

		if ( ! $needs_editor ) {
			return;
		}

		// Media picker cho logo/banner thương hiệu và tài liệu sản phẩm.
		wp_enqueue_media();

		wp_enqueue_script(
			'saha-core-admin',
			SAHA_CORE_URL . 'admin/assets/admin.js',
			array(),
			self::asset_version( 'admin/assets/admin.js' ),
			true
		);
	}

	/**
	 * Screen hiện tại có chứa field tuỳ biến của SAHA?
	 */
	private function screen_needs_field_ui(): bool {
		if ( ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		if ( 'product' === $screen->post_type && in_array( $screen->base, array( 'post', 'post-new' ), true ) ) {
			return true;
		}

		return 'edit-tags' === $screen->base || 'term' === $screen->base
			? in_array( $screen->taxonomy, Taxonomies::active(), true )
			: false;
	}

	/**
	 * Version asset theo filemtime.
	 *
	 * @param string $relative Đường dẫn tương đối trong plugin.
	 */
	private static function asset_version( string $relative ): string {
		$path = SAHA_CORE_PATH . ltrim( $relative, '/' );

		return is_readable( $path ) ? (string) filemtime( $path ) : SAHA_CORE_VERSION;
	}

	/**
	 * Trang Dashboard.
	 */
	public function render_dashboard(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		$stats = self::collect_stats();

		require SAHA_CORE_PATH . 'admin/views/dashboard.php';
	}

	/**
	 * Trang Cấu hình.
	 */
	public function render_settings(): void {
		if ( ! current_user_can( Roles::CAP_SETTINGS ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-core' ), 403 );
		}

		$schema   = Settings::schema();
		$sections = Settings::sections();
		$values   = Settings::all();

		require SAHA_CORE_PATH . 'admin/views/settings.php';
	}

	/**
	 * Dashboard widget tổng quan.
	 */
	public function add_dashboard_widget(): void {
		if ( ! current_user_can( Roles::CAP_MANAGE ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'saha_core_overview',
			__( 'SAHA — Tổng quan', 'saha-core' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Nội dung widget.
	 */
	public function render_dashboard_widget(): void {
		$stats = self::collect_stats();

		echo '<ul class="saha-stat-list">';

		foreach ( $stats as $stat ) {
			printf(
				'<li><strong>%1$s</strong> <span>%2$s</span></li>',
				esc_html( (string) $stat['value'] ),
				esc_html( (string) $stat['label'] )
			);
		}

		echo '</ul>';

		printf(
			'<p><a class="button button-primary" href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ),
			esc_html__( 'Mở dashboard SAHA', 'saha-core' )
		);
	}

	/**
	 * Số liệu tổng quan. Module phase sau bổ sung qua filter.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function collect_stats(): array {
		$stats = array(
			array(
				'label' => __( 'Sản phẩm đã publish', 'saha-core' ),
				'value' => self::count_products(),
			),
		);

		/**
		 * Bổ sung số liệu dashboard.
		 *
		 * @param array<int, array<string, mixed>> $stats Danh sách số liệu.
		 */
		return (array) apply_filters( 'saha_core_dashboard_stats', $stats );
	}

	/**
	 * Đếm sản phẩm publish, có cache ngắn.
	 */
	private static function count_products(): int {
		if ( ! post_type_exists( 'product' ) ) {
			return 0;
		}

		$counts = wp_count_posts( 'product' );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * Link nhanh tới cấu hình ở trang Plugins.
	 *
	 * @param string[] $links Link hiện có.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$settings = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '-settings' ) ),
			esc_html__( 'Cấu hình', 'saha-core' )
		);

		array_unshift( $links, $settings );

		return $links;
	}
}
