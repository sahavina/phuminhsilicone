<?php
/**
 * Sanitize tài liệu builder theo định nghĩa element.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Controls\ControlRegistry;
use Saha\Core\Builder\Schema\Document;
use Saha\Core\Builder\Schema\Node;
use Saha\Core\Builder\Schema\SchemaMigrator;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer — lớp bảo vệ chính của builder: client gửi gì cũng không vượt được schema.
 *
 * - Cấu trúc sai (type/cha–con/độ sâu/số lượng/kích thước) và giá trị control sai
 *   → trả lỗi theo đường dẫn `{nodeId}.{prop}`; KHÔNG lưu gì (tài liệu là một khối).
 * - Prop lạ không có trong định nghĩa → bỏ im lặng.
 * - ID sai định dạng hoặc trùng → cấp ID mới (response trả tài liệu đã sạch).
 * - Type không đăng ký → giữ nguyên dữ liệu gốc (không mất dữ liệu add-on), không render.
 */
final class Sanitizer {

	/**
	 * Lỗi của lần sanitize hiện tại.
	 *
	 * @var array<string, string>
	 */
	private array $errors = array();

	/**
	 * ID đã dùng.
	 *
	 * @var array<string, true>
	 */
	private array $ids = array();

	/**
	 * Số node đã duyệt.
	 *
	 * @var int
	 */
	private int $count = 0;

	/**
	 * Tạo sanitizer.
	 *
	 * @param ElementRegistry|null $elements Registry element.
	 * @param ControlRegistry|null $controls Registry control.
	 */
	public function __construct(
		private ?ElementRegistry $elements = null,
		private ?ControlRegistry $controls = null
	) {
		$this->elements ??= ElementRegistry::instance();
		$this->controls ??= ControlRegistry::instance();
	}

	/**
	 * Sanitize cả tài liệu.
	 *
	 * @param mixed  $input Mảng hoặc chuỗi JSON.
	 * @param string $root  Loại gốc của tài liệu: `root` (trang, block, footer) | `header-root` (header).
	 * @return array{document: Document|null, errors: array<string, string>}
	 */
	public function document( $input, string $root = 'root' ): array {
		$this->reset();

		if ( is_string( $input ) ) {
			if ( strlen( $input ) > Limits::MAX_BYTES ) {
				return $this->fail( __( 'Layout quá lớn (tối đa 1 MB).', 'saha-core' ) );
			}
			$input = json_decode( $input, true );
		}

		if ( ! is_array( $input ) || ( isset( $input['elements'] ) && ! is_array( $input['elements'] ) ) ) {
			return $this->fail( __( 'Dữ liệu layout không hợp lệ.', 'saha-core' ) );
		}

		if ( strlen( (string) wp_json_encode( $input ) ) > Limits::MAX_BYTES ) {
			return $this->fail( __( 'Layout quá lớn (tối đa 1 MB).', 'saha-core' ) );
		}

		if ( SchemaMigrator::isFromFuture( $input ) ) {
			return $this->fail( __( 'Layout được tạo bởi phiên bản SAHA Core mới hơn — hãy cập nhật plugin.', 'saha-core' ) );
		}

		$input = SchemaMigrator::migrate( $input );
		$nodes = array();

		foreach ( array_values( (array) ( $input['elements'] ?? array() ) ) as $index => $raw ) {
			$node = $this->node( $raw, $root, 1, 'elements.' . $index );

			if ( null !== $node ) {
				$nodes[] = $node;
			}
		}

		if ( $this->errors ) {
			return array(
				'document' => null,
				'errors'   => $this->errors,
			);
		}

		return array(
			'document' => new Document( $nodes, SchemaMigrator::CURRENT ),
			'errors'   => array(),
		);
	}

	/**
	 * Sanitize một node độc lập (POST /builder/render) — bỏ qua kiểm tra cha.
	 *
	 * @param mixed $raw Node thô.
	 * @return array{node: Node|null, errors: array<string, string>}
	 */
	public function standalone( $raw ): array {
		$this->reset();

		if ( ! is_array( $raw ) || strlen( (string) wp_json_encode( $raw ) ) > Limits::MAX_BYTES ) {
			$this->errors['node'] = __( 'Dữ liệu element không hợp lệ.', 'saha-core' );
		}

		$node = $this->errors ? null : $this->node( $raw, '*', 1, 'node' );

		return array(
			'node'   => $this->errors ? null : $node,
			'errors' => $this->errors,
		);
	}

