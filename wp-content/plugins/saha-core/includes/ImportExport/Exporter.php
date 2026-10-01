<?php
/**
 * Xuất giao diện ra file JSON (SCC 2.6, spec §83).
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\ImportExport;

use Saha\Core\Blocks\PostType as BlockPostType;
use Saha\Core\Builder\LayoutRepository;
use Saha\Core\Templates\Repository as Templates;
use Saha\Core\ThemeOptions\Repository as ThemeOptions;

defined( 'ABSPATH' ) || exit;

/**
 * Exporter — gói `saha-export` v1:
 *
 * ```
 * { format, version, generator, source, created_at,
 *   blocks:    [ { id, title, status, layout } ],
 *   pages:     [ { id, title, slug, status, front_page, layout } ],
 *   templates: [ { id, title, status, type, priority, conditions, layout } ],
 *   theme_options: { … } | null,
 *   media: { "<id>": { url, title, alt } },          ← ảnh được tham chiếu (tải lại ở site nhận)
 *   refs:  { "post:<type>:<id>": slug, "term:<tax>:<id>": slug }  ← điều kiện template theo slug
 * }
 * ```
 *
 * Không xuất user, đơn hàng, sản phẩm, menu, khách / báo giá (spec §84). Block mà layout đã chọn
 * dùng tới được thêm tự động (site nhận không thiếu block).
 */
final class Exporter {

	public const FORMAT  = 'saha-export';
	public const VERSION = 1;

