<?php
/**
 * Thanh "Thêm vào giỏ" dính ở trang sản phẩm.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * StickyCart (TECHNICAL-DESIGN §10): hiện khi nút mua trên trang đã cuộn khỏi màn hình.
 *
 * Thanh **điều khiển form gốc** (không nhân bản logic giỏ hàng): "Thêm vào giỏ" bấm nút
 * gốc của WooCommerce (kiểm tồn kho, số lượng, biến thể như thường); sản phẩm biến thể chưa
 * chọn loại → cuộn về form. Không bán trực tuyến (catalogue) → nút "Yêu cầu báo giá".
 */
final class StickyCart {

	public const HANDLE = 'saha-sticky-cart';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render' ), 8 );
	}

	/**
	 * Bật ở trang hiện tại.
	 */
	private static function active(): bool {
		return function_exists( 'is_product' ) && is_product() && current_theme_supports( 'saha-theme-options' ) && (bool) ThemeOptions::get( 'shop.sticky_cart', false );
	}

	/**
	 * CSS/JS.
	 */
	public function enqueue(): void {
		if ( ! self::active() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, SAHA_CORE_URL . 'public/assets/css/sticky-cart.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			SAHA_CORE_URL . 'public/assets/js/sticky-cart.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * In thanh.
	 */
	public function render(): void {
		if ( ! self::active() ) {
			return;
		}

		$product = wc_get_product( (int) get_queried_object_id() );

		if ( ! $product instanceof \WC_Product ) {
			return;
		}

		$buy   = $product->is_purchasable() && $product->is_in_stock();
		$image = (string) $product->get_image( 'thumbnail', array( 'class' => 'saha-sticky-cart__img', 'alt' => '' ) );

		if ( $buy ) {
			$actions = '<button type="button" class="saha-btn saha-btn--outline saha-sticky-cart__add" data-saha-sticky-add>' . esc_html__( 'Thêm vào giỏ', 'saha-core' ) . '</button>';

			if ( ! $product->is_type( 'external' ) && (bool) apply_filters( 'saha_buy_now_enabled', ! CatalogMode::enabled(), $product ) ) {
				$actions .= '<button type="button" class="saha-btn saha-btn--accent saha-sticky-cart__buy" data-saha-sticky-buy>' . esc_html__( 'Mua ngay', 'saha-core' ) . '</button>';
			}
		} else {
			do_action( 'saha_quote_modal_needed' );
			$actions = sprintf(
				'<button type="button" class="saha-btn saha-btn--accent" data-saha-open-quote="1" data-saha-product-id="%1$d" data-saha-product-name="%2$s" data-saha-sku="%3$s">%4$s</button>',
				(int) $product->get_id(),
				esc_attr( $product->get_name() ),
				esc_attr( (string) $product->get_sku() ),
				esc_html__( 'Yêu cầu báo giá', 'saha-core' )
			);
		}

		printf(
			'<div class="saha-sticky-cart%1$s" data-saha-sticky-cart role="region" aria-label="%2$s" hidden><div class="saha-sticky-cart__inner">%3$s<div class="saha-sticky-cart__info"><span class="saha-sticky-cart__name">%4$s</span><span class="saha-sticky-cart__price">%5$s</span></div><div class="saha-sticky-cart__actions">%6$s</div></div></div>',
			$buy ? '' : ' saha-sticky-cart--quote',
			esc_attr__( 'Mua nhanh', 'saha-core' ),
			$image, // phpcs:ignore WordPress.Security.EscapeOutput -- WC_Product::get_image đã escape.
			esc_html( $product->get_name() ),
			wp_kses_post( $product->get_price_html() ),
			$actions // phpcs:ignore WordPress.Security.EscapeOutput -- từng phần đã escape.
		);
	}
}
