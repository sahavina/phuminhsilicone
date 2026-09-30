<?php
/**
 * Shortcode presentation. Chỉ render dữ liệu lấy từ plugin/settings.
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * [saha_hotline region="north|south|all" label="1"]
 *
 * Hotline lấy từ settings, href tel: chỉ chứa digits (spec §85).
 */
add_shortcode(
	'saha_hotline',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'region' => 'all',
				'label'  => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_hotline'
		);

		$hotlines = saha_theme_hotlines();

		if ( 'all' !== $atts['region'] ) {
			$hotlines = array_values(
				array_filter(
					$hotlines,
					static fn( array $item ): bool => $item['region'] === $atts['region']
				)
			);
		}

		if ( ! $hotlines ) {
			return '';
		}

		$show_label = '1' === (string) $atts['label'];
		$out        = '<span class="saha-hotline-group">';

		foreach ( $hotlines as $hotline ) {
			$out .= sprintf(
				'<a class="saha-hotline" href="%1$s" data-saha-event="click_phone" data-saha-region="%2$s">%3$s<span class="saha-hotline__number">%4$s</span></a>',
				esc_url( $hotline['href'] ),
				esc_attr( $hotline['region'] ),
				$show_label ? '<span class="saha-hotline__label">' . esc_html( $hotline['label'] ) . '</span>' : '',
				esc_html( $hotline['number'] )
			);
		}

		return $out . '</span>';
	}
);

/**
 * [saha_zalo text="Chat Zalo"]
 */
add_shortcode(
	'saha_zalo',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array( 'text' => __( 'Chat Zalo', 'flatsome-child' ) ),
			is_array( $atts ) ? $atts : array(),
			'saha_zalo'
		);

		$url = saha_theme_zalo_url();

		if ( '' === $url ) {
			return '';
		}

		return sprintf(
			'<a class="saha-zalo" href="%1$s" target="_blank" rel="noopener nofollow" data-saha-event="click_zalo">%2$s</a>',
			esc_url( $url ),
			esc_html( (string) $atts['text'] )
		);
	}
);

/**
 * [saha_company field="company_name|address|website|email"]
 *
 * Tránh hardcode thông tin công ty trong UX Builder block.
 */
add_shortcode(
	'saha_company',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array( 'field' => 'company_name' ),
			is_array( $atts ) ? $atts : array(),
			'saha_company'
		);

		$allowed = array( 'company_name', 'site_brand', 'address', 'website', 'email' );
		$field   = sanitize_key( (string) $atts['field'] );

		if ( ! in_array( $field, $allowed, true ) ) {
			return '';
		}

		$value = (string) saha_theme_setting( $field );

		if ( '' === $value ) {
			return '';
		}

		if ( 'website' === $field ) {
			return sprintf( '<a class="saha-company-website" href="%1$s">%2$s</a>', esc_url( $value ), esc_html( $value ) );
		}

		if ( 'email' === $field ) {
			return sprintf( '<a class="saha-company-email" href="mailto:%1$s">%2$s</a>', esc_attr( $value ), esc_html( $value ) );
		}

		return '<span class="saha-company-' . esc_attr( $field ) . '">' . esc_html( $value ) . '</span>';
	}
);

/**
 * [saha_brand_grid number="0" orderby="name" order="ASC" hide_empty="1" show_count="1"]
 *
 * Dùng cho trang /thuong-hieu/ và block "Thương hiệu nổi bật" ở trang chủ.
 * Element chỉ render — dữ liệu lấy từ service của plugin (spec §36).
 */
add_shortcode(
	'saha_brand_grid',
	static function ( $atts ): string {
		if ( ! saha_theme_has_core() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'number'     => '0',
				'orderby'    => 'name',
				'order'      => 'ASC',
				'hide_empty' => '1',
				'show_count' => '1',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_brand_grid'
		);

		$brands = saha_get_brands(
			array(
				'number'     => (int) $atts['number'],
				'orderby'    => sanitize_key( (string) $atts['orderby'] ),
				'order'      => sanitize_key( (string) $atts['order'] ),
				'hide_empty' => '1' === (string) $atts['hide_empty'],
			)
		);

		ob_start();

		saha_theme_part(
			'brand/grid',
			array(
				'brands'     => $brands,
				'show_count' => '1' === (string) $atts['show_count'],
			)
		);

		return (string) ob_get_clean();
	}
);

/**
 * [saha_search placeholder="..." autofocus="0"]
 *
 * Ô tìm kiếm có autocomplete. Tự enqueue asset khi được render.
 */
add_shortcode(
	'saha_search',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'placeholder' => '',
				'autofocus'   => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_search'
		);

		ob_start();

		saha_theme_part(
			'search/form',
			array(
				'placeholder' => sanitize_text_field( (string) $atts['placeholder'] ),
				'autofocus'   => '1' === (string) $atts['autofocus'],
			)
		);

		return (string) ob_get_clean();
	}
);

/**
 * [saha_product_filter show_brand="1" show_application="1" show_availability="1" show_price="0"]
 */
add_shortcode(
	'saha_product_filter',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'show_brand'        => '1',
				'show_application'  => '1',
				'show_availability' => '1',
				'show_price'        => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_product_filter'
		);

		ob_start();

		saha_theme_part(
			'common/filter',
			array(
				'show_brand'        => '1' === (string) $atts['show_brand'],
				'show_application'  => '1' === (string) $atts['show_application'],
				'show_availability' => '1' === (string) $atts['show_availability'],
				'show_price'        => '1' === (string) $atts['show_price'],
			)
		);

		return (string) ob_get_clean();
	}
);

/**
 * [saha_quote_form title="Yêu cầu báo giá" product_id="0"]
 *
 * Dùng cho trang /bao-gia/ hoặc landing page. Tự enqueue form.css + quote-form.js.
 */
add_shortcode(
	'saha_quote_form',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'      => '',
				'product_id' => '0',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_quote_form'
		);

		ob_start();

		saha_theme_part(
			'quote/form',
			array(
				'title'      => sanitize_text_field( (string) $atts['title'] ),
				'product_id' => absint( $atts['product_id'] ),
				'context'    => 'inline',
			)
		);

		return (string) ob_get_clean();
	}
);

/**
 * [saha_contact_form title="Liên hệ tư vấn" source="contact"]
 */
add_shortcode(
	'saha_contact_form',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'  => '',
				'source' => 'contact',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_contact_form'
		);

		ob_start();

		saha_theme_part(
			'contact/form',
			array(
				'title'  => sanitize_text_field( (string) $atts['title'] ),
				'source' => sanitize_key( (string) $atts['source'] ),
			)
		);

		return (string) ob_get_clean();
	}
);
