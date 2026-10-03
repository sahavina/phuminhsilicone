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
	 * Áp dụng giao diện kiểu cửa hàng: header + footer (dùng cho toàn site), trang chủ, bộ màu/font.
	 *
	 * ## OPTIONS
	 *
	 * [--front]
	 * : Xuất bản và đặt trang chủ mới làm trang chủ của site.
	 *
	 * [--no-palette]
	 * : Không đổi Theme Options (màu, font, kiểu thẻ, nút nổi).
	 *
	 * [--restore-options]
	 * : Chỉ khôi phục Theme Options đã lưu trước lần áp dụng gần nhất.
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha starter-store --front
	 *     wp saha starter-store --restore-options
	 *
	 * @subcommand starter-store
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function starter_store( array $args, array $assoc_args ): void {
		unset( $args );

		if ( ! empty( $assoc_args['restore-options'] ) ) {
			Builder\StoreKit::restoreOptions()
				? \WP_CLI::success( 'Đã khôi phục Theme Options.' )
				: \WP_CLI::error( 'Không có bản Theme Options đã lưu.' );
			return;
		}

		$result = Builder\StoreKit::install( ! empty( $assoc_args['front'] ), \WP_CLI\Utils\get_flag_value( $assoc_args, 'palette', true ) );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() . ' ' . wp_json_encode( $result->get_error_data() ) );
			return;
		}

		\WP_CLI::success( sprintf( 'Header #%d, footer #%d, shop & danh mục #%d, trang chủ #%d%s.', $result['header'], $result['footer'], $result['archive'], $result['homepage'], $result['palette'] ? ', đã đổi bộ màu/font' : '' ) );
	}

	/**
	 * Xuất giao diện (trang dựng bằng builder, Block dùng chung, template, Theme Options) ra file JSON.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Đường dẫn file JSON sẽ ghi.
	 *
	 * [--pages=<ids>]
	 * : ID trang, cách nhau dấu phẩy. Mặc định: mọi trang dùng builder. "none" = không xuất trang.
	 *
	 * [--templates=<ids>]
	 * : ID template (header, footer, nội dung). Mặc định: tất cả. "none" = không xuất.
	 *
	 * [--blocks=<ids>]
	 * : ID block. Mặc định: tất cả (block được trang/template dùng luôn được thêm). "none" = chỉ block được dùng.
	 *
	 * [--[no-]theme-options]
	 * : Xuất Theme Options (mặc định có; --no-theme-options để bỏ).
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha export giao-dien.json
	 *     wp saha export header-footer.json --pages=none --blocks=none
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function export( array $args, array $assoc_args ): void {
		$ids = static function ( string $key ) use ( $assoc_args ): ?array {
			if ( ! isset( $assoc_args[ $key ] ) ) {
				return null;
			}

			return 'none' === $assoc_args[ $key ] ? array() : array_filter( array_map( 'intval', explode( ',', (string) $assoc_args[ $key ] ) ) );
		};

		$package = ImportExport\Exporter::build(
			array(
				'pages'         => $ids( 'pages' ),
				'templates'     => $ids( 'templates' ),
				'blocks'        => $ids( 'blocks' ),
				'theme_options' => \WP_CLI\Utils\get_flag_value( $assoc_args, 'theme-options', true ),
			)
		);

		if ( false === file_put_contents( $args[0], ImportExport\Exporter::json( $package ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI ghi file người dùng chỉ định.
			\WP_CLI::error( 'Không ghi được file ' . $args[0] );
		}

		\WP_CLI::success(
			sprintf(
				'Đã xuất %d trang, %d template, %d block, %d ảnh%s → %s',
				count( $package['pages'] ),
				count( $package['templates'] ),
				count( $package['blocks'] ),
				count( $package['media'] ),
				null !== $package['theme_options'] ? ', Theme Options' : '',
				$args[0]
			)
		);
	}

	/**
	 * Nhập giao diện từ file JSON đã xuất. Luôn tạo mới (không ghi đè trang / block / template đang có).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : File JSON.
	 *
	 * [--dry-run]
	 * : Chỉ kiểm tra, không ghi gì.
	 *
	 * [--keep-page-status]
	 * : Giữ trạng thái trang như file (mặc định: trang nhập vào là nháp).
	 *
	 * [--front-page]
	 * : Đặt trang chủ theo file (trang được xuất bản).
	 *
	 * [--replace-templates]
	 * : Template đang xuất bản cùng loại với template nhập → chuyển nháp.
	 *
	 * [--[no-]theme-options]
	 * : Nhập Theme Options (mặc định có, bản cũ được sao lưu; --no-theme-options để bỏ).
	 *
	 * [--only=<kinds>]
	 * : Chỉ nhập: blocks,pages,templates (cách nhau dấu phẩy).
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha import giao-dien.json --dry-run
	 *     wp saha import giao-dien.json --replace-templates --front-page --user=admin
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function import( array $args, array $assoc_args ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			\WP_CLI::error( 'Cần chạy với --user=<quản trị viên> (quyền manage_options).' );
		}

		if ( ! is_readable( $args[0] ) ) {
			\WP_CLI::error( 'Không đọc được file ' . $args[0] );
		}

		$data = ImportExport\Importer::parse( (string) file_get_contents( $args[0] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- file cục bộ.

		if ( is_wp_error( $data ) ) {
			\WP_CLI::error( $data->get_error_message() );
		}

		$only  = isset( $assoc_args['only'] ) ? array_map( 'trim', explode( ',', (string) $assoc_args['only'] ) ) : array( 'blocks', 'pages', 'templates' );
		$report = ( new ImportExport\Importer(
			array(
				'dry_run'           => ! empty( $assoc_args['dry-run'] ),
				'blocks'            => in_array( 'blocks', $only, true ),
				'pages'             => in_array( 'pages', $only, true ),
				'templates'         => in_array( 'templates', $only, true ),
				'theme_options'     => \WP_CLI\Utils\get_flag_value( $assoc_args, 'theme-options', true ),
				'page_status'       => ! empty( $assoc_args['keep-page-status'] ) ? 'keep' : 'draft',
				'front_page'        => ! empty( $assoc_args['front-page'] ),
				'replace_templates' => ! empty( $assoc_args['replace-templates'] ),
			)
		) )->run( $data );

		foreach ( $report['created'] as $row ) {
			\WP_CLI::log( sprintf( '+ %s #%d %s (%s)', $row['kind'], $row['id'], $row['title'], $row['status'] ) );
		}

		foreach ( $report['warnings'] as $warning ) {
			\WP_CLI::warning( $warning );
		}

		foreach ( $report['errors'] as $error ) {
			\WP_CLI::log( 'LỖI: ' . $error );
		}

		$planned = $report['planned'];

		\WP_CLI::success(
			sprintf(
				'%s: %d block, %d trang, %d template, %d ảnh tham chiếu%s. Đã tạo %d, cảnh báo %d, lỗi %d.',
				! empty( $assoc_args['dry-run'] ) ? 'Chạy thử' : 'Đã nhập',
				$planned['blocks'],
				$planned['pages'],
				$planned['templates'],
				$planned['media'],
				$planned['theme_options'] ? ', Theme Options' : '',
				count( $report['created'] ),
				count( $report['warnings'] ),
				count( $report['errors'] )
			)
		);
	}

	/**
	 * Xoá cache SAHA (dữ liệu catalogue + HTML render cache của builder) bằng cách tăng thế hệ cache.
	 *
	 * Dùng khi sửa code element/template trong lúc phát triển mà không đổi version plugin.
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha flush-cache
	 *
	 * @subcommand flush-cache
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function flush_cache( array $args, array $assoc_args ): void {
		unset( $args, $assoc_args );

		Cache::bump();

		\WP_CLI::success( sprintf( 'Đã xoá cache SAHA (thế hệ %d).', Cache::generation() ) );
	}

	/**
	 * Xoá cache Cloudflare (cần SAHA_CF_ZONE_ID + SAHA_CF_API_TOKEN trong wp-config.php).
	 *
	 * ## OPTIONS
	 *
	 * [<url>...]
	 * : URL cần xoá. Bỏ trống = xoá toàn bộ zone.
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha cf-purge
	 *     wp saha cf-purge https://siliconephuminh.com/ https://siliconephuminh.com/shop/
	 *
	 * @subcommand cf-purge
	 *
	 * @param string[]             $args       URL.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function cf_purge( array $args, array $assoc_args ): void {
		unset( $assoc_args );

		if ( ! Performance\CloudflarePurge::configured() ) {
			\WP_CLI::error( 'Chưa cấu hình SAHA_CF_ZONE_ID / SAHA_CF_API_TOKEN trong wp-config.php.' );
		}

		$result = Performance\CloudflarePurge::send( Performance\CloudflarePurge::payloads( $args, array() === $args ) );

		$result['ok'] ? \WP_CLI::success( $result['message'] ) : \WP_CLI::error( 'Cloudflare: ' . $result['message'] );
	}

	/**
	 * Tạo trang chủ mẫu 14 khối dựng bằng SAHA Builder (không shortcode).
	 *
	 * ## OPTIONS
	 *
	 * [--front]
	 * : Xuất bản và đặt làm trang chủ của site. Không có cờ này: tạo bản nháp.
	 *
	 * ## EXAMPLES
	 *
	 *     wp saha homepage --front
	 *
	 * @param string[]             $args       Positional.
	 * @param array<string, mixed> $assoc_args Tuỳ chọn.
	 */
	public function homepage( array $args, array $assoc_args ): void {
		unset( $args );

		$id = Builder\Starter::installHomepage( ! empty( $assoc_args['front'] ) );

		if ( is_wp_error( $id ) ) {
			\WP_CLI::error( $id->get_error_message() . ' ' . wp_json_encode( $id->get_error_data() ) );
			return;
		}

		\WP_CLI::success( sprintf( 'Đã tạo trang chủ #%d: %s', $id, get_permalink( $id ) ) );
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
