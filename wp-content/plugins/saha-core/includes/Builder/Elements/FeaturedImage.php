<?php
/**
 * Element động: ảnh đại diện.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder\Elements;

use Saha\Core\Builder\CssRules;
use Saha\Core\Builder\RenderContext;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * FeaturedImage — ảnh đầu bài thường là LCP → mặc định ưu tiên tải.
 */
final class FeaturedImage extends DynamicElement {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return self::dynamicDef(
			array(
				'type'     => 'featured-image',
				'name'     => __( 'Ảnh đại diện (động)', 'saha-core' ),
				'icon'     => 'image',
				'controls' => array(
					'size'     => array(
						'type'    => 'select',
						'label'   => __( 'Kích thước', 'saha-core' ),
						'section' => 'content',
						'default' => 'large',
						'options' => array(
							'medium_large' => __( 'Vừa', 'saha-core' ),
							'large'        => __( 'Lớn', 'saha-core' ),
							'full'         => __( 'Gốc', 'saha-core' ),
						),
					),
					'priority' => array(
						'type'    => 'toggle',
						'label'   => __( 'Ưu tiên tải (ảnh đầu trang)', 'saha-core' ),
						'section' => 'content',
						'default' => true,
					),
					'radius'   => array(
						'type'    => 'size',
						'label'   => __( 'Bo góc', 'saha-core' ),
						'section' => 'style',
						'units'   => array( 'px' ),
						'min'     => 0,
						'max'     => 60,
					),
				),
			)
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML con (không dùng).
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		$size     = in_array( $this->prop( $node, 'size' ), array( 'medium_large', 'large', 'full' ), true ) ? (string) $this->prop( $node, 'size' ) : 'large';
		$priority = (bool) $this->prop( $node, 'priority' );
		$html     = self::withSubject(
			$ctx,
			static function ( \WP_Post $post ) use ( $size, $priority ): string {
				return has_post_thumbnail( $post )
					? (string) get_the_post_thumbnail(
						$post,
						$size,
						$priority ? array(
							'loading'       => 'eager',
							'fetchpriority' => 'high',
						) : array( 'loading' => 'lazy' )
					)
					: '';
			}
		);

		return null === $html || '' === $html
			? $this->placeholder( $node, $ctx, __( 'Ảnh đại diện (bài này chưa có ảnh)', 'saha-core' ) )
			: '<figure' . $this->rootAttributes( $node, $ctx, array( 'saha-dynamic', 'saha-featured-image' ) ) . '>' . $html . '</figure>';
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( ' img', 'border-radius', $node->prop( 'radius' ) );
	}
}
