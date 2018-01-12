<?php
/**
 * DynamicPageList3
 * DPL Article Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 **/

namespace DPL;

use Article as WikiArticle;
use MWException;
use Title;

class Article extends WikiArticle {
	/**
	 * Article Headings - Maps heading to count (# of pages under each heading).
	 *
	 * @var array
	 */
	static private $headings = [];

	/**
	 * Used only for static::newFromRow
	 *
	 * @var \DPL\Parameters
	 */
	static private $parameters;

	/**
	 * Used only for static::newFromRow
	 *
	 * @var \Title
	 */
	static private $title;

	/**
	 * Title
	 *
	 * @var \Title
	 */
	public $mTitle = null;

	/**
	 * Namespace ID
	 *
	 * @var string
	 */
	public $mNamespace = - 1;

	/**
	 * Page ID
	 *
	 * @var int
	 */
	public $mID = 0;

	/**
	 * Selected title of initial page.
	 *
	 * @var string
	 */
	public $mSelTitle = '';

	/**
	 * Selected namespace ID of initial page.
	 *
	 * @var string
	 */
	public $mSelNamespace = - 1;

	/**
	 * Selected title of image.
	 *
	 * @var string
	 */
	public $mImageSelTitle = '';

	/**
	 * HTML link to page.
	 *
	 * @var string
	 */
	public $mLink = '';

	/**
	 * External link on the page.
	 *
	 * @var string
	 */
	public $mExternalLink = '';

	/**
	 * First character of the page title.
	 *
	 * @var string
	 */
	public $mStartChar = null;

	/**
	 * Heading (link to the associated page) that page belongs to in the list (default '' means no heading)
	 *
	 * @var string
	 */
	public $mParentHLink = '';

	/**
	 * Category links on the page.
	 *
	 * @var array
	 */
	public $mCategoryLinks = [];

	/**
	 * Category names (without link) in the page.
	 *
	 * @var array
	 */
	public $mCategoryTexts = [];

	/**
	 * Number of times this page has been viewed.
	 *
	 * @var int
	 */
	public $mCounter = 0;

	/**
	 * Article length in bytes of wiki text
	 *
	 * @var string
	 */
	public $mSize = '';

	/**
	 * Timestamp depending on the user's request (can be first/last edit, page_touched, ...)
	 *
	 * @var string
	 */
	public $mDate = null;

	/**
	 * Timestamp depending on the user's request, based on user format definition.
	 *
	 * @var string
	 */
	public $myDate = null;

	/**
	 * Revision ID
	 *
	 * @var int
	 */
	public $mRevision = null;

	/**
	 * Link to editor (first/last, depending on user's request) 's page or contributions if not registered.
	 *
	 * @var string
	 */
	public $mUserLink = null;

	/**
	 * Name of editor (first/last, depending on user's request) or contributions if not registered.
	 *
	 * @var string
	 */
	public $mUser = null;

	/**
	 * Edit Summary(Revision Comment)
	 *
	 * @var string
	 */
	public $mComment = null;

	/**
	 * Number of bytes changed.
	 *
	 * @var int
	 */
	public $mContribution = 0;

	/**
	 * Short string indicating the size of a contribution.
	 *
	 * @var string
	 */
	public $mContrib = '';

	/**
	 * User text of who made the changes.
	 *
	 * @var string
	 */
	public $mContributor = null;

	/**
	 * Main Constructor
	 *
	 * @param \Title $title Title
	 * @param int $namespace Namespace
	 * @return void
	 */
	public function __construct( Title $title, $namespace ) {
		$this->mTitle = $title;
		$this->mNamespace = $namespace;
	}

