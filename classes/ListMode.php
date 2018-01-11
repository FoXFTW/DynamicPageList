<?php
/**
 * DynamicPageList3
 * DPL ListMode Class
 *
 * @author        IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license        GPL
 * @package        DynamicPageList3
 *
 **/

namespace DPL;

use Sanitizer;

class ListMode {
	public $name;
	public $sListStart = '';
	public $sListEnd = '';
	public $sHeadingStart = '';
	public $sHeadingEnd = '';
	public $sItemStart = '';
	public $sItemEnd = '';
	public $sInline = '';
	public $sSectionTags = [];
	public $aMultiSecSeparators = [];
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
				if ( stristr( $inlineText, '<BR />' ) ) { //one item per line (pseudo-inline)
					$this->sListStart = '<DIV' . $_listAttributes . '>';
					$this->sListEnd = '</DIV>';
				}

				$this->sItemStart = '<SPAN' . $_itemAttributes . '>';
				$this->sItemEnd = '</SPAN>';
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
					$this->sListStart = '<OL start=1 ' . $_listAttributes . '>';
				} else {
					$this->sListStart = '<OL start=' . ( $iOffset + 1 ) . ' ' . $_listAttributes . '>';
				}

				$this->sListEnd = '</OL>';
				$this->sItemStart = '<LI' . $_itemAttributes . '>';
				$this->sItemEnd = '</LI>';
				break;

			case 'unordered':
				$this->sListStart = '<UL' . $_listAttributes . '>';
				$this->sListEnd = '</UL>';
				$this->sItemStart = '<LI' . $_itemAttributes . '>';
				$this->sItemEnd = '</LI>';
				break;

			case 'definition':
				$this->sListStart = '<DL' . $_listAttributes . '>';
				$this->sListEnd = '</DL>';
				// item html attributes on dt element or dd element ?
				$this->sHeadingStart = '<DT>';
				$this->sHeadingEnd = '</DT><DD>';
				$this->sItemEnd = '</DD>';
				break;

			case 'h2':
			case 'h3':
			case 'h4':
				$this->sListStart = '<DIV' . $_listAttributes . '>';
				$this->sListEnd = '</DIV>';
				$this->sHeadingStart = '<' . $listMode . '>';
				$this->sHeadingEnd = '</' . $listMode . '>';
				break;

			case 'userformat':
				list( $this->sListStart, $this->sItemStart, $this->sItemEnd, $this->sListEnd ) =
					array_pad( $listSeparators, 4, null );
				$this->sInline = $inlineText;
				break;
		}
	}
}
