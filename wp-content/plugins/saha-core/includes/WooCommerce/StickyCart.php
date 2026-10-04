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
			// Điện thoại: chỉ hiện biểu tượng giỏ (chữ vẫn là tên nút cho trình đọc màn hình).
			$cart_icon = '<svg class="saha-sticky-cart__icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M3 4h2l2.4 11h11.2L21 7H6.2"/></svg>';
			$actions   = '<button type="button" class="saha-btn saha-btn--outline saha-sticky-cart__add" data-saha-sticky-add>' . $cart_icon . '<span class="saha-sticky-cart__label">' . esc_html__( 'Thêm vào giỏ', 'saha-core' ) . '</span></button>';

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

		// Điện thoại: thanh này thay thanh "Gọi · Báo giá" của theme → giữ nút gọi.
		$phone = function_exists( 'saha_hotline' ) ? saha_hotline() : '';
		$tel   = '' !== $phone && function_exists( 'saha_tel_href' ) ? saha_tel_href( $phone ) : '';

		if ( '' !== $tel ) {
			$image .= sprintf(
				'<a class="saha-sticky-cart__call" href="%1$s" aria-label="%2$s" data-saha-event="click_phone"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/></svg></a>',
				esc_url( $tel ),
				/* translators: %s: số điện thoại */
				esc_attr( sprintf( __( 'Gọi %s', 'saha-core' ), $phone ) )
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
