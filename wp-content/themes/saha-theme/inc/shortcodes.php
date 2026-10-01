<?php
/**
 * Shortcode presentation. Chỉ render dữ liệu lấy từ plugin/settings.
 *
 * @package Saha\Theme
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
			array( 'text' => __( 'Chat Zalo', 'saha' ) ),
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
				'title'      => '',
				'subtitle'   => '',
				'view_all'   => '',
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

		wp_enqueue_style( 'saha-brand' );

		if ( '' !== trim( (string) $atts['title'] ) ) {
			wp_enqueue_style( 'saha-sections' );

			saha_theme_part(
				'common/section-heading',
				array(
					'title'        => sanitize_text_field( (string) $atts['title'] ),
					'subtitle'     => sanitize_text_field( (string) $atts['subtitle'] ),
					'view_all_url' => 'none' === $atts['view_all'] ? '' : esc_url_raw( (string) $atts['view_all'] ),
				)
			);
		}

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

/*
 * -------------------------------------------------------------------------
 * PHASE 5 — Khối trang chủ
 *
 * Shortcode chỉ chuẩn hoá tham số rồi gọi service của plugin + template part.
 * Không query trực tiếp (spec §36).
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'saha_theme_view_all_url' ) ) {
	/**
	 * URL "Xem tất cả" mặc định theo nguồn của khối.
	 *
	 * @param array<string, mixed> $atts Tham số shortcode.
	 */
	function saha_theme_view_all_url( array $atts ): string {
		$custom = trim( (string) ( $atts['view_all'] ?? '' ) );

		if ( 'none' === $custom ) {
			return '';
		}

		if ( '' !== $custom ) {
			return esc_url_raw( $custom );
		}

		$source = (string) ( $atts['source'] ?? '' );
		$map    = array(
			'category'    => array( 'product_cat', (string) ( $atts['category'] ?? '' ) ),
			'brand'       => array( 'product_brand', (string) ( $atts['brand'] ?? '' ) ),
			'application' => array( 'product_application', (string) ( $atts['application'] ?? '' ) ),
		);

		if ( isset( $map[ $source ] ) && '' !== $map[ $source ][1] && taxonomy_exists( $map[ $source ][0] ) ) {
			$value = $map[ $source ][1];
			$link  = ctype_digit( $value )
				? get_term_link( (int) $value, $map[ $source ][0] )
				: get_term_link( sanitize_title( $value ), $map[ $source ][0] );

			return is_wp_error( $link ) ? '' : $link;
		}

		if ( 'ids' === $source ) {
			return '';
		}

		return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : '';
	}
}

if ( ! function_exists( 'saha_theme_render_products' ) ) {
	/**
	 * Render khối sản phẩm từ tham số shortcode.
	 *
	 * @param array<string, mixed> $atts Tham số đã qua shortcode_atts.
	 */
	function saha_theme_render_products( array $atts ): string {
		if ( ! saha_theme_has_core() || ! function_exists( 'saha_catalog_product_ids' ) ) {
			return '';
		}

		$ids = saha_catalog_product_ids(
			array(
				'source'            => $atts['source'],
				'category'          => $atts['category'],
				'brand'             => $atts['brand'],
				'application'       => $atts['application'],
				'ids'               => $atts['ids'],
				'limit'             => (int) $atts['limit'],
				'orderby'           => $atts['orderby'],
				'hide_out_of_stock' => '1' === (string) $atts['hide_out_of_stock'],
			)
		);

		ob_start();

		saha_theme_part(
			'product/grid',
			array(
				'ids'            => $ids,
				'columns'        => (int) $atts['columns'],
				'title'          => sanitize_text_field( (string) $atts['title'] ),
				'subtitle'       => sanitize_text_field( (string) $atts['subtitle'] ),
				'view_all_url'   => saha_theme_view_all_url( $atts ),
				'view_all_label' => sanitize_text_field( (string) $atts['view_all_label'] ),
			)
		);

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'saha_theme_products_defaults' ) ) {
	/**
	 * Tham số mặc định của khối sản phẩm.
	 *
	 * @return array<string, string>
	 */
	function saha_theme_products_defaults(): array {
		return array(
			'source'            => 'latest',
			'category'          => '',
			'brand'             => '',
			'application'       => '',
			'ids'               => '',
			'limit'             => '8',
			'columns'           => '4',
			'orderby'           => 'date',
			'hide_out_of_stock' => '0',
			'title'             => '',
			'subtitle'          => '',
			'view_all'          => '',
			'view_all_label'    => '',
		);
	}
}

