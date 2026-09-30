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

		/**
		 * Cho phép phase sau đăng ký thêm UX element.
		 */
		do_action( 'saha_theme_register_ux_elements' );
	}
);