	/**
	 * Danh sách có thể xuất (cho màn hình chọn).
	 *
	 * @return array{blocks: array<int, array{id: int, title: string}>, templates: array<int, array{id: int, title: string, type: string}>, pages: array<int, array{id: int, title: string}>}
	 */
	public static function available(): array {
		$list = static function ( string $type, array $extra = array() ): array {
			$out = array();

			foreach ( get_posts(
				array(
					'post_type'      => $type,
					'post_status'    => array( 'publish', 'draft', 'private' ),
					'posts_per_page' => 500,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				) + $extra
			) as $post ) {
				if ( ! LayoutRepository::raw( (int) $post->ID ) ) {
					continue;
				}

				$out[] = array(
					'id'    => (int) $post->ID,
					'title' => '' !== $post->post_title ? (string) $post->post_title : '#' . $post->ID,
					'type'  => (string) get_post_meta( (int) $post->ID, Templates::TYPE_META, true ),
				);
			}

			return $out;
		};

		return array(
			'blocks'    => $list( BlockPostType::NAME ),
			'templates' => $list( Templates::POST_TYPE ),
			'pages'     => $list(
				'page',
				array(
					'meta_key'   => LayoutRepository::META_ENABLED, // phpcs:ignore WordPress.DB.SlowDBQuery -- màn hình admin, ít trang.
					'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			),
		);
	}

	/**
	 * Dựng gói.
	 *
	 * @param array{blocks?: int[]|null, templates?: int[]|null, pages?: int[]|null, theme_options?: bool} $what null = tất cả.
	 * @return array<string, mixed>
	 */
	public static function build( array $what ): array {
		$available = self::available();
		$pick      = static function ( string $kind ) use ( $what, $available ): array {
			$all = array_column( $available[ $kind ], 'id' );

			if ( ! array_key_exists( $kind, $what ) || null === $what[ $kind ] ) {
				return $all;
			}

			return array_values( array_intersect( $all, array_map( 'intval', (array) $what[ $kind ] ) ) );
		};

		$package = array(
			'format'        => self::FORMAT,
			'version'       => self::VERSION,
			'generator'     => 'SAHA Core ' . SAHA_CORE_VERSION,
			'source'        => home_url( '/' ),
			'created_at'    => gmdate( 'c' ),
			'blocks'        => array(),
			'pages'         => array(),
			'templates'     => array(),
			'theme_options' => null,
			'media'         => array(),
			'refs'          => array(),
		);

		$media  = array();
		$blocks = $pick( 'blocks' );
		$front  = 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : 0;

		foreach ( $pick( 'pages' ) as $id ) {
			$layout = (array) LayoutRepository::raw( $id );

			Walker::collect( $layout, $media, $blocks );

			$package['pages'][] = array(
				'id'         => $id,
				'title'      => (string) get_post_field( 'post_title', $id ),
				'slug'       => (string) get_post_field( 'post_name', $id ),
				'status'     => (string) get_post_status( $id ),
				'front_page' => $id === $front,
				'layout'     => $layout,
			);
		}

		foreach ( $pick( 'templates' ) as $id ) {
			$layout     = (array) LayoutRepository::raw( $id );
			$conditions = json_decode( (string) get_post_meta( $id, Templates::COND_META, true ), true );

			Walker::collect( $layout, $media, $blocks );

			$package['templates'][] = array(
				'id'         => $id,
				'title'      => (string) get_post_field( 'post_title', $id ),
				'status'     => (string) get_post_status( $id ),
				'type'       => (string) get_post_meta( $id, Templates::TYPE_META, true ),
				'priority'   => (int) get_post_meta( $id, Templates::PRIORITY_META, true ),
				'conditions' => is_array( $conditions ) ? $conditions : array(),
				'layout'     => $layout,
			);

			self::collectRefs( is_array( $conditions ) ? $conditions : array(), $package['refs'] );
		}

		// Block (cả block được layout khác dùng tới, và block lồng block) — vòng tới khi không thêm mới.
		$done = array();

		while ( $todo = array_diff( array_unique( $blocks ), $done ) ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			foreach ( $todo as $id ) {
				$done[] = $id;

				if ( BlockPostType::NAME !== get_post_type( $id ) ) {
					continue;
				}

				$layout = (array) LayoutRepository::raw( (int) $id );

				Walker::collect( $layout, $media, $blocks );

				$package['blocks'][] = array(
					'id'     => (int) $id,
					'title'  => (string) get_post_field( 'post_title', (int) $id ),
					'status' => (string) get_post_status( (int) $id ),
					'layout' => $layout,
				);
			}
		}

		if ( ! empty( $what['theme_options'] ) ) {
			$options                  = ThemeOptions::all();
			$package['theme_options'] = $options;

			foreach ( self::themeMediaFields() as $path ) {
				$value = (int) ( $options[ $path[0] ][ $path[1] ] ?? 0 );

				if ( $value > 0 ) {
					$media[] = $value;
				}
			}
		}

		foreach ( array_unique( $media ) as $id ) {
			$url = wp_attachment_is_image( $id ) ? (string) wp_get_attachment_url( $id ) : '';

			if ( '' !== $url ) {
				$package['media'][ (string) $id ] = array(
					'url'   => $url,
					'title' => (string) get_post_field( 'post_title', $id ),
					'alt'   => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				);
			}
		}

		return $package;
	}

	/**
	 * Trường ảnh của Theme Options (lưu ID đính kèm).
	 *
	 * @return array<int, array{0: string, 1: string}>
	 */
	public static function themeMediaFields(): array {
		$fields = array();

		foreach ( \Saha\Core\ThemeOptions\Schema::groups() as $group => $def ) {
			foreach ( (array) ( $def['fields'] ?? array() ) as $key => $field ) {
				if ( 'media' === ( $field['type'] ?? '' ) ) {
					$fields[] = array( (string) $group, (string) $key );
				}
			}
		}

		return $fields;
	}

	/**
	 * Slug của bài / term mà điều kiện template trỏ tới (ID khác nhau giữa các site).
	 *
	 * @param array<string, mixed> $conditions Điều kiện.
	 * @param array<string, string> $refs      Bảng tham chiếu (ghi thêm).
	 */
	private static function collectRefs( array $conditions, array &$refs ): void {
		foreach ( array( 'include', 'exclude' ) as $group ) {
			foreach ( (array) ( $conditions[ $group ] ?? array() ) as $rule ) {
				$name = (string) ( $rule['rule'] ?? '' );

				foreach ( (array) ( $rule['value'] ?? array() ) as $value ) {
					$id = (int) $value;

					if ( $id <= 0 ) {
						continue;
					}

					if ( in_array( $name, Walker::POST_RULES, true ) ) {
						$slug = (string) get_post_field( 'post_name', $id );

						if ( '' !== $slug ) {
							$refs[ 'post:' . $name . ':' . $id ] = $slug;
						}
					} elseif ( in_array( $name, Walker::TERM_RULES, true ) ) {
						$term = get_term( $id, $name );

						if ( $term instanceof \WP_Term ) {
							$refs[ 'term:' . $name . ':' . $id ] = (string) $term->slug;
						}
					}
				}
			}
		}
	}

	/**
	 * Tên file tải về.
	 */
	public static function filename(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return 'saha-export-' . sanitize_file_name( $host ) . '-' . gmdate( 'Ymd-His' ) . '.json';
	}

	/**
	 * JSON của gói.
	 *
	 * @param array<string, mixed> $package Gói.
	 */
	public static function json( array $package ): string {
		return (string) wp_json_encode( $package, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
	}
}
