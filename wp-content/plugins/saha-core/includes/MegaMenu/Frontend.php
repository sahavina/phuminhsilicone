<?php
/**
 * Hiển thị mega menu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\MegaMenu;

use Saha\Core\Builder\Frontend as BuilderFrontend;
use Saha\Core\Builder\LayoutService;
use Saha\Core\Builder\RenderContext;

defined( 'ABSPATH' ) || exit;

/**
 * Frontend — gắn vào walker mặc định của WordPress bằng filter, nên chạy với mọi
 * `wp_nav_menu()` có tham số `saha_mega => true` (element Menu ngang của builder,
 * menu chính trong header PHP của saha-theme, theme khác tự bật được).
 *
 * Mục cấp 1 kiểu mega:
 * - có Block → bảng mega chứa nội dung block (menu con của mục đó bị ẩn trên desktop);
 * - không Block → menu con hiện thành bảng chia N cột.
 *
 * Menu không có cờ (menu dọc, off-canvas mobile) giữ nguyên menu thường → mobile
 * không phải tải nội dung mega.
 */
final class Frontend {

	public const STYLE  = 'saha-mega-menu';
	public const SCRIPT = 'saha-mega-menu';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_filter( 'wp_nav_menu_args', array( $this, 'depth' ) );
		add_filter( 'nav_menu_css_class', array( $this, 'classes' ), 10, 4 );
		add_filter( 'nav_menu_item_attributes', array( $this, 'itemAttributes' ), 10, 4 );
		add_filter( 'nav_menu_link_attributes', array( $this, 'linkAttributes' ), 10, 4 );
		add_filter( 'walker_nav_menu_start_el', array( $this, 'panel' ), 10, 4 );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ), 20 );
	}

	/**
	 * Mục này hiển thị dạng mega trong menu này không.
	 *
	 * @param mixed $item  Mục menu.
	 * @param mixed $args  Tham số wp_nav_menu.
	 * @param int   $depth Cấp.
	 */
	private static function applies( $item, $args, int $depth ): bool {
		return 0 === $depth
			&& is_object( $args ) && ! empty( $args->saha_mega )
			&& is_object( $item ) && isset( $item->ID )
			&& Settings::isMega( (int) $item->ID );
	}

	/**
	 * Block nội dung dùng được (đã xuất bản); không thì mục quay về menu con chia cột.
	 *
	 * @param int $item_id ID mục menu.
	 */
	private static function block( int $item_id ): int {
		$block = Settings::get( $item_id )['blockId'];

		return $block > 0 && 'publish' === get_post_status( $block ) ? $block : 0;
	}

	/**
	 * Mega chia cột cần menu cấp 3 (cấp 2 = tiêu đề cột, cấp 3 = danh sách):
	 * menu có cờ mega lấy ít nhất 3 cấp khi site đang có mục mega.
	 *
	 * @param array<string, mixed> $args Tham số wp_nav_menu.
	 * @return array<string, mixed>
	 */
	public function depth( $args ): array {
		$args = (array) $args;

		if ( ! empty( $args['saha_mega'] ) && isset( $args['depth'] ) && $args['depth'] > 0 && $args['depth'] < 3 && Settings::compiled()['active'] ) {
			$args['depth'] = 3;
		}

		return $args;
	}

	/**
	 * Class của <li>.
	 *
	 * @param string[] $classes Class.
	 * @param mixed    $item    Mục menu.
	 * @param mixed    $args    Tham số.
	 * @param int      $depth   Cấp.
	 * @return string[]
	 */
	public function classes( $classes, $item, $args, $depth = 0 ): array {
		$classes = (array) $classes;

		if ( ! self::applies( $item, $args, (int) $depth ) ) {
			return $classes;
		}

		$settings  = Settings::get( (int) $item->ID );
		$classes[] = 'saha-mega-item';
		$classes[] = 'saha-mega-item--' . $settings['width'];

		if ( self::block( (int) $item->ID ) > 0 ) {
			$classes[] = 'saha-mega-item--block';
			$classes[] = 'menu-item-has-children';
		} else {
			$classes[] = 'saha-mega-item--cols-' . $settings['columns'];
		}

		return array_values( array_unique( $classes ) );
	}

	/**
	 * Độ rộng tuỳ chỉnh qua biến CSS trên <li>.
	 *
	 * @param array<string, string> $atts  Thuộc tính.
	 * @param mixed                 $item  Mục menu.
	 * @param mixed                 $args  Tham số.
	 * @param int                   $depth Cấp.
	 * @return array<string, string>
	 */
	public function itemAttributes( $atts, $item, $args, $depth = 0 ): array {
		$atts = (array) $atts;

		if ( self::applies( $item, $args, (int) $depth ) ) {
			$settings = Settings::get( (int) $item->ID );

			if ( 'custom' === $settings['width'] ) {
				$atts['style'] = '--saha-mega-width:' . $settings['customWidth'] . 'px';
			}
		}

		return $atts;
	}

	/**
	 * Link cấp 1 báo cho công nghệ hỗ trợ là có bảng con (mega-menu.js cập nhật trạng thái).
	 *
	 * @param array<string, string> $atts  Thuộc tính.
	 * @param mixed                 $item  Mục menu.
	 * @param mixed                 $args  Tham số.
	 * @param int                   $depth Cấp.
	 * @return array<string, string>
	 */
	public function linkAttributes( $atts, $item, $args, $depth = 0 ): array {
		$atts = (array) $atts;

		if ( self::applies( $item, $args, (int) $depth ) ) {
			$atts['aria-expanded'] = 'false';
		}

		return $atts;
	}

	/**
	 * Bảng mega chứa block, in ngay sau link cấp 1.
	 *
	 * @param string $output HTML của mục (đến hết thẻ <a>).
	 * @param mixed  $item   Mục menu.
	 * @param int    $depth  Cấp.
	 * @param mixed  $args   Tham số.
	 */
	public function panel( $output, $item, $depth, $args ): string {
		$output = (string) $output;

		if ( ! self::applies( $item, $args, (int) $depth ) ) {
			return $output;
		}

		$block = self::block( (int) $item->ID );

		if ( $block <= 0 ) {
			return $output;
		}

		$result = LayoutService::renderBlock( $block, new RenderContext( 0, false ) );

		if ( '' === $result['html'] ) {
			return $output;
		}

		return $output . '<div class="saha-mega"><div class="saha-mega__inner">' . $result['html'] . '</div></div>';
	}

	/**
	 * CSS/JS mega + CSS của các block dùng trong mega (biết trước nhờ option đã biên dịch).
	 */
	public function assets(): void {
		$compiled = Settings::compiled();

		if ( ! $compiled['active'] ) {
			return;
		}

		wp_enqueue_style( self::STYLE, SAHA_CORE_URL . 'public/assets/css/mega-menu.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			self::SCRIPT,
			SAHA_CORE_URL . 'public/assets/js/mega-menu.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		if ( $compiled['blocks'] ) {
			BuilderFrontend::enqueueBase();

			foreach ( $compiled['blocks'] as $block_id ) {
				BuilderFrontend::enqueueDocumentCss( $block_id );
			}
		}
	}
}
