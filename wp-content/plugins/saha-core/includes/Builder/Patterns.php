<?php
/**
 * Khối mẫu (section dựng sẵn) cho bảng "Thêm" của SAHA Builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Patterns — mỗi khối mẫu là một section hoàn chỉnh, lấy từ bộ mẫu trang chủ có sẵn
 * (StoreKit kiểu cửa hàng, Starter cơ bản) để giao diện khối mẫu và trang chủ mẫu luôn khớp nhau.
 *
 * Dữ liệu động (ảnh sản phẩm mới nhất, link shop, danh mục) tính lúc editor mở. Mỗi khối được
 * chuẩn hoá qua Sanitizer (đủ ID, prop hợp lệ) trước khi gửi cho editor; editor chèn bản sao với ID mới.
 *
 * Mở rộng: filter `saha_builder_patterns` (thêm / bớt khối).
 */
final class Patterns {

	/**
	 * Khối mẫu kiểu cửa hàng — theo thứ tự section của StoreKit::homepage().
	 *
	 * @return array<int, array{id: string, name: string, icon: string, description: string}>
	 */
	private static function storeMeta(): array {
		return array(
			array(
				'id'          => 'store-hero',
				'name'        => __( 'Hero + ảnh trượt', 'saha-core' ),
				'icon'        => 'cover-image',
				'description' => __( 'Tiêu đề lớn, ưu điểm, 2 nút, ảnh trượt bên phải.', 'saha-core' ),
			),
			array(
				'id'          => 'store-categories',
				'name'        => __( 'Danh mục sản phẩm', 'saha-core' ),
				'icon'        => 'category',
				'description' => __( 'Tiêu đề khối + danh sách danh mục dạng thẻ.', 'saha-core' ),
			),
			array(
				'id'          => 'store-products',
				'name'        => __( 'Sản phẩm nổi bật (tab)', 'saha-core' ),
				'icon'        => 'products',
				'description' => __( 'Lưới sản phẩm có tab lọc theo danh mục.', 'saha-core' ),
			),
			array(
				'id'          => 'store-promos',
				'name'        => __( 'Ba ô quảng bá', 'saha-core' ),
				'icon'        => 'images-alt2',
				'description' => __( '3 banner cạnh nhau, mỗi ô một nút.', 'saha-core' ),
			),
			array(
				'id'          => 'store-reasons',
				'name'        => __( 'Vì sao chọn chúng tôi', 'saha-core' ),
				'icon'        => 'awards',
				'description' => __( 'Lưới 8 ô icon + tiêu đề + mô tả ngắn.', 'saha-core' ),
			),
			array(
				'id'          => 'store-solutions',
				'name'        => __( 'Giải pháp theo nhu cầu', 'saha-core' ),
				'icon'        => 'lightbulb',
				'description' => __( 'Danh mục ứng dụng / nhu cầu dạng thẻ.', 'saha-core' ),
			),
			array(
				'id'          => 'store-brands',
				'name'        => __( 'Thương hiệu', 'saha-core' ),
				'icon'        => 'tag',
				'description' => __( 'Logo / thẻ thương hiệu.', 'saha-core' ),
			),
			array(
				'id'          => 'store-news-reviews',
				'name'        => __( 'Tin tức + khách hàng nói', 'saha-core' ),
				'icon'        => 'testimonial',
				'description' => __( 'Bài viết mới bên trái, đánh giá mẫu bên phải (thay bằng đánh giá thật).', 'saha-core' ),
			),
			array(
				'id'          => 'store-faq',
				'name'        => __( 'Câu hỏi thường gặp', 'saha-core' ),
				'icon'        => 'editor-help',
				'description' => __( 'Accordion hỏi đáp (có dữ liệu FAQ cho Google).', 'saha-core' ),
			),
		);
	}

