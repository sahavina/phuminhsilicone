<?php
/**
 * Màn hình Appearance → SAHA Theme Options.
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder\Admin;

use Saha\Builder\Assets;

defined( 'ABSPATH' ) || exit;

/**
 * ThemeOptionsScreen.
 *
 * Chỉ dựng khung HTML và nạp ứng dụng React; mọi dữ liệu đi qua REST
 * `/saha/v1/settings` của saha-core.
 */
final class ThemeOptionsScreen {

	public const SLUG = 'saha-theme-options';

	/**
	 * Hook suffix của trang (để chỉ nạp JS ở đúng trang này).
	 *
	 * @var string
	 */
	private string $hook = '';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenu' ) );
		// Lối tắt trong menu SAHA (spec SCC §81) — saha-core bắn hook này khi dựng menu.
		add_action( 'saha_core_admin_menu', array( $this, 'addShortcut' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Appearance → SAHA Theme Options (spec SCC §27).
	 */
	public function addMenu(): void {
		$hook = add_theme_page(
			__( 'SAHA Theme Options', 'saha-builder' ),
			__( 'SAHA Theme Options', 'saha-builder' ),
			'edit_theme_options',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook = is_string( $hook ) ? $hook : '';
	}

	/**
	 * Lối tắt SAHA → Theme Options, trỏ về đúng một trang.
	 *
	 * @param string $parent Slug menu SAHA.
	 */
	public function addShortcut( string $parent ): void {
		add_submenu_page(
			$parent,
			__( 'Theme Options', 'saha-builder' ),
			__( 'Theme Options', 'saha-builder' ),
			'edit_theme_options',
			'themes.php?page=' . self::SLUG
		);

		// Có menu SAHA → bỏ mục trùng ở Giao diện (trang vẫn ở URL cũ, chỉ ẩn dòng menu).
		add_action(
			'admin_menu',
			static function (): void {
				remove_submenu_page( 'themes.php', self::SLUG );
			},
			999
		);
	}

	/**
	 * Nạp ứng dụng.
	 *
	 * @param string $hook_suffix Hook của trang hiện tại.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}

		// MediaUpload (chọn logo) cần wp.media.
		wp_enqueue_media();

		$handle = Assets::enqueue( 'theme-options' );

		if ( '' === $handle ) {
			return;
		}

		wp_add_inline_script(
			$handle,
			'window.sahaThemeOptions = ' . wp_json_encode(
				array(
					'homeUrl'     => home_url( '/' ),
					'themeActive' => current_theme_supports( 'saha-theme-options' ),
					'canUpload'   => current_user_can( 'upload_files' ),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Khung trang — React gắn vào #saha-theme-options.
	 */
	public function render(): void {
		if ( ! current_user_can( 'edit_theme_options' ) ) {
			wp_die( esc_html__( 'Bạn không có quyền truy cập trang này.', 'saha-builder' ), 403 );
		}

		$built = is_readable( SAHA_BUILDER_PATH . 'build/theme-options.asset.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SAHA Theme Options', 'saha-builder' ); ?></h1>
			<?php if ( ! $built ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Chưa có bản build của ứng dụng. Chạy "npm run build" ở gốc repo.', 'saha-builder' ); ?></p></div>
			<?php else : ?>
				<div id="saha-theme-options"><p><?php esc_html_e( 'Đang tải…', 'saha-builder' ); ?></p></div>
				<noscript><p><?php esc_html_e( 'Trang này cần JavaScript.', 'saha-builder' ); ?></p></noscript>
			<?php endif; ?>
		</div>
		<?php
	}
}
