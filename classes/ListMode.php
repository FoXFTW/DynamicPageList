<?php
/**
 * DynamicPageList3
 * DPL ListMode Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

use Sanitizer;

class ListMode {
	/**
	 * @var string List Mode Name
	 */
	public $name;

	/**
	 * @var string List starting tag
	 */
	public $sListStart = '';

	/**
	 * @var string List ending tag
	 */
	public $sListEnd = '';

	/**
	 * @var string Heading starting tag (uses $name)
	 */
	public $sHeadingStart = '';

	/**
	 * @var string Heading ending tag (uses $name)
	 */
	public $sHeadingEnd = '';

	/**
	 * @var string Item starting tag
	 */
	public $sItemStart = '';

	/**
	 * @var string Item ending tag
	 */
	public $sItemEnd = '';

	/**
	 * @var string $inlineText or new line
	 */
	public $sInline = '';

	/**
	 * @var array $sectionSeparators
	 */
	public $sSectionTags = [];

	/**
	 * @var array $multiSectionSeparators
	 */
	public $aMultiSecSeparators = [];

	/**
	 * @var int $dominantSection
	 */
	public $iDominantSection = - 1;

	/**
	 * Holds listAttributes in 'list' key and itemAttributes in 'item' key
	 * Used in __construct
	 *
	 * @var array
	 */
	private $listItemAttributes = [];

	/**
	 * Main Constructor
	 *
	 * @param string $listMode List Mode
	 * @param string $sectionSeparators Section Separators
	 * @param string $multiSectionSeparators Multi-Section Separators
	 * @param string $inlineText Inline Text
	 * @param string $listAttributes [Optional] List Attributes
	 * @param string $itemAttributes [Optional] Item Attributes
	 * @param array $listSeparators List Separators
	 * @param int $iOffset Offset
	 * @param int $dominantSection Dominant Section
	 * @return void
	 */
	public function __construct(
		$listMode, $sectionSeparators, $multiSectionSeparators, $inlineText, $listAttributes = '',
		$itemAttributes = '', $listSeparators, $iOffset, $dominantSection
	) {
		// default for inlinetext (if not in mode=userformat)
		if ( ( $listMode != 'userformat' ) && ( $inlineText == '' ) ) {
			$inlineText = '&#160;-&#160;';
		}

		$this->name = $listMode;
		$this->sSectionTags = $sectionSeparators;
		$this->aMultiSecSeparators = $multiSectionSeparators;
		$this->iDominantSection = $dominantSection - 1; // 0 based index

		$this->listItemAttributes = [
			'list' => $this->sanitizeAttribute( $listAttributes, 'ul' ),
			'item' => $this->sanitizeAttribute( $itemAttributes, 'li' ),
		];

		switch ( strtolower( $listMode ) ) {
			case 'inline':
				$this->setInlineMode( $inlineText );
				break;

			case 'gallery':
				$this->setGalleryMode();
				break;

			case 'ordered':
				$this->setOrderedMode( $iOffset );
				break;

			case 'unordered':
				$this->setUnorderedMode();
				break;

			case 'definition':
				$this->setDefinitionMode();
				break;

			case 'h2':
			case 'h3':
			case 'h4':
				$this->setHeadingMode( $listMode );
				break;

			case 'userformat':
				$this->setUserFormatMode( $listSeparators, $inlineText );
				break;
		}
	}

	/**
	 * Wrapper for Sanitizer::fixTagAttributes
	 * Returns empty string if attribute is empty
	 *
	 * @param string $attribute The Attribute
	 * @param string $tagType Tag Type
	 * @return string Sanitized Attribute
	 */
	private function sanitizeAttribute( $attribute, $tagType ) {
		if ( !empty( $attribute ) ) {
			$attribute = Sanitizer::fixTagAttributes( $attribute, $tagType );

			return " {$attribute}";
		} else {
			return '';
		}
	}

	/**
	 * Sets attributes for Inline Mode
	 * If '<br />' is present in $inlineText, List will be wrapped in divs
	 *
	 * @param string $inlineText
	 */
	private function setInlineMode( $inlineText ) {
		if ( stristr( $inlineText, '<br />' ) ) { //one item per line (pseudo-inline)
			$this->sListStart = "<div {$this->listItemAttributes['list']}>";
			$this->sListEnd = '</div>';
		}

		$this->sItemStart = "<span {$this->listItemAttributes['item']}>";
		$this->sItemEnd = '</span>';
		$this->sInline = $inlineText;
	}

	/**
	 * Sets attributes for Gallery Mode
	 */
	private function setGalleryMode() {
		$this->sListStart = "<gallery>\n";
		$this->sListEnd = "\n</gallery>";
		$this->sItemStart = '';
		$this->sItemEnd = '||';
		$this->sInline = "\n";
	}

	/**
	 * Sets attributes for Ordered List Mode
	 * If Offset is 0 list starts at 1
	 *
	 * @param int $offset Starting offset
	 */
	private function setOrderedMode( $offset ) {
		if ( intval( $offset ) === 0 ) {
			$this->sListStart = "<ol start=1 {$this->listItemAttributes['list']}>";
		} else {
			$offset ++;
			$this->sListStart = "<ol start={$offset} {$this->listItemAttributes['list']}>";
		}

		$this->sListEnd = '</ol>';
		$this->sItemStart = "<li {$this->listItemAttributes['item']}>";
		$this->sItemEnd = '</li>';
	}

	/**
	 * Sets attributes for Unordered List Mode
	 */
	private function setUnorderedMode() {
		$this->sListStart = "<ul {$this->listItemAttributes['list']}>";
		$this->sListEnd = '</ul>';
		$this->sItemStart = "<li {$this->listItemAttributes['item']}>";
		$this->sItemEnd = '</li>';
	}

	/**
	 * Sets attributes for Definition List Mode
	 */
	private function setDefinitionMode() {
		$this->sListStart = "<dl {$this->listItemAttributes['list']}>";
		$this->sListEnd = '</dl>';
		$this->sHeadingStart = "<dt {$this->listItemAttributes['item']}>";
		$this->sHeadingEnd = "</dt><dd {$this->listItemAttributes['item']}>";
		$this->sItemEnd = '</dd>';
	}

	/**
	 * Sets attributes for Heading List Mode
	 *
	 * @param string $listMode Tag Name
	 */
	private function setHeadingMode( $listMode ) {
		$this->sListStart = "<div {$this->listItemAttributes['list']}>";
		$this->sListEnd = '</div>';
		$this->sHeadingStart = "<{$listMode}>";
		$this->sHeadingEnd = "</{$listMode}>";
	}

	/**
	 * Sets sListStart to listSeparators[0] or null,
	 * sItemStart to listSeparators[1] or null,
	 * sItemEnd to listSeparators[2] or null,
	 * sListEnd to listSeparators[3] or null
	 *
	 * @param array $listSeparators
	 * @param string $inlineText
	 */
	private function setUserFormatMode( $listSeparators, $inlineText ) {
		list( $this->sListStart, $this->sItemStart, $this->sItemEnd, $this->sListEnd ) =
			array_pad( $listSeparators, 4, null );
		$this->sInline = $inlineText;
	}
}