	/**
	 * Toàn bộ khối mẫu (chưa chuẩn hoá).
	 *
	 * @return array<int, array{id: string, name: string, group: string, icon: string, description: string, node: array<string, mixed>}>
	 */
	public static function all(): array {
		$patterns = array();
		$store    = StoreKit::homepage()['elements'];

		foreach ( self::storeMeta() as $i => $meta ) {
			if ( isset( $store[ $i ] ) && 'section' === ( $store[ $i ]['type'] ?? '' ) ) {
				$patterns[] = $meta + array(
					'group' => 'store',
					'node'  => $store[ $i ],
				);
			}
		}

		// Starter cơ bản: hero có ô tìm kiếm (luôn là section đầu) + CTA báo giá cuối trang (section cuối).
		$basic = Starter::homepage()['elements'];
		$first = $basic[0] ?? null;
		$last  = $basic ? $basic[ count( $basic ) - 1 ] : null;

		if ( $first && 'section' === $first['type'] ) {
			$patterns[] = array(
				'id'          => 'basic-search-hero',
				'name'        => __( 'Hero + ô tìm kiếm', 'saha-core' ),
				'group'       => 'basic',
				'icon'        => 'search',
				'description' => __( 'Tiêu đề lớn, lời dẫn, ô tìm theo tên / mã sản phẩm.', 'saha-core' ),
				'node'        => $first,
			);
		}

		if ( $last && 'section' === $last['type'] && $last !== $first ) {
			$patterns[] = array(
				'id'          => 'basic-quote-cta',
				'name'        => __( 'Kêu gọi báo giá', 'saha-core' ),
				'group'       => 'basic',
				'icon'        => 'megaphone',
				'description' => __( 'Dải CTA nền màu, nút Yêu cầu báo giá.', 'saha-core' ),
				'node'        => $last,
			);
		}

		$patterns[] = array(
			'id'          => 'basic-media-text',
			'name'        => __( 'Ảnh + nội dung (2 cột)', 'saha-core' ),
			'group'       => 'basic',
			'icon'        => 'align-pull-left',
			'description' => __( 'Ảnh bên trái, tiêu đề + đoạn văn + nút bên phải.', 'saha-core' ),
			'node'        => self::mediaText(),
		);

		/**
		 * Lọc danh sách khối mẫu của builder.
		 *
		 * @param array<int, array<string, mixed>> $patterns Khối mẫu: id, name, group, icon, description, node.
		 */
		return (array) apply_filters( 'saha_builder_patterns', $patterns );
	}

	/**
	 * Khối "Ảnh + nội dung".
	 *
	 * @return array<string, mixed>
	 */
	private static function mediaText(): array {
		return array(
			'type'     => 'section',
			'advanced' => array(
				'padding' => array(
					'desktop' => array(
						'top'    => '56px',
						'bottom' => '56px',
					),
					'mobile'  => array(
						'top'    => '32px',
						'bottom' => '32px',
					),
				),
			),
			'children' => array(
				array(
					'type'     => 'row',
					'props'    => array( 'gap' => array( 'desktop' => '40px' ) ),
					'children' => array(
						array(
							'type'     => 'column',
							'props'    => array( 'width' => array( 'desktop' => '50%' ) ),
							'children' => array(
								array(
									'type'  => 'image',
									'props' => array(),
								),
							),
						),
						array(
							'type'     => 'column',
							'props'    => array(
								'width'         => array( 'desktop' => '50%' ),
								'verticalAlign' => 'center',
							),
							'children' => array(
								array(
									'type'  => 'heading',
									'props' => array(
										'text' => __( 'Tiêu đề giới thiệu', 'saha-core' ),
										'tag'  => 'h2',
									),
								),
								array(
									'type'  => 'text',
									'props' => array(
										'content' => '<p>' . esc_html__( 'Viết vài câu giới thiệu sản phẩm, dịch vụ hoặc thế mạnh của bạn.', 'saha-core' ) . '</p>',
									),
								),
								array(
									'type'  => 'button',
									'props' => array(
										'text' => __( 'Tìm hiểu thêm', 'saha-core' ),
										'link' => array( 'url' => '' ),
									),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Khối mẫu đã chuẩn hoá cho editor. Khối không hợp lệ (ví dụ element bị tắt) bị bỏ.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function forClient(): array {
		$sanitizer = new Sanitizer();
		$out       = array();

		foreach ( self::all() as $pattern ) {
			if ( ! is_array( $pattern['node'] ?? null ) ) {
				continue;
			}

			$result = $sanitizer->document(
				array(
					'version'  => 1,
					'elements' => array( $pattern['node'] ),
				),
				'root'
			);

			if ( $result['errors'] || ! $result['document'] ) {
				continue;
			}

			$node = $result['document']->toArray()['elements'][0] ?? null;

			if ( ! $node ) {
				continue;
			}

			$out[] = array(
				'id'          => sanitize_key( (string) $pattern['id'] ),
				'name'        => (string) $pattern['name'],
				'group'       => (string) ( $pattern['group'] ?? 'basic' ),
				'icon'        => (string) ( $pattern['icon'] ?? 'layout' ),
				'description' => (string) ( $pattern['description'] ?? '' ),
				'node'        => $node,
			);
		}

		return $out;
	}
}
