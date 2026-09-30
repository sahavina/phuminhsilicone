<?php
/**
 * WooCommerce presentation layer.
 *
 * Chế độ catalogue chỉ ẩn giá/giỏ hàng bằng filter — KHÔNG xoá WooCommerce,
 * để bật bán hàng đầy đủ về sau chỉ cần đổi 1 setting (spec §5, §66).
 *
 * @package Flatsome_Child_Saha
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action(
	'init',
	static function (): void {
		if ( ! saha_theme_catalogue_mode() ) {
			return;
		}

		// Ẩn nút thêm vào giỏ ở loop và single.
		add_filter( 'woocommerce_is_purchasable', '__return_false' );

		// Ẩn giá.
		add_filter( 'woocommerce_get_price_html', 'saha_theme_hide_price_html', 20, 2 );

		// Bỏ nút add-to-cart ở loop.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

		// Bỏ form add-to-cart ở single.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
	},
	20
);

if ( ! function_exists( 'saha_theme_hide_price_html' ) ) {
	/**
	 * Thay giá bằng nhãn CTA khi ở chế độ catalogue.
	 *
	 * @param string $price_html HTML giá gốc.
	 * @param mixed  $product    WC_Product.
	 */
	function saha_theme_hide_price_html( string $price_html, $product ): string {
		unset( $product );

		return sprintf(
			'<span class="saha-price-hidden">%s</span>',
			esc_html__( 'Liên hệ báo giá', 'flatsome-child' )
		);
	}
}

/**
 * Thêm SKU vào product card của loop (spec §53) — chỉ khi sản phẩm có SKU.
 */
add_action(
	'woocommerce_after_shop_loop_item_title',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$sku = (string) $product->get_sku();

		if ( '' === $sku ) {
			return;
		}

		printf(
			'<div class="saha-product-card__sku"><span class="saha-label">%1$s</span> <span class="saha-value">%2$s</span></div>',
			esc_html__( 'Mã:', 'flatsome-child' ),
			esc_html( $sku )
		);
	},
	9
);

/**
 * Số sản phẩm mỗi trang của archive — tránh query quá lớn (spec §28).
 */
add_filter(
	'loop_shop_per_page',
	static function ( $per_page ) {
		unset( $per_page );

		/**
		 * Số sản phẩm mỗi trang.
		 *
		 * @param int $per_page Số lượng.
		 */
		return (int) apply_filters( 'saha_theme_products_per_page', 24 );
	},
	20
);

/*
 * -------------------------------------------------------------------------
 * PHASE 2 — Catalogue presentation
 * -------------------------------------------------------------------------
 */

/**
 * Thương hiệu trên product card của loop.
 */
add_action(
	'woocommerce_before_shop_loop_item_title',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product || ! function_exists( 'saha_get_product_brand' ) ) {
			return;
		}

		$brand = saha_get_product_brand( $product->get_id() );

		if ( empty( $brand['name'] ) ) {
			return;
		}

		printf(
			'<div class="saha-product-card__brand">%s</div>',
			esc_html( (string) $brand['name'] )
		);
	},
	15
);

/**
 * Khối meta dưới tên sản phẩm: SKU, thương hiệu, tình trạng, đơn vị.
 */
add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/meta', array( 'product_id' => get_the_ID() ) );
	},
	6
);

/**
 * CTA: hotline + yêu cầu báo giá.
 */
add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/cta', array( 'product_id' => get_the_ID() ) );
	},
	35
);

/**
 * Các khối nội dung kỹ thuật: ứng dụng, thông số, hướng dẫn, lưu ý, tài liệu.
 *
 * Mô tả dài/ngắn vẫn do WooCommerce hiển thị — không render lại để tránh trùng.
 */
add_action(
	'woocommerce_after_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/sections', array( 'product_id' => get_the_ID() ) );
	},
	12
);

/**
 * Sản phẩm khác cùng thương hiệu (spec §16).
 */
