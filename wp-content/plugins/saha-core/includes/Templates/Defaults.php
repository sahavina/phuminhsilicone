<?php
/**
 * Header/footer mặc định dựng bằng builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Templates;

use Saha\Core\Builder\LayoutService;

defined( 'ABSPATH' ) || exit;

/**
 * Defaults — tài liệu builder tái tạo header/footer PHP của saha-theme:
 *
 * Header: thanh trên (hotline, email, mạng xã hội — chỉ desktop, ẩn khi dính) ·
 * hàng chính desktop (logo · menu · tìm kiếm, hotline, giỏ) · hàng chính tablet/mobile
 * (☰ · logo · giỏ) · menu di động (tìm kiếm, menu dọc, nút gọi).
 * Footer: 4 cột (giới thiệu · menu · liên hệ · mạng xã hội) + dòng bản quyền.
 *
 * Không đặt ID: Sanitizer tự cấp khi lưu.
 */
final class Defaults {

	/**
	 * Tài liệu header.
	 *
	 * @return array<string, mixed>
	 */
	public static function header(): array {
		$zone = static fn( array $children, array $props = array() ): array => array(
			'type'     => 'header-zone',
			'props'    => $props,
			'children' => $children,
		);

		$desktop_only = array(
			'hideTablet' => true,
			'hideMobile' => true,
		);

		return array(
			'version'  => 1,
			'elements' => array(
				array(
					'type'     => 'site-header',
					'props'    => array( 'sticky' => 'always' ),
					'children' => array(
						array(
							'type'     => 'header-row',
							'props'    => array(
								'height'     => array( 'desktop' => '36px' ),
								'background' => 'var(--saha-secondary)',
								'textColor'  => '#ffffff',
								'hideSticky' => true,
							),
							'advanced' => $desktop_only,
							'children' => array(
								$zone(
									array(
										array(
											'type'  => 'contact',
											'props' => array( 'label' => __( 'Hotline:', 'saha-core' ) ),
										),
										array(
											'type'  => 'contact',
											'props' => array( 'kind' => 'email' ),
										),
									),
									array( 'gap' => array( 'desktop' => '24px' ) )
								),
								$zone( array() ),
								$zone(
									array(
										array(
											'type'  => 'social',
											'props' => array( 'size' => '18px' ),
										),
									)
								),
							),
						),
						array(
							'type'     => 'header-row',
							'props'    => array( 'height' => array( 'desktop' => '80px' ) ),
							'advanced' => $desktop_only,
							'children' => array(
								$zone( array( array( 'type' => 'logo' ) ) ),
								$zone(
									array(
										array(
											'type'  => 'nav-menu',
											'props' => array( 'location' => 'primary' ),
										),
									)
								),
								$zone(
									array(
										array(
											'type'  => 'search',
											'props' => array( 'width' => array( 'desktop' => '240px' ) ),
										),
										array(
											'type'  => 'contact',
											'props' => array(
												'typography' => array( 'fontWeight' => '700' ),
												'color'      => 'var(--saha-primary)',
											),
										),
										array( 'type' => 'cart' ),
									),
									array( 'gap' => array( 'desktop' => '16px' ) )
								),
							),
						),
						array(
							'type'     => 'header-row',
							'props'    => array( 'height' => array( 'desktop' => '64px' ) ),
							'advanced' => array( 'hideDesktop' => true ),
							'children' => array(
								$zone( array( array( 'type' => 'menu-toggle' ) ) ),
								$zone(
									array(
										array(
											'type'  => 'logo',
											'props' => array( 'height' => array( 'desktop' => '36px' ) ),
										),
									)
								),
								$zone( array( array( 'type' => 'cart' ) ) ),
							),
						),
						array(
							'type'     => 'header-offcanvas',
							'children' => array(
								array( 'type' => 'search' ),
								array(
									'type'  => 'nav-menu',
									'props' => array(
										'location'    => 'primary',
										'orientation' => 'vertical',
									),
								),
								array(
									'type'  => 'contact',
									'props' => array( 'style' => 'button' ),
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Tài liệu footer.
	 *
	 * @return array<string, mixed>
	 */
	public static function footer(): array {
		$muted   = 'rgba(255,255,255,.85)';
		$heading = static fn( string $text ): array => array(
			'type'  => 'heading',
			'props' => array(
				'text'       => $text,
				'tag'        => 'h3',
				'typography' => array( 'fontSize' => array( 'desktop' => '17px' ) ),
			),
		);

		$intro = get_bloginfo( 'description' );
		$intro = '' !== $intro ? $intro : __( 'Tổng kho keo dán công nghiệp chính hãng — tư vấn kỹ thuật và báo giá nhanh.', 'saha-core' );

		return array(
			'version'  => 1,
			'elements' => array(
				array(
					'type'     => 'section',
					'props'    => array(
						'background' => array( 'color' => 'var(--saha-secondary)' ),
						'textColor'  => '#ffffff',
						'tag'        => 'div',
					),
					'advanced' => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '48px',
								'bottom' => '40px',
							),
						),
					),
					'children' => array(
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '32px' ),
								'stackOn' => 'tablet',
							),
							'children' => array(
								array(
									'type'     => 'column',
									'props'    => array( 'width' => array( 'desktop' => '34%' ) ),
									'children' => array(
										array(
											'type'  => 'logo',
											'props' => array( 'height' => array( 'desktop' => '44px' ) ),
										),
										array(
											'type'  => 'text',
											'props' => array(
												'content' => '<p>' . esc_html( $intro ) . '</p>',
												'color'   => $muted,
											),
										),
									),
								),
								array(
									'type'     => 'column',
									'children' => array(
										$heading( __( 'Danh mục', 'saha-core' ) ),
										array(
											'type'  => 'nav-menu',
											'props' => array(
												'location'    => 'footer',
												'orientation' => 'vertical',
												'depth'       => 1,
												'ariaLabel'   => __( 'Menu chân trang', 'saha-core' ),
												'color'       => $muted,
											),
										),
									),
								),
								array(
									'type'     => 'column',
									'children' => array(
										$heading( __( 'Liên hệ', 'saha-core' ) ),
										array(
											'type'  => 'contact',
											'props' => array(
												'label' => __( 'Miền Bắc:', 'saha-core' ),
												'color' => $muted,
											),
										),
										array(
											'type'  => 'contact',
											'props' => array(
												'kind'  => 'phone_south',
												'label' => __( 'Miền Nam:', 'saha-core' ),
												'color' => $muted,
											),
										),
										array(
											'type'  => 'contact',
											'props' => array(
												'kind'  => 'email',
												'color' => $muted,
											),
										),
									),
								),
								array(
									'type'     => 'column',
									'children' => array(
										$heading( __( 'Kết nối', 'saha-core' ) ),
										array(
											'type'  => 'social',
											'props' => array( 'color' => '#ffffff' ),
										),
									),
								),
							),
						),
					),
				),
				array(
					'type'     => 'section',
					'props'    => array(
						'background' => array( 'color' => '#111827' ),
						'textColor'  => $muted,
						'tag'        => 'div',
					),
					'advanced' => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '14px',
								'bottom' => '14px',
							),
						),
					),
					'children' => array(
						array(
							'type'  => 'copyright',
							'props' => array( 'typography' => array( 'fontSize' => array( 'desktop' => '14px' ) ) ),
						),
					),
				),
			),
		);
	}

	/**
	 * Tài liệu khởi đầu khi tạo template trống.
	 *
	 * @param string $type header | footer.
	 * @return array<string, mixed>
	 */
	public static function starter( string $type ): array {
		$content = self::contentStarter( $type );

		if ( null !== $content ) {
			return $content;
		}

		if ( 'header' !== $type ) {
			return array(
				'version'  => 1,
				'elements' => array(),
			);
		}

		return array(
			'version'  => 1,
			'elements' => array(
				array(
					'type'     => 'site-header',
					'children' => array(
						array(
							'type'     => 'header-row',
							'children' => array(
								array( 'type' => 'header-zone' ),
								array( 'type' => 'header-zone' ),
								array( 'type' => 'header-zone' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Mẫu cho template nội dung (mốc 2.2) — tái tạo bố cục PHP hiện tại của saha-theme.
	 *
	 * Trang sản phẩm dùng hai element "hook" (thông tin + phần dưới) để giữ mọi phần
	 * theme/plugin gắn vào trang sản phẩm (CTA báo giá, thông số, cùng thương hiệu…);
	 * muốn bố cục khác thì thay bằng element lẻ (Giá, Thêm vào giỏ, Tab…).
	 *
	 * @param string $type Loại.
	 * @return array<string, mixed>|null
	 */
	private static function contentStarter( string $type ): ?array {
		$section = static fn( array $children, array $props = array() ): array => array(
			'type'     => 'section',
			'props'    => $props,
			'advanced' => array(
				'padding' => array(
					'desktop' => array(
						'top'    => '32px',
						'bottom' => '48px',
					),
					'mobile'  => array(
						'top'    => '20px',
						'bottom' => '32px',
					),
				),
			),
			'children' => $children,
		);
		$el      = static fn( string $element, array $props = array() ): array => array(
			'type'  => $element,
			'props' => $props,
		);
		$narrow  = array( 'contentWidth' => '800px' );

		$elements = match ( $type ) {
			'single_product'  => array(
				$section(
					array(
						$el( 'breadcrumb' ),
						array(
							'type'     => 'row',
							'props'    => array( 'gap' => array( 'desktop' => '40px' ) ),
							'children' => array(
								array(
									'type'     => 'column',
									'props'    => array( 'width' => array( 'desktop' => '50%' ) ),
									'children' => array( $el( 'product-gallery' ) ),
								),
								array(
									'type'     => 'column',
									'children' => array( $el( 'product-summary' ) ),
								),
							),
						),
						$el( 'product-after-summary' ),
					)
				),
			),
			'product_archive' => array(
				$section(
					array(
						$el( 'breadcrumb' ),
						$el( 'archive-title' ),
						$el( 'product-archive' ),
					)
				),
			),
			'single_post'     => array(
				$section(
					array(
						$el( 'breadcrumb' ),
						$el( 'post-title' ),
						$el( 'post-meta' ),
						$el( 'featured-image' ),
						$el( 'post-content' ),
					),
					$narrow
				),
			),
			'page'            => array(
				$section(
					array(
						$el( 'breadcrumb' ),
						$el( 'post-title' ),
						$el( 'post-content' ),
					)
				),
			),
			'archive', 'search' => array(
				$section(
					array(
						$el( 'breadcrumb' ),
						$el( 'archive-title' ),
						$el( 'archive-posts' ),
					)
				),
			),
			'404'             => array(
				$section(
					array(
						$el(
							'heading',
							array(
								'text' => __( 'Không tìm thấy trang bạn cần', 'saha-core' ),
								'tag'  => 'h1',
							)
						),
						$el( 'text', array( 'content' => '<p>' . esc_html__( 'Thử tìm theo tên hoặc mã sản phẩm.', 'saha-core' ) . '</p>' ) ),
						$el( 'search', array( 'products' => true ) ),
						$el( 'spacer', array( 'height' => array( 'desktop' => '24px' ) ) ),
						$el(
							'button',
							array(
								'text' => __( 'Về trang chủ', 'saha-core' ),
								'link' => array( 'url' => home_url( '/' ) ),
							)
						),
					),
					$narrow
				),
			),
			default           => null,
		};

		return null === $elements ? null : array(
			'version'  => 1,
			'elements' => $elements,
		);
	}

	/**
	 * Tạo một template.
	 *
	 * @param string              $type     header | footer.
	 * @param string              $title    Tên.
	 * @param array<string,mixed> $document Tài liệu.
	 * @return int|\WP_Error
	 */
	public static function create( string $type, string $title, array $document ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Repository::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => array(
					Repository::TYPE_META => $type,
					Repository::COND_META => (string) wp_json_encode(
						array(
							'include' => array(),
							'exclude' => array(),
						)
					),
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = LayoutService::save( (int) $post_id, $document, '' );

		if ( 'saved' !== $result['status'] ) {
			wp_delete_post( (int) $post_id, true );

			return new \WP_Error( 'saha_template_invalid', __( 'Không tạo được template.', 'saha-core' ), $result['errors'] ?? array() );
		}

		return (int) $post_id;
	}

	/**
	 * Tạo header + footer mặc định; dùng cho toàn site nếu loại đó chưa có template nào đang dùng.
	 *
	 * @return array<string, int|\WP_Error>
	 */
	public static function install(): array {
		$map = get_option( Repository::MAP_OPTION, array() );
		$out = array();

		foreach ( array(
			'header' => array( __( 'Header mặc định', 'saha-core' ), self::header() ),
			'footer' => array( __( 'Footer mặc định', 'saha-core' ), self::footer() ),
		) as $type => $spec ) {
			$id = self::create( $type, $spec[0], $spec[1] );

			if ( ! is_wp_error( $id ) && empty( $map[ $type ]['all'] ) ) {
				Repository::activate( $id );
			}

			$out[ $type ] = $id;
		}

		return $out;
	}
}
