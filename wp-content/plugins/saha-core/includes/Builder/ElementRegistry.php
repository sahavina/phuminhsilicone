<?php
/**
 * Danh sách element của builder.
 *
 * @package Saha\Core
 */

declare( strict_types=1 );

namespace Saha\Core\Builder;

use Saha\Core\Builder\Controls\ControlRegistry;
use Saha\Core\Builder\Elements\Account;
use Saha\Core\Builder\Elements\Cart;
use Saha\Core\Builder\Elements\Contact;
use Saha\Core\Builder\Elements\Copyright;
use Saha\Core\Builder\Elements\HeaderOffcanvas;
use Saha\Core\Builder\Elements\CategoryMenu;
use Saha\Core\Builder\Elements\Accordion;
use Saha\Core\Builder\Elements\AccordionItem;
use Saha\Core\Builder\Elements\IconList;
use Saha\Core\Builder\Elements\Marquee;
use Saha\Core\Builder\Elements\SectionTitle;
use Saha\Core\Builder\Elements\Slide;
use Saha\Core\Builder\Elements\Slider;
use Saha\Core\Builder\Elements\Testimonial;
use Saha\Core\Builder\Elements\Testimonials;
use Saha\Core\Builder\Elements\ArchivePosts;
use Saha\Core\Builder\Elements\ArchiveTitle;
use Saha\Core\Builder\Elements\Breadcrumb;
use Saha\Core\Builder\Elements\FeaturedImage;
use Saha\Core\Builder\Elements\PostContent;
use Saha\Core\Builder\Elements\PostExcerpt;
use Saha\Core\Builder\Elements\PostMeta;
use Saha\Core\Builder\Elements\PostTitle;
use Saha\Core\Builder\Elements\QuoteList;
use Saha\Core\Builder\Elements\QuoteListLink;
use Saha\Core\Builder\Elements\ProductAddToCart;
use Saha\Core\Builder\Elements\ProductAfterSummary;
use Saha\Core\Builder\Elements\ProductArchive;
use Saha\Core\Builder\Elements\ProductGallery;
use Saha\Core\Builder\Elements\ProductMeta;
use Saha\Core\Builder\Elements\ProductPrice;
use Saha\Core\Builder\Elements\ProductRelated;
use Saha\Core\Builder\Elements\ProductSummary;
use Saha\Core\Builder\Elements\ProductTabs;
use Saha\Core\Builder\Elements\HeaderRow;
use Saha\Core\Builder\Elements\HeaderZone;
use Saha\Core\Builder\Elements\Logo;
use Saha\Core\Builder\Elements\MenuToggle;
use Saha\Core\Builder\Elements\NavMenu;
use Saha\Core\Builder\Elements\Search;
use Saha\Core\Builder\Elements\SiteHeader;
use Saha\Core\Builder\Elements\Social;
use Saha\Core\Builder\Elements\Banner;
use Saha\Core\Builder\Elements\Block;
use Saha\Core\Builder\Elements\Button;
use Saha\Core\Builder\Elements\Column;
use Saha\Core\Builder\Elements\Container;
use Saha\Core\Builder\Elements\Cta;
use Saha\Core\Builder\Elements\Divider;
use Saha\Core\Builder\Elements\Element;
use Saha\Core\Builder\Elements\Heading;
use Saha\Core\Builder\Elements\Html;
use Saha\Core\Builder\Elements\Icon;
use Saha\Core\Builder\Elements\IconBox;
use Saha\Core\Builder\Elements\Image;
use Saha\Core\Builder\Elements\Posts;
use Saha\Core\Builder\Elements\ProductCategories;
use Saha\Core\Builder\Elements\Products;
use Saha\Core\Builder\Elements\Row;
use Saha\Core\Builder\Elements\Section;
use Saha\Core\Builder\Elements\Shortcode;
use Saha\Core\Builder\Elements\Spacer;
use Saha\Core\Builder\Elements\Text;

defined( 'ABSPATH' ) || exit;

/**
 * ElementRegistry.
 */
final class ElementRegistry {

	/**
	 * Singleton.
	 *
	 * @var ElementRegistry|null
	 */
	private static ?ElementRegistry $instance = null;

	/**
	 * Element theo type.
	 *
	 * @var array<string, Element>
	 */
	private array $elements = array();