	/**
	 * Initialize a new instance from a database row.
	 *
	 * @param array $row Database Row
	 * @param \DPL\Parameters $parameters \DPL\Parameters Object
	 * @param \Title $title Mediawiki Title Object
	 * @param int $pageNamespace Page Namespace ID
	 * @param string $pageTitle Page Title as Selected from Query
	 * @return \DPL\Article \DPL\Article Object
	 * @throws MWException
	 */
	static public function newFromRow(
		$row, Parameters $parameters, Title $title, $pageNamespace, $pageTitle
	) {
		global $wgLang;

		static::$parameters = $parameters;
		static::$title = $title;

		$article = new Article( $title, $pageNamespace );
		$article->mLink = static::getArticleLinkFromRow( $row, $pageNamespace );
		$article->mStartChar = static::getFirstCharFromRow( $row, $pageTitle );
		$article->mID = intval( $row['page_id'] );

		// External link
		if ( isset( $row['el_to'] ) ) {
			$article->mExternalLink = $row['el_to'];
		}

		// SHOW PAGE_COUNTER
		if ( isset( $row['page_counter'] ) ) {
			$article->mCounter = $row['page_counter'];
		}

		// SHOW PAGE_SIZE
		$pageSize = static::getPageSizeFromRow( $row );
		if ( !is_null( $pageSize ) ) {
			$article->mSize = $pageSize;
		}

		$initiallySelectedPage = static::getInitiallySelectedPageFromRow( $row );
		if ( !empty( $initiallySelectedPage ) ) {
			$article->mSelTitle = $initiallySelectedPage['title'];
			$article->mSelNamespace = $initiallySelectedPage['namespace'];
		}

		$selectedImageTitle = static::getSelectedImageTitleFromRow( $row );
		if ( !is_null( $selectedImageTitle ) ) {
			$article->mImageSelTitle = $selectedImageTitle;
		}

		if ( $parameters->getParameter( 'goal' ) != 'categories' ) {
			$revisionData = static::getRevisionDataFromRow( $row );
			if ( !empty( $revisionData ) ) {
				$article->mRevision = $revisionData['id'];
				$article->mUser = $revisionData['user'];
				$article->mDate = $revisionData['date'];;
				$article->mComment = $revisionData['comment'];
			}

			$timestamp = static::getTimestampFromRow( $row );
			if ( !is_null( $timestamp ) ) {
				$article->mDate = $timestamp;
			}

			// Time zone adjustment
			if ( !is_null( $article->mDate ) ) {
				$article->mDate = $wgLang->userAdjust( $article->mDate );
			}

			// Apply the userdateformat
			$userDateFormat = $parameters->getParameter( 'userdateformat' );
			if ( !is_null( $article->mDate ) && !is_null( $userDateFormat ) ) {
				$date = gmdate( $userDateFormat, wfTimeStamp( TS_UNIX, $article->mDate ) );
				$article->myDate = $date;
			}

			// CONTRIBUTION, CONTRIBUTOR
			$contribution = static::getContributionAndContributorFromRow( $row );
			if ( !empty( $contribution ) ) {
				$article->mContribution = $contribution['contribution'];
				$article->mContributor = $contribution['contributor'];
				$article->mContrib = $contribution['contrib'];
			}

			// USER/AUTHOR(S)
			$author = static::getAuthorFromRow( $row );
			if ( !empty( $author ) ) {
				$article->mUserLink = $author['link'];
				$article->mUser = $author['user'];
			}

			// CATEGORY LINKS FROM CURRENT PAGE
			$categories = static::getCategoriesFromRow( $row );
			if ( !empty( $categories ) ) {
				$article->mCategoryLinks = $categories['links'];
				$article->mCategoryTexts = $categories['texts'];
			}

			// PARENT HEADING
			$parentHeading = static::getParentHeadLinkFromRow( $row );
			if ( !is_null( $parentHeading ) ) {
				$article->mParentHLink = $parentHeading;
			}
		}

		// Reset Parameter and Title Object
		static::$parameters = null;
		static::$title = null;

		return $article;
	}

	/**
	 * Generates the Wikitext Article link
	 *
	 * @param array $row DB Row
	 * @param int $pageNamespace Page Namespace
	 * @return string
	 * @throws MWException
	 */
	private static function getArticleLinkFromRow( array $row, $pageNamespace ) {
		$titleText = static::getTitleText();
		$titleTextEscaped = htmlspecialchars( $titleText );

		$showCurId = (bool)static::$parameters->getParameter( 'showcurid' );
		if ( $showCurId && isset( $row['page_id'] ) ) {
			$linkUrl = static::$title->getLinkURL( [ 'curid' => $row['page_id'] ] );
			$articleLink = "[{$linkUrl} {$titleTextEscaped}]";
		} else {
			$articleLink = "[[";

			$escapeLinks = (bool)static::$parameters->getParameter( 'escapelinks' );
			if ( $escapeLinks || ( $pageNamespace == NS_CATEGORY || $pageNamespace == NS_FILE ) ) {
				$articleLink .= ":";
			}

			$articleLink .= static::$title->getFullText() . '|' . $titleTextEscaped . ']]';
		}

		return $articleLink;
	}

