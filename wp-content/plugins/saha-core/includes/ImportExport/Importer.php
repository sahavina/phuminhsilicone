<?php
/**
 * Nhập giao diện từ file JSON (SCC 2.6, spec §83, §84).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ImportExport;

use Saha\Core\Blocks\PostType as BlockPostType;
use Saha\Core\Builder\LayoutService;
use Saha\Core\Builder\Sanitizer;
use Saha\Core\Cache;
use Saha\Core\Logger;
use Saha\Core\MegaMenu\Settings as MegaMenu;
use Saha\Core\Templates\Repository as Templates;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Importer — thứ tự: kiểm tra gói → ảnh → block → trang → template → Theme Options.
 *
 * - Luôn **tạo mới** (không ghi đè trang / block / template đang có; slug trùng → WordPress thêm -2).
 * - Mọi layout qua LayoutService::save → Sanitizer (giống lưu trong builder); layout lỗi bị bỏ, có cảnh báo.
 * - Ảnh: cùng site → dùng lại ID; đã nhập trước đó (meta SOURCE_META) → dùng lại; còn lại tải bằng
 *   `media_sideload_image` (WordPress kiểm mime; `wp_safe_remote_get` chặn địa chỉ nội bộ).
 * - Điều kiện template: ID bài / term của site nguồn → slug (refs) → ID ở site nhận; không có → bỏ, cảnh báo.
 * - Theme Options: sao lưu option hiện tại vào BACKUP_OPTION rồi lưu qua ThemeOptions\Repository::save (sanitize từng trường).
 * - Chạy thử (`dry_run`): không ghi gì; kiểm cấu trúc layout (bỏ ảnh / block) + liệt kê việc sẽ làm.
 */
final class Importer {

	public const MAX_BYTES     = 10 * 1024 * 1024;
	public const MAX_ITEMS     = 500;
	public const MAX_MEDIA     = 200;
	public const SOURCE_META   = '_saha_import_source';
	public const BACKUP_OPTION = 'saha_theme_options_before_import';

	/**
	 * Tuỳ chọn.
	 *
	 * @var array{dry_run: bool, blocks: bool, pages: bool, templates: bool, theme_options: bool, page_status: string, front_page: bool, replace_templates: bool}
	 */
	private array $options;

	/**
	 * Báo cáo.
	 *
	 * @var array{created: array<int, array{kind: string, id: int, title: string, status: string}>, warnings: string[], errors: string[], planned: array<string, int>}
	 */
	private array $report = array(
		'created'  => array(),
		'warnings' => array(),
		'errors'   => array(),
		'planned'  => array(),
	);

	/**
	 * Map ID cũ → mới.
	 *
	 * @var array{media: array<int, int>, blocks: array<int, int>, pages: array<int, int>}
	 */
	private array $map = array(
		'media'  => array(),
		'blocks' => array(),
		'pages'  => array(),
	);

	/**
	 * Tuỳ chọn mặc định.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'dry_run'           => false,
			'blocks'            => true,
			'pages'             => true,
			'templates'         => true,
			'theme_options'     => true,
			'page_status'       => 'draft',
			'front_page'        => false,
			'replace_templates' => false,
		);
	}

	/**
	 * Khởi tạo.
	 *
	 * @param array<string, mixed> $options Tuỳ chọn.
	 */
	public function __construct( array $options = array() ) {
		$o = wp_parse_args( $options, self::defaults() );

		$this->options = array(
			'dry_run'           => (bool) $o['dry_run'],
			'blocks'            => (bool) $o['blocks'],
			'pages'             => (bool) $o['pages'],
			'templates'         => (bool) $o['templates'],
			'theme_options'     => (bool) $o['theme_options'],
			'page_status'       => 'keep' === $o['page_status'] ? 'keep' : 'draft',
			'front_page'        => (bool) $o['front_page'],
			'replace_templates' => (bool) $o['replace_templates'],
		);
	}

