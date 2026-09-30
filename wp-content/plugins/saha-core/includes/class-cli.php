<?php
/**
 * WP-CLI: wp saha qa | seed | unseed | maintenance
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Công cụ dòng lệnh cho QA và vận hành.
 */
final class Cli {

	/**
	 * Kiểm tra tự động cấu hình, database, quyền, REST, tìm kiếm, SEO, bảo mật, hiệu năng.
	 *
	 * Chỉ đọc dữ liệu — an toàn trên production. Thoát mã 1 nếu có mục "fail".
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table | json | csv
	 * ---
	 * default: table
	 * ---
	 *
	 * [--strict]
	 * : Coi "warn" là lỗi (dùng trong CI trước khi deploy production).
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha qa
	 *     wp saha qa --format=json > qa.json
	 *     wp saha qa --strict
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function qa( array $args, array $assoc_args ): void {
		unset( $args );

		$results = ( new Qa() )->run();
		$format  = (string) ( $assoc_args['format'] ?? 'table' );

		\WP_CLI\Utils\format_items( $format, $results, array( 'status', 'group', 'label', 'detail' ) );

		$summary = Qa::summary( $results );

		\WP_CLI::log(
			sprintf(
				'Đạt: %d · Cảnh báo: %d · Lỗi: %d · Bỏ qua: %d',
				$summary[ Qa::PASS ],
				$summary[ Qa::WARN ],
				$summary[ Qa::FAIL ],
				$summary[ Qa::SKIP ]
			)
		);

		$strict = ! empty( $assoc_args['strict'] );

		if ( $summary[ Qa::FAIL ] > 0 || ( $strict && $summary[ Qa::WARN ] > 0 ) ) {
			\WP_CLI::halt( 1 );
		}

		\WP_CLI::success( 'Kiểm tra hệ thống đạt.' );
	}

	/**
	 * Tạo dữ liệu mẫu cho staging / local: thương hiệu, danh mục, ứng dụng,
	 * 24 sản phẩm (có Loctite 243, Apollo A500), trang báo giá/liên hệ, blog.
	 *
	 * Idempotent. Mọi thứ được đánh dấu để `wp saha unseed` gỡ sạch.
	 * KHÔNG chạy trên production.
	 *
	 * ## OPTIONS
	 *
	 * [--homepage-layout=<file>]
	 * : Tạo trang chủ từ layout UX Builder, ví dụ docs/layouts/homepage.ux.txt
	 *
	 * [--set-front]
	 * : Đặt trang vừa tạo làm trang chủ, kể cả khi site đang có trang chủ khác.
	 *
	 * [--with-crm]
	 * : Tạo thêm báo giá + lead mẫu (không gửi email).
	 *
	 * [--yes]
	 * : Bỏ qua xác nhận khi môi trường là production.
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha seed --with-crm
	 *     wp saha seed --homepage-layout=../docs/layouts/homepage.ux.txt --set-front
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function seed( array $args, array $assoc_args ): void {
		unset( $args );

		if ( 'production' === wp_get_environment_type() ) {
			\WP_CLI::confirm( 'Môi trường đang là PRODUCTION. Vẫn tạo dữ liệu mẫu?', $assoc_args );
		}

		try {
			$created = ( new Seeder() )->run(
				array(
					'homepage_layout' => (string) ( $assoc_args['homepage-layout'] ?? '' ),
					'set_front'       => ! empty( $assoc_args['set-front'] ),
					'with_crm'        => ! empty( $assoc_args['with-crm'] ),
				)
			);
		} catch ( \Throwable $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}

		self::print_counts( $created, 'Không có gì mới — dữ liệu mẫu đã có sẵn.' );

		\WP_CLI::success( 'Đã tạo dữ liệu mẫu. Chạy `wp saha qa` để kiểm tra.' );
	}

	/**
	 * Gỡ toàn bộ dữ liệu mẫu do `wp saha seed` tạo. Không đụng dữ liệu thật.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Không hỏi xác nhận.
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function unseed( array $args, array $assoc_args ): void {
		unset( $args );

		\WP_CLI::confirm( 'Xoá vĩnh viễn toàn bộ dữ liệu MẪU (sản phẩm, trang, bài viết, term, báo giá/lead có tiền tố "[Mẫu]")?', $assoc_args );

		$removed = ( new Seeder() )->purge();

		self::print_counts( $removed, 'Không tìm thấy dữ liệu mẫu nào.' );

		\WP_CLI::success( 'Đã gỡ dữ liệu mẫu.' );
	}

	/**
	 * Chạy ngay tác vụ bảo trì (dọn log cũ) thay vì chờ cron.
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function maintenance( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		self::print_counts( ( new Maintenance() )->run(), 'Không có log nào quá hạn.' );

		\WP_CLI::success( 'Đã chạy bảo trì.' );
	}

	/**
	 * In bảng số lượng.
	 *
	 * @param array<string, int> $counts Số lượng theo loại.
	 * @param string             $empty  Thông báo khi rỗng.
	 */
	private static function print_counts( array $counts, string $empty ): void {
		$counts = array_filter( $counts );

		if ( ! $counts ) {
			\WP_CLI::log( $empty );
			return;
		}

		$rows = array();

		foreach ( $counts as $type => $count ) {
			$rows[] = array(
				'loại'     => $type,
				'số lượng' => $count,
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'loại', 'số lượng' ) );
	}
}
