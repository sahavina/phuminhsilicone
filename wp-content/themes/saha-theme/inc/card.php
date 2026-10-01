<?php
/**
 * Thẻ sản phẩm kiểu "Cửa hàng" (Theme Options → Cửa hàng → Kiểu thẻ sản phẩm).
 *
 * Áp cho mọi lưới WooCommerce: shop, danh mục, element Sản phẩm của builder, liên quan.
 * Chỉ trình bày — giá, mua được hay không, chế độ catalogue vẫn do WooCommerce/saha-core quyết định.
 *
 * @package Saha\Theme
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

if ( ! function_exists( 'saha_theme_store_cards' ) ) {
	/**
	 * Đang dùng thẻ kiểu cửa hàng không.
	 */
	function saha_theme_store_cards(): bool {
		return 'store' === saha_theme_option( 'shop.card_style', 'default' );
	}
}

if ( ! function_exists( 'saha_theme_discount_percent' ) ) {
	/**
	 * % giảm giá lớn nhất của sản phẩm (0 nếu không giảm).
	 *
	 * @param WC_Product $product Sản phẩm.
	 */
	function saha_theme_discount_percent( WC_Product $product ): int {
		$pairs = array();

		if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
			$prices = $product->get_variation_prices( true );

			foreach ( (array) ( $prices['regular_price'] ?? array() ) as $id => $regular ) {
				$pairs[] = array( (float) $regular, (float) ( $prices['sale_price'][ $id ] ?? $regular ) );
			}
		} else {
			$pairs[] = array( (float) $product->get_regular_price(), (float) $product->get_sale_price() );
		}

		$best = 0;

		foreach ( $pairs as list( $regular, $sale ) ) {
			if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
				$best = max( $best, (int) round( ( 1 - $sale / $regular ) * 100 ) );
			}
		}

		return $best;
	}
}

// Body class: CSS thẻ kiểu cửa hàng áp cho mọi lưới sản phẩm trên trang.
add_filter(
	'body_class',
	static function ( array $classes ): array {
		if ( saha_theme_store_cards() ) {
			$classes[] = 'saha-cards-store';
		}

		return $classes;
	}
);

// `init` (không phải `wp`): REST render của builder (canvas) cũng dùng thẻ này.
add_action(
	'init',
	static function (): void {
		if ( ! saha_theme_store_cards() ) {
			return;
		}

		// Nút "Thêm vào giỏ" dạng chữ dưới thẻ → thay bằng nút tròn trên ảnh.
		remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
		add_action( 'woocommerce_after_shop_loop_item', 'saha_theme_card_action', 10 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'saha_theme_card_sold', 15 );
		add_action( 'woocommerce_after_shop_loop_item_title', 'saha_theme_card_specs', 20 );
	},
	30
);

// Nhãn "−41%" thay cho "Sale!".
add_filter(
	'woocommerce_sale_flash',
	static function ( $html, $post, $product ) {
		if ( ! saha_theme_store_cards() || ! $product instanceof WC_Product ) {
			return $html;
		}

		// Chế độ catalogue: không hiện giá → không hiện % giảm.
		if ( saha_theme_catalogue_mode() ) {
			return '';
		}

		$percent = saha_theme_discount_percent( $product );

		return $percent > 0
			? '<span class="onsale saha-card__badge">' . esc_html( '−' . $percent . '%' ) . '</span>'
			: $html;
	},
	10,
	3
);

