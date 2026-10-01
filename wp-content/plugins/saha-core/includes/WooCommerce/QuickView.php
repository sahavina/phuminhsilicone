<?php
/**
 * Xem nhanh sản phẩm từ thẻ (shop, danh mục, lưới sản phẩm của builder).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

use Saha\Core\Builder\LayoutRepository;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * QuickView (TECHNICAL-DESIGN §10, §15):
 *
 * - nút "Xem nhanh" trên thẻ → `GET /saha/v1/products/{id}/quick-view` (rate limit, chỉ sản phẩm
 *   đã xuất bản và hiện trong catalogue) → HTML hiển thị trong `<dialog>` (Esc, giữ focus, trả focus);
 * - form thêm giỏ là form của WooCommerce (biến thể dùng `wc-add-to-cart-variation` + ô chọn);
 *   gửi bằng Store API `POST /wc/store/v1/cart/add-item` → không rời trang; "Mua ngay" → thanh toán;
 * - chế độ catalogue: nút "Yêu cầu báo giá" thay form.
 */
final class QuickView {

	public const HANDLE = 'saha-quick-view';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'woocommerce_after_shop_loop_item', array( $this, 'button' ), 15 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'wp_footer', array( $this, 'needModal' ), 5 );
	}

	/**
	 * Đang bật.
	 */
	public static function enabled(): bool {
		return current_theme_supports( 'saha-theme-options' ) && (bool) ThemeOptions::get( 'shop.quick_view', false );
	}

	/**
	 * Trang có thể có thẻ sản phẩm.
	 */
	private static function pageHasCards(): bool {
		if ( function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() || is_product() ) ) {
			return true;
		}

		return is_search() || is_front_page() || ( is_singular() && LayoutRepository::isEnabled( (int) get_queried_object_id() ) );
	}

	/**
	 * Nút trên thẻ.
	 */
	public function button(): void {
		global $product;

		if ( ! self::enabled() || ! $product instanceof \WC_Product ) {
			return;
		}

		printf(
			'<button type="button" class="saha-qv-btn" data-saha-quick-view="%1$d" aria-haspopup="dialog" aria-label="%2$s"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg><span class="saha-qv-btn__text">%3$s</span></button>',
			(int) $product->get_id(),
			/* translators: %s: tên sản phẩm */
			esc_attr( sprintf( __( 'Xem nhanh “%s”', 'saha-core' ), $product->get_name() ) ),
			esc_html__( 'Xem nhanh', 'saha-core' )
		);
	}

	/**
	 * CSS/JS (+ script biến thể của WooCommerce, ô chọn) ở trang có thẻ sản phẩm.
	 */
	public function enqueue(): void {
		if ( ! self::enabled() || ! self::pageHasCards() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, SAHA_CORE_URL . 'public/assets/css/quick-view.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script( 'wc-add-to-cart-variation' );

		if ( Swatches::enabled() ) {
			wp_enqueue_style( Swatches::SCRIPT );
			wp_enqueue_script( Swatches::SCRIPT );
		}

		wp_enqueue_script(
			self::HANDLE,
			SAHA_CORE_URL . 'public/assets/js/quick-view.js',
			array( 'jquery' ),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script(
			self::HANDLE,
			'sahaQuickView',
			array(
				'endpoint'  => rest_url( 'saha/v1/products/' ),
				'storeApi'  => rest_url( 'wc/store/v1/cart/add-item' ),
				'nonce'     => wp_create_nonce( 'wc_store_api' ),
				'cartUrl'   => wc_get_cart_url(),
				'checkout'  => wc_get_checkout_url(),
				'i18n'      => array(
					'close'   => __( 'Đóng', 'saha-core' ),
					'loading' => __( 'Đang tải…', 'saha-core' ),
					'error'   => __( 'Không tải được sản phẩm. Thử lại sau.', 'saha-core' ),
					'added'   => __( 'Đã thêm vào giỏ hàng.', 'saha-core' ),
					'viewCart' => __( 'Xem giỏ hàng', 'saha-core' ),
				),
			)
		);
	}

	/**
	 * Trang có Xem nhanh: theme in sẵn modal báo giá (nút báo giá trong hộp Xem nhanh dùng).
	 */
	public function needModal(): void {
		if ( self::enabled() && self::pageHasCards() ) {
			do_action( 'saha_quote_modal_needed' );
		}
	}

	/**
	 * HTML hộp Xem nhanh (null nếu không xem được).
	 *
	 * @param int $id Sản phẩm.
	 */
	public static function html( int $id ): ?string {
		$product = wc_get_product( $id );

		if ( ! $product instanceof \WC_Product || 'publish' !== $product->get_status() || ! $product->is_visible() ) {
			return null;
		}

		$old_post    = $GLOBALS['post'] ?? null;
		$old_product = $GLOBALS['product'] ?? null;

		$GLOBALS['post']    = get_post( $id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- đặt tạm cho template WooCommerce, khôi phục dưới.
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $GLOBALS['post'] );

		ob_start();

		echo '<div class="saha-qv product">';
		echo '<div class="saha-qv__media">' . $product->get_image( 'woocommerce_single', array( 'class' => 'saha-qv__img' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- WC_Product::get_image đã escape.
		echo '<div class="saha-qv__summary summary">';
		echo '<h2 class="saha-qv__title" id="saha-qv-title">' . esc_html( $product->get_name() ) . '</h2>';

		if ( '' !== (string) $product->get_sku() ) {
			echo '<p class="saha-qv__sku">' . esc_html__( 'Mã:', 'saha-core' ) . ' <strong>' . esc_html( (string) $product->get_sku() ) . '</strong></p>';
		}

		echo '<div class="saha-qv__price price">' . wp_kses_post( $product->get_price_html() ) . '</div>';

		$short = (string) apply_filters( 'woocommerce_short_description', $product->get_short_description() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- hook của WooCommerce.

		if ( '' !== trim( wp_strip_all_tags( $short ) ) ) {
			echo '<div class="saha-qv__excerpt">' . wp_kses_post( $short ) . '</div>';
		}

		if ( $product->is_purchasable() && $product->is_in_stock() ) {
			woocommerce_template_single_add_to_cart();
		} else {
			printf(
				'<button type="button" class="saha-btn saha-btn--accent saha-btn--lg" data-saha-open-quote="1" data-saha-product-id="%1$d" data-saha-product-name="%2$s" data-saha-sku="%3$s">%4$s</button>',
				(int) $product->get_id(),
				esc_attr( $product->get_name() ),
				esc_attr( (string) $product->get_sku() ),
				esc_html__( 'Yêu cầu báo giá', 'saha-core' )
			);
		}

		echo QuoteList::button( $product, 'saha-ql-add--qv' ); // phpcs:ignore WordPress.Security.EscapeOutput -- button() đã escape.

		echo '<p class="saha-qv__more"><a href="' . esc_url( (string) $product->get_permalink() ) . '">' . esc_html__( 'Xem chi tiết sản phẩm →', 'saha-core' ) . '</a></p>';
		echo '<div class="saha-qv__notice" role="status" aria-live="polite"></div>';
		echo '</div></div>';

		$html = (string) ob_get_clean();

		$GLOBALS['post']    = $old_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $old_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		wp_reset_postdata();

		return $html;
	}
}
