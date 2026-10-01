<?php
/**
 * Theme Options — schema (nguồn duy nhất cho UI, mặc định và sanitize).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Schema.
 *
 * Cấu trúc: nhóm → field. Giá trị lưu lồng theo nhóm:
 * `[ 'colors' => [ 'primary' => '#0b5cab', … ], … ]`.
 *
 * Mốc 1.1 chỉ khai báo field đã có tác dụng thật trên saha-theme. Nhóm
 * Product / Cart / Checkout / Performance được thêm khi module tương ứng có
 * (mốc 1.6) — không tạo cấu hình "chết" (TECHNICAL-DESIGN §9).
 */
final class Schema {

	/**
	 * Font: stack hệ thống (không tải gì) + font web Google Fonts có đủ dấu tiếng Việt
	 * (khoá `google` = tên họ font; chỉ tải khi được chọn ở Theme Options — WebFonts).
	 * Không dùng Georgia: thiếu glyph dựng sẵn (ế, ộ…) nên dấu bị tách rời.
	 *
	 * @return array<string, array{label: string, stack: string, google?: string}>
	 */
	public static function fontStacks(): array {
		$stacks = array(
			'system' => array(
				'label' => __( 'Hệ thống (Segoe UI / Roboto / San Francisco)', 'saha-core' ),
				'stack' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif',
			),
			'sans'   => array(
				'label' => __( 'Sans-serif (Helvetica / Arial)', 'saha-core' ),
				'stack' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
			),
			'serif'  => array(
				'label' => __( 'Serif (Times New Roman)', 'saha-core' ),
				'stack' => '"Times New Roman", Times, "Noto Serif", serif',
			),
			'mono'   => array(
				'label' => __( 'Monospace', 'saha-core' ),
				'stack' => 'ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace',
			),
		);

		$fallback = ', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif';

		foreach ( array(
			'be-vietnam-pro' => 'Be Vietnam Pro',
			'montserrat'     => 'Montserrat',
			'inter'          => 'Inter',
			'roboto'         => 'Roboto',
			'nunito'         => 'Nunito',
			'open-sans'      => 'Open Sans',
			'lexend'         => 'Lexend',
		) as $key => $family ) {
			$stacks[ $key ] = array(
				/* translators: %s: tên font */
				'label'  => sprintf( __( '%s (Google Fonts)', 'saha-core' ), $family ),
				'stack'  => '"' . $family . '"' . $fallback,
				'google' => $family,
			);
		}

		/**
		 * Thêm font stack (ví dụ font tự host đã khai báo @font-face ở theme con).
		 *
		 * @param array<string, array{label: string, stack: string}> $stacks Font stack.
		 */
		return (array) apply_filters( 'saha_theme_font_stacks', $stacks );
	}