if ( ! function_exists( 'saha_theme_card_action' ) ) {
	/**
	 * Nút tròn trên ảnh: thêm vào giỏ (AJAX của WooCommerce) / chọn biến thể / yêu cầu báo giá.
	 */
	function saha_theme_card_action(): void {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$name = $product->get_name();

		if ( $product->is_purchasable() && $product->is_in_stock() ) {
			$simple = $product->is_type( 'simple' );

			printf(
				'<a href="%1$s" class="saha-card__action %2$s" data-quantity="1" data-product_id="%3$d" data-product_sku="%4$s" aria-label="%5$s" rel="nofollow">%6$s</a>',
				esc_url( $simple ? $product->add_to_cart_url() : $product->get_permalink() ),
				esc_attr( $simple ? 'button add_to_cart_button ajax_add_to_cart product_type_simple' : 'button product_type_' . $product->get_type() ),
				(int) $product->get_id(),
				esc_attr( (string) $product->get_sku() ),
				/* translators: %s: tên sản phẩm */
				esc_attr( sprintf( $simple ? __( 'Thêm “%s” vào giỏ', 'saha' ) : __( 'Chọn loại cho “%s”', 'saha' ), $name ) ),
				saha_theme_card_icon( 'cart' ) // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh.
			);
			return;
		}

		// Không bán trực tuyến (chế độ catalogue / hết hàng): mở form báo giá có sẵn sản phẩm.
		do_action( 'saha_quote_modal_needed' );

		printf(
			'<button type="button" class="saha-card__action saha-card__action--quote" data-saha-open-quote="1" data-saha-product-id="%1$d" data-saha-product-name="%2$s" data-saha-sku="%3$s" aria-label="%4$s">%5$s</button>',
			(int) $product->get_id(),
			esc_attr( $name ),
			esc_attr( (string) $product->get_sku() ),
			/* translators: %s: tên sản phẩm */
			esc_attr( sprintf( __( 'Yêu cầu báo giá “%s”', 'saha' ), $name ) ),
			saha_theme_card_icon( 'quote' ) // phpcs:ignore WordPress.Security.EscapeOutput -- SVG tĩnh.
		);
	}
}

if ( ! function_exists( 'saha_theme_card_icon' ) ) {
	/**
	 * Icon SVG nhỏ cho nút trên thẻ.
	 *
	 * @param string $name cart | quote.
	 */
	function saha_theme_card_icon( string $name ): string {
		$paths = array(
			'cart'  => '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11h11.2L21 7H6.2"/>',
			'quote' => '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
		);

		return '<svg class="saha-card__icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[ $name ] ?? '' ) . '</svg>';
	}
}

if ( ! function_exists( 'saha_theme_card_sold' ) ) {
	/**
	 * Thanh "Đã bán" — số bán thật (`total_sales` của WooCommerce); tắt mặc định.
	 */
	function saha_theme_card_sold(): void {
		global $product;

		if ( ! $product instanceof WC_Product || ! saha_theme_option( 'shop.card_sold', false ) ) {
			return;
		}

		$sold = (int) $product->get_total_sales();

		if ( $sold <= 0 ) {
			return;
		}

		$goal    = max( 10, (int) saha_theme_option( 'shop.card_sold_goal', 100 ) );
		$percent = min( 100, (int) round( $sold / $goal * 100 ) );

		printf(
			'<div class="saha-card__sold"><span class="saha-card__sold-bar" style="width:%1$d%%"></span><span class="saha-card__sold-text">%2$s</span></div>',
			(int) $percent,
			/* translators: %s: số đã bán */
			esc_html( sprintf( __( 'Đã bán %s', 'saha' ), number_format_i18n( $sold ) ) )
		);
	}
}

if ( ! function_exists( 'saha_theme_card_specs' ) ) {
	/**
	 * Vài dòng thông số (bảng thông số SAHA của sản phẩm).
	 */
	function saha_theme_card_specs(): void {
		global $product;

		$limit = max( 0, min( 4, (int) saha_theme_option( 'shop.card_specs', 3 ) ) );

		if ( ! $product instanceof WC_Product || 0 === $limit || ! function_exists( 'saha_get_product_specs' ) ) {
			return;
		}

		$specs = array_slice( saha_get_product_specs( $product->get_id() ), 0, $limit );

		if ( ! $specs ) {
			return;
		}

		echo '<ul class="saha-card__specs">';

		foreach ( $specs as $spec ) {
			printf( '<li><span>%1$s:</span> %2$s</li>', esc_html( (string) ( $spec['label'] ?? '' ) ), esc_html( (string) ( $spec['value'] ?? '' ) ) );
		}

		echo '</ul>';
	}
}