/**
 * [saha_products source="featured|latest|sale|category|brand|application|ids" category="keo-silicone"
 *                brand="loctite" limit="8" columns="4" title="..." view_all="" (URL | none)]
 *
 * Product Grid (spec §35) — dùng cho: Sản phẩm nổi bật, Sản phẩm mới, Keo Silicone,
 * Keo công nghiệp, PU Foam, Loctite… trên trang chủ.
 */
add_shortcode(
	'saha_products',
	static function ( $atts ): string {
		return saha_theme_render_products(
			shortcode_atts( saha_theme_products_defaults(), is_array( $atts ) ? $atts : array(), 'saha_products' )
		);
	}
);

/**
 * [saha_featured_products limit="8" title="Sản phẩm nổi bật"] — Featured Products (spec §35).
 */
add_shortcode(
	'saha_featured_products',
	static function ( $atts ): string {
		$atts           = shortcode_atts( saha_theme_products_defaults(), is_array( $atts ) ? $atts : array(), 'saha_featured_products' );
		$atts['source'] = 'featured';

		return saha_theme_render_products( $atts );
	}
);

/**
 * [saha_brand_products brand="loctite" limit="8" title="Keo Loctite"] — Brand Products (spec §35).
 */
add_shortcode(
	'saha_brand_products',
	static function ( $atts ): string {
		$atts           = shortcode_atts( saha_theme_products_defaults(), is_array( $atts ) ? $atts : array(), 'saha_brand_products' );
		$atts['source'] = 'brand';

		return saha_theme_render_products( $atts );
	}
);

if ( ! function_exists( 'saha_theme_render_term_grid' ) ) {
	/**
	 * Render grid danh mục / ứng dụng.
	 *
	 * @param string               $taxonomy Taxonomy.
	 * @param array<string, mixed> $atts     Tham số shortcode.
	 */
	function saha_theme_render_term_grid( string $taxonomy, array $atts ): string {
		if ( ! saha_theme_has_core() || ! function_exists( 'saha_catalog_terms' ) ) {
			return '';
		}

		$terms = saha_catalog_terms(
			$taxonomy,
			array(
				'parent'     => $atts['parent'],
				'include'    => $atts['include'],
				'limit'      => (int) $atts['limit'],
				'hide_empty' => '1' === (string) $atts['hide_empty'],
				'orderby'    => $atts['orderby'],
			)
		);

		ob_start();

		saha_theme_part(
			'common/term-grid',
			array(
				'terms'        => $terms,
				'columns'      => (int) $atts['columns'],
				'title'        => sanitize_text_field( (string) $atts['title'] ),
				'subtitle'     => sanitize_text_field( (string) $atts['subtitle'] ),
				'view_all_url' => 'none' === $atts['view_all'] ? '' : esc_url_raw( (string) $atts['view_all'] ),
				'show_count'   => '1' === (string) $atts['show_count'],
				'style'        => $atts['style'],
			)
		);

		return (string) ob_get_clean();
	}
}

if ( ! function_exists( 'saha_theme_term_grid_defaults' ) ) {
	/**
	 * Tham số mặc định grid term.
	 *
	 * @return array<string, string>
	 */
	function saha_theme_term_grid_defaults(): array {
		return array(
			'parent'     => '0',
			'include'    => '',
			'limit'      => '8',
			'columns'    => '4',
			'hide_empty' => '1',
			'orderby'    => 'menu_order',
			'show_count' => '0',
			'style'      => 'image',
			'title'      => '',
			'subtitle'   => '',
			'view_all'   => '',
		);
	}
}

/**
 * [saha_category_grid parent="0" include="12,15" limit="8" columns="4" style="image|chip" title="Danh mục chính"]
 */
add_shortcode(
	'saha_category_grid',
	static function ( $atts ): string {
		return saha_theme_render_term_grid(
			'product_cat',
			shortcode_atts( saha_theme_term_grid_defaults(), is_array( $atts ) ? $atts : array(), 'saha_category_grid' )
		);
	}
);

/**
 * [saha_application_grid limit="6" columns="6" style="chip" title="Ứng dụng"]
 */
add_shortcode(
	'saha_application_grid',
	static function ( $atts ): string {
		return saha_theme_render_term_grid(
			'product_application',
			shortcode_atts( saha_theme_term_grid_defaults(), is_array( $atts ) ? $atts : array(), 'saha_application_grid' )
		);
	}
);

