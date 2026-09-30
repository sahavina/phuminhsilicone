<?php
/**
 * Lớp nền của element builder.
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
 * Element — định nghĩa (nguồn duy nhất cho editor + sanitizer), render HTML, style.
 *
 * Quy ước definition():
 * - `type`            [a-z0-9_-]
 * - `name`, `icon`, `category` (layout | content | marketing | woocommerce | blog | header)
 * - `allowedParents`  type cha hợp lệ; `root` = cấp gốc tài liệu
 * - `allowedChildren` type con hợp lệ; `[]` = không có con; `['*']` = bất kỳ
 * - `controls`        key => định nghĩa control (type, label, default, responsive, section)
 * - `dynamic`         true nếu HTML phụ thuộc request/dữ liệu thay đổi (không render cache)
 * - `assets`          ['script' => handles, 'style' => handles] — chỉ nạp khi có element
 */
abstract class Element {

	/**
	 * Cha hợp lệ của element nội dung (tiêu đề, văn bản, nút…).
	 */
	public const CONTENT_PARENTS = array( 'section', 'column', 'container' );

	/**
	 * Định nghĩa đã chuẩn hoá (cache trong request).
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $resolved = null;

	/**
	 * Định nghĩa gốc của element.
	 *
	 * @return array<string, mixed>
	 */
	abstract protected function definition(): array;

	/**
	 * Render HTML.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML của các con (đã render).
	 */
	abstract public function render( Node $node, RenderContext $ctx, string $content ): string;

	/**
	 * Khai báo style riêng của element (margin/padding nâng cao do CssGenerator lo).
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Tập luật, đã forNode().
	 */
	public function styles( Node $node, CssRules $css ): void {}

	/**
	 * Định nghĩa đầy đủ (có mặc định, qua filter).
	 *
	 * @return array<string, mixed>
	 */
	public function def(): array {
		if ( null === $this->resolved ) {
			$def = $this->definition() + array(
				'name'            => '',
				'icon'            => 'block-default',
				'category'        => 'content',
				'allowedParents'  => array( 'column' ),
				'allowedChildren' => array(),
				'controls'        => array(),
				'dynamic'         => false,
				'assets'          => array(),
			);

			/**
			 * Sửa định nghĩa element.
			 *
			 * @param array<string, mixed> $def  Định nghĩa.
			 * @param string               $type Type.
			 */
			$this->resolved = (array) apply_filters( 'saha_builder_element_definition', $def, (string) $def['type'] );
		}

		return $this->resolved;
	}

	/**
	 * Type.
	 */
	public function type(): string {
		return (string) $this->def()['type'];
	}

	/**
	 * Prop có áp mặc định của control.
	 *
	 * @param Node   $node Node.
	 * @param string $key  Key.
	 * @return mixed
	 */
	public function prop( Node $node, string $key ) {
		return $node->prop( $key, $this->def()['controls'][ $key ]['default'] ?? null );
	}

	/**
	 * Thuộc tính của thẻ gốc: class chung, id, ẩn theo thiết bị, data-saha-id (editor).
	 *
	 * @param Node                  $node    Node.
	 * @param RenderContext         $ctx     Ngữ cảnh.
	 * @param string[]              $classes Class riêng của element.
	 * @param array<string, string> $attrs   Thuộc tính khác (chưa escape).
	 * @return string Đã escape, bắt đầu bằng khoảng trắng.
	 */
	protected function rootAttributes( Node $node, RenderContext $ctx, array $classes = array(), array $attrs = array() ): string {
		$advanced = $node->advanced;
		$list     = array_merge( array( 'saha-e', 'saha-e-' . $node->id ), $classes );

		foreach ( array(
			'hideDesktop' => 'saha-hide-desktop',
			'hideTablet'  => 'saha-hide-tablet',
			'hideMobile'  => 'saha-hide-mobile',
		) as $key => $class ) {
			if ( ! empty( $advanced[ $key ] ) ) {
				$list[] = $class;
			}
		}

		if ( ! empty( $advanced['cssClass'] ) && is_string( $advanced['cssClass'] ) ) {
			$list = array_merge( $list, explode( ' ', $advanced['cssClass'] ) );
		}

		/**
		 * Thêm class cho thẻ gốc của element.
		 *
		 * @param string[] $list Class.
		 * @param Node     $node Node.
		 */
		$list = (array) apply_filters( 'saha_builder_node_classes', $list, $node );
		$list = array_unique( array_filter( array_map( 'sanitize_html_class', $list ) ) );

		$html = ' class="' . esc_attr( implode( ' ', $list ) ) . '"';

		if ( ! empty( $advanced['cssId'] ) && is_string( $advanced['cssId'] ) ) {
			$html .= ' id="' . esc_attr( $advanced['cssId'] ) . '"';
		}

		if ( $ctx->editor ) {
			$html .= ' data-saha-id="' . esc_attr( $node->id ) . '"';
		}

		foreach ( $attrs as $name => $value ) {
			if ( preg_match( '/^[a-z][a-z0-9-]*$/', $name ) ) {
				$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
			}
		}

		return $html;
	}
}
