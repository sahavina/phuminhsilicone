<?php
/**
 * Element: Tìm kiếm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\Icons;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Search — tìm sản phẩm (theo tên, mã, thương hiệu nhờ Search của saha-core);
 * không có WooCommerce thì tìm bài viết.
 *
 * Kiểu "Mặc định": form của WooCommerce/WordPress (như mốc 1.5).
 * Kiểu "Ô liền nút": form riêng — ô nhập + nút (icon hoặc chữ) màu nhấn dính liền, bo góc.
 * `dynamic`: ô tìm kiếm điền sẵn từ khoá đang tìm.
 */
final class Search extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'           => 'search',
			'name'           => __( 'Tìm kiếm', 'saha-core' ),
			'icon'           => 'search',
			'category'       => 'header',
			'allowedParents' => self::CONTENT_PARENTS,
			'dynamic'        => true,
			'controls'       => array(
				'products'    => array(
					'type'    => 'toggle',
					'label'   => __( 'Tìm sản phẩm (WooCommerce)', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'live'        => array(
					'type'    => 'toggle',
					'label'   => __( 'Gợi ý khi gõ (ảnh, mã, giá)', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'style'       => array(
					'type'    => 'select',
					'label'   => __( 'Kiểu', 'saha-core' ),
					'section' => 'content',
					'default' => 'default',
					'options' => array(
						'default' => __( 'Mặc định', 'saha-core' ),
						'joined'  => __( 'Ô liền nút (nút màu nhấn)', 'saha-core' ),
					),
				),
				'placeholder' => array(
					'type'      => 'text',
					'label'     => __( 'Chữ gợi ý (kiểu Ô liền nút)', 'saha-core' ),
					'section'   => 'content',
					'default'   => __( 'Tìm theo tên, mã sản phẩm, thương hiệu…', 'saha-core' ),
					'maxLength' => 120,
				),
				'buttonText'  => array(
					'type'      => 'text',
					'label'     => __( 'Chữ trên nút (trống = icon kính lúp)', 'saha-core' ),
					'section'   => 'content',
					'maxLength' => 30,
				),
				'width'       => array(
					'type'       => 'size',
					'label'      => __( 'Độ rộng', 'saha-core' ),
					'section'    => 'style',
					'responsive' => true,
					'units'      => array( 'px', '%' ),
					'min'        => 80,
					'max'        => 1200,
				),
				'buttonBg'    => array(
					'type'    => 'color',
					'label'   => __( 'Màu nút', 'saha-core' ),
					'section' => 'style',
				),
				'buttonColor' => array(
					'type'    => 'color',
					'label'   => __( 'Màu icon/chữ trên nút', 'saha-core' ),
					'section' => 'style',
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
		$products = $this->prop( $node, 'products' ) && function_exists( 'get_product_search_form' );
		$live     = '';

		if ( $products && $this->prop( $node, 'live' ) && ! $ctx->editor ) {
			self::enqueueLive();
			$live = ' data-saha-live-search';
		}

		if ( 'joined' !== $this->prop( $node, 'style' ) ) {
			$form = $products ? (string) get_product_search_form( false ) : (string) get_search_form( array( 'echo' => false ) );

			return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-search-el' ) ) . $live . '>' . $form . '</div>';
		}

		$id     = 'saha-s-' . $node->id;
		$text   = trim( (string) $node->prop( 'buttonText', '' ) );
		$button = '' !== $text ? esc_html( $text ) : Icons::svg( 'search' ) . '<span class="screen-reader-text">' . esc_html__( 'Tìm', 'saha-core' ) . '</span>';

		$form = '<form role="search" method="get" class="saha-search-el__form" action="' . esc_url( home_url( '/' ) ) . '">'
			. '<label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Tìm kiếm', 'saha-core' ) . '</label>'
			. '<input type="search" id="' . esc_attr( $id ) . '" class="saha-search-el__input" name="s" value="' . esc_attr( $ctx->editor ? '' : get_search_query() ) . '" placeholder="' . esc_attr( (string) $this->prop( $node, 'placeholder' ) ) . '" autocomplete="off" required>'
			. ( $products ? '<input type="hidden" name="post_type" value="product">' : '' )
			. '<button type="submit" class="saha-search-el__button">' . $button . '</button></form>';

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-search-el', 'saha-search-el--joined' ) ) . $live . '>' . $form . '</div>';
	}

	/**
	 * CSS/JS gợi ý khi gõ — nạp lúc element được in (in ở footer), chỉ trang có ô tìm kiếm.
	 */
	public static function enqueueLive(): void {
		if ( wp_script_is( 'saha-live-search', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style( 'saha-live-search', SAHA_CORE_URL . 'public/assets/css/live-search.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			'saha-live-search',
			SAHA_CORE_URL . 'public/assets/js/live-search.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			'saha-live-search',
			'sahaLiveSearch',
			array(
				'endpoint'  => rest_url( 'saha/v1/search' ),
				'searchUrl' => home_url( '/' ),
				'minLength' => \Saha\Core\Search::MIN_LENGTH,
				'limit'     => 6,
				'i18n'      => array(
					'loading'   => __( 'Đang tìm…', 'saha-core' ),
					'none'      => __( 'Không tìm thấy sản phẩm phù hợp.', 'saha-core' ),
					'error'     => __( 'Không tìm được lúc này. Bấm Enter để tìm.', 'saha-core' ),
					/* translators: %d: số kết quả */
					'count'     => __( '%d gợi ý. Dùng phím mũi tên để chọn.', 'saha-core' ),
					/* translators: %d: tổng số kết quả */
					'all'       => __( 'Xem tất cả %d kết quả', 'saha-core' ),
					'sku'       => __( 'Mã', 'saha-core' ),
					'suggested' => __( 'Gợi ý sản phẩm', 'saha-core' ),
				),
			)
		);
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', 'width', $node->prop( 'width' ) );
		$css->set( ' .saha-search-el__button', 'background-color', $node->prop( 'buttonBg' ) );
		$css->set( ' .saha-search-el__button', 'color', $node->prop( 'buttonColor' ) );
	}
}
