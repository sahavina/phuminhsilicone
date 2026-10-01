<?php
/**
 * Bộ icon SVG của builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

defined( 'ABSPATH' ) || exit;

/**
 * Icons — SVG nội tuyến dạng nét (stroke 24×24), kế thừa màu chữ (currentColor).
 *
 * Không tải font icon: không có request thêm, không nháy chữ, không CLS.
 * Thêm icon: filter `saha_builder_icons` → [ name => [ label, inner SVG ] ].
 */
final class Icons {

	/**
	 * Danh sách icon.
	 *
	 * @var array<string, array{0: string, 1: string}>|null
	 */
	private static ?array $icons = null;

	/**
	 * Mọi icon.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function all(): array {
		if ( null === self::$icons ) {
			$icons = array(
				'check'         => array( __( 'Dấu tích', 'saha-core' ), '<path d="M20 6 9 17l-5-5"/>' ),
				'check-circle'  => array( __( 'Tích tròn', 'saha-core' ), '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>' ),
				'x'             => array( __( 'Dấu X', 'saha-core' ), '<path d="M18 6 6 18M6 6l12 12"/>' ),
				'phone'         => array( __( 'Điện thoại', 'saha-core' ), '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>' ),
				'mail'          => array( __( 'Email', 'saha-core' ), '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>' ),
				'message'       => array( __( 'Tin nhắn', 'saha-core' ), '<path d="M4 5h16v11H9l-5 4z"/>' ),
				'headset'       => array( __( 'Tư vấn', 'saha-core' ), '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="14" width="4" height="6" rx="1"/><rect x="17" y="14" width="4" height="6" rx="1"/>' ),
				'map-pin'       => array( __( 'Địa điểm', 'saha-core' ), '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>' ),
				'clock'         => array( __( 'Đồng hồ', 'saha-core' ), '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>' ),
				'truck'         => array( __( 'Giao hàng', 'saha-core' ), '<path d="M3 6h11v10H3zM14 9h4l3 3v4h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>' ),
				'package'       => array( __( 'Kiện hàng', 'saha-core' ), '<path d="m3 7 9-4 9 4v10l-9 4-9-4z"/><path d="m3 7 9 4 9-4M12 11v10"/>' ),
				'shield'        => array( __( 'Bảo hành', 'saha-core' ), '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>' ),
				'award'         => array( __( 'Chứng nhận', 'saha-core' ), '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 21l5-3 5 3-1.5-7"/>' ),
				'star'          => array( __( 'Ngôi sao', 'saha-core' ), '<path d="m12 3 2.8 5.8 6.2.9-4.5 4.4 1.1 6.1L12 17.3l-5.6 2.9 1.1-6.1L3 9.7l6.2-.9z"/>' ),
				'heart'         => array( __( 'Trái tim', 'saha-core' ), '<path d="M12 20s-8-4.6-8-10.2A4.8 4.8 0 0 1 12 7a4.8 4.8 0 0 1 8 2.8C20 15.4 12 20 12 20z"/>' ),
				'tag'           => array( __( 'Nhãn giá', 'saha-core' ), '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>' ),
				'percent'       => array( __( 'Khuyến mại', 'saha-core' ), '<path d="M19 5 5 19"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/>' ),
				'cart'          => array( __( 'Giỏ hàng', 'saha-core' ), '<circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2 3h3l2.6 12.2a1 1 0 0 0 1 .8h9.6a1 1 0 0 0 1-.8L21 7H6"/>' ),
				'user'          => array( __( 'Người dùng', 'saha-core' ), '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>' ),
				'users'         => array( __( 'Khách hàng', 'saha-core' ), '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-5.5 7-5.5s7 2 7 5.5M16 4.5a3.5 3.5 0 0 1 0 7M22 20c0-2.6-1.6-4.4-4-5.1"/>' ),
				'search'        => array( __( 'Tìm kiếm', 'saha-core' ), '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>' ),
				'arrow-right'   => array( __( 'Mũi tên phải', 'saha-core' ), '<path d="M5 12h14M13 6l6 6-6 6"/>' ),
				'chevron-right' => array( __( 'Dấu >', 'saha-core' ), '<path d="m9 6 6 6-6 6"/>' ),
				'play'          => array( __( 'Phát', 'saha-core' ), '<path d="M8 5.5v13l11-6.5z" fill="currentColor"/>' ),
				'info'          => array( __( 'Thông tin', 'saha-core' ), '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>' ),
				'wrench'        => array( __( 'Kỹ thuật', 'saha-core' ), '<path d="M14.5 5.5a4 4 0 0 0 5 5L11 19a2.1 2.1 0 0 1-3-3z"/>' ),
				'zap'           => array( __( 'Nhanh', 'saha-core' ), '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>' ),
				'droplet'       => array( __( 'Giọt keo', 'saha-core' ), '<path d="M12 3s6 6.5 6 11a6 6 0 0 1-12 0c0-4.5 6-11 6-11z"/>' ),
				'thermometer'   => array( __( 'Nhiệt độ', 'saha-core' ), '<path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0z"/>' ),
				'layers'        => array( __( 'Lớp', 'saha-core' ), '<path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/>' ),
				'factory'       => array( __( 'Nhà máy', 'saha-core' ), '<path d="M3 21V10l6 4v-4l6 4V6h6v15z"/>' ),
				'home'          => array( __( 'Trang chủ', 'saha-core' ), '<path d="m3 11 9-7 9 7M5 10v10h14V10"/>' ),
				'file-text'     => array( __( 'Tài liệu', 'saha-core' ), '<path d="M6 3h9l4 4v14H6zM15 3v4h4M9 12h6M9 16h6"/>' ),
				'download'      => array( __( 'Tải về', 'saha-core' ), '<path d="M12 4v11M7 10l5 5 5-5M5 20h14"/>' ),
				'menu'          => array( __( 'Menu', 'saha-core' ), '<path d="M3 6h18M3 12h18M3 18h18"/>' ),
				'close'         => array( __( 'Đóng', 'saha-core' ), '<path d="M6 6l12 12M18 6 6 18"/>' ),
				// Mạng xã hội (vẽ đơn giản, không phải logo chính thức).
				'facebook'      => array( 'Facebook', '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v7h4v-7h3l1-4h-4V8z"/>' ),
				'zalo'          => array( 'Zalo', '<path d="M4 5h16v11H10l-5 4v-4H4z"/><path d="M9 8.5h5l-5 5h5"/>' ),
				'youtube'       => array( 'YouTube', '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>' ),
				'tiktok'        => array( 'TikTok', '<path d="M14 3v11.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 3c.8 2.6 2.8 4.4 6 4.5"/>' ),
				'instagram'     => array( 'Instagram', '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><path d="M17.5 6.5h.01"/>' ),
				'linkedin'      => array( 'LinkedIn', '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 11v5M8 8h.01M12 16v-5M12 13a2 2 0 0 1 4 0v3"/>' ),
			);

			/**
			 * Thêm icon (SVG 24×24, dạng nét).
			 *
			 * @param array<string, array{0: string, 1: string}> $icons name => [ nhãn, nội dung SVG ].
			 */
			self::$icons = (array) apply_filters( 'saha_builder_icons', $icons );
		}

		return self::$icons;
	}

	/**
	 * Có icon này không.
	 *
	 * @param string $name Tên.
	 */
	public static function has( string $name ): bool {
		return isset( self::all()[ $name ] );
	}

	/**
	 * SVG hoàn chỉnh (trang trí → aria-hidden).
	 *
	 * @param string $name  Tên.
	 * @param string $class Class thêm.
	 */
	public static function svg( string $name, string $class = '' ): string {
		$icon = self::all()[ $name ] ?? null;

		if ( null === $icon ) {
			return '';
		}

		return '<svg class="saha-icon ' . esc_attr( $class ) . '" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icon[1] . '</svg>';
	}
}