add_action(
	'woocommerce_after_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/brand-products', array( 'product_id' => get_the_ID() ) );
	},
	22
);

/**
 * Đánh dấu container sản phẩm để main.js bắn event view_product.
 *
 * @param string[] $classes Class hiện có.
 * @return string[]
 */
add_filter(
	'woocommerce_post_class',
	static function ( array $classes, $product ): array {
		if ( ! is_singular( 'product' ) || ! $product instanceof WC_Product ) {
			return $classes;
		}

		$classes[] = 'saha-product-single';

		return $classes;
	},
	10,
	2
);

add_action(
	'woocommerce_before_single_product_summary',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		printf(
			'<span class="saha-visually-hidden" data-saha-product-view="%1$d" data-saha-sku="%2$s"></span>',
			absint( $product->get_id() ),
			esc_attr( (string) $product->get_sku() )
		);
	},
	1
);

/**
 * Header trang thương hiệu.
 *
 * WooCommerce tự map mọi taxonomy của product sang archive-product.php,
 * nên không cần file taxonomy-product_brand.php riêng.
 */
add_action(
	'woocommerce_archive_description',
	static function (): void {
		if ( ! is_tax( 'product_brand' ) || ! saha_theme_has_core() ) {
			return;
		}

		$saha_term = get_queried_object();

		if ( ! $saha_term instanceof WP_Term ) {
			return;
		}

		saha_theme_part( 'brand/header', array( 'brand' => saha_get_brand( $saha_term ) ) );
	},
	5
);

/**
 * Nội dung SEO đặt DƯỚI danh sách sản phẩm (spec §23).
 */
add_action(
	'woocommerce_after_main_content',
	static function (): void {
		if ( ! is_tax( 'product_brand' ) || ! saha_theme_has_core() ) {
			return;
		}

		$saha_term = get_queried_object();

		if ( ! $saha_term instanceof WP_Term ) {
			return;
		}

		$saha_brand = saha_get_brand( $saha_term );
		$saha_seo   = (string) ( $saha_brand['seo_content'] ?? '' );

		// Chỉ hiển thị ở trang 1 để tránh nội dung trùng khi phân trang.
		if ( '' === trim( $saha_seo ) || get_query_var( 'paged' ) > 1 ) {
			return;
		}

		printf(
			'<div class="saha-brand-seo-content">%s</div>',
			wp_kses_post( wpautop( $saha_seo ) )
		);
	},
	5
);

/**
 * Ẩn tiêu đề archive mặc định của Flatsome trên trang thương hiệu,
 * vì header riêng đã có H1 (tránh 2 H1 — spec §22).
 *
 * @param bool $show Trạng thái hiện tại.
 */
add_filter(
	'woocommerce_show_page_title',
	static function ( $show ) {
		return is_tax( 'product_brand' ) ? false : $show;
	}
);

/**
 * Bộ lọc trên sidebar archive sản phẩm.
 *
 * Dùng hook của WooCommerce thay vì override archive-product.php.
 */
add_action(
	'woocommerce_before_shop_loop',
	static function (): void {
		if ( ! is_shop() && ! is_product_taxonomy() && ! is_search() ) {
			return;
		}

		/**
		 * Bật/tắt bộ lọc trên archive.
		 *
		 * @param bool $enabled Trạng thái.
		 */
		if ( ! apply_filters( 'saha_theme_show_archive_filter', true ) ) {
			return;
		}

		saha_theme_part( 'common/filter' );
	},
	15
);

/**
 * Empty state khi không có sản phẩm nào khớp (spec §82).
 */
add_action(
	'woocommerce_no_products_found',
	static function (): void {
		$saha_filters = saha_theme_has_core() ? saha_filter_current() : array();

		saha_theme_part(
			'common/empty-state',
			array(
				'title'       => is_search()
					? __( 'Không tìm thấy sản phẩm phù hợp', 'flatsome-child' )
					: __( 'Chưa có sản phẩm trong mục này', 'flatsome-child' ),
				'description' => $saha_filters
					? __( 'Thử bỏ bớt điều kiện lọc, hoặc tìm theo mã sản phẩm.', 'flatsome-child' )
					: __( 'Thử tìm theo tên hoặc mã sản phẩm, hoặc chọn danh mục khác.', 'flatsome-child' ),
				'show_search' => true,
				'show_cats'   => true,
			)
		);
	},
	5
);

