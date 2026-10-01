<?php
/**
 * Element: Menu.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * NavMenu — menu WordPress (Giao diện → Menu) theo vị trí hoặc chọn thẳng.
 *
 * Theo vị trí (khuyến nghị): quản trị viên đổi menu ở Giao diện → Menu, không phải
 * mở builder. `dynamic`: class "mục đang xem" đổi theo trang → không cache.
 */
final class NavMenu extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		$locations = array( '' => __( '— Chọn menu cụ thể bên dưới —', 'saha-core' ) ) + get_registered_nav_menus();

		return array(
			'type'           => 'nav-menu',
			'name'           => __( 'Menu', 'saha-core' ),
			'icon'           => 'menu-alt',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'location'    => array(
					'type'    => 'select',
					'label'   => __( 'Vị trí menu', 'saha-core' ),
					'section' => 'content',
					'default' => 'primary',
					'options' => $locations,
					'help'    => __( 'Gán menu cho vị trí ở Giao diện → Menu.', 'saha-core' ),
				),
				'menu'        => array(
					'type'     => 'term',
					'taxonomy' => 'nav_menu',
					'label'    => __( 'Hoặc chọn menu cụ thể', 'saha-core' ),
					'section'  => 'content',
				),
				'orientation' => array(
					'type'       => 'select',
					'label'      => __( 'Kiểu', 'saha-core' ),
					'section'    => 'content',
					'default'    => 'horizontal',
					'options'    => array(
						'horizontal' => __( 'Ngang (menu con thả xuống)', 'saha-core' ),
						'vertical'   => __( 'Dọc', 'saha-core' ),
					),
				),
				'depth'       => array(
					'type'    => 'number',
					'label'   => __( 'Số cấp', 'saha-core' ),
					'section' => 'content',
					'default' => 2,
					'min'     => 1,
					'max'     => 3,
				),
				'ariaLabel'   => array(
					'type'      => 'text',
					'label'     => __( 'Tên vùng điều hướng (trình đọc màn hình)', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Menu chính', 'saha-core' ),
					'maxLength' => 60,
				),
				'uppercase'   => array(
					'type'    => 'toggle',
					'label'   => __( 'Chữ in hoa', 'saha-core' ),
					'section' => 'style',
					'default' => false,
				),
				'caret'       => array(
					'type'    => 'toggle',
					'label'   => __( 'Mũi tên ở mục có menu con', 'saha-core' ),
					'section' => 'style',
					'default' => false,
				),
				'typography'  => array(
					'type'    => 'typography',
					'label'   => __( 'Kiểu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'color'       => array(
					'type'    => 'color',
					'label'   => __( 'Màu chữ', 'saha-core' ),
					'section' => 'style',
				),
				'hoverColor'  => array(
					'type'    => 'color',
					'label'   => __( 'Màu khi rê chuột / đang xem', 'saha-core' ),
					'section' => 'style',
				),
				'gap'         => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách giữa mục', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px' ),
					'min'        => 0,
					'max'        => 80,
				),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$args = array(
			'container'   => false,
			'menu_class'  => 'saha-nav__list',
			'depth'       => max( 1, min( 3, (int) $this->prop( $node, 'depth' ) ) ),
			'fallback_cb' => false,
			'echo'        => false,
			// Mega menu (SCC 2.1) chỉ ở menu ngang; menu dọc/off-canvas giữ menu con thường.
			'saha_mega'   => 'vertical' !== $this->prop( $node, 'orientation' ),
		);

		$menu     = (string) $node->prop( 'menu', '' );
		$location = (string) $this->prop( $node, 'location' );

		if ( '' !== $menu ) {
			$args['menu'] = $menu;
		} elseif ( '' !== $location && has_nav_menu( $location ) ) {
			$args['theme_location'] = $location;
		} else {
			return $ctx->editor
				? '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-nav', 'saha-image--empty' ) ) . '>' . esc_html__( 'Chưa có menu ở vị trí này — tạo và gán menu ở Giao diện → Menu.', 'saha-core' ) . '</div>'
				: '';
		}

		$html = wp_nav_menu( $args );

		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}

		$classes = array( 'saha-nav', 'vertical' === $this->prop( $node, 'orientation' ) ? 'saha-nav--vertical' : 'saha-nav--horizontal' );

		if ( $this->prop( $node, 'uppercase' ) ) {
			$classes[] = 'saha-nav--upper';
		}

		if ( $this->prop( $node, 'caret' ) ) {
			$classes[] = 'saha-nav--caret';
		}

		return '<nav' . $this->rootAttributes( $node, $ctx, $classes, array( 'aria-label' => (string) $this->prop( $node, 'ariaLabel' ) ) ) . '>' . $html . '</nav>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		// Chỉ link của mục menu (không đụng nội dung Block trong mega menu).
		$css->typography( ' .menu-item > a', $node->prop( 'typography' ) );
		// Màu chữ chỉ cho mục cấp 1 — menu con/mega có nền riêng (trắng).
		$css->set( ' .saha-nav__list > .menu-item > a', 'color', $node->prop( 'color' ) );
		$css->set( ' .menu-item > a:hover', 'color', $node->prop( 'hoverColor' ) );
		$css->set( ' .current-menu-item > a', 'color', $node->prop( 'hoverColor' ) );
		$css->set( ' .current-menu-ancestor > a', 'color', $node->prop( 'hoverColor' ) );
		$css->set( ' .saha-nav__list', 'gap', $node->prop( 'gap' ) );
	}
}