	/**
	 * Lấy registry.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();

			$core = array(
				// Bố cục.
				new Section(),
				new Container(),
				new Row(),
				new Column(),
				new Spacer(),
				new Divider(),
				// Nội dung.
				new Heading(),
				new Text(),
				new Button(),
				new Image(),
				new Icon(),
				new IconBox(),
				new Html(),
				new Shortcode(),
				new SectionTitle(),
				new IconList(),
				new Accordion(),
				new AccordionItem(),
				// Marketing.
				new Banner(),
				new Cta(),
				new Block(),
				new Marquee(),
				new Slider(),
				new Slide(),
				new Testimonials(),
				new Testimonial(),
				// WooCommerce, blog.
				new Products(),
				new ProductCategories(),
				new Posts(),
				// Header & footer (mốc 1.5).
				new SiteHeader(),
				new HeaderRow(),
				new HeaderZone(),
				new HeaderOffcanvas(),
				new MenuToggle(),
				new Logo(),
				new NavMenu(),
				new Search(),
				new CategoryMenu(),
				new Account(),
				new Cart(),
				new QuoteListLink(),
				new Contact(),
				new Social(),
				new Copyright(),
				// Template Builder — element động (mốc 2.2).
				new QuoteList(),
				new PostTitle(),
				new PostContent(),
				new PostExcerpt(),
				new FeaturedImage(),
				new PostMeta(),
				new Breadcrumb(),
				new ArchiveTitle(),
				new ArchivePosts(),
				new ProductGallery(),
				new ProductPrice(),
				new ProductAddToCart(),
				new ProductMeta(),
				new ProductTabs(),
				new ProductRelated(),
				new ProductSummary(),
				new ProductAfterSummary(),
				new ProductArchive(),
			);

			foreach ( $core as $element ) {
				self::$instance->register( $element );
			}

			/**
			 * Thêm/bớt element (spec §78).
			 *
			 * add_action( 'saha_builder_elements', fn( $registry ) => $registry->register( new My_Element() ) );
			 *
			 * @param ElementRegistry $registry Registry.
			 */
			do_action( 'saha_builder_elements', self::$instance );
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
	 * Đăng ký element.
	 *
	 * @param Element $element Element.
	 */
	public function register( Element $element ): void {
		$type = $element->type();

		if ( preg_match( '/^[a-z][a-z0-9_-]{0,39}$/', $type ) ) {
			$this->elements[ $type ] = $element;
		}
	}

	/**
	 * Gỡ element.
	 *
	 * @param string $type Type.
	 */
	public function unregister( string $type ): void {
		unset( $this->elements[ $type ] );
	}

	/**
	 * Lấy element.
	 *
	 * @param string $type Type.
	 */
	public function get( string $type ): ?Element {
		return $this->elements[ $type ] ?? null;
	}

	/**
	 * Mọi element.
	 *
	 * @return array<string, Element>
	 */
	public function all(): array {
		return $this->elements;
	}

	/**
	 * Control của tab "Nâng cao" — dùng chung cho mọi element.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function advancedControls(): array {
		return array(
			'margin'      => array(
				'type'       => 'spacing',
				'label'      => __( 'Lề ngoài (margin)', 'saha-core' ),
				'section'    => 'advanced',
				'responsive' => true,
				'allowAuto'  => true,
				'min'        => -500,
				'max'        => 1000,
			),
			'padding'     => array(
				'type'       => 'spacing',
				'label'      => __( 'Lề trong (padding)', 'saha-core' ),
				'section'    => 'advanced',
				'responsive' => true,
				'min'        => 0,
				'max'        => 1000,
			),
			'cssId'       => array(
				'type'    => 'htmlId',
				'label'   => __( 'ID (dùng làm neo #)', 'saha-core' ),
				'section' => 'advanced',
			),
			'cssClass'    => array(
				'type'    => 'classList',
				'label'   => __( 'Class CSS', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideDesktop' => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên desktop', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideTablet'  => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên tablet', 'saha-core' ),
				'section' => 'advanced',
			),
			'hideMobile'  => array(
				'type'    => 'toggle',
				'label'   => __( 'Ẩn trên mobile', 'saha-core' ),
				'section' => 'advanced',
			),
		);
	}

	/**
	 * Định nghĩa gửi cho editor (GET /builder/elements).
	 *
	 * @return array<string, mixed>
	 */
	public function forClient(): array {
		$controls = ControlRegistry::instance();
		$elements = array();

		foreach ( $this->elements as $type => $element ) {
			$def   = $element->def();
			$items = array();

			foreach ( (array) $def['controls'] as $key => $control ) {
				$items[] = array( 'key' => (string) $key ) + $controls->forClient( (array) $control );
			}

			$elements[] = array(
				'type'            => $type,
				'name'            => (string) $def['name'],
				'icon'            => (string) $def['icon'],
				'category'        => (string) $def['category'],
				'allowedParents'  => array_values( (array) $def['allowedParents'] ),
				'allowedChildren' => array_values( (array) $def['allowedChildren'] ),
				'initialChildren' => array_values( (array) $def['initialChildren'] ),
				'dynamic'         => (bool) $def['dynamic'],
				'controls'        => $items,
			);
		}

		$advanced = array();

		foreach ( self::advancedControls() as $key => $control ) {
			$advanced[] = array( 'key' => $key ) + $controls->forClient( $control );
		}

		return array(
			'schemaVersion' => Schema\SchemaMigrator::CURRENT,
			'elements'      => $elements,
			'advanced'      => $advanced,
			'breakpoints'   => array(
				'tablet' => Limits::TABLET_MAX,
				'mobile' => Limits::MOBILE_MAX,
			),
			'limits'        => array(
				'maxDepth'    => Limits::MAX_DEPTH,
				'maxElements' => Limits::MAX_ELEMENTS,
				'maxBytes'    => Limits::MAX_BYTES,
			),
		);
	}
}