/**
 * Bỏ empty state mặc định của WooCommerce để không hiện 2 thông báo.
 */
add_action(
	'init',
	static function (): void {
		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
	},
	20
);

/**
 * Quote modal: chỉ render ở trang sản phẩm và trang có khối Quote CTA,
 * không phải mọi trang (spec §73).
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( is_singular( 'product' ) ) {
			saha_theme_part( 'quote/modal', array( 'product_id' => get_the_ID() ) );
			return;
		}

		if ( saha_theme_content_has_shortcode( 'saha_quote_cta' ) ) {
			saha_theme_part( 'quote/modal' );
		}
	},
	15
);

/*
 * -------------------------------------------------------------------------
 * PHASE 6 — Breadcrumb một nguồn (spec §71)
 * -------------------------------------------------------------------------
 */

if ( ! function_exists( 'woocommerce_breadcrumb' ) ) {
	/**
	 * Override hàm pluggable của WooCommerce.
	 *
	 * WooCommerce khai báo woocommerce_breadcrumb() trong `if ( ! function_exists() )`
	 * và chỉ nạp template functions ở after_setup_theme — sau khi child theme đã
	 * nạp — nên đây là cách override chính thức, không sửa core.
	 *
	 * Mọi chỗ Flatsome/WooCommerce gọi breadcrumb sẽ đi qua đây và chỉ render
	 * MỘT nguồn: Rank Math → Yoast → WooCommerce. Khi dùng Rank Math/Yoast,
	 * BreadcrumbList schema của WooCommerce cũng không được sinh (nó được bắn
	 * trong luồng WooCommerce bên dưới), tránh schema trùng.
	 *
	 * @param array<string, mixed> $args Tham số breadcrumb của WooCommerce.
	 */
	function woocommerce_breadcrumb( $args = array() ) {
		$provider = function_exists( 'saha_breadcrumb_provider' ) ? saha_breadcrumb_provider() : 'woocommerce';

		if ( 'rank_math' === $provider ) {
			rank_math_the_breadcrumbs();
			return;
		}

		if ( 'yoast' === $provider ) {
			yoast_breadcrumb( '<nav class="woocommerce-breadcrumb saha-breadcrumb" aria-label="' . esc_attr__( 'Đường dẫn', 'flatsome-child' ) . '">', '</nav>' );
			return;
		}

		// Giữ nguyên hành vi mặc định của WooCommerce — dùng text domain
		// "woocommerce" có chủ đích để tái dùng bản dịch sẵn có của WooCommerce.
		$args = wp_parse_args(
			$args,
			apply_filters(
				'woocommerce_breadcrumb_defaults',
				array(
					'delimiter'   => '&nbsp;&#47;&nbsp;',
					'wrap_before' => '<nav class="woocommerce-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'woocommerce' ) . '">',
					'wrap_after'  => '</nav>',
					'before'      => '',
					'after'       => '',
					'home'        => _x( 'Home', 'breadcrumb', 'woocommerce' ),
				)
			)
		);

		$breadcrumbs = new WC_Breadcrumb();

		if ( ! empty( $args['home'] ) ) {
			$breadcrumbs->add_crumb( $args['home'], apply_filters( 'woocommerce_breadcrumb_home_url', home_url() ) );
		}

		$args['breadcrumb'] = $breadcrumbs->generate();

		/**
		 * Hook gốc của WooCommerce — WC_Structured_Data dùng hook này để sinh
		 * BreadcrumbList schema.
		 */
		do_action( 'woocommerce_breadcrumb', $breadcrumbs, $args );

		wc_get_template( 'global/breadcrumb.php', $args );
	}
}