	/**
	 * Returns the Text of the Title object
	 * Prefixed with Namespace if 'shownamespace' is true
	 * Cut to 'titlemaxlen' is Title Text is longer than 'titlemaxlen'
	 *
	 * @return null|string|string[]
	 * @throws MWException
	 */
	private static function getTitleText() {
		$titleText = static::$title->getText();

		if ( static::$parameters->getParameter( 'shownamespace' ) === true ) {
			$titleText = static::$title->getPrefixedText();
		}

		$replaceInTitle = static::$parameters->getParameter( 'replaceintitle' );
		if ( !is_null( $replaceInTitle ) ) {
			if ( isset( $replaceInTitle[0] ) && isset( $replaceInTitle[1] ) ) {
				$pattern = $replaceInTitle[0];
				$replacement = $replaceInTitle[1];
				$titleText = preg_replace( $pattern, $replacement, $titleText );
			} else {
				throw new MWException( __METHOD__ .
				                       ': Pattern or Replacement missing in "replaceintitle"' );
			}
		}

		$titleMaxLen = static::$parameters->getParameter( 'titlemaxlen' );
		if ( !is_null( $titleMaxLen ) && strlen( $titleText ) > intval( $titleMaxLen ) ) {
			$titleText = substr( $titleText, 0, intval( $titleMaxLen ) ) . '...';
		}

		return $titleText;
	}

	/**
	 * get first char used for category-style output
	 *
	 * @param array $row
	 * @param $pageTitle
	 * @return string
	 */
	private static function getFirstCharFromRow( array $row, $pageTitle ) {
		global $wgContLang;

		if ( isset( $row['sortkey'] ) ) {
			return $wgContLang->convert( $wgContLang->firstChar( $row['sortkey'] ) );
		} else {
			return $wgContLang->convert( $wgContLang->firstChar( $pageTitle ) );
		}
	}

	/**
	 * Returns Page length if 'addpagesize' is true
	 *
	 * @param array $row
	 * @return null|string
	 */
	private static function getPageSizeFromRow( array $row ) {
		$addPageSize = (bool)static::$parameters->getParameter( 'addpagesize' );
		if ( $addPageSize && isset( $row['page_len'] ) ) {
			return $row['page_len'];
		}

		return null;
	}

	/**
	 * Returns initially selected page and namespace. 'unknown page' and 0 is 'sel_title' is not set
	 * Empty array otherwise
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getInitiallySelectedPageFromRow( array $row ) {
		$linksTo = static::$parameters->getParameter( 'linksto' );
		$linksFrom = static::$parameters->getParameter( 'linksfrom' );

		if ( ( is_array( $linksTo ) && count( $linksTo ) > 0 ) ||
		     ( is_array( $linksFrom ) && count( $linksFrom ) > 0 ) ) {
			if ( !isset( $row['sel_title'] ) ) {
				return [
					'title' => 'unknown page',
					'namespace' => 0,
				];
			} else {
				return [
					'title' => $row['sel_title'],
					'namespace' => $row['sel_ns'],
				];
			}
		}

		return [];
	}

	/**
	 * Get selected image Title
	 *
	 * @param array $row
	 * @return mixed|null|string
	 */
	private static function getSelectedImageTitleFromRow( array $row ) {
		$imageUsed = static::$parameters->getParameter( 'imageused' );

		if ( is_array( $imageUsed ) && count( $imageUsed ) > 0 ) {
			if ( !isset( $row['image_sel_title'] ) ) {
				return 'unknown image';
			} else {
				return $row['image_sel_title'];
			}
		}

		return null;
	}

	/**
	 * Returns Revision ID, User, Timestamp and Comment if 'lastrevisionbefore' or
	 * 'allrevisionsbefore' or 'firstrevisionsince' or 'allrevisionssince' is set
	 * Empty array otherwise
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getRevisionDataFromRow( array $row ) {
		$lastRevisionBefore = static::$parameters->getParameter( 'lastrevisionbefore' );
		$allRevisionsBefore = static::$parameters->getParameter( 'allrevisionsbefore' );
		$firstRevisionSince = static::$parameters->getParameter( 'firstrevisionsince' );
		$allRevisionsSince = static::$parameters->getParameter( 'allrevisionssince' );

		if ( !is_null( $lastRevisionBefore ) || !is_null( $allRevisionsBefore ) ||
		     !is_null( $firstRevisionSince ) || !is_null( $allRevisionsSince ) ) {
			return [
				'id' => $row['rev_id'],
				'user' => $row['rev_user_text'],
				'date' => $row['rev_timestamp'],
				'comment' => $row['rev_comment'],
			];
		}

		return [];
	}

	/**
	 * SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
	 *
	 * @param array $row
	 * @return mixed|null
	 */
	private static function getTimestampFromRow( array $row ) {
		$addPageTouchedDate = static::$parameters->getParameter( 'addpagetoucheddate' );
		$addFirstCategoryDate = static::$parameters->getParameter( 'addfirstcategorydate' );
		$addEditDate = static::$parameters->getParameter( 'addeditdate' );

		if ( $addPageTouchedDate ) {
			return $row['page_touched'];
		} elseif ( $addFirstCategoryDate ) {
			return $row['cl_timestamp'];
		} elseif ( $addEditDate && isset( $row['rev_timestamp'] ) ) {
			return $row['rev_timestamp'];
		} elseif ( $addEditDate && isset( $row['page_touched'] ) ) {
			return $row['page_touched'];
		}

		return null;
	}

