<?php
/**
 * Danh sách báo giá nhiều sản phẩm — phía khách (SCC 2.5, spec §102).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\WooCommerce;

use Saha\Core\Builder\LayoutService;
use Saha\Core\Quote;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * QuoteList:
 *
 * - nút "Thêm vào danh sách báo giá" (trang sản phẩm, element Thêm vào giỏ ở chế độ báo giá, Xem nhanh);
 * - danh sách nằm ở trình duyệt khách (localStorage) — không tạo phiên / cookie phía server,
 *   trang vẫn cache được; gửi một lần qua `POST /saha/v1/quote/list`;
 * - trang "Danh sách báo giá" (element `quote-list`) tự tạo khi bật tuỳ chọn, ID ở option PAGE_OPTION;
 * - icon header (element `quote-list-link`) hiện số sản phẩm trong danh sách.
 */
final class QuoteList {

	public const HANDLE      = 'saha-quote-list';
	public const PAGE_OPTION = 'saha_quote_list_page';

	/**
	 * Gắn hook.
	 */
	public function register(): void {
		add_action( 'woocommerce_single_product_summary', array( $this, 'singleButton' ), 36 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybeCreatePage' ) );
	}

	/**
	 * Tuỳ chọn đang bật.
	 */
	public static function enabled(): bool {
		return current_theme_supports( 'saha-theme-options' ) && (bool) ThemeOptions::get( 'shop.quote_list', false );
	}

	/**
	 * Trang danh sách (đã xuất bản) — 0 nếu chưa có.
	 */
	public static function pageId(): int {
		$id = (int) get_option( self::PAGE_OPTION, 0 );

		return $id > 0 && 'page' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ? $id : 0;
	}

	/**
	 * URL trang danh sách ('' nếu chưa có).
	 */
	public static function pageUrl(): string {
		$id = self::pageId();

		return $id > 0 ? (string) get_permalink( $id ) : '';
	}

	/**
	 * Bật tuỳ chọn mà chưa có trang → tạo trang dựng bằng builder. Trang đã bị xoá hẳn mới tạo lại;
	 * trang chuyển nháp / thùng rác là chủ ý của admin → không tạo thêm.
	 */
	public static function maybeCreatePage(): void {
		if ( ! self::enabled() || ! current_user_can( 'publish_pages' ) ) {
			return;
		}

		$id = (int) get_option( self::PAGE_OPTION, 0 );

		if ( $id > 0 && null !== get_post( $id ) ) {
			return;
		}

		self::createPage();
	}

	/**
	 * Tạo trang "Danh sách báo giá".
	 *
	 * @return int|\WP_Error Page ID.
	 */
	public static function createPage() {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => __( 'Danh sách báo giá', 'saha-core' ),
				'post_name'   => 'danh-sach-bao-gia',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Lỗi bất kỳ khi lưu layout → xoá trang vừa tạo (không để lại trang trống, lần sau tạo lại).
		try {
			$result = LayoutService::save( (int) $post_id, self::pageDocument(), '' );
		} catch ( \Throwable $e ) {
			$result = array( 'status' => 'error' );
		}

		if ( 'saved' !== $result['status'] ) {
			wp_delete_post( (int) $post_id, true );

			return new \WP_Error( 'saha_quote_list_page', __( 'Không tạo được trang Danh sách báo giá.', 'saha-core' ) );
		}

		update_option( self::PAGE_OPTION, (int) $post_id, false );

		return (int) $post_id;
	}

	/**
	 * Tài liệu builder của trang danh sách.
	 *
	 * @return array<string, mixed>
	 */
	public static function pageDocument(): array {
		return array(
			'version'  => 1,
			'elements' => array(
				array(
					'type'     => 'section',
					'props'    => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '40px',
								'bottom' => '56px',
							),
						),
					),
					'children' => array(
						array(
							'type'  => 'heading',
							'props' => array(
								'text' => __( 'Danh sách báo giá', 'saha-core' ),
								'tag'  => 'h1',
							),
						),
						array(
							'type'  => 'quote-list',
							'props' => array(),
						),
					),
				),
			),
		);
	}

	/**
	 * HTML nút thêm vào danh sách. JS đọc biến thể đang chọn trong form gần nhất (nếu có).
	 *
	 * @param \WC_Product $product Sản phẩm.
	 * @param string      $extra   Class thêm.
	 */
	public static function button( \WC_Product $product, string $extra = '' ): string {
		if ( ! self::enabled() ) {
			return '';
		}

		$image = (string) wp_get_attachment_image_url( (int) $product->get_image_id(), 'thumbnail' );

		return sprintf(
			'<button type="button" class="saha-ql-add%1$s" data-saha-ql-add data-product-id="%2$d" data-name="%3$s" data-sku="%4$s" data-image="%5$s" data-url="%6$s"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M9 5h11M9 12h11M9 19h11M4 5h.01M4 12h.01M4 19h.01"/></svg><span class="saha-ql-add__text">%7$s</span></button>',
			'' !== $extra ? ' ' . esc_attr( $extra ) : '',
			(int) $product->get_id(),
			esc_attr( $product->get_name() ),
			esc_attr( (string) $product->get_sku() ),
			esc_url( $image ),
			esc_url( (string) $product->get_permalink() ),
			esc_html__( 'Thêm vào danh sách báo giá', 'saha-core' )
		);
	}

	/**
	 * Trang sản phẩm: sau CTA báo giá của theme (ưu tiên 35).
	 */
	public function singleButton(): void {
		global $product;

		if ( $product instanceof \WC_Product ) {
			echo self::button( $product, 'saha-ql-add--single' ); // phpcs:ignore WordPress.Security.EscapeOutput -- button() đã escape.
		}
	}

	/**
	 * CSS/JS ở mọi trang (nhỏ): nút thêm, số trên icon header, trang danh sách.
	 */
	public function enqueue(): void {
		if ( ! self::enabled() ) {
			return;
		}

		wp_enqueue_style( self::HANDLE, SAHA_CORE_URL . 'public/assets/css/quote-list.css', array(), SAHA_CORE_VERSION );
		wp_enqueue_script(
			self::HANDLE,
			SAHA_CORE_URL . 'public/assets/js/quote-list.js',
			array(),
			SAHA_CORE_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		wp_localize_script( self::HANDLE, 'sahaQuoteList', self::config() );
	}

	/**
	 * Cấu hình cho JS.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		return array(
			'endpoint' => rest_url( 'saha/v1/quote/list' ),
			'nonceUrl' => rest_url( 'saha/v1/nonce' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'pageUrl'  => self::pageUrl(),
			'shopUrl'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ),
			'maxItems' => Quote::MAX_ITEMS,
			'maxQty'   => Quote::MAX_ITEM_QTY,
			'i18n'     => array(
				'added'       => __( 'Đã thêm vào danh sách báo giá.', 'saha-core' ),
				'inList'      => __( 'Đã có trong danh sách', 'saha-core' ),
				'viewList'    => __( 'Xem danh sách', 'saha-core' ),
				'full'        => __( 'Danh sách đã đủ số sản phẩm tối đa.', 'saha-core' ),
				'chooseVar'   => __( 'Hãy chọn đủ thuộc tính (màu, dung tích…) trước.', 'saha-core' ),
				'empty'       => __( 'Danh sách báo giá đang trống.', 'saha-core' ),
				'browse'      => __( 'Xem sản phẩm', 'saha-core' ),
				'product'     => __( 'Sản phẩm', 'saha-core' ),
				'qty'         => __( 'Số lượng', 'saha-core' ),
				'note'        => __( 'Ghi chú (quy cách, màu…)', 'saha-core' ),
				/* translators: %s: tên sản phẩm */
				'qtyOf'       => __( 'Số lượng “%s”', 'saha-core' ),
				/* translators: %s: tên sản phẩm */
				'noteOf'      => __( 'Ghi chú cho “%s”', 'saha-core' ),
				/* translators: %s: tên sản phẩm */
				'remove'      => __( 'Xoá “%s” khỏi danh sách', 'saha-core' ),
				'sku'         => __( 'Mã', 'saha-core' ),
				'clear'       => __( 'Xoá tất cả', 'saha-core' ),
				'clearAsk'    => __( 'Xoá toàn bộ danh sách báo giá?', 'saha-core' ),
				'removed'     => __( 'Đã xoá khỏi danh sách.', 'saha-core' ),
				'invalid'     => __( 'Không còn kinh doanh', 'saha-core' ),
				'formTitle'   => __( 'Thông tin liên hệ', 'saha-core' ),
				'name'        => __( 'Họ tên', 'saha-core' ),
				'phone'       => __( 'Số điện thoại', 'saha-core' ),
				'email'       => __( 'Email', 'saha-core' ),
				'company'     => __( 'Công ty / cửa hàng', 'saha-core' ),
				'message'     => __( 'Yêu cầu thêm (địa chỉ giao, thời gian cần hàng…)', 'saha-core' ),
				'required'    => __( '(bắt buộc)', 'saha-core' ),
				'submit'      => __( 'Gửi yêu cầu báo giá', 'saha-core' ),
				'sending'     => __( 'Đang gửi…', 'saha-core' ),
				'error'       => __( 'Không gửi được. Vui lòng thử lại hoặc gọi hotline.', 'saha-core' ),
				'successNext' => __( 'Danh sách đã được làm trống. Bạn có thể tiếp tục chọn sản phẩm khác.', 'saha-core' ),
				/* translators: %d: số sản phẩm */
				'count'       => __( '%d sản phẩm trong danh sách báo giá', 'saha-core' ),
			),
		);
	}
}
