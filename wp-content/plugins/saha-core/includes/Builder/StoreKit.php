<?php
/**
 * Bộ giao diện "kiểu cửa hàng" (D4): trang chủ + header + footer + bộ màu, dựng bằng builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Roles;
use Saha\Core\Templates\Defaults as TemplateDefaults;
use Saha\Core\Templates\Repository as Templates;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * StoreKit — bố cục theo mẫu cửa hàng (header 3 tầng, hero navy, danh mục, sản phẩm có tab,
 * banner, cam kết, giải pháp, thương hiệu, tin tức + đánh giá, hỏi đáp, footer 4 cột), nội dung
 * và thương hiệu SAHA. Không sao chép chữ/ảnh/logo của mẫu.
 *
 * Áp dụng: Trang → Tất cả trang → "Áp dụng giao diện kiểu cửa hàng", hoặc `wp saha starter-store`.
 * Theme Options cũ được lưu lại (option `saha_theme_options_before_store`) để khôi phục.
 */
final class StoreKit {

	public const ACTION = 'saha_store_kit';
	public const BACKUP = 'saha_theme_options_before_store';

	/**
	 * Gắn hook admin.
	 */
	public function register(): void {
		add_filter( 'views_edit-page', array( $this, 'button' ), 11 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Quyền: dựng trang + header/footer + đổi Theme Options và trang chủ.
	 */
	public static function allowed(): bool {
		return current_user_can( Roles::CAP_BUILDER ) && current_user_can( Roles::CAP_TEMPLATES ) && current_user_can( 'manage_options' ) && current_user_can( 'edit_theme_options' );
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
				'<p><a class="button" href="%1$s" onclick="return confirm(%2$s);">%3$s</a></p>',
				esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION ), self::ACTION ) ),
				esc_attr( (string) wp_json_encode( __( 'Tạo trang chủ, header, footer, trang danh mục kiểu cửa hàng và đổi bộ màu/font trong Theme Options? Theme Options hiện tại được lưu lại để khôi phục.', 'saha-core' ) ) ),
				esc_html__( 'Áp dụng giao diện kiểu cửa hàng', 'saha-core' )
			);
		}

		return $views;
	}

	/**
	 * Áp dụng từ admin → mở trang chủ mới trong builder.
	 */
	public function handle(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Bạn không có quyền thực hiện thao tác này.', 'saha-core' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$result = self::install( true, true );

		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ) );
		}

		wp_safe_redirect( add_query_arg( array( 'page' => 'saha-builder', 'post' => $result['homepage'] ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Tạo header, footer (dùng cho toàn site), trang chủ; tuỳ chọn đổi bộ màu/font.
	 *
	 * @param bool $front   Đặt trang mới làm trang chủ.
	 * @param bool $palette Đổi Theme Options sang bộ màu/font/thẻ kiểu cửa hàng.
	 * @return array{header: int, footer: int, archive: int, homepage: int, palette: bool}|\WP_Error
	 */
	public static function install( bool $front, bool $palette ) {
		$header = TemplateDefaults::create( 'header', __( 'Header kiểu cửa hàng', 'saha-core' ), TemplateDefaults::headerStore() );

		if ( is_wp_error( $header ) ) {
			return $header;
		}

		$footer = TemplateDefaults::create( 'footer', __( 'Footer kiểu cửa hàng', 'saha-core' ), self::footer() );

		if ( is_wp_error( $footer ) ) {
			return $footer;
		}

		$archive = TemplateDefaults::create( 'product_archive', __( 'Shop & danh mục kiểu cửa hàng', 'saha-core' ), self::archive() );

		if ( is_wp_error( $archive ) ) {
			return $archive;
		}

		Templates::activate( (int) $header );
		Templates::activate( (int) $footer );
		Templates::activate( (int) $archive );

		$page = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => $front ? 'publish' : 'draft',
				'post_title'  => __( 'Trang chủ (kiểu cửa hàng)', 'saha-core' ),
			),
			true
		);

		if ( is_wp_error( $page ) ) {
			return $page;
		}

		$saved = LayoutService::save( (int) $page, self::homepage(), '' );

		if ( 'saved' !== $saved['status'] ) {
			wp_delete_post( (int) $page, true );

			return new \WP_Error( 'saha_store_kit', __( 'Không tạo được trang chủ kiểu cửa hàng.', 'saha-core' ), $saved['errors'] ?? array() );
		}

		if ( $front ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', (int) $page );
		}

		if ( $palette ) {
			self::applyOptions();
		}

		return array(
			'header'   => (int) $header,
			'footer'   => (int) $footer,
			'archive'  => (int) $archive,
			'homepage' => (int) $page,
			'palette'  => $palette,
		);
	}

	/**
	 * Bộ màu navy + vàng đồng, font Be Vietnam Pro, thẻ sản phẩm kiểu cửa hàng, nút nổi.
	 * Lưu Theme Options hiện tại trước khi đổi (một bản, không ghi đè bản đã lưu).
	 */
	public static function applyOptions(): void {
		$options = ThemeOptions::all();

		if ( false === get_option( self::BACKUP, false ) ) {
			add_option( self::BACKUP, $options, '', false );
		}

		$options['colors'] = array_merge(
			(array) ( $options['colors'] ?? array() ),
			self::palette()
		);

		foreach ( array( 'body', 'heading', 'menu', 'button' ) as $group ) {
			$options['typography'][ $group ]['fontFamily'] = 'be-vietnam-pro';
		}

		$options['shop']['card_style'] = 'store';
		$options['floating']           = array_merge(
			(array) ( $options['floating'] ?? array() ),
			array(
				'contact'     => true,
				'back_to_top' => true,
			)
		);

		ThemeOptions::save( $options );
	}

	/**
	 * Khôi phục Theme Options trước khi áp bộ cửa hàng.
	 */
	public static function restoreOptions(): bool {
		$backup = get_option( self::BACKUP, false );

		if ( ! is_array( $backup ) ) {
			return false;
		}

		ThemeOptions::save( $backup );
		delete_option( self::BACKUP );

		return true;
	}

	/**
	 * Bộ màu.
	 *
	 * @return array<string, string>
	 */
	public static function palette(): array {
		return array(
			'primary'    => '#13294b',
			'secondary'  => '#0e1f3a',
			'accent'     => '#d4a33b',
			'text'       => '#14213a',
			'heading'    => '#13294b',
			'border'     => '#e4e7ec',
			'background' => '#ffffff',
			'surface'    => '#f4f6fa',
			'muted'      => '#667085',
			'error'      => '#d0021b',
		);
	}

	/**
	 * Section có padding chuẩn.
	 *
	 * @param array<int, array<string, mixed>> $children Con.
	 * @param array<string, mixed>             $props    Thiết lập section.
	 * @return array<string, mixed>
	 */
	private static function section( array $children, array $props = array() ): array {
		return array(
			'type'     => 'section',
			'props'    => $props,
			'advanced' => array(
				'padding' => array(
					'desktop' => array(
						'top'    => '56px',
						'bottom' => '56px',
					),
					'mobile'  => array(
						'top'    => '36px',
						'bottom' => '36px',
					),
				),
			),
			'children' => $children,
		);
	}

	/**
	 * Tiêu đề khối.
	 *
	 * @param string $title Tiêu đề.
	 * @param string $url   Link "Xem tất cả".
	 * @param string $align left | center.
	 * @return array<string, mixed>
	 */
	private static function title( string $title, string $url = '', string $align = 'left' ): array {
		$props = array(
			'title' => $title,
			'align' => $align,
		);

		if ( '' !== $url ) {
			$props['link'] = array( 'url' => $url );
		} else {
			$props['linkText'] = '';
		}

		return array(
			'type'  => 'section-title',
			'props' => $props,
		);
	}

	/**
	 * Cột.
	 *
	 * @param array<int, array<string, mixed>> $children Con.
	 * @param string                           $width    Độ rộng desktop.
	 * @return array<string, mixed>
	 */
	private static function column( array $children, string $width = '' ): array {
		return array(
			'type'     => 'column',
			'props'    => '' !== $width ? array( 'width' => array( 'desktop' => $width ) ) : array(),
			'children' => $children,
		);
	}

	/**
	 * Hộp icon dạng thẻ.
	 *
	 * @param string $icon  Icon.
	 * @param string $title Tiêu đề.
	 * @param string $text  Mô tả.
	 * @return array<string, mixed>
	 */
	private static function reason( string $icon, string $title, string $text ): array {
		return self::column(
			array(
				array(
					'type'  => 'iconbox',
					'props' => array(
						'icon'        => $icon,
						'title'       => $title,
						'titleTag'    => 'h3',
						'description' => $text,
						'boxStyle'    => 'card',
						'iconColor'   => 'var(--saha-accent)',
						'align'       => array( 'desktop' => 'center' ),
						'titleTypo'   => array( 'fontSize' => array( 'desktop' => '16px' ) ),
					),
				),
			)
		);
	}

	/**
	 * Banner nhỏ (ô quảng bá).
	 *
	 * @param string $title Tiêu đề.
	 * @param string $text  Mô tả.
	 * @param string $label Chữ nút.
	 * @param string $url   Link.
	 * @return array<string, mixed>
	 */
	private static function promo( string $title, string $text, string $label, string $url ): array {
		return self::column(
			array(
				array(
					'type'  => 'banner',
					'props' => array(
						'title'        => $title,
						'titleTag'     => 'h3',
						'text'         => $text,
						'buttonText'   => $label,
						'buttonLink'   => array( 'url' => $url ),
						'minHeight'    => array( 'desktop' => '220px' ),
						'overlay'      => 'rgba(14,31,58,.92)',
						'contentWidth' => '100%',
						'hAlign'       => array( 'desktop' => 'left' ),
						'vAlign'       => 'center',
						'titleTypo'    => array( 'fontSize' => array( 'desktop' => '22px' ) ),
						'buttonStyle'  => 'accent',
					),
				),
			)
		);
	}

	/**
	 * URL trang theo slug, hoặc trang shop.
	 *
	 * @param string $slug Slug.
	 */
	private static function url( string $slug ): string {
		if ( 'shop' === $slug ) {
			return function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
		}

		$page = get_page_by_path( $slug );

		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : '';
	}

	/**
	 * Shop / danh mục kiểu cửa hàng (D5): tiêu đề trong khung kèm số sản phẩm + nhãn giao hàng,
	 * cột trái danh mục + khoảng giá + thương hiệu/ứng dụng/tình trạng, thanh "Đang hiện · Sắp xếp".
	 *
	 * @return array<string, mixed>
	 */
	public static function archive(): array {
		return array(
			'version'  => 1,
			'elements' => array(
				array(
					'type'     => 'section',
					'props'    => array(),
					'advanced' => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '24px',
								'bottom' => '56px',
							),
							'mobile'  => array(
								'top'    => '16px',
								'bottom' => '36px',
							),
						),
					),
					'children' => array(
						array(
							'type'  => 'breadcrumb',
							'props' => array(),
						),
						array(
							'type'  => 'archive-title',
							'props' => array(
								'style' => 'card',
								'badge' => __( 'Giao hàng toàn quốc', 'saha-core' ),
							),
						),
						array(
							'type'  => 'product-archive',
							'props' => array( 'layout' => 'sidebar' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Trang chủ kiểu cửa hàng.
	 *
	 * @return array<string, mixed>
	 */
	public static function homepage(): array {
		$shop    = self::url( 'shop' );
		$contact = self::url( 'lien-he' );
		$quote   = self::url( 'bao-gia' );
		$brands  = self::url( 'thuong-hieu' );
		$white   = '#ffffff';

		// Ảnh trượt ở hero: ảnh đại diện của tối đa 3 sản phẩm mới nhất (không có → hero một cột).
		// Không lấy ảnh bất kỳ trong Thư viện (có thể là logo, ảnh tài liệu…). Thay ảnh banner thật trong builder.
		$images = array();

		foreach ( get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_thumbnail_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- chạy một lần khi tạo mẫu.
			)
		) as $product_id ) {
			$images[] = (int) get_post_thumbnail_id( (int) $product_id );
		}

		$images = array_values( array_filter( $images ) );
		$slides = array();

		foreach ( $images as $i => $id ) {
			$slides[] = array(
				'type'  => 'slide',
				'props' => array(
					'image'    => array(
						'id'   => (int) $id,
						'size' => 'large',
					),
					'priority' => 0 === $i,
				),
			);
		}

		$hero_text = array(
			array(
				'type'  => 'text',
				'props' => array(
					'content'    => '<p>' . esc_html__( 'Keo dán chính hãng · Giao nhanh toàn quốc', 'saha-core' ) . '</p>',
					'color'      => 'var(--saha-accent)',
					'typography' => array(
						'fontSize'      => array( 'desktop' => '13px' ),
						'fontWeight'    => '700',
						'letterSpacing' => array( 'desktop' => '2px' ),
						'textTransform' => 'uppercase',
					),
				),
			),
			array(
				'type'  => 'heading',
				'props' => array(
					'text'       => __( 'Keo dán chuẩn kỹ thuật, bền cho mọi công trình', 'saha-core' ),
					'tag'        => 'h1',
					'color'      => $white,
					'typography' => array(
						'fontSize'      => array(
							'desktop' => '36px',
							'mobile'  => '26px',
						),
						'fontWeight'    => '800',
						'textTransform' => 'uppercase',
						'lineHeight'    => array( 'desktop' => '1.15' ),
					),
				),
			),
			array(
				'type'  => 'text',
				'props' => array(
					'content'    => '<p>' . esc_html__( 'Keo silicone, PU Foam, keo công nghiệp Loctite, keo AB, keo 502… cho nhà thầu, xưởng sản xuất và đại lý. Tư vấn chọn đúng loại keo cho vật liệu của bạn.', 'saha-core' ) . '</p>',
					'color'      => 'rgba(255,255,255,.85)',
					'typography' => array( 'fontSize' => array( 'desktop' => '17px' ) ),
				),
			),
			array(
				'type'  => 'icon-list',
				'props' => array(
					'items' => implode(
						"\n",
						array(
							__( 'Hàng chính hãng, đủ chứng từ', 'saha-core' ),
							__( 'Tư vấn kỹ thuật miễn phí', 'saha-core' ),
							__( 'Giá sỉ cho nhà thầu, đại lý', 'saha-core' ),
						)
					),
					'color' => $white,
				),
			),
			array(
				'type'  => 'spacer',
				'props' => array( 'height' => array( 'desktop' => '24px' ) ),
			),
			array(
				'type'     => 'container',
				'props'    => array(
					'direction' => 'row',
					'gap'       => array( 'desktop' => '12px' ),
					'wrap'      => true,
					'align'     => 'center',
				),
				'children' => array(
					array(
						'type'  => 'button',
						'props' => array(
							'text'      => __( 'Xem sản phẩm', 'saha-core' ),
							'link'      => array( 'url' => $shop ),
							'variant'   => 'accent',
							'size'      => 'lg',
						),
					),
					array(
						'type'  => 'button',
						'props' => array(
							'text'      => __( 'Nhận báo giá', 'saha-core' ),
							'link'      => array( 'url' => '' !== $quote ? $quote : $contact ),
							'variant'   => 'outline',
							'size'      => 'lg',
							'textColor' => $white,
						),
					),
				),
			),
		);

		$hero_columns = array( self::column( $hero_text, $slides ? '55%' : '' ) );

		if ( $slides ) {
			$hero_columns[] = self::column(
				array(
					array(
						'type'     => 'slider',
						'props'    => array(
							'radius' => '12px',
							'label'  => __( 'Hình ảnh nổi bật', 'saha-core' ),
						),
						'children' => $slides,
					),
				)
			);
		}

		$reasons = array(
			array( 'award', __( 'Chính hãng 100%', 'saha-core' ), __( 'Đủ chứng từ, nguồn gốc rõ ràng', 'saha-core' ) ),
			array( 'headset', __( 'Tư vấn kỹ thuật', 'saha-core' ), __( 'Chọn đúng keo cho từng vật liệu', 'saha-core' ) ),
			array( 'tag', __( 'Giá sỉ rõ ràng', 'saha-core' ), __( 'Báo giá trọn gói, không phí ẩn', 'saha-core' ) ),
			array( 'truck', __( 'Giao nhanh', 'saha-core' ), __( 'Giao hàng toàn quốc', 'saha-core' ) ),
			array( 'file-text', __( 'Hoá đơn VAT', 'saha-core' ), __( 'Xuất hoá đơn theo yêu cầu', 'saha-core' ) ),
			array( 'shield', __( 'Bảo đảm chất lượng', 'saha-core' ), __( 'Đổi hàng khi lỗi từ nhà sản xuất', 'saha-core' ) ),
			array( 'clock', __( 'Phản hồi nhanh', 'saha-core' ), __( 'Trả lời báo giá trong giờ làm việc', 'saha-core' ) ),
			array( 'users', __( 'Cho doanh nghiệp', 'saha-core' ), __( 'Nhà thầu, xưởng sản xuất, đại lý', 'saha-core' ) ),
		);

		$surface = array( 'background' => array( 'color' => 'var(--saha-surface)' ) );

		return array(
			'version'  => 1,
			'elements' => array(
				// 1. Hero.
				array(
					'type'     => 'section',
					'props'    => array(
						'minHeight'     => array(
							'desktop' => '520px',
							'mobile'  => '0px',
						),
						'verticalAlign' => 'center',
						'background'    => array( 'color' => 'var(--saha-secondary)' ),
						'textColor'     => $white,
					),
					'advanced' => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '56px',
								'bottom' => '56px',
							),
							'mobile'  => array(
								'top'    => '40px',
								'bottom' => '40px',
							),
						),
					),
					'children' => array(
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'           => array( 'desktop' => '48px' ),
								'verticalAlign' => array( 'desktop' => 'center' ),
							),
							'children' => $hero_columns,
						),
					),
				),
				// 2. Danh mục.
				self::section(
					array(
						self::title( __( 'Danh mục sản phẩm', 'saha-core' ), $shop ),
						array(
							'type'  => 'product-categories',
							'props' => array(
								'taxonomy'        => 'product_cat',
								'limit'           => 6,
								'showCount'       => false,
								'showDescription' => true,
								'columns'         => array(
									'desktop' => 6,
									'tablet'  => 3,
									'mobile'  => 2,
								),
								'gap'             => array( 'desktop' => '16px' ),
							),
						),
					)
				),
				// 3. Sản phẩm nổi bật, tab theo danh mục.
				self::section(
					array(
						self::title( __( 'Sản phẩm nổi bật', 'saha-core' ), $shop ),
						array(
							'type'  => 'products',
							'props' => array(
								'source'    => 'latest',
								'tabs'      => 'categories',
								'tabsLimit' => 6,
								'limit'     => 10,
								'columns'   => array(
									'desktop' => 5,
									'tablet'  => 3,
									'mobile'  => 2,
								),
								'gap'       => array( 'desktop' => '16px' ),
							),
						),
					)
				),
				// 4. Ba ô quảng bá.
				self::section(
					array(
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '20px' ),
								'stackOn' => 'tablet',
							),
							'children' => array(
								self::promo( __( 'Keo silicone', 'saha-core' ), __( 'Chống thấm, chịu thời tiết cho kính, nhôm, mái tôn', 'saha-core' ), __( 'Khám phá', 'saha-core' ), $shop ),
								self::promo( __( 'Keo công nghiệp', 'saha-core' ), __( 'Khoá ren, kín ren, dán nhanh cho xưởng cơ khí', 'saha-core' ), __( 'Xem ngay', 'saha-core' ), $shop ),
								self::promo( __( 'Báo giá số lượng lớn', 'saha-core' ), __( 'Giá sỉ cho nhà thầu, đại lý, xưởng sản xuất', 'saha-core' ), __( 'Nhận báo giá', 'saha-core' ), '' !== $quote ? $quote : $contact ),
							),
						),
					)
				),
				// 5. Vì sao chọn.
				self::section(
					array(
						self::title( __( 'Vì sao chọn SAHA?', 'saha-core' ), '', 'center' ),
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '16px' ),
								'stackOn' => 'mobile',
							),
							'children' => array_map( static fn( array $r ): array => self::reason( $r[0], $r[1], $r[2] ), array_slice( $reasons, 0, 4 ) ),
						),
						array(
							'type'  => 'spacer',
							'props' => array( 'height' => array( 'desktop' => '16px' ) ),
						),
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '16px' ),
								'stackOn' => 'mobile',
							),
							'children' => array_map( static fn( array $r ): array => self::reason( $r[0], $r[1], $r[2] ), array_slice( $reasons, 4, 4 ) ),
						),
					),
					$surface
				),
				// 6. Giải pháp theo nhu cầu (ứng dụng).
				self::section(
					array(
						self::title( __( 'Giải pháp theo nhu cầu', 'saha-core' ) ),
						array(
							'type'  => 'product-categories',
							'props' => array(
								'taxonomy'  => 'product_application',
								'limit'     => 6,
								'showCount' => false,
								'columns'   => array(
									'desktop' => 3,
									'tablet'  => 3,
									'mobile'  => 2,
								),
							),
						),
					)
				),
				// 7. Thương hiệu.
				self::section(
					array(
						self::title( __( 'Thương hiệu nổi bật', 'saha-core' ), $brands ),
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
								'gap'       => array( 'desktop' => '12px' ),
							),
						),
					),
					$surface
				),
				// 8. Tin tức + khách hàng nói.
				self::section(
					array(
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '32px' ),
								'stackOn' => 'tablet',
							),
							'children' => array(
								self::column(
									array(
										self::title( __( 'Kiến thức & kinh nghiệm', 'saha-core' ), self::url( 'blog' ) ),
										array(
											'type'  => 'posts',
											'props' => array(
												'limit'   => 4,
												'columns' => array(
													'desktop' => 2,
													'tablet'  => 2,
													'mobile'  => 1,
												),
											),
										),
									),
									'66%'
								),
								self::column(
									array(
										self::title( __( 'Khách hàng nói về chúng tôi', 'saha-core' ) ),
										array(
											'type'     => 'testimonials',
											'props'    => array(
												'columns' => array( 'desktop' => 1 ),
												'gap'     => array( 'desktop' => '12px' ),
											),
											// Đánh giá mẫu: phải thay bằng đánh giá thật trước khi đưa lên website.
											'children' => array(
												array(
													'type'  => 'testimonial',
													'props' => array(
														'quote' => __( '(Đánh giá mẫu — thay bằng ý kiến thật của khách hàng.)', 'saha-core' ),
														'name'  => __( 'Tên khách hàng', 'saha-core' ),
														'meta'  => __( 'Công ty / khu vực', 'saha-core' ),
													),
												),
												array(
													'type'  => 'testimonial',
													'props' => array(
														'quote' => __( '(Đánh giá mẫu — thay bằng ý kiến thật của khách hàng.)', 'saha-core' ),
														'name'  => __( 'Tên khách hàng', 'saha-core' ),
														'meta'  => __( 'Công ty / khu vực', 'saha-core' ),
													),
												),
												array(
													'type'  => 'testimonial',
													'props' => array(
														'quote' => __( '(Đánh giá mẫu — thay bằng ý kiến thật của khách hàng.)', 'saha-core' ),
														'name'  => __( 'Tên khách hàng', 'saha-core' ),
														'meta'  => __( 'Công ty / khu vực', 'saha-core' ),
													),
												),
											),
										),
									)
								),
							),
						),
					)
				),
				// 9. Câu hỏi thường gặp.
				self::section(
					array(
						self::title( __( 'Câu hỏi thường gặp', 'saha-core' ), '', 'center' ),
						array(
							'type'     => 'accordion',
							'children' => array(
								self::faq( __( 'Làm sao để nhận báo giá?', 'saha-core' ), __( 'Bấm "Yêu cầu báo giá" ở sản phẩm hoặc gọi hotline. Ghi rõ sản phẩm, số lượng và nơi giao để được báo giá nhanh.', 'saha-core' ), true ),
								self::faq( __( 'Chọn keo silicone trung tính hay gốc axit?', 'saha-core' ), __( 'Silicone trung tính dùng cho kính, nhôm, đá, bê tông; silicone gốc axit dùng cho kính thông thường, không dùng trên kim loại dễ ăn mòn. Liên hệ để được tư vấn theo vật liệu cụ thể.', 'saha-core' ) ),
								self::faq( __( 'Có giao hàng tận nơi không?', 'saha-core' ), __( '(Nội dung mẫu — điền chính sách giao hàng thực tế của cửa hàng.)', 'saha-core' ) ),
								self::faq( __( 'Có xuất hoá đơn VAT không?', 'saha-core' ), __( '(Nội dung mẫu — điền chính sách hoá đơn thực tế của cửa hàng.)', 'saha-core' ) ),
							),
						),
					),
					array( 'contentWidth' => '900px' )
				),
			),
		);
	}

	/**
	 * Một câu hỏi.
	 *
	 * @param string $question Câu hỏi.
	 * @param string $answer   Trả lời.
	 * @param bool   $open     Mở sẵn.
	 * @return array<string, mixed>
	 */
	private static function faq( string $question, string $answer, bool $open = false ): array {
		return array(
			'type'  => 'accordion-item',
			'props' => array(
				'title'   => $question,
				'content' => '<p>' . esc_html( $answer ) . '</p>',
				'open'    => $open,
			),
		);
	}

	/**
	 * Footer kiểu cửa hàng: 4 cột trên nền màu phụ + dòng bản quyền.
	 *
	 * @return array<string, mixed>
	 */
	public static function footer(): array {
		$muted   = 'rgba(255,255,255,.8)';
		$heading = static fn( string $text ): array => array(
			'type'  => 'heading',
			'props' => array(
				'text'       => $text,
				'tag'        => 'h3',
				'color'      => '#ffffff',
				'typography' => array(
					'fontSize'      => array( 'desktop' => '15px' ),
					'textTransform' => 'uppercase',
					'letterSpacing' => array( 'desktop' => '1px' ),
				),
			),
		);

		$intro = get_bloginfo( 'description' );
		$intro = '' !== $intro ? $intro : __( 'Tổng kho keo dán công nghiệp chính hãng — tư vấn kỹ thuật và báo giá nhanh.', 'saha-core' );

		$policy = '';
		foreach ( array( 'chinh-sach-bao-mat', 'lien-he', 'bao-gia' ) as $slug ) {
			$page = get_page_by_path( $slug );

			if ( $page instanceof \WP_Post ) {
				$policy .= '<li><a href="' . esc_url( (string) get_permalink( $page ) ) . '">' . esc_html( get_the_title( $page ) ) . '</a></li>';
			}
		}

		$columns = array(
			self::column(
				array(
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
					array(
						'type'  => 'social',
						'props' => array( 'size' => '20px' ),
					),
				),
				'' !== $policy ? '30%' : '34%'
			),
			self::column(
				array(
					$heading( __( 'Liên kết nhanh', 'saha-core' ) ),
					array(
						'type'  => 'nav-menu',
						'props' => array(
							'location'    => 'footer',
							'orientation' => 'vertical',
							'depth'       => 1,
							'ariaLabel'   => __( 'Liên kết nhanh', 'saha-core' ),
							'color'       => $muted,
						),
					),
				),
				'' !== $policy ? '20%' : '33%'
			),
		);

		if ( '' !== $policy ) {
			$columns[] = self::column(
				array(
					$heading( __( 'Thông tin', 'saha-core' ) ),
					array(
						'type'  => 'text',
						'props' => array(
							'content'   => '<ul>' . $policy . '</ul>',
							'color'     => $muted,
							'linkColor' => $muted,
						),
					),
				),
				'20%'
			);
		}

		$columns[] = self::column(
			array(
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
			'' !== $policy ? '30%' : '33%'
		);

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
								'top'    => '56px',
								'bottom' => '40px',
							),
						),
					),
					'children' => array(
						array(
							'type'     => 'row',
							'props'    => array(
								'gap'     => array( 'desktop' => '40px' ),
								'stackOn' => 'tablet',
							),
							'children' => $columns,
						),
					),
				),
				array(
					'type'     => 'section',
					'props'    => array(
						'background' => array( 'color' => '#0a1628' ),
						'textColor'  => $muted,
						'tag'        => 'div',
					),
					'advanced' => array(
						'padding' => array(
							'desktop' => array(
								'top'    => '16px',
								'bottom' => '16px',
							),
						),
					),
					'children' => array(
						array(
							'type'  => 'copyright',
							'props' => array( 'align' => array( 'desktop' => 'center' ) ),
						),
					),
				),
			),
		);
	}
}