	/**
	 * Đọc + kiểm tra JSON thô.
	 *
	 * @param string $json Nội dung file.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function parse( string $json ) {
		if ( strlen( $json ) > self::MAX_BYTES ) {
			return new \WP_Error( 'saha_import_size', __( 'File quá lớn (tối đa 10 MB).', 'saha-core' ) );
		}

		$data = json_decode( $json, true, 512 );

		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'saha_import_json', __( 'File không phải JSON hợp lệ.', 'saha-core' ) );
		}

		return self::validate( $data );
	}

	/**
	 * Kiểm cấu trúc gói (không đụng database).
	 *
	 * @param array<string, mixed> $data Gói.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function validate( array $data ) {
		if ( Exporter::FORMAT !== ( $data['format'] ?? '' ) ) {
			return new \WP_Error( 'saha_import_format', __( 'Không phải file xuất của SAHA (thiếu "format": "saha-export").', 'saha-core' ) );
		}

		if ( (int) ( $data['version'] ?? 0 ) !== Exporter::VERSION ) {
			/* translators: %d: phiên bản */
			return new \WP_Error( 'saha_import_version', sprintf( __( 'Phiên bản file %d chưa được hỗ trợ.', 'saha-core' ), (int) ( $data['version'] ?? 0 ) ) );
		}

		foreach ( array( 'blocks', 'pages', 'templates' ) as $kind ) {
			$list = $data[ $kind ] ?? array();

			if ( ! is_array( $list ) || count( $list ) > self::MAX_ITEMS ) {
				/* translators: %s: loại */
				return new \WP_Error( 'saha_import_list', sprintf( __( 'Mục "%s" không hợp lệ hoặc quá nhiều phần tử.', 'saha-core' ), $kind ) );
			}

			foreach ( $list as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['id'] ) || ! is_array( $item['layout'] ?? null ) ) {
					/* translators: %s: loại */
					return new \WP_Error( 'saha_import_item', sprintf( __( 'Một phần tử trong "%s" thiếu id hoặc layout.', 'saha-core' ), $kind ) );
				}
			}
		}

		foreach ( array( 'media', 'refs' ) as $key ) {
			if ( isset( $data[ $key ] ) && ! is_array( $data[ $key ] ) ) {
				/* translators: %s: khoá */
				return new \WP_Error( 'saha_import_map', sprintf( __( '"%s" không hợp lệ.', 'saha-core' ), $key ) );
			}
		}

		if ( count( (array) ( $data['media'] ?? array() ) ) > self::MAX_MEDIA ) {
			/* translators: %d: số ảnh tối đa */
			return new \WP_Error( 'saha_import_media', sprintf( __( 'File tham chiếu quá nhiều ảnh (tối đa %d).', 'saha-core' ), self::MAX_MEDIA ) );
		}

		if ( isset( $data['theme_options'] ) && null !== $data['theme_options'] && ! is_array( $data['theme_options'] ) ) {
			return new \WP_Error( 'saha_import_options', __( '"theme_options" không hợp lệ.', 'saha-core' ) );
		}

		return $data;
	}

	/**
	 * Chạy.
	 *
	 * @param array<string, mixed> $data Gói đã validate.
	 * @return array<string, mixed> Báo cáo.
	 */
	public function run( array $data ): array {
		$dry = $this->options['dry_run'];

		$blocks    = $this->options['blocks'] ? (array) ( $data['blocks'] ?? array() ) : array();
		$pages     = $this->options['pages'] ? (array) ( $data['pages'] ?? array() ) : array();
		$templates = $this->options['templates'] ? (array) ( $data['templates'] ?? array() ) : array();
		$options   = $this->options['theme_options'] && is_array( $data['theme_options'] ?? null ) ? $data['theme_options'] : null;

		$this->report['planned'] = array(
			'blocks'        => count( $blocks ),
			'pages'         => count( $pages ),
			'templates'     => count( $templates ),
			'theme_options' => null !== $options ? 1 : 0,
			'media'         => count( (array) ( $data['media'] ?? array() ) ),
		);

		if ( $dry ) {
			$this->dryRun( $data, array_merge( $blocks, $pages, $templates ) );
			return $this->report;
		}

		// Trang nhập có thể vướng khoá / hook lưu bài — không để lỗi nửa chừng làm hỏng phần đã nhập.
		$this->resolveMedia( (array) ( $data['media'] ?? array() ), (string) ( $data['source'] ?? '' ) );

		// Block: tạo post trước (block có thể chứa block khác), rồi mới lưu layout đã đổi ID.
		$created_blocks = array();

		foreach ( $blocks as $item ) {
			$id = $this->createPost( BlockPostType::NAME, $item, (string) ( $item['status'] ?? 'publish' ) );

			if ( $id > 0 ) {
				$this->map['blocks'][ (int) $item['id'] ] = $id;
				$created_blocks[]                         = array( $id, $item );
			}
		}

		foreach ( $created_blocks as list( $id, $item ) ) {
			$this->saveLayout( $id, $item, 'block' );
		}

		foreach ( $pages as $item ) {
			$status = 'keep' === $this->options['page_status'] ? (string) ( $item['status'] ?? 'draft' ) : 'draft';
			$id     = $this->createPost( 'page', $item, $status, (string) ( $item['slug'] ?? '' ) );

			if ( $id <= 0 ) {
				continue;
			}

			$this->map['pages'][ (int) $item['id'] ] = $id;
			$this->saveLayout( $id, $item, 'page' );

			if ( $this->options['front_page'] && ! empty( $item['front_page'] ) ) {
				if ( 'publish' !== get_post_status( $id ) ) {
					wp_update_post(
						array(
							'ID'          => $id,
							'post_status' => 'publish',
						)
					);
				}

				update_option( 'show_on_front', 'page' );
				update_option( 'page_on_front', $id );
			}
		}

		if ( $templates && $this->options['replace_templates'] ) {
			$this->retireTemplates( array_unique( array_map( static fn( $t ) => (string) ( $t['type'] ?? '' ), $templates ) ) );
		}

		foreach ( $templates as $item ) {
			$this->importTemplate( $item, (array) ( $data['refs'] ?? array() ) );
		}

		if ( null !== $options ) {
			$this->importThemeOptions( $options );
		}

		Templates::compile();

		if ( class_exists( MegaMenu::class ) ) {
			MegaMenu::compile();
		}

		Cache::bump();

		Logger::info(
			'Import giao diện.',
			'import',
			array(
				'created'  => count( $this->report['created'] ),
				'warnings' => count( $this->report['warnings'] ),
			)
		);

		return $this->report;
	}

	/**
	 * Chạy thử: kiểm cấu trúc layout (không có ảnh / block), không ghi gì.
	 *
	 * @param array<string, mixed>             $data  Gói.
	 * @param array<int, array<string, mixed>> $items Mọi phần tử sẽ nhập.
	 */
	private function dryRun( array $data, array $items ): void {
		$sanitizer = new Sanitizer();

		foreach ( $items as $item ) {
			$root   = 'header' === ( $item['type'] ?? '' ) ? 'header-root' : 'root';
			$result = $sanitizer->document( Walker::strip( (array) $item['layout'] ), $root );

			if ( $result['errors'] ) {
				/* translators: %s: tên */
				$this->report['warnings'][] = sprintf( __( '%s: layout không hợp lệ ở site này (element thiếu / sai) — sẽ bị bỏ.', 'saha-core' ), self::label( $item ) );
			}

			if ( isset( $item['type'] ) && ! isset( Templates::types()[ (string) $item['type'] ] ) ) {
				/* translators: %s: tên */
				$this->report['warnings'][] = sprintf( __( '%s: loại template không hỗ trợ — sẽ bị bỏ.', 'saha-core' ), self::label( $item ) );
			}
		}

		$same = self::sameSite( (string) ( $data['source'] ?? '' ) );

		if ( ! $same && ! empty( $data['media'] ) ) {
			/* translators: %d: số ảnh */
			$this->report['warnings'][] = sprintf( __( '%d ảnh sẽ được tải từ site nguồn — site nguồn phải truy cập được từ máy chủ này.', 'saha-core' ), count( (array) $data['media'] ) );
		}
	}

	/**
	 * Cùng site với nơi xuất.
	 *
	 * @param string $source URL nguồn.
	 */
	private static function sameSite( string $source ): bool {
		return '' !== $source && untrailingslashit( $source ) === untrailingslashit( home_url( '/' ) );
	}

	/**
	 * Tên phần tử cho báo cáo.
	 *
	 * @param array<string, mixed> $item Phần tử.
	 */
	private static function label( array $item ): string {
		$title = sanitize_text_field( (string) ( $item['title'] ?? '' ) );

		return '“' . ( '' !== $title ? $title : '#' . (int) ( $item['id'] ?? 0 ) ) . '”';
	}

	/**
	 * Ảnh: cũ → mới.
	 *
	 * @param array<string, mixed> $media  Danh sách ảnh trong gói.
	 * @param string               $source URL site nguồn.
	 */
	private function resolveMedia( array $media, string $source ): void {
		$same     = self::sameSite( $source );
		$required = false;

		foreach ( $media as $old => $info ) {
			$old  = (int) $old;
			$info = (array) $info;
			$url  = esc_url_raw( (string) ( $info['url'] ?? '' ), array( 'http', 'https' ) );

			if ( $old <= 0 || '' === $url ) {
				continue;
			}

			if ( $same && wp_attachment_is_image( $old ) ) {
				$this->map['media'][ $old ] = $old;
				continue;
			}

			$existing = get_posts(
				array(
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_key'       => self::SOURCE_META, // phpcs:ignore WordPress.DB.SlowDBQuery -- chỉ khi nhập.
					'meta_value'     => $url, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			);

			if ( $existing ) {
				$this->map['media'][ $old ] = (int) $existing[0];
				continue;
			}

			if ( ! $required ) {
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				$required = true;
			}

			$id = media_sideload_image( $url, 0, sanitize_text_field( (string) ( $info['title'] ?? '' ) ), 'id' );

			if ( is_wp_error( $id ) ) {
				/* translators: 1: URL ảnh, 2: lỗi */
				$this->report['warnings'][] = sprintf( __( 'Không tải được ảnh %1$s: %2$s', 'saha-core' ), $url, $id->get_error_message() );
				continue;
			}

			update_post_meta( (int) $id, self::SOURCE_META, $url );

			$alt = sanitize_text_field( (string) ( $info['alt'] ?? '' ) );

			if ( '' !== $alt ) {
				update_post_meta( (int) $id, '_wp_attachment_image_alt', $alt );
			}

			$this->map['media'][ $old ] = (int) $id;
		}
	}

	/**
	 * Tạo post (chưa có layout).
	 *
	 * @param string               $type   Post type.
	 * @param array<string, mixed> $item   Phần tử.
	 * @param string               $status Trạng thái mong muốn.
	 * @param string               $slug   Slug.
	 * @param array<string, mixed> $meta   Meta.
	 */
	private function createPost( string $type, array $item, string $status, string $slug = '', array $meta = array() ): int {
		$status = in_array( $status, array( 'publish', 'draft', 'private' ), true ) ? $status : 'draft';
		$title  = sanitize_text_field( (string) ( $item['title'] ?? '' ) );

		$id = wp_insert_post(
			array(
				'post_type'   => $type,
				'post_status' => $status,
				'post_title'  => '' !== $title ? $title : __( '(Nhập — không tên)', 'saha-core' ),
				'post_name'   => sanitize_title( $slug ),
				'meta_input'  => $meta,
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			/* translators: 1: tên, 2: lỗi */
			$this->report['errors'][] = sprintf( __( '%1$s: không tạo được (%2$s).', 'saha-core' ), self::label( $item ), $id->get_error_message() );
			return 0;
		}

		return (int) $id;
	}

	/**
	 * Lưu layout đã đổi ID; lỗi → xoá post vừa tạo (không để lại trang trống).
	 *
	 * @param int                  $id   Post mới.
	 * @param array<string, mixed> $item Phần tử.
	 * @param string               $kind block | page | template.
	 */
	private function saveLayout( int $id, array $item, string $kind ): bool {
		$layout = Walker::remap( (array) $item['layout'], $this->map['media'], $this->map['blocks'], $this->report['warnings'], self::label( $item ) );

		try {
			$result = LayoutService::save( $id, $layout, '' );
		} catch ( \Throwable $e ) {
			$result = array(
				'status' => 'error',
				'errors' => array( $e->getMessage() ),
			);
		}

		if ( 'saved' !== ( $result['status'] ?? '' ) ) {
			wp_delete_post( $id, true );

			/* translators: 1: tên, 2: trạng thái */
			$this->report['errors'][] = sprintf( __( '%1$s: layout không hợp lệ ở site này (%2$s) — bỏ qua.', 'saha-core' ), self::label( $item ), (string) ( $result['status'] ?? '?' ) );

			foreach ( array_slice( (array) ( $result['errors'] ?? array() ), 0, 3 ) as $error ) {
				$this->report['errors'][] = '— ' . ( is_array( $error ) ? (string) wp_json_encode( $error, JSON_UNESCAPED_UNICODE ) : (string) $error );
			}

			return false;
		}

		$this->report['created'][] = array(
			'kind'   => $kind,
			'id'     => $id,
			'title'  => (string) get_post_field( 'post_title', $id ),
			'status' => (string) get_post_status( $id ),
		);

		return true;
	}

	/**
	 * Template: tạo, lưu layout, đổi điều kiện sang ID của site nhận.
	 *
	 * @param array<string, mixed>  $item Phần tử.
	 * @param array<string, string> $refs Bảng slug.
	 */
	private function importTemplate( array $item, array $refs ): void {
		$type = (string) ( $item['type'] ?? '' );

		if ( ! isset( Templates::types()[ $type ] ) ) {
			/* translators: %s: tên */
			$this->report['warnings'][] = sprintf( __( '%s: loại template không hỗ trợ — bỏ qua.', 'saha-core' ), self::label( $item ) );
			return;
		}

		$id = $this->createPost( Templates::POST_TYPE, $item, (string) ( $item['status'] ?? 'publish' ), '', array( Templates::TYPE_META => $type ) );

		if ( $id <= 0 || ! $this->saveLayout( $id, $item, 'template' ) ) {
			return;
		}

		Templates::saveConditions( $id, $this->remapConditions( (array) ( $item['conditions'] ?? array() ), $refs, self::label( $item ) ), (int) ( $item['priority'] ?? 0 ) );
	}

	/**
	 * Đổi ID trong điều kiện template.
	 *
	 * @param array<string, mixed>  $conditions Điều kiện.
	 * @param array<string, string> $refs       Bảng slug.
	 * @param string                $label      Tên (cảnh báo).
	 * @return array<string, mixed>
	 */
	private function remapConditions( array $conditions, array $refs, string $label ): array {
		$dropped = 0;

		foreach ( array( 'include', 'exclude' ) as $group ) {
			$rules = array();

			foreach ( (array) ( $conditions[ $group ] ?? array() ) as $rule ) {
				$name = (string) ( $rule['rule'] ?? '' );
				$post = in_array( $name, Walker::POST_RULES, true );
				$term = in_array( $name, Walker::TERM_RULES, true );

				if ( ! $post && ! $term ) {
					$rules[] = $rule;
					continue;
				}

				$values = array();

				foreach ( (array) ( $rule['value'] ?? array() ) as $old ) {
					$old = (int) $old;
					$new = 0;

					if ( $post ) {
						$new = 'page' === $name && isset( $this->map['pages'][ $old ] ) ? $this->map['pages'][ $old ] : 0;

						if ( ! $new && isset( $refs[ 'post:' . $name . ':' . $old ] ) ) {
							$found = get_page_by_path( sanitize_title( $refs[ 'post:' . $name . ':' . $old ] ), OBJECT, $name );
							$new   = $found ? (int) $found->ID : 0;
						}
					} elseif ( isset( $refs[ 'term:' . $name . ':' . $old ] ) ) {
						$found = get_term_by( 'slug', sanitize_title( $refs[ 'term:' . $name . ':' . $old ] ), $name );
						$new   = $found instanceof \WP_Term ? (int) $found->term_id : 0;
					}

					if ( $new > 0 ) {
						$values[] = $new;
					} else {
						++$dropped;
					}
				}

				if ( $values ) {
					$rules[] = array(
						'rule'  => $name,
						'value' => $values,
					);
				}
			}

			$conditions[ $group ] = $rules;
		}

		if ( $dropped > 0 ) {
			/* translators: 1: tên template, 2: số điều kiện */
			$this->report['warnings'][] = sprintf( __( '%1$s: %2$d điều kiện trỏ tới trang / danh mục không có ở site này — đã bỏ, kiểm tra lại ở "Điều kiện hiển thị".', 'saha-core' ), $label, $dropped );
		}

		return $conditions;
	}

	/**
	 * "Thay template cùng loại": template đang xuất bản cùng loại → chuyển nháp (đảo lại được).
	 *
	 * @param string[] $types Loại.
	 */
	private function retireTemplates( array $types ): void {
		foreach ( get_posts(
			array(
				'post_type'      => Templates::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'meta_key'       => Templates::TYPE_META, // phpcs:ignore WordPress.DB.SlowDBQuery -- chỉ khi nhập.
				'meta_value'     => $types, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_compare'   => 'IN',
			)
		) as $post ) {
			wp_update_post(
				array(
					'ID'          => (int) $post->ID,
					'post_status' => 'draft',
				)
			);

			/* translators: %s: tên template */
			$this->report['warnings'][] = sprintf( __( 'Template cũ “%s” đã chuyển sang nháp.', 'saha-core' ), (string) $post->post_title );
		}
	}

	/**
	 * Theme Options: sao lưu rồi lưu (sanitize từng trường; ảnh logo đổi ID).
	 *
	 * @param array<string, mixed> $options Giá trị trong gói.
	 */
	private function importThemeOptions( array $options ): void {
		update_option( self::BACKUP_OPTION, ThemeOptions::all(), false );

		foreach ( Exporter::themeMediaFields() as $path ) {
			$old = (int) ( $options[ $path[0] ][ $path[1] ] ?? 0 );

			if ( $old > 0 ) {
				$options[ $path[0] ][ $path[1] ] = $this->map['media'][ $old ] ?? 0;
			}
		}

		$result = ThemeOptions::save( $options );

		foreach ( (array) ( $result['errors'] ?? array() ) as $field => $error ) {
			/* translators: 1: trường, 2: lỗi */
			$this->report['warnings'][] = sprintf( __( 'Theme Options "%1$s": %2$s — giữ giá trị cũ.', 'saha-core' ), (string) $field, is_string( $error ) ? $error : (string) wp_json_encode( $error ) );
		}

		$this->report['created'][] = array(
			'kind'   => 'theme_options',
			'id'     => 0,
			'title'  => __( 'Theme Options (bản cũ đã sao lưu)', 'saha-core' ),
			'status' => '',
		);
	}
}
