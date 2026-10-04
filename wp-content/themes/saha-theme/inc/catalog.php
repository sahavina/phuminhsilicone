<?php
/**
 * Lớp trình bày catalogue: sản phẩm, báo giá, thương hiệu, bộ lọc, CTA mobile.
 *
 * Chuyển từ flatsome-child (đã QA Phase 2–8) sang saha-theme ở mốc 1.6 — giữ
 * nguyên template part, shortcode, JS, CSS; chỉ đổi nơi nạp. Logic ẩn giá / không
 * cho mua (chế độ catalogue) nằm ở saha-core (WooCommerce\CatalogMode).
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/*
 * ---------------------------------------------------------------------------
 * Asset: đăng ký hết, nạp theo trang (spec §73)
 * ---------------------------------------------------------------------------
 */
add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css = array(
			'saha-catalog-main'       => 'catalog-main',
			'saha-catalog-responsive' => 'catalog-responsive',
			'saha-catalog-product'    => 'catalog-product',
			'saha-catalog-brand'      => 'catalog-brand',
			'saha-catalog-search'     => 'catalog-search',
			'saha-catalog-form'       => 'catalog-form',
			'saha-catalog-sections'   => 'catalog-sections',
		);

		foreach ( $css as $handle => $file ) {
			$relative = 'assets/css/' . $file . '.css';
			wp_register_style( $handle, SAHA_THEME_URI . '/' . $relative, array( 'saha-theme' ), (string) filemtime( SAHA_THEME_DIR . '/' . $relative ) );
		}

		$js = array(
			'saha-catalog-main'           => 'catalog-main',
			'saha-catalog-search'         => 'catalog-search',
			'saha-catalog-product-filter' => 'catalog-product-filter',
			'saha-catalog-quote-form'     => 'catalog-quote-form',
		);

		foreach ( $js as $handle => $file ) {
			$relative = 'assets/js/' . $file . '.js';
			wp_register_script(
				$handle,
				SAHA_THEME_URI . '/' . $relative,
				'saha-catalog-main' === $handle ? array() : array( 'saha-catalog-main' ),
				(string) filemtime( SAHA_THEME_DIR . '/' . $relative ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
		}

		// Luôn cần: hotline, modal báo giá, CTA mobile, form.
		wp_enqueue_style( 'saha-catalog-main' );
		wp_enqueue_style( 'saha-catalog-responsive' );
		wp_enqueue_style( 'saha-catalog-form' );
		wp_enqueue_script( 'saha-catalog-main' );
		wp_localize_script( 'saha-catalog-main', 'SAHA_CONFIG', saha_theme_script_config() );

		$is_wc = function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() );

		// Trang dựng bằng builder có thể chứa lưới sản phẩm (thẻ có thương hiệu + SKU).
		if ( $is_wc || ( is_singular() && saha_theme_builder_active() ) ) {
			wp_enqueue_style( 'saha-catalog-product' );
		}

		if ( is_singular( 'product' ) || is_tax( array( 'product_brand', 'product_application' ) ) || saha_theme_content_has_shortcode( 'saha_brand_grid' ) ) {
			wp_enqueue_style( 'saha-catalog-brand' );
		}

		if ( is_search() ) {
			wp_enqueue_style( 'saha-catalog-search' );
		}

		// Trang dựng bằng shortcode cũ (khối sản phẩm, lưới danh mục…).
		if ( is_singular() && false !== strpos( (string) get_post_field( 'post_content', get_the_ID() ), '[saha_' ) ) {
			wp_enqueue_style( 'saha-catalog-sections' );
			wp_enqueue_style( 'saha-catalog-product' );
			wp_enqueue_style( 'saha-catalog-brand' );
		}
	},
	20
);

if ( ! function_exists( 'saha_theme_script_config' ) ) {
	/**
	 * Cấu hình cho JS. Không hardcode domain, không lộ dữ liệu nội bộ.
	 *
	 * @return array<string, mixed>
	 */
	function saha_theme_script_config(): array {
		$config = array(
			'restUrl'  => function_exists( 'saha_api_url' ) ? saha_api_url() : rest_url( 'saha/v1/' ),
			'nonce'    => function_exists( 'saha_public_nonce' ) ? saha_public_nonce() : '',
			'locale'   => get_locale(),
			'zaloUrl'  => saha_theme_zalo_url(),
			'quoteUrl' => function_exists( 'saha_quote_page_url' ) ? saha_quote_page_url() : '',
			'hotlines' => saha_theme_hotlines(),
			'i18n'     => array(
				'loading'   => __( 'Đang tải…', 'saha' ),
				'error'     => __( 'Có lỗi xảy ra, vui lòng thử lại.', 'saha' ),
				'noResult'  => __( 'Không tìm thấy sản phẩm phù hợp.', 'saha' ),
				'submitted' => __( 'Đã gửi yêu cầu. Chúng tôi sẽ liên hệ sớm nhất.', 'saha' ),
			),
		);

		/**
		 * Lọc config JS.
		 *
		 * @param array<string, mixed> $config Config.
		 */
		return (array) apply_filters( 'saha_theme_script_config', $config );
	}
}

