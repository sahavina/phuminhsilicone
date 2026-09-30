<?php
/**
 * Tài liệu layout của builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Document: `{ version, elements: Node[] }` (TECHNICAL-DESIGN §6.1).
 */
final class Document {

	/**
	 * Tạo tài liệu.
	 *
	 * @param Node[] $elements Node cấp gốc.
	 * @param int    $version  Phiên bản schema.
	 */
	public function __construct(
		public readonly array $elements = array(),
		public readonly int $version = SchemaMigrator::CURRENT
	) {}

	/**
	 * Dựng từ dữ liệu đã sanitize (database), có migrate schema.
	 *
	 * @param array<string, mixed> $data Dữ liệu.
	 */
	public static function fromArray( array $data ): self {
		$data     = SchemaMigrator::migrate( $data );
		$elements = array();

		foreach ( (array) ( $data['elements'] ?? array() ) as $node ) {
			if ( is_array( $node ) && isset( $node['id'], $node['type'] ) ) {
				$elements[] = Node::fromArray( $node );
			}
		}

		return new self( $elements, (int) $data['version'] );
	}

	/**
	 * Về mảng.
	 *
	 * @return array{version: int, elements: array<int, array<string, mixed>>}
	 */
	public function toArray(): array {
		return array(
			'version'  => $this->version,
			'elements' => array_map( static fn( Node $node ): array => $node->toArray(), $this->elements ),
		);
	}

	/**
	 * JSON để lưu (giữ nguyên tiếng Việt và dấu /, nhỏ và dễ đọc).
	 */
	public function toJson(): string {
		return (string) wp_json_encode( $this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/**
	 * Hash nội dung — khoá render cache, tên file CSS, phát hiện ghi đè.
	 */
	public function hash(): string {
		return substr( md5( $this->toJson() ), 0, 12 );
	}

	/**
	 * Tài liệu rỗng?
	 */
	public function isEmpty(): bool {
		return array() === $this->elements;
	}

	/**
	 * Duyệt mọi node (tiền thứ tự).
	 *
	 * @return \Generator<Node>
	 */
	public function walk(): \Generator {
		$stack = array_reverse( $this->elements );

		while ( $stack ) {
			$node = array_pop( $stack );

			yield $node;

			foreach ( array_reverse( $node->children ) as $child ) {
				$stack[] = $child;
			}
		}
	}
}
