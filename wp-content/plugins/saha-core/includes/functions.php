<?php
/**
 * Public helper API — đây là interface duy nhất child theme được dùng.
 *
 * Mọi hàm phải an toàn khi gọi sớm và không throw.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'saha_get_setting' ) ) {
	/**
	 * Lấy một setting toàn cục.
	 *
	 * @param string $key      Key setting.
	 * @param mixed  $fallback Giá trị mặc định.
	 * @return mixed
	 */
	function saha_get_setting( string $key, $fallback = '' ) {
		return Saha\Core\Settings::get( $key, $fallback );
	}
}

if ( ! function_exists( 'saha_hotline' ) ) {
	/**
	 * Hotline theo vùng.
	 *
	 * @param string $region north|south.
	 */
	function saha_hotline( string $region = 'north' ): string {
		$key = 'south' === $region ? 'hotline_south' : 'hotline_north';

		return (string) saha_get_setting( $key );
	}
}

if ( ! function_exists( 'saha_tel_href' ) ) {
	/**
	 * Sinh href tel: an toàn từ số hiển thị.
	 *
	 * @param string $phone Số hiển thị.
	 */
	function saha_tel_href( string $phone ): string {
		$digits = Saha\Core\Security::tel_digits( $phone );

		return '' === $digits ? '' : 'tel:' . $digits;
	}
}

if ( ! function_exists( 'saha_zalo_url' ) ) {
	/**
	 * Link Zalo sinh từ setting, không hardcode.
	 */
	function saha_zalo_url(): string {
		$digits = Saha\Core\Security::tel_digits( (string) saha_get_setting( 'zalo_phone' ) );

		if ( '' === $digits ) {
			return '';
		}

		return 'https://zalo.me/' . ltrim( $digits, '+' );
	}
}

if ( ! function_exists( 'saha_is_catalogue_mode' ) ) {
	/**
	 * Website đang chạy chế độ catalogue (ẩn giá/giỏ hàng)?
	 */
	function saha_is_catalogue_mode(): bool {
		return (bool) saha_get_setting( 'catalogue_mode', true );
	}
}

if ( ! function_exists( 'saha_cta_label' ) ) {
	/**
	 * Nhãn CTA mặc định.
	 */
	function saha_cta_label(): string {
		$label = (string) saha_get_setting( 'default_cta', 'Yêu cầu báo giá' );

		return '' !== $label ? $label : __( 'Yêu cầu báo giá', 'saha-core' );
	}
}

if ( ! function_exists( 'saha_quote_recipient' ) ) {
	/**
	 * Email nhận yêu cầu báo giá, fallback về admin email.
	 */
	function saha_quote_recipient(): string {
		$email = (string) saha_get_setting( 'quote_email' );

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		return (string) get_option( 'admin_email' );
	}
}

if ( ! function_exists( 'saha_log' ) ) {
	/**
	 * Ghi log qua logger abstraction.
	 *
	 * @param string               $message Nội dung.
	 * @param string               $level   debug|info|warning|error.
	 * @param string               $channel Kênh.
	 * @param array<string, mixed> $context Context (đã tự động redact key nhạy cảm).
	 */
	function saha_log( string $message, string $level = 'info', string $channel = 'core', array $context = array() ): void {
		Saha\Core\Logger::log( $message, $level, $channel, $context );
	}
}

if ( ! function_exists( 'saha_public_nonce' ) ) {
	/**
	 * Nonce cho header X-WP-Nonce của REST.
	 *
	 * PHẢI là action `wp_rest`: core WordPress (rest_cookie_check_errors) kiểm tra
	 * mọi X-WP-Nonce theo action này, kể cả với khách chưa đăng nhập — nonce
	 * action khác sẽ bị trả 403 trước khi tới route của plugin.
	 */
	function saha_public_nonce(): string {
		return wp_create_nonce( 'wp_rest' );
	}
}

if ( ! function_exists( 'saha_form_nonce_field' ) ) {
	/**
	 * Field nonce cho form submit không qua JavaScript (admin-post.php).
	 */
	function saha_form_nonce_field(): string {
		return sprintf(
			'<input type="hidden" name="saha_nonce" value="%s">',
			esc_attr( wp_create_nonce( Saha\Core\Security::PUBLIC_NONCE_ACTION ) )
		);
	}
}

