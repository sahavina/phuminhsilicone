<?php
/**
 * Element: accordion / câu hỏi thường gặp (chứa các mục).
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
 * Accordion — mỗi mục là `<details>/<summary>` của HTML: mở/đóng bằng bàn phím, đọc đúng
 * bởi trình đọc màn hình, không cần JS. "Mỗi lần mở một mục" dùng thuộc tính `name`
 * của `<details>` (trình duyệt cũ: mở được nhiều mục — vẫn dùng được).
 *
 * Tuỳ chọn in dữ liệu cấu trúc FAQPage (JSON-LD) từ chính nội dung các mục.
 */
final class Accordion extends Element {

	/**
	 * Định nghĩa.
	 *
	 * @return array<string, mixed>
	 */
	protected function definition(): array {
		return array(
			'type'            => 'accordion',
			'name'            => __( 'Accordion / Hỏi đáp', 'saha-core' ),
			'icon'            => 'info',
			'category'        => 'content',
			'allowedParents'  => self::CONTENT_PARENTS,
			'allowedChildren' => array( 'accordion-item' ),
			'initialChildren' => array( 'accordion-item', 'accordion-item', 'accordion-item' ),
			'controls'        => array(
				'single'     => array(
					'type'    => 'toggle',
					'label'   => __( 'Mỗi lần chỉ mở một mục', 'saha-core' ),
					'section' => 'content',
					'default' => true,
				),
				'faqSchema'  => array(
					'type'    => 'toggle',
					'label'   => __( 'Dữ liệu cấu trúc FAQ (Google)', 'saha-core' ),
					'section' => 'content',
					'default' => true,
					'help'    => __( 'Chỉ bật cho khối câu hỏi thường gặp; mỗi trang nên có một khối FAQ.', 'saha-core' ),
				),
				'background' => array(
					'type'    => 'color',
					'label'   => __( 'Nền mục', 'saha-core' ),
					'section' => 'style',
				),
				'accent'     => array(
					'type'    => 'color',
					'label'   => __( 'Màu nhấn (icon, mục đang mở)', 'saha-core' ),
					'section' => 'style',
				),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param Node          $node    Node.
	 * @param RenderContext $ctx     Ngữ cảnh.
	 * @param string        $content HTML các mục.
	 */
	public function render( Node $node, RenderContext $ctx, string $content ): string {
		if ( $this->prop( $node, 'single' ) && ! $ctx->editor ) {
			$content = (string) preg_replace( '/<details(?=\s[^>]*\bsaha-acc-item\b)/', '<details name="saha-acc-' . esc_attr( $node->id ) . '"', $content );
		}

		$schema = '';

		if ( $this->prop( $node, 'faqSchema' ) && ! $ctx->editor ) {
			$questions = array();

			foreach ( $node->children as $child ) {
				$question = trim( (string) $child->prop( 'title', '' ) );
				$answer   = trim( wp_strip_all_tags( (string) $child->prop( 'content', '' ) ) );

				if ( 'accordion-item' === $child->type && '' !== $question && '' !== $answer ) {
					$questions[] = array(
						'@type'          => 'Question',
						'name'           => $question,
						'acceptedAnswer' => array(
							'@type' => 'Answer',
							'text'  => $answer,
						),
					);
				}
			}

			if ( $questions ) {
				$schema = '<script type="application/ld+json">' . wp_json_encode(
					array(
						'@context'   => 'https://schema.org',
						'@type'      => 'FAQPage',
						'mainEntity' => $questions,
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
				) . '</script>';
			}
		}

		return '<div' . $this->rootAttributes( $node, $ctx, array( 'saha-acc' ) ) . '>' . $content . '</div>' . $schema;
	}

	/**
	 * Style.
	 *
	 * @param Node     $node Node.
	 * @param CssRules $css  Luật.
	 */
	public function styles( Node $node, CssRules $css ): void {
		$css->set( '', '--saha-acc-bg', $node->prop( 'background' ) );
		$css->set( '', '--saha-acc-accent', $node->prop( 'accent' ) );
	}
}
