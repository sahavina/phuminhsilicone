<?php
/**
 * Layout mẫu dựng sẵn bằng builder (trang chủ SAHA).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Starter — trang chủ 14 khối (docs/PHASE-5.md, spec §18) dựng hoàn toàn bằng
 * element của builder, thay cho `docs/layouts/homepage.ux.txt` (UX Builder +
 * shortcode). Không có ảnh hay URL ảnh hardcode: ảnh nền hero chọn trong builder.
 *
 * Tạo bằng nút "Tạo trang chủ mẫu" ở Trang → Tất cả trang, hoặc
 * `wp saha homepage --front`.
 */
final class Starter {

	public const ACTION = 'saha_starter_homepage';

	/**
	 * Gắn hook admin.
	 */
	public function register(): void {
		add_filter( 'views_edit-page', array( $this, 'button' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Người dùng được tạo trang chủ mẫu (dựng trang + đổi trang chủ của site).
	 */
	public static function allowed(): bool {
		return current_user_can( Roles::CAP_BUILDER ) && current_user_can( 'manage_options' );
	}

	/**
	 * Nút trên danh sách Trang.
	 *
	 * @param array<string, string> $views View.
	 * @return array<string, string>
	 */
	public function button( $views ): array {
		$views = (array) $views;

		if ( self::allowed() ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION ) ),
				esc_html__( 'Tạo trang chủ mẫu (SAHA Builder)', 'saha-core' )
			);
		}

		return $views;
	}