	/**
	 * Returns Contribution and Contributor if 'addcontribution' is set.
	 * Empty array otherwise
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getContributionAndContributorFromRow( array $row ) {
		$addContribution = static::$parameters->getParameter( 'addcontribution' );

		if ( $addContribution ) {
			$stars = '*****************';

			return [
				'contribution' => $row['contribution'],
				'contributor' => $row['contributor'],
				'contrib' => substr( $stars, 0, round( log( $row['contribution'] ) ) ),
			];
		}

		return [];
	}

	/**
	 * USER/AUTHOR(S)
	 * because we are going to do a recursive parse at the end of the output phase
	 * we have to generate wiki syntax for linking to a user´s homepage
	 * Returns empty array if neither 'adduser' nor 'addauthor' nor 'addlasteditor' is set
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getAuthorFromRow( array $row ) {
		$addUser = static::$parameters->getParameter( 'adduser' );
		$addAuthor = static::$parameters->getParameter( 'addauthor' );
		$addLastEditor = static::$parameters->getParameter( 'addlasteditor' );

		if ( $addUser || $addAuthor || $addLastEditor ) {
			return [
				'link' => "[[User:{$row['rev_user_text']}|{$row['rev_user_text']}]]",
				'user' => $row['rev_user_text'],
			];
		}

		return [];
	}

	/**
	 * Returns Categories and respective Names it 'addcategories' is set
	 * Empty array otherwise
	 *
	 * @param array $row
	 * @return array
	 */
	private static function getCategoriesFromRow( array $row ) {
		$addCategories = static::$parameters->getParameter( 'addcategories' );

		if ( $addCategories && isset( $row['cats'] ) ) {
			$artCatNames = explode( ' | ', $row['cats'] );
			$categories = [
				'links' => [],
				'texts' => [],
			];

			foreach ( $artCatNames as $artCatName ) {
				$categoryName = str_replace( '_', ' ', $artCatName );
				$categories['links'][] = "[[:Category:{$artCatName}|{$categoryName}]]";
				$categories['texts'][] = $categoryName;
			}

			return $categories;
		}

		return [];
	}

	/**
	 * PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
	 * Returns Wikitext or null if 'headingmode' is none or 'ordermethod' is not set
	 *
	 * @param array $row
	 * @return null|string
	 */
	private static function getParentHeadLinkFromRow( array $row ) {
		$headingMode = static::$parameters->getParameter( 'headingmode' );
		$orderMethod = static::$parameters->getParameter( 'ordermethod' );

		if ( $headingMode !== 'none' && isset( $orderMethod[0] ) ) {
			switch ( $orderMethod[0] ) {
				case 'category':
					//Count one more page in this heading
					$headingCount = 1;

					if ( isset( self::$headings[$row['cl_to']] ) ) {
						$headingCount = intval( self::$headings[$row['cl_to']] ) + 1;
					}

					self::$headings[$row['cl_to']] = $headingCount;

					//uncategorized page (used if ordermethod=category,...)
					if ( empty( $row['cl_to'] ) ) {
						$message = wfMessage( 'uncategorizedpages' );

						return "[[:Special:Uncategorizedpages|{$message}]]";
					} else {
						$rowName = str_replace( '_', ' ', $row['cl_to'] );

						return "[[:Category:{$row['cl_to']}|{$rowName}]]";
					}

				case 'user':
					$headingCount = 1;
					if ( isset( self::$headings[$row['rev_user_text']] ) ) {
						$headingCount = self::$headings[$row['rev_user_text']] + 1;
					}

					self::$headings[$row['rev_user_text']] = $headingCount;

					return "[[User:{$row['rev_user_text']}|{$row['rev_user_text']}]]";
			}
		}

		return null;
	}

	/**
	 * Returns all heading information processed from all newly instantiated article objects.
	 *
	 * @return array Headings
	 */
	static public function getHeadings() {
		return self::$headings;
	}

	/**
	 * Reset the headings to their initial state.
	 * Ideally this Article class should not exist and be handled by the built in MediaWiki class.
	 * @Bug https://jira/browse/HYD-913
	 *
	 * @return void
	 */
	static public function resetHeadings() {
		self::$headings = [];
	}
}