/*
 * ---------------------------------------------------------------------------
 * CTA dính trên mobile: Gọi · Zalo · Báo giá (spec §17)
 * ---------------------------------------------------------------------------
 */
if ( ! function_exists( 'saha_theme_mobile_cta_enabled' ) ) {
	/**
	 * Có hiện CTA dính mobile không.
	 */
	function saha_theme_mobile_cta_enabled(): bool {
		/**
		 * Bật/tắt CTA dính mobile.
		 *
		 * @param bool $enabled Trạng thái.
		 */
		return (bool) apply_filters( 'saha_theme_mobile_cta_enabled', ! is_404() && ! ( function_exists( 'is_checkout' ) && ( is_checkout() || is_cart() ) ) );
	}
}

add_filter(
	'body_class',
	static function ( array $classes ): array {
		// Chừa chỗ cho thanh CTA dính (catalog-responsive.css).
		if ( saha_theme_mobile_cta_enabled() ) {
			$classes[] = 'saha-has-sticky-cta';
		}

		return $classes;
	}
);

add_action(
	'wp_footer',
	static function (): void {
		if ( saha_theme_mobile_cta_enabled() ) {
			saha_theme_part( 'common/sticky-cta' );
		}
	},
	20
);

/*
 * ---------------------------------------------------------------------------
 * Bài viết: bài liên quan cuối bài (spec §21)
 * ---------------------------------------------------------------------------
 */
add_action(
	'saha_after_content',
	static function (): void {
		if ( ! is_singular( 'post' ) || ! function_exists( 'saha_related_post_ids' ) || ! saha_theme_setting( 'blog_related_posts', true ) ) {
			return;
		}

		echo '<div class="saha-container saha-container--content">';
		saha_theme_part( 'blog/related', array( 'ids' => saha_related_post_ids( (int) get_queried_object_id(), 3 ) ) );
		echo '</div>';
	}
);

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/*
 * ---------------------------------------------------------------------------
 * Thẻ sản phẩm (loop): thương hiệu + mã SKU (spec §53)
 * ---------------------------------------------------------------------------
 */
add_action(
	'woocommerce_before_shop_loop_item_title',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product || ! function_exists( 'saha_get_product_brand' ) ) {
			return;
		}

		$brand = saha_get_product_brand( $product->get_id() );

		if ( ! empty( $brand['name'] ) ) {
			printf( '<div class="saha-product-card__brand">%s</div>', esc_html( (string) $brand['name'] ) );
		}
	},
	15
);

add_action(
	'woocommerce_after_shop_loop_item_title',
	static function (): void {
		global $product;

		if ( ! $product instanceof WC_Product || '' === (string) $product->get_sku() ) {
			return;
		}

		$sku = (string) $product->get_sku();

		// SKU nhập từ đường dẫn sản phẩm ("keo-cha-ron-epoxy-…") hoặc quá dài: không giúp khách,
		// chỉ làm thẻ dài 3–4 dòng → không in trên thẻ (trang chi tiết vẫn hiện).
		$is_slug = 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+){2,}$/', $sku ) || $sku === get_post_field( 'post_name', $product->get_id() );

		if ( ! (bool) apply_filters( 'saha_theme_card_show_sku', ! $is_slug && strlen( $sku ) <= 24, $product ) ) {
			return;
		}

		printf(
			'<div class="saha-product-card__sku"><span class="saha-label">%1$s</span> <span class="saha-value">%2$s</span></div>',
			esc_html__( 'Mã:', 'saha' ),
			esc_html( (string) $product->get_sku() )
		);
	},
	9
);

/*
 * ---------------------------------------------------------------------------
 * Trang sản phẩm: meta, CTA báo giá, thông số/tài liệu, cùng thương hiệu
 * ---------------------------------------------------------------------------
 */
add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/meta', array( 'product_id' => get_the_ID() ) );
	},
	6
);

add_action(
	'woocommerce_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/cta', array( 'product_id' => get_the_ID() ) );
	},
	35
);

add_action(
	'woocommerce_after_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/sections', array( 'product_id' => get_the_ID() ) );
	},
	12
);

add_action(
	'woocommerce_after_single_product_summary',
	static function (): void {
		saha_theme_part( 'product/brand-products', array( 'product_id' => get_the_ID() ) );
	},
	22
);