	/**
	 * Tạo trang chủ mẫu → mở builder.
	 */
	public function handle(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'saha-core' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$id = self::installHomepage( true );

		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'saha-builder', 'post' => $id ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Tạo trang "Trang chủ" (bản nháp nếu không đặt làm trang chủ).
	 *
	 * @param bool $set_front Đặt làm trang chủ của site (Cài đặt → Đọc).
	 * @return int|\WP_Error ID trang.
	 */
	public static function installHomepage( bool $set_front ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => $set_front ? 'publish' : 'draft',
				'post_title'  => __( 'Trang chủ', 'saha-core' ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = LayoutService::save( (int) $post_id, self::homepage(), '' );

		if ( 'saved' !== $result['status'] ) {
			wp_delete_post( (int) $post_id, true );

			return new \WP_Error( 'saha_starter_invalid', __( 'Không tạo được trang chủ mẫu.', 'saha-core' ), $result['errors'] ?? array() );
		}

		if ( $set_front ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $post_id );
		}

		return (int) $post_id;
	}

	/**
	 * Tài liệu trang chủ.
	 *
	 * @return array<string, mixed>
	 */
	public static function homepage(): array {
		$surface = array( 'color' => 'var(--saha-surface)' );

		return array(
			'version'  => 1,
			'elements' => array(
				// 1. Hero: H1 duy nhất của trang + ô tìm kiếm (tìm theo mã: 243, A500…).
				array(
					'type'     => 'section',
					'props'    => array(
						'minHeight'     => array(
							'desktop' => '460px',
							'mobile'  => '360px',
						),
						'verticalAlign' => 'center',
						'background'    => array( 'color' => 'var(--saha-secondary)' ),
						'textColor'     => '#ffffff',
					),
					'children' => array(
						array(
							'type'     => 'row',
							'children' => array(
								array(
									'type'     => 'column',
									'props'    => array( 'width' => array( 'desktop' => '60%' ) ),
									'children' => array(
										array(
											'type'  => 'heading',
											'props' => array(
												'text'  => __( 'Tổng kho keo dán chính hãng', 'saha-core' ),
												'tag'   => 'h1',
												'color' => '#ffffff',
											),
										),
										array(
											'type'  => 'text',
											'props' => array(
												'content' => '<p>' . esc_html__( 'Keo silicone, PU Foam, keo công nghiệp Loctite, keo AB, keo 502… cho nhà thầu, xưởng sản xuất và đại lý toàn quốc.', 'saha-core' ) . '</p>',
												'typography' => array( 'fontSize' => array( 'desktop' => '18px' ) ),
											),
										),
										array(
											'type'  => 'search',
											'props' => array( 'products' => true ),
										),
									),
								),
							),
						),
					),
				),
				// 2. Danh mục chính.
				self::section(
					array(
						self::heading( __( 'Danh mục chính', 'saha-core' ) ),
						array(
							'type'  => 'product-categories',
							'props' => array(
								'taxonomy'  => 'product_cat',
								'limit'     => 8,
								'orderby'   => 'menu_order',
								'showCount' => false,
							),
						),
					)
				),
				// 3. Thương hiệu nổi bật.
				self::section(
					array(
						self::heading( __( 'Thương hiệu nổi bật', 'saha-core' ), self::pageUrl( 'thuong-hieu' ) ),
						array(
							'type'  => 'product-categories',
							'props' => array(
								'taxonomy'  => 'product_brand',
								'limit'     => 12,
								'orderby'   => 'count',
								'showCount' => false,
								'columns'   => array(
									'desktop' => 6,
									'tablet'  => 4,
									'mobile'  => 3,
								),
							),
						),
					),
					$surface
				),
				// 4–5. Nổi bật + mới.
				self::section(
					array_merge(
						self::products( __( 'Sản phẩm nổi bật', 'saha-core' ), array( 'source' => 'featured', 'orderby' => 'menu_order' ) ),
						array( self::spacer() ),
						self::products( __( 'Sản phẩm mới', 'saha-core' ), array( 'source' => 'latest' ) )
					)
				),
				// 6–10. Theo danh mục, CTA báo giá, theo thương hiệu.
				self::section(
					array_merge(
						self::products( __( 'Keo Silicone', 'saha-core' ), array( 'source' => 'category', 'category' => 'keo-silicone' ), __( 'Apollo, Bamboo, Wacker, Dowsil', 'saha-core' ) ),
						array( self::spacer() ),
						self::products( __( 'Keo công nghiệp', 'saha-core' ), array( 'source' => 'category', 'category' => 'keo-cong-nghiep' ), __( 'Khoá ren, kín ren, giữ đồng trục, dán nhanh, tạo gioăng', 'saha-core' ) ),
						array(
							self::spacer(),
							self::quoteCta(
								__( 'Cần báo giá số lượng lớn?', 'saha-core' ),
								__( 'Giá sỉ cho nhà thầu, đại lý và xưởng sản xuất. Phản hồi trong giờ làm việc.', 'saha-core' ),
								'var(--saha-primary)',
								'#ffffff'
							),
							self::spacer(),
						),
						self::products( __( 'PU Foam', 'saha-core' ), array( 'source' => 'category', 'category' => 'pu-foam' ) ),
						array( self::spacer() ),
						self::products( __( 'Keo Loctite', 'saha-core' ), array( 'source' => 'brand', 'brand' => 'loctite' ) )
					)
				),
				// 11. Ứng dụng.
				self::section(
					array(
						self::heading( __( 'Ứng dụng', 'saha-core' ) ),
						self::subtitle( __( 'Chọn keo theo công việc của bạn', 'saha-core' ) ),
						array(
							'type'  => 'product-categories',
							'props' => array(
								'taxonomy'  => 'product_application',
								'limit'     => 8,
								'showCount' => false,
							),
						),
					),
					$surface
				),
				// 12. Vì sao chọn SAHA.
				self::section(
					array(
						array(
							'type'  => 'heading',
							'props' => array(
								'text'  => __( 'Vì sao chọn SAHA', 'saha-core' ),
								'tag'   => 'h2',
								'align' => array( 'desktop' => 'center' ),
							),
						),
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '24px' ),
								'stackOn' => 'mobile',
							),
							'children' => array(
								self::reason( 'award', __( 'Hàng chính hãng', 'saha-core' ), __( 'Nhập trực tiếp từ nhà sản xuất và nhà phân phối uỷ quyền, có đầy đủ chứng từ.', 'saha-core' ) ),
								self::reason( 'headset', __( 'Tư vấn kỹ thuật', 'saha-core' ), __( 'Chọn đúng loại keo cho từng vật liệu và điều kiện thi công.', 'saha-core' ) ),
								self::reason( 'tag', __( 'Giá sỉ cạnh tranh', 'saha-core' ), __( 'Chính sách giá riêng cho nhà thầu, đại lý, xưởng sản xuất.', 'saha-core' ) ),
								self::reason( 'truck', __( 'Giao hàng toàn quốc', 'saha-core' ), __( 'Kho miền Bắc và miền Nam, giao nhanh tới công trình.', 'saha-core' ) ),
							),
						),
					)
				),
				// 13. Kiến thức & hướng dẫn.
				self::section(
					array(
						self::heading( __( 'Kiến thức & hướng dẫn', 'saha-core' ) ),
						array(
							'type'  => 'posts',
							'props' => array( 'limit' => 3 ),
						),
					)
				),
				// 14. CTA cuối trang.
				self::section(
					array(
						self::quoteCta(
							__( 'Chưa tìm thấy sản phẩm cần?', 'saha-core' ),
							__( 'Gửi yêu cầu, đội ngũ SAHA sẽ tìm và báo giá giúp bạn.', 'saha-core' ),
							'var(--saha-surface)',
							'var(--saha-heading)'
						),
					)
				),
			),
		);
	}

	/**
	 * Bỏ spacer thừa (đầu, cuối, liền nhau) khi có khối bị bỏ qua.
	 *
	 * @param array<int, array<string, mixed>> $children Con.
	 * @return array<int, array<string, mixed>>
	 */
	private static function tidy( array $children ): array {
		$out = array();

		foreach ( $children as $child ) {
			$is_spacer = 'spacer' === $child['type'];

			if ( $is_spacer && ( ! $out || 'spacer' === end( $out )['type'] ) ) {
				continue;
			}

			$out[] = $child;
		}

		while ( $out && 'spacer' === end( $out )['type'] ) {
			array_pop( $out );
		}

		return $out;
	}

	/**
	 * Section nội dung.
	 *
	 * @param array<int, array<string, mixed>> $children   Con.
	 * @param array<string, string>            $background Nền.
	 * @return array<string, mixed>
	 */
	private static function section( array $children, array $background = array() ): array {
		$props = $background ? array( 'background' => $background ) : array();

		return array(
			'type'     => 'section',
			'props'    => $props,
			'advanced' => array(
				'padding' => array(
					'desktop' => array(
						'top'    => '48px',
						'bottom' => '48px',
					),
					'mobile'  => array(
						'top'    => '32px',
						'bottom' => '32px',
					),
				),
			),
			'children' => self::tidy( $children ),
		);
	}

	/**
	 * Tiêu đề khối (H2 — H1 dành cho hero, spec §22).
	 *
	 * @param string $text Chữ.
	 * @param string $url  Link "xem tất cả" (tuỳ chọn).
	 * @return array<string, mixed>
	 */
	private static function heading( string $text, string $url = '' ): array {
		$props = array(
			'text' => $text,
			'tag'  => 'h2',
		);

		if ( '' !== $url ) {
			$props['link'] = array( 'url' => $url );
		}

		return array(
			'type'  => 'heading',
			'props' => $props,
		);
	}

	/**
	 * Dòng mô tả dưới tiêu đề.
	 *
	 * @param string $text Chữ.
	 * @return array<string, mixed>
	 */
	private static function subtitle( string $text ): array {
		return array(
			'type'  => 'text',
			'props' => array(
				'content' => '<p>' . esc_html( $text ) . '</p>',
				'color'   => 'var(--saha-muted)',
			),
		);
	}

	/**
	 * Tiêu đề + (mô tả) + lưới sản phẩm.
	 *
	 * @param string               $title    Tiêu đề.
	 * @param array<string, mixed> $props    Thiết lập element Sản phẩm.
	 * @param string               $subtitle Mô tả.
	 * @return array<int, array<string, mixed>>
	 */
	private static function products( string $title, array $props, string $subtitle = '' ): array {
		// Site chưa có danh mục/thương hiệu này → bỏ cả khối (term không tồn tại thì không lưu được).
		foreach ( array(
			'category' => 'product_cat',
			'brand'    => 'product_brand',
		) as $key => $taxonomy ) {
			if ( isset( $props[ $key ] ) && ( ! taxonomy_exists( $taxonomy ) || ! term_exists( (string) $props[ $key ], $taxonomy ) ) ) {
				return array();
			}
		}

		$out = array( self::heading( $title ) );

		if ( '' !== $subtitle ) {
			$out[] = self::subtitle( $subtitle );
		}

		$out[] = array(
			'type'  => 'products',
			'props' => $props + array(
				'orderby' => 'popularity',
				'limit'   => 8,
			),
		);

		return $out;
	}

	/**
	 * Khoảng cách giữa các khối trong cùng section.
	 *
	 * @return array<string, mixed>
	 */
	private static function spacer(): array {
		return array(
			'type'  => 'spacer',
			'props' => array(
				'height' => array(
					'desktop' => '40px',
					'mobile'  => '24px',
				),
			),
		);
	}

	/**
	 * CTA mở form báo giá tại chỗ.
	 *
	 * @param string $title Tiêu đề.
	 * @param string $text  Mô tả.
	 * @param string $bg    Nền.
	 * @param string $color Chữ.
	 * @return array<string, mixed>
	 */
	private static function quoteCta( string $title, string $text, string $bg, string $color ): array {
		return array(
			'type'  => 'cta',
			'props' => array(
				'title'      => $title,
				'text'       => $text,
				'button1'    => __( 'Yêu cầu báo giá', 'saha-core' ),
				'action1'    => 'quote',
				'background' => $bg,
				'textColor'  => $color,
			),
		);
	}

	/**
	 * Cột "Vì sao chọn SAHA".
	 *
	 * @param string $icon  Icon.
	 * @param string $title Tiêu đề.
	 * @param string $text  Mô tả.
	 * @return array<string, mixed>
	 */
	private static function reason( string $icon, string $title, string $text ): array {
		return array(
			'type'     => 'column',
			'children' => array(
				array(
					'type'  => 'iconbox',
					'props' => array(
						'icon'        => $icon,
						'title'       => $title,
						'titleTag'    => 'h3',
						'description' => $text,
						'align'       => array( 'desktop' => 'center' ),
					),
				),
			),
		);
	}

	/**
	 * URL trang theo slug (rỗng nếu chưa có).
	 *
	 * @param string $slug Slug.
	 */
	private static function pageUrl( string $slug ): string {
		$page = get_page_by_path( $slug );

		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : '';
	}
}
