<?php
/**
 * Element: Row.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Responsive;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * Row — hàng cột (flex, tự xuống dòng).
 *
 * Độ rộng cột dùng biến `--saha-col` (0–1): cột = col × 100% − gap × (1 − col),
 * nên tổng các cột bằng đúng 100% kể cả khi có khoảng cách. Row tính giúp cột:
 * - cột không đặt độ rộng chia đều phần còn lại (desktop);
 * - "xếp chồng" ở tablet/mobile: cột thành 100%, trừ khi cột đặt độ rộng riêng
 *   cho breakpoint đó.
 */
final class Row extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'row',
			'name'            => __( 'Hàng', 'saha-core' ),
			'icon'            => 'columns',
			'category'        => 'layout',
			'allowedParents'  => array( 'section', 'column', 'container' ),
			'allowedChildren' => array( 'column' ),
			'initialChildren' => array( 'column', 'column' ),
			'controls'        => array(
				'gap'             => array(
					'type'       => 'size',
					'label'      => __( 'Khoảng cách giữa cột', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'units'      => array( 'px', 'rem', '%' ),
					'min'        => 0,
					'max'        => 200,
					'help'       => __( 'Mặc định 24px.', 'saha-core' ),
				),
				'stackOn'         => array(
					'type'    => 'select',
					'label'   => __( 'Xếp chồng cột từ', 'saha-core' ),
					'section' => 'content',
					'default' => 'mobile',
					'options' => array(
						'mobile' => __( 'Mobile', 'saha-core' ),
						'tablet' => __( 'Tablet', 'saha-core' ),
						'none'   => __( 'Không xếp chồng', 'saha-core' ),
					),
				),
				'horizontalAlign' => array(
					'type'       => 'select',
					'label'      => __( 'Căn ngang', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'options'    => array(
						'start'         => __( 'Đầu', 'saha-core' ),
						'center'        => __( 'Giữa', 'saha-core' ),
						'end'           => __( 'Cuối', 'saha-core' ),
						'space-between' => __( 'Giãn đều', 'saha-core' ),
					),
				),
				'verticalAlign'   => array(
					'type'       => 'select',
					'label'      => __( 'Căn dọc các cột', 'saha-core' ),
					'section'    => 'content',
					'responsive' => true,
					'options'    => array(
						'stretch' => __( 'Kéo dài bằng nhau', 'saha-core' ),
						'start'   => __( 'Trên', 'saha-core' ),
						'center'  => __( 'Giữa', 'saha-core' ),
						'end'     => __( 'Dưới', 'saha-core' ),
					),
				),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-row' ) ) . '>' . $content . '</div>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-gap', $node->prop( 'gap' ) );
		$css->set(
			'',
			'justify-content',
			$node->prop( 'horizontalAlign' ),
			static fn( $v ) => array(
				'start'         => 'flex-start',
				'center'        => 'center',
				'end'           => 'flex-end',
				'space-between' => 'space-between',
			)[ $v ] ?? null
		);
		$css->set(
			'',
			'align-items',
			$node->prop( 'verticalAlign' ),
			static fn( $v ) => array(
				'stretch' => 'stretch',
				'start'   => 'flex-start',
				'center'  => 'center',
				'end'     => 'flex-end',
			)[ $v ] ?? null
		);

		$columns = array_values( array_filter( $node->children, static fn( Node $c ): bool => 'column' === $c->type ) );

		if ( ! $columns ) {
			return;
		}

		// Desktop: cột chưa đặt độ rộng chia đều phần còn lại.
		$used  = 0.0;
		$unset = array();

		foreach ( $columns as $column ) {
			$fraction = self::fraction( Responsive::at( $column->prop( 'width' ), 'desktop' ) );

			if ( null === $fraction ) {
				$unset[] = $column;
			} else {
				$used += $fraction;
			}
		}

		if ( $unset ) {
			$share = max( 0.05, ( 1 - min( 1.0, $used ) ) / count( $unset ) );

			foreach ( $unset as $column ) {
				$css->put( 'desktop', ' > .saha-e-' . $column->id, '--saha-col', self::format( $share ) );
			}
		}

		// Xếp chồng: tablet (≤1024) kéo theo mobile; cột có độ rộng riêng cho breakpoint thì giữ.
		$stack = (string) $this->prop( $node, 'stackOn' );

		foreach ( $columns as $column ) {
			$width = $column->prop( 'width' );

			if ( 'tablet' === $stack ) {
				$css->put( 'tablet', ' > .saha-e-' . $column->id, '--saha-col', self::format( self::fraction( Responsive::at( $width, 'tablet' ) ) ?? 1.0 ) );
			}

			if ( 'tablet' === $stack || 'mobile' === $stack ) {
				$css->put( 'mobile', ' > .saha-e-' . $column->id, '--saha-col', self::format( self::fraction( Responsive::at( $width, 'mobile' ) ) ?? 1.0 ) );
			}
		}
	}

	/**
	 * '50%' → 0.5.
	 *
	 * @param mixed $width Độ rộng.
	 */
	public static function fraction( $width ): ?float {
		if ( ! is_string( $width ) || ! preg_match( '/^(\d+(?:\.\d+)?)%$/', $width, $m ) ) {
			return null;
		}

		return max( 0.0, min( 1.0, (float) $m[1] / 100 ) );
	}

	/**
	 * 0.333333 → "0.3333".
	 *
	 * @param float $number Số.
	 */
	public static function format( float $number ): string {
		return rtrim( rtrim( number_format( $number, 4, '.', '' ), '0' ), '.' );
	}
}
