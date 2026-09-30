<?php
/**
 * UX Builder elements.
 *
 * Nguyên tắc (spec §36): UX element CHỈ render. Dữ liệu lấy từ service của
 * plugin saha-core, không bind SQL trực tiếp vào element.
 *
 * Element nghiệp vụ (Product Grid, Brand Grid, Quote CTA, Featured Products,
 * Brand Products) được bổ sung ở Phase 5 theo đúng khung dưới đây.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

add_action(
	'ux_builder_setup',
	static function (): void {
		if ( ! function_exists( 'add_ux_builder_shortcode' ) ) {
			return;
		}

		add_ux_builder_shortcode(
			'saha_hotline',
			array(
				'name'      => __( 'SAHA Hotline', 'flatsome-child' ),
				'category'  => __( 'SAHA', 'flatsome-child' ),
				'priority'  => 10,
				'options'   => array(
					'region' => array(
						'type'    => 'select',
						'heading' => __( 'Vùng', 'flatsome-child' ),
						'default' => 'all',
						'options' => array(
							'all'   => __( 'Cả hai', 'flatsome-child' ),
							'north' => __( 'Miền Bắc', 'flatsome-child' ),
							'south' => __( 'Miền Nam', 'flatsome-child' ),
						),
					),
					'label'  => array(
						'type'    => 'checkbox',
						'heading' => __( 'Hiện nhãn vùng', 'flatsome-child' ),
						'default' => '1',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_zalo',
			array(
				'name'     => __( 'SAHA Zalo', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 11,
				'options'  => array(
					'text' => array(
						'type'    => 'textfield',
						'heading' => __( 'Nhãn', 'flatsome-child' ),
						'default' => 'Chat Zalo',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_brand_grid',
			array(
				'name'     => __( 'SAHA Brand Grid', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 12,
				'options'  => array(
					'title'      => array(
						'type'    => 'textfield',
						'heading' => __( 'Tiêu đề khối', 'flatsome-child' ),
						'default' => '',
					),
					'view_all'   => array(
						'type'    => 'textfield',
						'heading' => __( 'Link "Xem tất cả"', 'flatsome-child' ),
						'default' => '',
					),
					'number'     => array(
						'type'    => 'textfield',
						'heading' => __( 'Số thương hiệu (0 = tất cả)', 'flatsome-child' ),
						'default' => '0',
					),
					'orderby'    => array(
						'type'    => 'select',
						'heading' => __( 'Sắp xếp theo', 'flatsome-child' ),
						'default' => 'name',
						'options' => array(
							'name'  => __( 'Tên', 'flatsome-child' ),
							'count' => __( 'Số sản phẩm', 'flatsome-child' ),
						),
					),
					'order'      => array(
						'type'    => 'select',
						'heading' => __( 'Thứ tự', 'flatsome-child' ),
						'default' => 'ASC',
						'options' => array(
							'ASC'  => __( 'Tăng dần', 'flatsome-child' ),
							'DESC' => __( 'Giảm dần', 'flatsome-child' ),
						),
					),
					'hide_empty' => array(
						'type'    => 'checkbox',
						'heading' => __( 'Ẩn thương hiệu chưa có sản phẩm', 'flatsome-child' ),
						'default' => '1',
					),
					'show_count' => array(
						'type'    => 'checkbox',
						'heading' => __( 'Hiện số sản phẩm', 'flatsome-child' ),
						'default' => '1',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_search',
			array(
				'name'     => __( 'SAHA Search', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 13,
				'options'  => array(
					'placeholder' => array(
						'type'    => 'textfield',
						'heading' => __( 'Placeholder', 'flatsome-child' ),
						'default' => '',
					),
					'autofocus'   => array(
						'type'    => 'checkbox',
						'heading' => __( 'Tự động focus', 'flatsome-child' ),
						'default' => '0',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_product_filter',
			array(
				'name'     => __( 'SAHA Product Filter', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 14,
				'options'  => array(
					'show_brand'        => array(
						'type'    => 'checkbox',
						'heading' => __( 'Lọc theo thương hiệu', 'flatsome-child' ),
						'default' => '1',
					),
					'show_application'  => array(
						'type'    => 'checkbox',
						'heading' => __( 'Lọc theo ứng dụng', 'flatsome-child' ),
						'default' => '1',
					),
					'show_availability' => array(
						'type'    => 'checkbox',
						'heading' => __( 'Lọc theo tình trạng', 'flatsome-child' ),
						'default' => '1',
					),
					'show_price'        => array(
						'type'    => 'checkbox',
						'heading' => __( 'Lọc theo giá', 'flatsome-child' ),
						'default' => '0',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_quote_form',
			array(
				'name'     => __( 'SAHA Quote Form', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 15,
				'options'  => array(
					'title'      => array(
						'type'    => 'textfield',
						'heading' => __( 'Tiêu đề', 'flatsome-child' ),
						'default' => 'Yêu cầu báo giá',
					),
					'product_id' => array(
						'type'    => 'textfield',
						'heading' => __( 'ID sản phẩm (tuỳ chọn)', 'flatsome-child' ),
						'default' => '0',
					),
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_contact_form',
			array(
				'name'     => __( 'SAHA Contact Form', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 16,
				'options'  => array(
					'title'  => array(
						'type'    => 'textfield',
						'heading' => __( 'Tiêu đề', 'flatsome-child' ),
						'default' => 'Liên hệ tư vấn',
					),
					'source' => array(
						'type'    => 'select',
						'heading' => __( 'Nguồn lead', 'flatsome-child' ),
						'default' => 'contact',
						'options' => array(
							'contact'      => __( 'Form liên hệ', 'flatsome-child' ),
							'landing_page' => __( 'Landing page', 'flatsome-child' ),
							'website'      => __( 'Website', 'flatsome-child' ),
						),
					),
				),
			)
		);

		/*
		 * PHASE 5 — khối trang chủ.
		 */
		$saha_term_select = static function ( string $taxonomy, string $heading ): array {
			return array(
				'type'    => 'select',
				'heading' => $heading,
				'default' => '',
				'config'  => array(
					'placeholder' => __( 'Chọn…', 'flatsome-child' ),
					'termSelect'  => array(
						'post_type'  => $taxonomy,
						'taxonomies' => $taxonomy,
					),
				),
			);
		};

		$saha_heading_options = array(
			'title'          => array(
				'type'    => 'textfield',
				'heading' => __( 'Tiêu đề khối', 'flatsome-child' ),
				'default' => '',
			),
			'subtitle'       => array(
				'type'    => 'textfield',
				'heading' => __( 'Mô tả ngắn', 'flatsome-child' ),
				'default' => '',
			),
			'view_all'       => array(
				'type'        => 'textfield',
				'heading'     => __( 'Link "Xem tất cả"', 'flatsome-child' ),
				'description' => __( 'Để trống = tự động theo nguồn. Nhập "none" để ẩn.', 'flatsome-child' ),
				'default'     => '',
			),
			'view_all_label' => array(
				'type'    => 'textfield',
				'heading' => __( 'Nhãn link', 'flatsome-child' ),
				'default' => '',
			),
		);

		$saha_grid_options = array(
			'limit'             => array(
				'type'    => 'slider',
				'heading' => __( 'Số sản phẩm', 'flatsome-child' ),
				'default' => 8,
				'min'     => 1,
				'max'     => 24,
			),
			'columns'           => array(
				'type'    => 'slider',
				'heading' => __( 'Số cột (desktop)', 'flatsome-child' ),
				'default' => 4,
				'min'     => 2,
				'max'     => 6,
			),
			'orderby'           => array(
				'type'    => 'select',
				'heading' => __( 'Sắp xếp', 'flatsome-child' ),
				'default' => 'date',
				'options' => array(
					'date'       => __( 'Mới nhất', 'flatsome-child' ),
					'popularity' => __( 'Bán chạy', 'flatsome-child' ),
					'menu_order' => __( 'Thứ tự tuỳ chỉnh', 'flatsome-child' ),
					'title'      => __( 'Tên A–Z', 'flatsome-child' ),
					'rand'       => __( 'Ngẫu nhiên (đổi theo chu kỳ cache)', 'flatsome-child' ),
				),
			),
			'hide_out_of_stock' => array(
				'type'    => 'checkbox',
				'heading' => __( 'Ẩn sản phẩm hết hàng', 'flatsome-child' ),
				'default' => '0',
			),
		);

		$saha_sources = function_exists( 'saha_catalog_sources' ) ? saha_catalog_sources() : array( 'latest' => __( 'Sản phẩm mới', 'flatsome-child' ) );

		add_ux_builder_shortcode(
			'saha_products',
			array(
				'name'     => __( 'SAHA Product Grid', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 20,
				'options'  => array_merge(
					$saha_heading_options,
					array(
						'source'      => array(
							'type'    => 'select',
							'heading' => __( 'Nguồn sản phẩm', 'flatsome-child' ),
							'default' => 'latest',
							'options' => $saha_sources,
						),
						'category'    => $saha_term_select( 'product_cat', __( 'Danh mục (lọc thêm)', 'flatsome-child' ) ),
						'brand'       => $saha_term_select( 'product_brand', __( 'Thương hiệu (lọc thêm)', 'flatsome-child' ) ),
						'application' => $saha_term_select( 'product_application', __( 'Ứng dụng', 'flatsome-child' ) ),
						'ids'         => array(
							'type'        => 'textfield',
							'heading'     => __( 'ID sản phẩm (nguồn "Chọn tay")', 'flatsome-child' ),
							'description' => __( 'Ngăn cách bằng dấu phẩy, theo đúng thứ tự muốn hiện.', 'flatsome-child' ),
							'default'     => '',
						),
					),
					$saha_grid_options
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_featured_products',
			array(
				'name'     => __( 'SAHA Featured Products', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 21,
				'options'  => array_merge(
					$saha_heading_options,
					array(
						'category' => $saha_term_select( 'product_cat', __( 'Chỉ trong danh mục', 'flatsome-child' ) ),
						'brand'    => $saha_term_select( 'product_brand', __( 'Chỉ của thương hiệu', 'flatsome-child' ) ),
					),
					$saha_grid_options
				),
			)
		);

		add_ux_builder_shortcode(
			'saha_brand_products',
			array(
				'name'     => __( 'SAHA Brand Products', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 22,
				'options'  => array_merge(
					$saha_heading_options,
					array(
						'brand'    => $saha_term_select( 'product_brand', __( 'Thương hiệu', 'flatsome-child' ) ),
						'category' => $saha_term_select( 'product_cat', __( 'Chỉ trong danh mục', 'flatsome-child' ) ),
					),
					$saha_grid_options
				),
			)
		);

		$saha_term_grid_options = array(
			'title'      => $saha_heading_options['title'],
			'subtitle'   => $saha_heading_options['subtitle'],
			'view_all'   => array(
				'type'    => 'textfield',
				'heading' => __( 'Link "Xem tất cả"', 'flatsome-child' ),
				'default' => '',
			),
			'include'    => array(
				'type'        => 'textfield',
				'heading'     => __( 'Chỉ hiện các ID', 'flatsome-child' ),
				'description' => __( 'Để trống = tự lấy theo cấp cha. Ngăn cách bằng dấu phẩy.', 'flatsome-child' ),
				'default'     => '',
			),
			'parent'     => array(
				'type'        => 'textfield',
				'heading'     => __( 'ID mục cha', 'flatsome-child' ),
				'description' => __( '0 = danh mục cấp cao nhất.', 'flatsome-child' ),
				'default'     => '0',
			),
			'limit'      => array(
				'type'    => 'slider',
				'heading' => __( 'Số mục', 'flatsome-child' ),
				'default' => 8,
				'min'     => 1,
				'max'     => 48,
			),
			'columns'    => array(
				'type'    => 'slider',
				'heading' => __( 'Số cột (desktop)', 'flatsome-child' ),
				'default' => 4,
				'min'     => 2,
				'max'     => 8,
			),
			'style'      => array(
				'type'    => 'select',
				'heading' => __( 'Kiểu hiển thị', 'flatsome-child' ),
				'default' => 'image',
				'options' => array(
					'image' => __( 'Thẻ có ảnh', 'flatsome-child' ),
					'chip'  => __( 'Nhãn gọn (chip)', 'flatsome-child' ),
				),
			),
			'orderby'    => array(
				'type'    => 'select',
				'heading' => __( 'Sắp xếp', 'flatsome-child' ),
				'default' => 'menu_order',
				'options' => array(
					'menu_order' => __( 'Thứ tự tuỳ chỉnh', 'flatsome-child' ),
					'count'      => __( 'Nhiều sản phẩm nhất', 'flatsome-child' ),
					'name'       => __( 'Tên A–Z', 'flatsome-child' ),
				),
			),
			'show_count' => array(
				'type'    => 'checkbox',
				'heading' => __( 'Hiện số sản phẩm', 'flatsome-child' ),
				'default' => '0',
			),
		);

		add_ux_builder_shortcode(
			'saha_category_grid',
			array(
				'name'     => __( 'SAHA Category Grid', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 23,
				'options'  => $saha_term_grid_options,
			)
		);

		add_ux_builder_shortcode(
			'saha_application_grid',
			array(
				'name'     => __( 'SAHA Application Grid', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 24,
				'options'  => $saha_term_grid_options,
			)
		);

		add_ux_builder_shortcode(
			'saha_quote_cta',
			array(
				'name'     => __( 'SAHA Quote CTA', 'flatsome-child' ),
				'category' => __( 'SAHA', 'flatsome-child' ),
				'priority' => 25,
				'options'  => array(
					'title'        => array(
						'type'    => 'textfield',
						'heading' => __( 'Tiêu đề', 'flatsome-child' ),
						'default' => 'Cần báo giá số lượng lớn?',
					),
					'text'         => array(
						'type'    => 'textarea',
						'heading' => __( 'Mô tả', 'flatsome-child' ),
						'default' => '',
					),
					'button'       => array(
						'type'    => 'textfield',
						'heading' => __( 'Nhãn nút', 'flatsome-child' ),
						'default' => '',
					),
					'style'        => array(
						'type'    => 'select',
						'heading' => __( 'Kiểu', 'flatsome-child' ),
						'default' => 'primary',
						'options' => array(
							'primary' => __( 'Nền màu chính', 'flatsome-child' ),
							'light'   => __( 'Nền sáng', 'flatsome-child' ),
						),
					),
					'show_hotline' => array(
						'type'    => 'checkbox',
						'heading' => __( 'Hiện hotline', 'flatsome-child' ),
						'default' => '1',
					),
					'show_zalo'    => array(
						'type'    => 'checkbox',
						'heading' => __( 'Hiện Zalo', 'flatsome-child' ),
						'default' => '1',
					),
				),
			)
		);

		foreach ( array( 'saha_term_links', 'saha_social', 'saha_copyright' ) as $saha_footer_tag ) {
			$saha_footer_names = array(
				'saha_term_links' => __( 'SAHA Footer Links', 'flatsome-child' ),
				'saha_social'     => __( 'SAHA Social', 'flatsome-child' ),
				'saha_copyright'  => __( 'SAHA Copyright', 'flatsome-child' ),
			);

			$saha_footer_options = 'saha_term_links' === $saha_footer_tag
				? array(
					'taxonomy' => array(
						'type'    => 'select',
						'heading' => __( 'Loại', 'flatsome-child' ),
						'default' => 'product_cat',
						'options' => array(
							'product_cat'         => __( 'Danh mục', 'flatsome-child' ),
							'product_brand'       => __( 'Thương hiệu', 'flatsome-child' ),
							'product_application' => __( 'Ứng dụng', 'flatsome-child' ),
						),
					),
					'limit'    => array(
						'type'    => 'slider',
						'heading' => __( 'Số link', 'flatsome-child' ),
						'default' => 8,
						'min'     => 1,
						'max'     => 30,
					),
				)
				: array();

			add_ux_builder_shortcode(
				$saha_footer_tag,
				array(
					'name'     => $saha_footer_names[ $saha_footer_tag ],
					'category' => __( 'SAHA', 'flatsome-child' ),
					'priority' => 30,
					'options'  => $saha_footer_options,
				)
			);
		}

		/**
		 * Cho phép phase sau đăng ký thêm UX element.
		 */
		do_action( 'saha_theme_register_ux_elements' );
	}
);
