<?php
/**
 * Một element trong cây layout.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Node.
 *
 * Node có type không đăng ký (ví dụ element của add-on đã tắt) được giữ nguyên
 * dữ liệu gốc trong `$raw` để lưu lại không mất gì — nhưng không bao giờ render.
 */
final class Node {

	/**
	 * Tạo node.
	 *
	 * @param string               $id       8 ký tự [a-z0-9].
	 * @param string               $type     Loại element.
	 * @param array<string, mixed> $props    Giá trị control (đã sanitize, chỉ key có đặt).
	 * @param array<string, mixed> $advanced Tab nâng cao (cssId, cssClass, ẩn theo thiết bị, margin, padding).
	 * @param Node[]               $children Con.
	 * @param array|null           $raw      Dữ liệu gốc của type không đăng ký.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $type,
		public readonly array $props = array(),
		public readonly array $advanced = array(),
		public readonly array $children = array(),
		public readonly ?array $raw = null
	) {}

	/**
	 * Dựng từ dữ liệu ĐÃ sanitize (đọc từ database).
	 *
	 * Không validate lại — nhưng renderer vẫn escape mọi output và CssRules vẫn
	 * lọc giá trị CSS, nên dữ liệu bị sửa tay trong DB cũng không gây XSS.
	 *
	 * @param array<string, mixed> $data Dữ liệu.
	 */
	public static function fromArray( array $data ): self {
		$children = array();

		foreach ( (array) ( $data['children'] ?? array() ) as $child ) {
			if ( is_array( $child ) && isset( $child['id'], $child['type'] ) ) {
				$children[] = self::fromArray( $child );
			}
		}

		return new self(
			(string) $data['id'],
			(string) $data['type'],
			is_array( $data['props'] ?? null ) ? $data['props'] : array(),
			is_array( $data['advanced'] ?? null ) ? $data['advanced'] : array(),
			$children
		);
	}

	/**
	 * Node giữ nguyên dữ liệu gốc (type không đăng ký).
	 *
	 * @param string               $id   ID.
	 * @param string               $type Type.
	 * @param array<string, mixed> $raw  Dữ liệu gốc.
	 */
	public static function unknown( string $id, string $type, array $raw ): self {
		unset( $raw['id'], $raw['type'] );

		return new self( $id, $type, array(), array(), array(), $raw );
	}

	/**
	 * Về mảng để lưu JSON.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		if ( null !== $this->raw ) {
			return array(
				'id'   => $this->id,
				'type' => $this->type,
			) + $this->raw;
		}

		$out = array(
			'id'   => $this->id,
			'type' => $this->type,
		);

		// Object rỗng → {} trong JSON, không phải [] (client JS phân biệt).
		$out['props'] = $this->props ? $this->props : new \stdClass();

		if ( $this->advanced ) {
			$out['advanced'] = $this->advanced;
		}

		if ( $this->children ) {
			$out['children'] = array_map( static fn( Node $child ): array => $child->toArray(), $this->children );
		}

		return $out;
	}

	/**
	 * Type có đăng ký không (node không phải raw).
	 */
	public function isKnown(): bool {
		return null === $this->raw;
	}

	/**
	 * Giá trị một prop (không áp mặc định — Element::prop() làm việc đó).
	 *
	 * @param string $key      Key.
	 * @param mixed  $fallback Mặc định.
	 * @return mixed
	 */
	public function prop( string $key, $fallback = null ) {
		return array_key_exists( $key, $this->props ) ? $this->props[ $key ] : $fallback;
	}

	/**
	 * Hash nội dung của node (khoá render cache).
	 */
	public function hash(): string {
		return md5( (string) wp_json_encode( $this->toArray() ) );
	}
}