	/**
	 * Định nghĩa đầy đủ.
	 *
	 * Mỗi field: type, label, default, và tuỳ type: options, units, min, max,
	 * responsive, cssVar, help, storage (field lưu ở nơi khác).
	 *
	 * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
	 */
	public static function groups(): array {
		$typography = static function ( string $family, string $size, string $weight, string $line_height ): array {
			return array(
				'fontFamily'    => $family,
				'fontSize'      => $size,
				'fontWeight'    => $weight,
				'lineHeight'    => $line_height,
				'letterSpacing' => '0',
			);
		};

		$groups = array(
			'general'    => array(
				'label'  => __( 'Chung', 'saha-core' ),
				'fields' => array(
					'logo'        => array(
						'type'    => 'media',
						'label'   => __( 'Logo', 'saha-core' ),
						'default' => 0,
						'help'    => __( 'Để trống sẽ hiện tên website.', 'saha-core' ),
					),
					'logo_mobile' => array(
						'type'    => 'media',
						'label'   => __( 'Logo trên mobile', 'saha-core' ),
						'default' => 0,
						'help'    => __( 'Để trống sẽ dùng logo chính.', 'saha-core' ),
					),
					'logo_height' => array(
						'type'       => 'size',
						'label'      => __( 'Chiều cao logo', 'saha-core' ),
						'default'    => array(
							'desktop' => '48px',
							'mobile'  => '36px',
						),
						'units'      => array( 'px' ),
						'min'        => 16,
						'max'        => 160,
						'responsive' => true,
						'cssVar'     => '--saha-logo-height',
					),
				),
			),
			'layout'     => array(
				'label'  => __( 'Bố cục', 'saha-core' ),
				'fields' => array(
					'container_width' => array(
						'type'    => 'size',
						'label'   => __( 'Độ rộng khung nội dung', 'saha-core' ),
						'default' => '1200px',
						'units'   => array( 'px' ),
						'min'     => 960,
						'max'     => 1920,
						'help'    => __( 'Thường dùng: 1200px, 1280px, 1440px.', 'saha-core' ),
						'cssVar'  => '--saha-container',
					),
					'content_width'   => array(
						'type'    => 'size',
						'label'   => __( 'Độ rộng bài viết', 'saha-core' ),
						'default' => '800px',
						'units'   => array( 'px' ),
						'min'     => 560,
						'max'     => 1200,
						'cssVar'  => '--saha-content',
					),
					'sidebar_width'   => array(
						'type'    => 'size',
						'label'   => __( 'Độ rộng sidebar', 'saha-core' ),
						'default' => '280px',
						'units'   => array( 'px' ),
						'min'     => 200,
						'max'     => 480,
						'cssVar'  => '--saha-sidebar',
					),
					'gutter'          => array(
						'type'       => 'size',
						'label'      => __( 'Khoảng cách lề', 'saha-core' ),
						'default'    => array(
							'desktop' => '24px',
							'mobile'  => '16px',
						),
						'units'      => array( 'px' ),
						'min'        => 8,
						'max'        => 64,
						'responsive' => true,
						'cssVar'     => '--saha-gutter',
					),
				),
			),
			'colors'     => array(
				'label'  => __( 'Màu sắc', 'saha-core' ),
				'fields' => array(
					'primary'    => array( 'type' => 'color', 'label' => __( 'Màu chính', 'saha-core' ), 'default' => '#0b5cab', 'cssVar' => '--saha-primary' ),
					'secondary'  => array( 'type' => 'color', 'label' => __( 'Màu phụ', 'saha-core' ), 'default' => '#1f2937', 'cssVar' => '--saha-secondary' ),
					'accent'     => array( 'type' => 'color', 'label' => __( 'Màu nhấn', 'saha-core' ), 'default' => '#f5a623', 'cssVar' => '--saha-accent' ),
					'success'    => array( 'type' => 'color', 'label' => __( 'Thành công', 'saha-core' ), 'default' => '#1e7e34', 'cssVar' => '--saha-success' ),
					'warning'    => array( 'type' => 'color', 'label' => __( 'Cảnh báo', 'saha-core' ), 'default' => '#b26a00', 'cssVar' => '--saha-warning' ),
					'error'      => array( 'type' => 'color', 'label' => __( 'Lỗi', 'saha-core' ), 'default' => '#c62828', 'cssVar' => '--saha-error' ),
					'text'       => array( 'type' => 'color', 'label' => __( 'Chữ', 'saha-core' ), 'default' => '#1f2328', 'cssVar' => '--saha-text' ),
					'heading'    => array( 'type' => 'color', 'label' => __( 'Tiêu đề', 'saha-core' ), 'default' => '#111827', 'cssVar' => '--saha-heading' ),
					'border'     => array( 'type' => 'color', 'label' => __( 'Viền', 'saha-core' ), 'default' => '#e3e6ea', 'cssVar' => '--saha-border' ),
					'background' => array( 'type' => 'color', 'label' => __( 'Nền', 'saha-core' ), 'default' => '#ffffff', 'cssVar' => '--saha-background' ),
					'surface'    => array( 'type' => 'color', 'label' => __( 'Nền phụ (khối xen kẽ, thẻ)', 'saha-core' ), 'default' => '#f6f7f9', 'cssVar' => '--saha-surface' ),
					'muted'      => array( 'type' => 'color', 'label' => __( 'Chữ phụ (mô tả, ngày tháng)', 'saha-core' ), 'default' => '#5f6b7a', 'cssVar' => '--saha-muted' ),
				),
			),
			'typography' => array(
				'label'  => __( 'Chữ', 'saha-core' ),
				'fields' => array(
					'body'    => array(
						'type'    => 'typography',
						'label'   => __( 'Nội dung', 'saha-core' ),
						'default' => $typography( 'system', '16px', '400', '1.6' ),
						'cssVar'  => '--saha-type-body',
					),
					'heading' => array(
						'type'    => 'typography',
						'label'   => __( 'Tiêu đề', 'saha-core' ),
						'default' => $typography( 'system', '', '700', '1.25' ),
						'help'    => __( 'Cỡ chữ từng cấp tiêu đề chỉnh ở H1–H3 bên dưới.', 'saha-core' ),
						'cssVar'  => '--saha-type-heading',
					),
					'menu'    => array(
						'type'    => 'typography',
						'label'   => __( 'Menu', 'saha-core' ),
						'default' => $typography( 'system', '15px', '600', '1.4' ),
						'cssVar'  => '--saha-type-menu',
					),
					'button'  => array(
						'type'    => 'typography',
						'label'   => __( 'Nút', 'saha-core' ),
						'default' => $typography( 'system', '15px', '600', '1.2' ),
						'cssVar'  => '--saha-type-button',
					),
					'h1_size' => array(
						'type'       => 'size',
						'label'      => __( 'Cỡ H1', 'saha-core' ),
						'default'    => array( 'desktop' => '36px', 'tablet' => '30px', 'mobile' => '26px' ),
						'units'      => array( 'px', 'rem' ),
						'min'        => 12,
						'max'        => 96,
						'responsive' => true,
						'cssVar'     => '--saha-h1-size',
					),
					'h2_size' => array(
						'type'       => 'size',
						'label'      => __( 'Cỡ H2', 'saha-core' ),
						'default'    => array( 'desktop' => '28px', 'tablet' => '24px', 'mobile' => '22px' ),
						'units'      => array( 'px', 'rem' ),
						'min'        => 12,
						'max'        => 72,
						'responsive' => true,
						'cssVar'     => '--saha-h2-size',
					),
					'h3_size' => array(
						'type'       => 'size',
						'label'      => __( 'Cỡ H3', 'saha-core' ),
						'default'    => array( 'desktop' => '22px', 'mobile' => '19px' ),
						'units'      => array( 'px', 'rem' ),
						'min'        => 12,
						'max'        => 56,
						'responsive' => true,
						'cssVar'     => '--saha-h3-size',
					),
				),
			),
			'header'     => array(
				'label'  => __( 'Header', 'saha-core' ),
				'fields' => array(
					'height'         => array(
						'type'       => 'size',
						'label'      => __( 'Chiều cao header', 'saha-core' ),
						'default'    => array( 'desktop' => '80px', 'mobile' => '64px' ),
						'units'      => array( 'px' ),
						'min'        => 40,
						'max'        => 200,
						'responsive' => true,
						'cssVar'     => '--saha-header-height',
					),
					'sticky'         => array(
						'type'    => 'toggle',
						'label'   => __( 'Header dính khi cuộn', 'saha-core' ),
						'default' => true,
					),
					'sticky_mode'    => array(
						'type'    => 'select',
						'label'   => __( 'Kiểu dính', 'saha-core' ),
						'default' => 'always',
						'options' => array(
							'always'   => __( 'Luôn hiện', 'saha-core' ),
							'scrollUp' => __( 'Chỉ hiện khi cuộn lên', 'saha-core' ),
						),
					),
					'sticky_height'  => array(
						'type'    => 'size',
						'label'   => __( 'Chiều cao khi dính', 'saha-core' ),
						'default' => '64px',
						'units'   => array( 'px' ),
						'min'     => 40,
						'max'     => 160,
						'cssVar'  => '--saha-sticky-height',
					),
					'sticky_bg'      => array(
						'type'    => 'color',
						'label'   => __( 'Nền khi dính', 'saha-core' ),
						'default' => '#ffffff',
						'cssVar'  => '--saha-sticky-bg',
					),
					'show_search'    => array( 'type' => 'toggle', 'label' => __( 'Ô tìm kiếm', 'saha-core' ), 'default' => true ),
					'show_hotline'   => array( 'type' => 'toggle', 'label' => __( 'Hotline', 'saha-core' ), 'default' => true ),
					'show_account'   => array( 'type' => 'toggle', 'label' => __( 'Tài khoản', 'saha-core' ), 'default' => false ),
					'show_cart'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Giỏ hàng', 'saha-core' ),
						'default' => true,
						'help'    => __( 'Tự ẩn khi bật chế độ catalogue.', 'saha-core' ),
					),
				),
			),
			'footer'     => array(
				'label'  => __( 'Footer', 'saha-core' ),
				'fields' => array(
					'copyright' => array(
						'type'    => 'text',
						'label'   => __( 'Dòng bản quyền', 'saha-core' ),
						'default' => '© {year} {site}',
						'help'    => __( 'Dùng {year} cho năm hiện tại, {site} cho tên website.', 'saha-core' ),
					),
				),
			),
			'blog'       => array(
				'label'  => __( 'Blog', 'saha-core' ),
				'fields' => array(
					'layout'  => array(
						'type'    => 'select',
						'label'   => __( 'Bố cục danh sách', 'saha-core' ),
						'default' => 'grid',
						'options' => array(
							'grid' => __( 'Lưới', 'saha-core' ),
							'list' => __( 'Danh sách', 'saha-core' ),
						),
					),
					'columns' => array(
						'type'    => 'number',
						'label'   => __( 'Số cột (lưới)', 'saha-core' ),
						'default' => 3,
						'min'     => 1,
						'max'     => 4,
					),
				),
			),
			'shop'       => array(
				'label'  => __( 'Cửa hàng', 'saha-core' ),
				'fields' => array(
					'per_page'        => array( 'type' => 'number', 'label' => __( 'Sản phẩm mỗi trang', 'saha-core' ), 'default' => 24, 'min' => 4, 'max' => 60 ),
					'columns_desktop' => array( 'type' => 'number', 'label' => __( 'Số cột — desktop', 'saha-core' ), 'default' => 4, 'min' => 2, 'max' => 6 ),
					'columns_tablet'  => array( 'type' => 'number', 'label' => __( 'Số cột — tablet', 'saha-core' ), 'default' => 3, 'min' => 2, 'max' => 4 ),
					'columns_mobile'  => array( 'type' => 'number', 'label' => __( 'Số cột — mobile', 'saha-core' ), 'default' => 2, 'min' => 1, 'max' => 2 ),
					'card_style'      => array(
						'type'    => 'select',
						'label'   => __( 'Kiểu thẻ sản phẩm', 'saha-core' ),
						'default' => 'default',
						'options' => array(
							'default' => __( 'Mặc định', 'saha-core' ),
							'store'   => __( 'Cửa hàng: nhãn −%, nút giỏ/báo giá trên ảnh, thông số', 'saha-core' ),
						),
					),
					'card_specs'      => array( 'type' => 'number', 'label' => __( 'Số dòng thông số trên thẻ (kiểu Cửa hàng)', 'saha-core' ), 'default' => 3, 'min' => 0, 'max' => 4 ),
					'card_sold'       => array(
						'type'    => 'toggle',
						'label'   => __( 'Thanh "Đã bán" (số bán thật của WooCommerce)', 'saha-core' ),
						'default' => false,
						'help'    => __( 'Chỉ bật khi số bán là thật — không dùng để tạo cảm giác khan hiếm.', 'saha-core' ),
					),
					'card_sold_goal'  => array( 'type' => 'number', 'label' => __( 'Mốc đầy thanh "Đã bán"', 'saha-core' ), 'default' => 100, 'min' => 10, 'max' => 10000 ),
					'swatches'        => array(
						'type'    => 'toggle',
						'label'   => __( 'Ô chọn màu / ảnh / chữ cho biến thể', 'saha-core' ),
						'default' => true,
						'help'    => __( 'Kiểu ô của từng thuộc tính đặt ở Sản phẩm → Thuộc tính. Thuộc tính để "Danh sách thả xuống" giữ nguyên.', 'saha-core' ),
					),
					'sticky_cart'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Thanh "Thêm vào giỏ" dính khi cuộn qua nút mua (trang sản phẩm)', 'saha-core' ),
						'default' => false,
					),
					'quick_view'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Nút "Xem nhanh" trên thẻ sản phẩm', 'saha-core' ),
						'default' => false,
					),
					'quote_list'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Danh sách báo giá nhiều sản phẩm (nút "Thêm vào danh sách báo giá")', 'saha-core' ),
						'default' => false,
						'help'    => __( 'Bật lần đầu: tự tạo trang "Danh sách báo giá". Thêm element "Icon danh sách báo giá" vào header để khách mở danh sách.', 'saha-core' ),
					),
					'mini_cart'       => array(
						'type'    => 'toggle',
						'label'   => __( 'Bấm icon giỏ / thêm vào giỏ → mở ngăn giỏ hàng (không chuyển trang)', 'saha-core' ),
						'default' => true,
						'help'    => __( 'Tắt ở chế độ catalogue. Không có JavaScript thì icon giỏ vẫn mở trang giỏ hàng.', 'saha-core' ),
					),
				),
			),
			'catalog'    => array(
				'label'  => __( 'Chế độ catalogue', 'saha-core' ),
				'fields' => array(
					'enabled' => array(
						'type'    => 'toggle',
						'label'   => __( 'Bật chế độ catalogue', 'saha-core' ),
						'default' => true,
						'help'    => __( 'Ẩn giá và giỏ hàng, thay bằng nút báo giá. Cùng một cài đặt với SAHA → Cấu hình.', 'saha-core' ),
						// Một nguồn sự thật: đọc/ghi thẳng cài đặt kinh doanh hiện có (TECHNICAL-DESIGN §5.4).
						'storage' => 'saha_core_settings.catalogue_mode',
					),
				),
			),
			'floating'   => array(
				'label'  => __( 'Nút nổi', 'saha-core' ),
				'fields' => array(
					'contact'     => array(
						'type'    => 'toggle',
						'label'   => __( 'Nút liên hệ nổi (Gọi · Zalo · Báo giá)', 'saha-core' ),
						'default' => false,
						'help'    => __( 'Hotline và Zalo lấy từ SAHA → Cấu hình.', 'saha-core' ),
					),
					'back_to_top' => array(
						'type'    => 'toggle',
						'label'   => __( 'Nút lên đầu trang', 'saha-core' ),
						'default' => false,
					),
					'position'    => array(
						'type'    => 'select',
						'label'   => __( 'Vị trí', 'saha-core' ),
						'default' => 'right',
						'options' => array(
							'right' => __( 'Góc phải', 'saha-core' ),
							'left'  => __( 'Góc trái', 'saha-core' ),
						),
					),
					'mobile'      => array(
						'type'    => 'toggle',
						'label'   => __( 'Hiện cả trên điện thoại', 'saha-core' ),
						'default' => false,
						'help'    => __( 'Tắt khi theme đã có thanh liên hệ dính ở chân màn hình điện thoại (saha-theme).', 'saha-core' ),
					),
				),
			),
			'custom_css' => array(
				'label'  => __( 'CSS tuỳ chỉnh', 'saha-core' ),
				'fields' => array(
					'css' => array(
						'type'       => 'css',
						'label'      => __( 'CSS', 'saha-core' ),
						'default'    => '',
						'help'       => __( 'Nạp sau toàn bộ CSS của theme. Cần quyền edit_css.', 'saha-core' ),
						'capability' => 'edit_css',
					),
				),
			),
		);

		/**
		 * Thêm/sửa nhóm và field Theme Options.
		 *
		 * @param array<string, array<string, mixed>> $groups Nhóm.
		 */
		return (array) apply_filters( 'saha_theme_options_schema', $groups );
	}

	/**
	 * Giá trị mặc định, lồng theo nhóm.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function defaults(): array {
		$out = array();

		foreach ( self::groups() as $group => $definition ) {
			foreach ( (array) $definition['fields'] as $key => $field ) {
				$out[ $group ][ $key ] = $field['default'] ?? null;
			}
		}

		return $out;
	}

	/**
	 * Định nghĩa một field.
	 *
	 * @param string $group Nhóm.
	 * @param string $key   Field.
	 * @return array<string, mixed>|null
	 */
	public static function field( string $group, string $key ): ?array {
		$groups = self::groups();

		return $groups[ $group ]['fields'][ $key ] ?? null;
	}

	/**
	 * Schema gửi cho ứng dụng admin (JSON-safe, đã dịch).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function forClient(): array {
		$out = array();

		foreach ( self::groups() as $group => $definition ) {
			$fields = array();

			foreach ( (array) $definition['fields'] as $key => $field ) {
				$client = array(
					'key'        => $key,
					'type'       => (string) $field['type'],
					'label'      => (string) ( $field['label'] ?? $key ),
					'help'       => (string) ( $field['help'] ?? '' ),
					'default'    => $field['default'] ?? null,
					'responsive' => ! empty( $field['responsive'] ),
				);

				foreach ( array( 'units', 'min', 'max' ) as $prop ) {
					if ( isset( $field[ $prop ] ) ) {
						$client[ $prop ] = $field[ $prop ];
					}
				}

				if ( isset( $field['options'] ) ) {
					$client['options'] = array();

					foreach ( (array) $field['options'] as $value => $label ) {
						$client['options'][] = array(
							'value' => (string) $value,
							'label' => (string) $label,
						);
					}
				}

				if ( 'typography' === $field['type'] ) {
					$client['fonts'] = array();

					foreach ( self::fontStacks() as $value => $font ) {
						$client['fonts'][] = array(
							'value' => (string) $value,
							'label' => (string) $font['label'],
						);
					}
				}

				// Field cần quyền riêng (CSS) → UI hiển thị nhưng khoá nếu thiếu quyền.
				$client['editable'] = empty( $field['capability'] ) || current_user_can( (string) $field['capability'] );

				$fields[] = $client;
			}

			$out[] = array(
				'key'    => $group,
				'label'  => (string) $definition['label'],
				'fields' => $fields,
			);
		}

		return $out;
	}
}
