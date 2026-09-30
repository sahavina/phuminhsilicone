<?php
/**
 * Danh sách loại control.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Controls;

use Saha\Core\Builder\InvalidValue;
use Saha\Core\Builder\Responsive;

defined( 'ABSPATH' ) || exit;

/**
 * ControlRegistry.
 */
final class ControlRegistry {

	/**
	 * Singleton.
	 *
	 * @var ControlRegistry|null
	 */
	private static ?ControlRegistry $instance = null;

	/**
	 * Control theo type.
	 *
	 * @var array<string, Control>
	 */
	private array $controls = array();

	/**
	 * Lấy registry (khởi tạo lần đầu, cho phép add-on thêm control).
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();

			foreach ( array(
				new Text(),
				new Textarea(),
				new RichText(),
				new Number(),
				new Select(),
				new Toggle(),
				new Color(),
				new Size(),
				new Spacing(),
				new Typography(),
				new Align(),
				new Media(),
				new Link(),
				new Background(),
				new HtmlId(),
				new ClassList(),
			) as $control ) {
				self::$instance->register( $control );
			}

			/**
			 * Thêm loại control.
			 *
			 * @param ControlRegistry $registry Registry — gọi `register( new MyControl() )`.
			 */
			do_action( 'saha_builder_register_controls', self::$instance );
		}

		return self::$instance;
	}

	/**
	 * Xoá singleton (test).
	 */
	public static function reset(): void {
		self::$instance = null;
	}

	/**
	 * Đăng ký control.
	 *
	 * @param Control $control Control.
	 */
	public function register( Control $control ): void {
		$this->controls[ $control->type() ] = $control;
	}

	/**
	 * Lấy control.
	 *
	 * @param string $type Type.
	 */
	public function get( string $type ): ?Control {
		return $this->controls[ $type ] ?? null;
	}

	/**
	 * Sanitize giá trị theo định nghĩa control, xử lý responsive.
	 *
	 * @param mixed                $value Giá trị thô.
	 * @param array<string, mixed> $def   Định nghĩa.
	 * @return mixed Null = không đặt.
	 * @throws InvalidValue Khi không hợp lệ hoặc control không tồn tại.
	 */
	public function sanitize( $value, array $def ) {
		$control = $this->get( (string) ( $def['type'] ?? '' ) );

		if ( null === $control ) {
			throw new InvalidValue( __( 'Loại control không tồn tại.', 'saha-core' ) );
		}

		if ( null === $value ) {
			return null;
		}

		if ( ! empty( $def['responsive'] ) ) {
			return Responsive::map( $value, static fn( $v ) => $control->sanitize( $v, $def ) );
		}

		return $control->sanitize( $value, $def );
	}

	/**
	 * Định nghĩa control cho editor.
	 *
	 * @param array<string, mixed> $def Định nghĩa.
	 * @return array<string, mixed>
	 */
	public function forClient( array $def ): array {
		$control = $this->get( (string) ( $def['type'] ?? '' ) );

		return $control ? $control->forClient( $def ) : $def;
	}
}