if ( ! function_exists( 'saha_honeypot_field' ) ) {
	/**
	 * Render field honeypot cho form.
	 */
	function saha_honeypot_field(): string {
		return sprintf(
			'<p class="saha-hp" aria-hidden="true"><label>%1$s<input type="text" name="%2$s" value="" tabindex="-1" autocomplete="off"></label></p>',
			esc_html__( 'Để trống ô này', 'saha-core' ),
			esc_attr( Saha\Core\Security::HONEYPOT_FIELD )
		);
	}
}

if ( ! function_exists( 'saha_api_url' ) ) {
	/**
	 * URL REST của plugin, không hardcode domain.
	 *
	 * @param string $path Đường dẫn sau namespace, ví dụ `search`.
	 */
	function saha_api_url( string $path = '' ): string {
		return rest_url( 'saha/v1/' . ltrim( $path, '/' ) );
	}
}

if ( ! function_exists( 'saha_get_brand' ) ) {
	/**
	 * Dữ liệu một thương hiệu.
	 *
	 * @param int|string|WP_Term $brand Term ID, slug hoặc WP_Term.
	 * @return array<string, mixed> Rỗng nếu không tìm thấy.
	 */
	function saha_get_brand( $brand ): array {
		return Saha\Core\Brand::get( $brand );
	}
}

if ( ! function_exists( 'saha_get_brands' ) ) {
	/**
	 * Danh sách thương hiệu (có cache).
	 *
	 * @param array<string, mixed> $args orderby, order, number, hide_empty, include.
	 * @return array<int, array<string, mixed>>
	 */
	function saha_get_brands( array $args = array() ): array {
		return Saha\Core\Brand::get_all( $args );
	}
}

if ( ! function_exists( 'saha_get_product_brand' ) ) {
	/**
	 * Thương hiệu của một sản phẩm.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	function saha_get_product_brand( int $product_id ): array {
		return Saha\Core\Brand::get_for_product( $product_id );
	}
}

if ( ! function_exists( 'saha_get_product_meta' ) ) {
	/**
	 * Một meta tuỳ biến của sản phẩm.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $key        Key không prefix, ví dụ `unit`.
	 * @return mixed
	 */
	function saha_get_product_meta( int $product_id, string $key ) {
		return Saha\Core\Product::get_meta( $product_id, $key );
	}
}

if ( ! function_exists( 'saha_get_product_specs' ) ) {
	/**
	 * Thông số kỹ thuật của sản phẩm.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, string>>
	 */
	function saha_get_product_specs( int $product_id ): array {
		return Saha\Core\Product::get_specs( $product_id );
	}
}

if ( ! function_exists( 'saha_get_product_documents' ) ) {
	/**
	 * Tài liệu của sản phẩm (TDS/SDS/Catalogue/Manual).
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	function saha_get_product_documents( int $product_id ): array {
		return Saha\Core\Product::get_documents( $product_id );
	}
}

if ( ! function_exists( 'saha_get_availability_label' ) ) {
	/**
	 * Nhãn tình trạng hàng.
	 *
	 * @param int $product_id Product ID.
	 */
	function saha_get_availability_label( int $product_id ): string {
		return Saha\Core\Product::get_availability_label( $product_id );
	}
}

if ( ! function_exists( 'saha_get_product_card_data' ) ) {
	/**
	 * Dữ liệu product card.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	function saha_get_product_card_data( int $product_id ): array {
		return Saha\Core\Product::get_card_data( $product_id );
	}
}

if ( ! function_exists( 'saha_get_product_cta' ) ) {
	/**
	 * Chế độ và nhãn CTA của sản phẩm, đã áp dụng fallback về cấu hình chung.
	 *
	 * @param int $product_id Product ID.
	 * @return array{mode: string, label: string}
	 */
	function saha_get_product_cta( int $product_id ): array {
		$mode  = (string) Saha\Core\Product::get_meta( $product_id, 'cta_mode' );
		$label = (string) Saha\Core\Product::get_meta( $product_id, 'cta_label' );

		return array(
			'mode'  => '' !== $mode ? $mode : 'quote',
			'label' => '' !== $label ? $label : saha_cta_label(),
		);
	}
}

