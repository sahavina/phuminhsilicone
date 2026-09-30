<?php
/**
 * Sinh CSS của layout.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Schema\Document;
use Saha\Core\Builder\Schema\Node;

defined( 'ABSPATH' ) || exit;

/**
 * CssGenerator — mỗi element style qua `.saha-e-{id}`; margin/padding của tab
 * Nâng cao áp chung cho mọi element.
 */
final class CssGenerator {

	/**
	 * Tạo generator.
	 *
	 * @param ElementRegistry|null $elements Registry.
	 */
	public function __construct( private ?ElementRegistry $elements = null ) {
		$this->elements ??= ElementRegistry::instance();
	}

	/**
	 * CSS của cả tài liệu.
	 *
	 * @param Document $document Tài liệu.
	 */
	public function document( Document $document ): string {
		$rules = new CssRules();

		foreach ( $document->elements as $node ) {
			$this->collect( $node, $rules );
		}

		return $rules->toCss();
	}

	/**
	 * CSS của một subtree (render cho editor).
	 *
	 * @param Node $node Node.
	 */
	public function node( Node $node ): string {
		$rules = new CssRules();

		$this->collect( $node, $rules );

		return $rules->toCss();
	}

	/**
	 * Gom luật của node và con.
	 *
	 * @param Node     $node  Node.
	 * @param CssRules $rules Luật.
	 */
	private function collect( Node $node, CssRules $rules ): void {
		$element = $this->elements->get( $node->type );

		if ( null === $element || ! $node->isKnown() ) {
			return;
		}

		$rules->forNode( $node->id );
		$rules->spacing( '', 'margin', $node->advanced['margin'] ?? null );
		$rules->spacing( '', 'padding', $node->advanced['padding'] ?? null );
		$element->styles( $node, $rules );

		foreach ( $node->children as $child ) {
			$this->collect( $child, $rules );
		}
	}
}
