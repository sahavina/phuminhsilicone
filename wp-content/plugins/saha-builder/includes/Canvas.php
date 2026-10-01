<?php
/**
 * Canvas của builder: trang frontend tối giản nạp CSS thật trong iframe.
 *
 * @package Saha\Builder
 */

declare( strict_types=1 );

namespace Saha\Builder;

use Saha\Builder\Admin\BuilderScreen;
use Saha\Core\Builder\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Canvas.
 *
 * `{permalink}?saha_builder_canvas={id}&_wpnonce=…` → HTML có wp_head() của theme
 * (Theme Options global.css, CSS theme, builder.css) và một vùng trống `#saha-canvas`.
 * Ứng dụng builder (cùng origin) chèn HTML do REST `/builder/render` trả về vào đó
 * → canvas và frontend dùng CÙNG renderer và CÙNG CSS (rủi ro R3).
 */
final class Canvas {

	public const QUERY_VAR = 'saha_builder_canvas';

	/**
	 * Gắn hook (chỉ khi request là canvas).
	 */
	public function register(): void {
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce kiểm trong render().
			return;
		}

		// Trước _wp_admin_bar_init (template_redirect, priority 0).
		add_filter( 'show_admin_bar', '__return_false' );
		add_filter( 'saha_builder_enqueue_layout_css', '__return_false' );
		add_action( 'template_redirect', array( $this, 'render' ), 1 );
	}

	/**
	 * URL canvas của một post.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function url( int $post_id ): string {
		return add_query_arg(
			array(
				self::QUERY_VAR => $post_id,
				'_wpnonce'      => wp_create_nonce( self::QUERY_VAR . '_' . $post_id ),
			),
			// Block (không public) không có URL riêng → dùng trang chủ; canvas không phụ thuộc truy vấn chính.
			is_post_type_viewable( (string) get_post_type( $post_id ) ) ? (string) get_permalink( $post_id ) : home_url( '/' )
		);
	}

	/**
	 * In canvas và dừng.
	 */
	public function render(): void {
		$post_id = absint( wp_unslash( $_GET[ self::QUERY_VAR ] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- kiểm ngay dưới.
		$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, self::QUERY_VAR . '_' . $post_id ) || ! BuilderScreen::canEdit( $post_id ) ) {
			wp_die( esc_html__( 'Bạn không có quyền xem canvas này.', 'saha-builder' ), 403 );
		}

		status_header( 200 );
		nocache_headers();
		send_frame_options_header();

		if ( class_exists( Frontend::class ) ) {
			Frontend::enqueueBase();
		}

		add_filter(
			'body_class',
			static function ( array $classes ) use ( $post_id ): array {
				$classes[] = 'saha-builder-canvas';
				$classes[] = 'saha-builder-page';

				/**
				 * Class thêm cho <body> của canvas (ví dụ template trang sản phẩm cần `woocommerce`).
				 *
				 * @param string[] $classes Class.
				 * @param int      $post_id Bài đang dựng.
				 */
				return (array) apply_filters( 'saha_builder_canvas_body_class', $classes, $post_id );
			}
		);

		/**
		 * Class thêm cho vùng #saha-canvas (ví dụ `product` để CSS `div.product …` của WooCommerce áp vào).
		 *
		 * @param string[] $classes Class.
		 * @param int      $post_id Bài đang dựng.
		 */
		$canvas_classes = (array) apply_filters( 'saha_builder_canvas_classes', array( 'saha-builder-content', 'saha-builder-' . $post_id ), $post_id );

		?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<main class="saha-main">
	<div id="saha-canvas" class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $canvas_classes ) ) ); ?>"></div>
</main>
<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
}