if ( ! function_exists( 'saha_search' ) ) {
	/**
	 * Tìm sản phẩm theo tên, SKU, thương hiệu, danh mục, mô tả.
	 *
	 * @param string $term     Từ khoá.
	 * @param int    $page     Trang.
	 * @param int    $per_page Số kết quả mỗi trang.
	 * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, query: string}
	 */
	function saha_search( string $term, int $page = 1, int $per_page = 10 ): array {
		return Saha\Core\Search::search( $term, $page, $per_page );
	}
}

if ( ! function_exists( 'saha_filter_current' ) ) {
	/**
	 * Filter đang áp dụng trên URL hiện tại, đã sanitize.
	 *
	 * @return array<string, mixed>
	 */
	function saha_filter_current(): array {
		return Saha\Core\Filter::current();
	}
}

if ( ! function_exists( 'saha_filter_url' ) ) {
	/**
	 * URL đã áp một filter, giữ nguyên các filter khác.
	 *
	 * @param string $var   Query var, ví dụ `saha_brand`.
	 * @param string $value Giá trị; rỗng để bỏ filter.
	 */
	function saha_filter_url( string $var, string $value ): string {
		return Saha\Core\Filter::build_url( $var, $value );
	}
}

if ( ! function_exists( 'saha_filter_base_url' ) ) {
	/**
	 * URL archive hiện tại, đã bỏ filter và phân trang.
	 */
	function saha_filter_base_url(): string {
		return Saha\Core\Filter::current_base_url();
	}
}

if ( ! function_exists( 'saha_availability_options' ) ) {
	/**
	 * Danh sách tình trạng hàng.
	 *
	 * @return array<string, string>
	 */
	function saha_availability_options(): array {
		return Saha\Core\Product::availability_options();
	}
}

if ( ! function_exists( 'saha_quote_page_url' ) ) {
	/**
	 * URL trang yêu cầu báo giá (cấu hình trong admin).
	 */
	function saha_quote_page_url(): string {
		return (string) saha_get_setting( 'quote_page_url', '' );
	}
}

if ( ! function_exists( 'saha_form_result' ) ) {
	/**
	 * Thông báo kết quả submit form không-JS, đọc từ query var.
	 *
	 * @return array{type: string, message: string}|null
	 */
	function saha_form_result(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- chỉ hiển thị thông báo.
		$code = isset( $_GET[ Saha\Core\Form_Handler::RESULT_VAR ] ) ? sanitize_key( wp_unslash( (string) $_GET[ Saha\Core\Form_Handler::RESULT_VAR ] ) ) : '';

		return '' === $code ? null : Saha\Core\Form_Handler::message_for( $code );
	}
}

if ( ! function_exists( 'saha_quote_statuses' ) ) {
	/**
	 * Trạng thái báo giá.
	 *
	 * @return array<string, string>
	 */
	function saha_quote_statuses(): array {
		return Saha\Core\Quote::statuses();
	}
}

if ( ! function_exists( 'saha_catalog_product_ids' ) ) {
	/**
	 * ID sản phẩm cho một khối trang chủ (có cache, tự vô hiệu khi dữ liệu đổi).
	 *
	 * @param array<string, mixed> $args source (featured|latest|sale|category|brand|application|ids),
	 *                                   category, brand, application, ids, limit, orderby, hide_out_of_stock.
	 * @return int[]
	 */
	function saha_catalog_product_ids( array $args ): array {
		return Saha\Core\Catalog::product_ids( $args );
	}
}

if ( ! function_exists( 'saha_catalog_terms' ) ) {
	/**
	 * Term cho grid danh mục / ứng dụng.
	 *
	 * @param string               $taxonomy product_cat | product_application.
	 * @param array<string, mixed> $args     parent, include, limit, hide_empty, orderby.
	 * @return array<int, array<string, mixed>>
	 */
	function saha_catalog_terms( string $taxonomy, array $args = array() ): array {
		return Saha\Core\Catalog::terms( $taxonomy, $args );
	}
}

if ( ! function_exists( 'saha_catalog_sources' ) ) {
	/**
	 * Các nguồn sản phẩm cho khối trang chủ.
	 *
	 * @return array<string, string>
	 */
	function saha_catalog_sources(): array {
		return Saha\Core\Catalog::sources();
	}
}