/**
 * [saha_quote_cta title="..." text="..." button="..." show_hotline="1" show_zalo="1" style="primary|light"]
 *
 * Quote CTA (spec §35). Trang có shortcode này sẽ có quote modal.
 */
add_shortcode(
	'saha_quote_cta',
	static function ( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'        => '',
				'text'         => '',
				'button'       => '',
				'show_hotline' => '1',
				'show_zalo'    => '1',
				'style'        => 'primary',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_quote_cta'
		);

		ob_start();

		saha_theme_part(
			'common/quote-cta',
			array(
				'title'        => sanitize_text_field( (string) $atts['title'] ),
				'text'         => sanitize_text_field( (string) $atts['text'] ),
				'button'       => sanitize_text_field( (string) $atts['button'] ),
				'show_hotline' => '1' === (string) $atts['show_hotline'],
				'show_zalo'    => '1' === (string) $atts['show_zalo'],
				'style'        => sanitize_key( (string) $atts['style'] ),
			)
		);

		return (string) ob_get_clean();
	}
);

/**
 * [saha_term_links taxonomy="product_cat|product_brand" limit="8" orderby="count"]
 *
 * Danh sách link cho footer (spec §20): Danh mục, Thương hiệu. Link crawlable (spec §24).
 */
add_shortcode(
	'saha_term_links',
	static function ( $atts ): string {
		if ( ! saha_theme_has_core() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'taxonomy' => 'product_cat',
				'limit'    => '8',
				'orderby'  => 'count',
			),
			is_array( $atts ) ? $atts : array(),
			'saha_term_links'
		);

		$taxonomy = sanitize_key( (string) $atts['taxonomy'] );
		$limit    = max( 1, min( 30, (int) $atts['limit'] ) );

		if ( 'product_brand' === $taxonomy ) {
			$items = saha_get_brands(
				array(
					'number'  => $limit,
					'orderby' => 'count' === $atts['orderby'] ? 'count' : 'name',
					'order'   => 'count' === $atts['orderby'] ? 'DESC' : 'ASC',
				)
			);
		} else {
			$items = saha_catalog_terms(
				in_array( $taxonomy, array( 'product_cat', 'product_application' ), true ) ? $taxonomy : 'product_cat',
				array(
					'limit'   => $limit,
					'orderby' => sanitize_key( (string) $atts['orderby'] ),
				)
			);
		}

		if ( ! $items ) {
			return '';
		}

		$out = '<ul class="saha-term-links">';

		foreach ( $items as $item ) {
			if ( empty( $item['url'] ) ) {
				continue;
			}

			$out .= sprintf(
				'<li><a href="%1$s">%2$s</a></li>',
				esc_url( (string) $item['url'] ),
				esc_html( (string) $item['name'] )
			);
		}

		return $out . '</ul>';
	}
);

/**
 * [saha_social] — Facebook + Zalo từ settings (spec §20, §33).
 */
add_shortcode(
	'saha_social',
	static function (): string {
		$links = array();

		$facebook = (string) saha_theme_setting( 'facebook', '' );

		if ( '' !== $facebook ) {
			$links[] = sprintf(
				'<a class="saha-social__item saha-social__item--facebook" href="%1$s" target="_blank" rel="noopener me">%2$s</a>',
				esc_url( $facebook ),
				esc_html__( 'Facebook', 'saha' )
			);
		}

		$zalo = saha_theme_zalo_url();

		if ( '' !== $zalo ) {
			$links[] = sprintf(
				'<a class="saha-social__item saha-social__item--zalo" href="%1$s" target="_blank" rel="noopener nofollow" data-saha-event="click_zalo">%2$s</a>',
				esc_url( $zalo ),
				esc_html__( 'Zalo', 'saha' )
			);
		}

		return $links ? '<div class="saha-social">' . implode( '', $links ) . '</div>' : '';
	}
);

/**
 * [saha_copyright] — năm hiện tại + tên công ty từ settings, không hardcode năm.
 */
add_shortcode(
	'saha_copyright',
	static function (): string {
		$company = (string) saha_theme_setting( 'company_name', get_bloginfo( 'name' ) );

		return sprintf(
			'<span class="saha-copyright">&copy; %1$s %2$s</span>',
			esc_html( wp_date( 'Y' ) ),
			esc_html( $company )
		);
	}
);
