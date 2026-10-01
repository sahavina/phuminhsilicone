<?php
/**
 * Element động: tiêu đề + mô tả trang danh sách (danh mục, thương hiệu, chuyên mục, tìm kiếm).
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
 * ArchiveTitle — H1 của trang danh sách; tuỳ chọn in mô tả term bên dưới.
 */
final class ArchiveTitle extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'archive-title',
				'name'     => __( 'Tiêu đề danh sách (động)', 'saha-core' ),
				'icon'     => 'heading',
				'controls' => array(
					'tag'             => array(
						'type'    => 'select',
						'label'   => __( 'Thẻ', 'saha-core' ),
						'section' => 'content',
						'default' => 'h1',
						'options' => array(
							'h1' => 'H1',
							'h2' => 'H2',
						),
					),
					'showDescription' => array(
						'type'    => 'toggle',
						'label'   => __( 'Hiện mô tả danh mục', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'align'           => array(
						'type'       => 'align',
						'label'      => __( 'Căn lề', 'saha-core' ),
						'section'    => 'style',
						'responsive' => true,
						'options'    => array( 'left', 'center', 'right' ),
					),
					'typography'      => array(
						'type'    => 'typography',
						'label'   => __( 'Kiểu chữ tiêu đề', 'saha-core' ),
						'section' => 'style',
					),
				),
			)
		);
	}

	/**
	 * Tiêu đề của request hiện tại.
	 */
	private static function title(): string {
		if ( is_search() ) {
			/* translators: %s: từ khoá */
			return sprintf( __( 'Kết quả tìm kiếm cho “%s”', 'saha-core' ), get_search_query() );
		}

		if ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) {
			return (string) woocommerce_page_title( false );
		}

		if ( is_home() && ! is_front_page() ) {
			return (string) get_the_title( (int) get_option( 'page_for_posts' ) );
		}

		// Chuyên mục, thẻ, taxonomy: chỉ tên (không có tiền tố "Danh mục:" của WordPress).
		if ( is_category() || is_tag() || is_tax() ) {
			return (string) single_term_title( '', false );
		}

		if ( is_author() ) {
			return (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() );
		}

		return wp_strip_all_tags( (string) get_the_archive_title() );
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$tag         = 'h2' === $this->prop( $node, 'tag' ) ? 'h2' : 'h1';
		$title       = $ctx->editor ? __( 'Tên danh mục / chuyên mục', 'saha-core' ) : self::title();
		$description = '';

		if ( $this->prop( $node, 'showDescription' ) ) {
			$description = $ctx->editor
				? '<p>' . esc_html__( 'Mô tả danh mục (nhập ở Sản phẩm → Danh mục).', 'saha-core' ) . '</p>'
				: wp_kses_post( wpautop( (string) get_the_archive_description() ) );
		}

		if ( '' === $title ) {
			return '';
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-archive-title' ) ) . '>'
			. '<' . $tag . ' class="saha-heading saha-archive-title__text">' . esc_html( $title ) . '</' . $tag . '>'
			. ( '' !== trim( $description ) ? '<div class="saha-archive-title__desc saha-prose">' . $description . '</div>' : '' )
			. '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'text-align', $node->prop( 'align' ) );
		$css->typography( ' .saha-archive-title__text', $node->prop( 'typography' ) );
	}
}