// Mốc đo xem sản phẩm cho analytics (catalog-main.js).
add_action(
	'woocommerce_before_single_product_summary',
	static function (): void {
		global $product;

		if ( $product instanceof WC_Product ) {
			printf( '<span class="saha-visually-hidden" data-saha-product-view="%1$d" data-saha-sku="%2$s"></span>', absint( $product->get_id() ), esc_attr( (string) $product->get_sku() ) );
		}
	},
	1
);

/*
 * ---------------------------------------------------------------------------
 * Trang thương hiệu: header riêng (có H1) + nội dung SEO dưới danh sách
 * ---------------------------------------------------------------------------
 */
add_action(
	'woocommerce_archive_description',
	static function (): void {
		$term = get_queried_object();

		if ( is_tax( 'product_brand' ) && $term instanceof WP_Term && function_exists( 'saha_get_brand' ) ) {
			saha_theme_part( 'brand/header', array( 'brand' => saha_get_brand( $term ) ) );
		}
	},
	5
);

add_action(
	'woocommerce_after_main_content',
	static function (): void {
		$term = get_queried_object();

		if ( ! is_tax( 'product_brand' ) || ! $term instanceof WP_Term || ! function_exists( 'saha_get_brand' ) || get_query_var( 'paged' ) > 1 ) {
			return;
		}

		$seo = (string) ( saha_get_brand( $term )['seo_content'] ?? '' );

		if ( '' !== trim( $seo ) ) {
			printf( '<div class="saha-brand-seo-content">%s</div>', wp_kses_post( wpautop( $seo ) ) );
		}
	},
	5
);

// Trang thương hiệu đã có H1 trong header riêng (tránh 2 H1 — spec §22).
add_filter(
	'woocommerce_show_page_title',
	static function ( $show ) {
		return is_tax( 'product_brand' ) ? false : $show;
	}
);

/*
 * ---------------------------------------------------------------------------
 * Archive: bộ lọc + trạng thái rỗng (spec §82)
 * ---------------------------------------------------------------------------
 */
add_action(
	'woocommerce_before_shop_loop',
	static function (): void {
		if ( ( is_shop() || is_product_taxonomy() || is_search() ) && apply_filters( 'saha_theme_show_archive_filter', true ) ) {
			wp_enqueue_script( 'saha-catalog-product-filter' );
			saha_theme_part( 'common/filter' );
		}
	},
	15
);

// Cột lọc bên trái (element "Danh sách sản phẩm" bố cục Cột lọc của saha-core).
add_action(
	'saha_product_archive_sidebar',
	static function ( $args ): void {
		$args = (array) $args;
		$term = $args['term'] ?? null;

		if ( ! apply_filters( 'saha_theme_show_archive_filter', true ) ) {
			return;
		}

		wp_enqueue_script( 'saha-catalog-product-filter' );
		saha_theme_part(
			'common/filter',
			array(
				'layout'       => 'sidebar',
				'price_ranges' => ! empty( $args['price'] ) && class_exists( 'Saha\Core\Filter' ) && ! saha_theme_catalogue_mode()
					? Saha\Core\Filter::price_ranges( $term instanceof WP_Term ? $term : null )
					: array(),
			)
		);
	}
);

add_action(
	'woocommerce_no_products_found',
	static function (): void {
		$filters = function_exists( 'saha_filter_current' ) ? saha_filter_current() : array();

		saha_theme_part(
			'common/empty-state',
			array(
				'title'       => is_search() ? __( 'Không tìm thấy sản phẩm phù hợp', 'saha' ) : __( 'Chưa có sản phẩm trong mục này', 'saha' ),
				'description' => $filters ? __( 'Thử bỏ bớt điều kiện lọc, hoặc tìm theo mã sản phẩm.', 'saha' ) : __( 'Thử tìm theo tên hoặc mã sản phẩm, hoặc chọn danh mục khác.', 'saha' ),
				'show_search' => true,
				'show_cats'   => true,
			)
		);
	},
	5
);

add_action(
	'init',
	static function (): void {
		remove_action( 'woocommerce_no_products_found', 'wc_no_products_found', 10 );
	},
	20
);

/*
 * ---------------------------------------------------------------------------
 * Modal báo giá: chỉ ở trang sản phẩm và trang có khối báo giá (spec §73)
 * ---------------------------------------------------------------------------
 */
add_action(
	'wp_footer',
	static function (): void {
		if ( is_singular( 'product' ) ) {
			wp_enqueue_script( 'saha-catalog-quote-form' );
			saha_theme_part( 'quote/modal', array( 'product_id' => get_the_ID() ) );
			return;
		}

		// Shortcode cũ, hoặc CTA của builder có nút "Mở form báo giá".
		if ( saha_theme_content_has_shortcode( 'saha_quote_cta' ) || did_action( 'saha_quote_modal_needed' ) ) {
			wp_enqueue_script( 'saha-catalog-quote-form' );
			saha_theme_part( 'quote/modal' );
		}
	},
	15
);
