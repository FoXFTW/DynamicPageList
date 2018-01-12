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
		$_listAttributes =
			( $listAttributes == '' ) ? '' : ' ' . Sanitizer::fixTagAttributes( $listAttributes, 'ul' );
		$_itemAttributes =
			( $itemAttributes == '' ) ? '' : ' ' . Sanitizer::fixTagAttributes( $itemAttributes, 'li' );

		$this->sSectionTags = $sectionSeparators;
		$this->aMultiSecSeparators = $multiSectionSeparators;
		$this->iDominantSection = $dominantSection - 1; // 0 based index

		switch ( strtolower($listMode) ) {
			case 'inline':
				if ( stristr( $inlineText, '<br />' ) ) { //one item per line (pseudo-inline)
					$this->sListStart = "<div {$_listAttributes}>";
					$this->sListEnd = '</div>';
				}

				$this->sItemStart = "<span {$_itemAttributes}>";
				$this->sItemEnd = '</span>';
				$this->sInline = $inlineText;
				break;

			case 'gallery':
				$this->sListStart = "<gallery>\n";
				$this->sListEnd = "\n</gallery>";
				$this->sItemStart = '';
				$this->sItemEnd = '||';
				$this->sInline = "\n";
				break;

			case 'ordered':
				if ( $iOffset == 0 ) {
					$this->sListStart = "<ol start=1 {$_listAttributes}>";
				} else {
					$iOffset++;
					$this->sListStart = "<ol start={$iOffset} {$_listAttributes}>";
				}

				$this->sListEnd = '</ol>';
				$this->sItemStart = "<li {$_itemAttributes}>";
				$this->sItemEnd = '</li>';
				break;

			case 'unordered':
				$this->sListStart = "<ul {$_listAttributes}>";
				$this->sListEnd = '</ul>';
				$this->sItemStart = "<li {$_itemAttributes}>";
				$this->sItemEnd = '</li>';
				break;

			case 'definition':
				$this->sListStart = "<dl {$_listAttributes}>";
				$this->sListEnd = '</dl>';
				// item html attributes on dt element or dd element ?
				$this->sHeadingStart = '<dt>';
				$this->sHeadingEnd = '</dt><dd>';
				$this->sItemEnd = '</dd>';
				break;

			case 'h2':
			case 'h3':
			case 'h4':
				$this->sListStart = "<div {$_listAttributes}>";
				$this->sListEnd = '</div>';
				$this->sHeadingStart = "<{$listMode}>";
				$this->sHeadingEnd = "</{$listMode}>";
				break;

			case 'userformat':
				list( $this->sListStart, $this->sItemStart, $this->sItemEnd, $this->sListEnd ) =
					array_pad( $listSeparators, 4, null );
				$this->sInline = $inlineText;
				break;
		}
	}
}