	/**
	 * Sanitize một node và con của nó.
	 *
	 * @param mixed  $raw    Node thô.
	 * @param string $parent Type cha ('root' ở cấp gốc, '*' = không kiểm).
	 * @param int    $depth  Độ sâu (gốc = 1).
	 * @param string $path   Đường dẫn cho thông báo lỗi khi node chưa có ID.
	 */
	private function node( $raw, string $parent, int $depth, string $path ): ?Node {
		if ( ! is_array( $raw ) || ! isset( $raw['type'] ) || ! is_string( $raw['type'] ) || ! preg_match( '/^[a-z][a-z0-9_-]{0,39}$/', $raw['type'] ) ) {
			$this->errors[ $path ] = __( 'Phần tử không hợp lệ.', 'saha-core' );
			return null;
		}

		if ( ++$this->count > Limits::MAX_ELEMENTS ) {
			/* translators: %d: số element tối đa */
			$this->errors['document'] = sprintf( __( 'Layout có quá nhiều phần tử (tối đa %d).', 'saha-core' ), Limits::MAX_ELEMENTS );
			return null;
		}

		if ( $depth > Limits::MAX_DEPTH ) {
			/* translators: %d: độ sâu tối đa */
			$this->errors['document'] = sprintf( __( 'Layout lồng quá sâu (tối đa %d cấp).', 'saha-core' ), Limits::MAX_DEPTH );
			return null;
		}

		$id      = $this->id( $raw['id'] ?? null );
		$type    = $raw['type'];
		$element = $this->elements->get( $type );

		if ( null === $element ) {
			return Node::unknown( $id, $type, $raw );
		}

		$def = $element->def();

		if ( '*' !== $parent && ! in_array( $parent, (array) $def['allowedParents'], true ) ) {
			$this->errors[ $id ] = sprintf(
				/* translators: 1: tên element, 2: type cha */
				__( '"%1$s" không được đặt trong "%2$s".', 'saha-core' ),
				(string) $def['name'],
				in_array( $parent, array( 'root', 'header-root' ), true ) ? __( 'cấp gốc', 'saha-core' ) : $this->name( $parent )
			);
			return null;
		}

		$props    = $this->values( is_array( $raw['props'] ?? null ) ? $raw['props'] : array(), (array) $def['controls'], $id );
		$advanced = $this->values( is_array( $raw['advanced'] ?? null ) ? $raw['advanced'] : array(), ElementRegistry::advancedControls(), $id );
		$children = array();
		$rawKids  = is_array( $raw['children'] ?? null ) ? array_values( $raw['children'] ) : array();
		$allowed  = (array) $def['allowedChildren'];

		if ( $rawKids && ! $allowed ) {
			/* translators: %s: tên element */
			$this->errors[ $id ] = sprintf( __( '"%s" không chứa được phần tử con.', 'saha-core' ), (string) $def['name'] );
			return null;
		}

		foreach ( $rawKids as $index => $rawChild ) {
			$childType = is_array( $rawChild ) && is_string( $rawChild['type'] ?? null ) ? $rawChild['type'] : '';

			if ( '' !== $childType && ! in_array( '*', $allowed, true ) && null !== $this->elements->get( $childType ) && ! in_array( $childType, $allowed, true ) ) {
				$this->errors[ $id . '.children.' . $index ] = sprintf(
					/* translators: 1: tên element con, 2: tên element cha */
					__( '"%1$s" không được đặt trong "%2$s".', 'saha-core' ),
					$this->name( $childType ),
					(string) $def['name']
				);
				continue;
			}

			$child = $this->node( $rawChild, $type, $depth + 1, $id . '.children.' . $index );

			if ( null !== $child ) {
				$children[] = $child;
			}
		}

		return new Node( $id, $type, $props, $advanced, $children );
	}

	/**
	 * Sanitize các giá trị theo danh sách control; key lạ bị bỏ.
	 *
	 * @param array<string, mixed>                $input    Giá trị thô.
	 * @param array<string, array<string, mixed>> $controls Định nghĩa control.
	 * @param string                              $id       ID node (cho đường dẫn lỗi).
	 * @return array<string, mixed>
	 */
	private function values( array $input, array $controls, string $id ): array {
		$out = array();

		foreach ( $controls as $key => $def ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}

			try {
				$clean = $this->controls->sanitize( $input[ $key ], (array) $def );
			} catch ( InvalidValue | \Saha\Core\ThemeOptions\InvalidValue $e ) {
				$this->errors[ $id . '.' . $key ] = $e->getMessage();
				continue;
			}

			if ( null !== $clean ) {
				$out[ $key ] = $clean;
			}
		}

		return $out;
	}

	/**
	 * ID hợp lệ và duy nhất; sai/trùng thì cấp mới.
	 *
	 * @param mixed $id ID thô.
	 */
	private function id( $id ): string {
		$id = is_string( $id ) ? $id : '';

		if ( ! preg_match( '/^[a-z0-9]{8}$/', $id ) || isset( $this->ids[ $id ] ) ) {
			do {
				$id = self::newId();
			} while ( isset( $this->ids[ $id ] ) );
		}

		$this->ids[ $id ] = true;

		return $id;
	}

	/**
	 * ID ngẫu nhiên 8 ký tự [a-z0-9].
	 */
	public static function newId(): string {
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
		$id    = '';

		for ( $i = 0; $i < 8; $i++ ) {
			$id .= $chars[ random_int( 0, 35 ) ];
		}

		return $id;
	}

	/**
	 * Tên hiển thị của type.
	 *
	 * @param string $type Type.
	 */
	private function name( string $type ): string {
		$element = $this->elements->get( $type );

		return $element ? (string) $element->def()['name'] : $type;
	}

	/**
	 * Bắt đầu lần sanitize mới.
	 */
	private function reset(): void {
		$this->errors = array();
		$this->ids    = array();
		$this->count  = 0;
	}

	/**
	 * Lỗi cấp tài liệu.
	 *
	 * @param string $message Thông báo.
	 * @return array{document: null, errors: array<string, string>}
	 */
	private function fail( string $message ): array {
		return array(
			'document' => null,
			'errors'   => array( 'document' => $message ),
		);
	}
}
