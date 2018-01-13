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
	private static $headings = [];

	/**
	 * Used only for static::newFromRow
	 * Holds the temporary Parameter object
	 *
	 * @var \DPL\Parameters
	 */
	private static $parameters;

	/**
	 * Used only for static::newFromRow
	 * Holds the temporary Title object
	 *
	 * @var \Title
	 */
	private static $title;

	/**
	 * Used only for static::newFromRow
	 * Holds the temporary Article object
	 *
	 * @var \DPL\Article
	 */
	private static $article;

	/**
	 * Used only for static::newFromRow
	 * Holds Page Namespace and Title in an array
	 *
	 * @var array
	 */
	private static $pageData = [
		'title' => '',
		'nameSpace' => - 1,
	];

	/**
	 * Used only for static::newFromRow
	 * Array with DB Data for Article
	 *
	 * @var array
	 */
	private static $dbRow = [];

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

		parent::__construct( $title );
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
		static::$dbRow = $row;
		static::$parameters = $parameters;
		static::$title = $title;
		static::$pageData = [
			'nameSpace' => $pageNamespace,
			'title' => $pageTitle,
		];
		static::$article = new Article( $title, $pageNamespace );

		static::loadRowDataIntoArticle();

		$article = static::$article;
		static::resetTemporaryStaticVars();

		return $article;
	}

	/**
	 * Sets article data based on row data and parameters
	 *
	 * @throws MWException
	 */
	private static function loadRowDataIntoArticle() {
		static::setArticleId();
		static::setArticleLink();
		static::setFirstChar();
		static::setExternalLinkTo();
		static::setPageCounter();
		static::setPageSize();
		static::setInitiallySelectedPage();
		static::setSelectedImageTitle();

		if ( static::goalParameterIsNotCategories() ) {
			static::setRevisionData();
			static::setTimestampFromDb();
			static::setContributionAndContributor();
			static::setAuthor();
			static::setCategories();
			static::setParentHeadLink();
		}
	}

	/**
	 * Sets mID to 'page_id' from db row
	 *
	 * @return void
	 */
	private static function setArticleId() {
		static::$article->mID = intval( static::$dbRow['page_id'] );
	}

	/**
	 * Generates the Wikitext Article link
	 *
	 * @return void
	 * @throws MWException
	 */
	private static function setArticleLink() {
		$titleText = static::getTitleText();
		$titleTextEscaped = htmlspecialchars( $titleText );
		$pageNamespace = static::$pageData['nameSpace'];

		$showCurId = (bool)static::$parameters->getParameter( 'showcurid' );
		if ( $showCurId && isset( $row['page_id'] ) ) {
			$linkUrl = static::$title->getLinkURL( [ 'curid' => static::$dbRow['page_id'] ] );
			$articleLink = "[{$linkUrl} {$titleTextEscaped}]";
		} else {
			$articleLink = "[[";

			$escapeLinks = (bool)static::$parameters->getParameter( 'escapelinks' );
			if ( $escapeLinks || ( $pageNamespace == NS_CATEGORY || $pageNamespace == NS_FILE ) ) {
				$articleLink .= ":";
			}

			$articleLink .= static::$title->getFullText() . '|' . $titleTextEscaped . ']]';
		}

		static::$article->mLink = $articleLink;
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
	 * @return void
	 */
	private static function setFirstChar() {
		global $wgContLang;

		if ( isset( static::$dbRow['sortkey'] ) ) {
			$char = $wgContLang->convert( $wgContLang->firstChar( static::$dbRow['sortkey'] ) );
		} else {
			$char = $wgContLang->convert( $wgContLang->firstChar( static::$pageData['title'] ) );
		}

		static::$article->mStartChar = $char;
	}

	/**
	 * Sets mExternalLink if 'el_to' is set in db row
	 *
	 * @return void
	 */
	private static function setExternalLinkTo() {
		if ( isset( static::$dbRow['el_to'] ) ) {
			static::$article->mExternalLink = static::$dbRow['el_to'];
		}
	}

	/**
	 * Sets mCounter if 'page_counter' is set in db row
	 *
	 * @return void
	 */
	private static function setPageCounter() {
		if ( isset( static::$dbRow['page_counter'] ) ) {
			static::$article->mCounter = static::$dbRow['page_counter'];
		}
	}

	/**
	 * Sets mSize if 'addpagesize' is true and 'page_len' exists in db row
	 *
	 * @return void
	 */
	private static function setPageSize() {
		$addPageSize = (bool)static::$parameters->getParameter( 'addpagesize' );

		if ( $addPageSize && isset( static::$dbRow['page_len'] ) ) {
			static::$article->mSize = static::$dbRow['page_len'];
		}
	}

	/**
	 * Sets mSelTitle and mSelNamespace to 'sel_title' and 'sel_ns' if 'linksto' or 'linksfrom'
	 * is present as a parameter.
	 * Sets it to 'unknown page' and 0 if 'sel_title' is not present in db row
	 * If neither 'linksto' nor 'linksfrom' is set, nothing will be set
	 *
	 * @return void
	 */
	private static function setInitiallySelectedPage() {
		$linksTo = static::$parameters->getParameter( 'linksto' );
		$linksFrom = static::$parameters->getParameter( 'linksfrom' );

		if ( ( is_array( $linksTo ) && count( $linksTo ) > 0 ) ||
		     ( is_array( $linksFrom ) && count( $linksFrom ) > 0 ) ) {
			if ( !isset( $row['sel_title'] ) ) {
				static::$article->mSelTitle = 'unknown page';
				static::$article->mSelNamespace = 0;
			} else {
				static::$article->mSelTitle = static::$dbRow['sel_title'];
				static::$article->mSelNamespace = static::$dbRow['sel_ns'];
			}
		}
	}

	/**
	 * Set mImageSelTitle if 'imageused' cound is > 0
	 *
	 * @return void
	 */
	private static function setSelectedImageTitle() {
		$imageUsed = static::$parameters->getParameter( 'imageused' );

		if ( is_array( $imageUsed ) && count( $imageUsed ) > 0 ) {
			if ( !isset( static::$dbRow['image_sel_title'] ) ) {
				static::$article->mImageSelTitle = 'unknown image';
			} else {
				static::$article->mImageSelTitle = static::$dbRow['image_sel_title'];
			}
		}
	}

	private static function goalParameterIsNotCategories() {
		$goal = static::$parameters->getParameter( 'goal' );

		return $goal !== 'categories';
	}

	/**
	 * Sets Revision ID, User, Timestamp and Comment if 'lastrevisionbefore' or
	 * 'allrevisionsbefore' or 'firstrevisionsince' or 'allrevisionssince' is set
	 * Nothing otherwise
	 *
	 * @return void
	 */
	private static function setRevisionData() {
		$lastRevisionBefore = static::$parameters->getParameter( 'lastrevisionbefore' );
		$allRevisionsBefore = static::$parameters->getParameter( 'allrevisionsbefore' );
		$firstRevisionSince = static::$parameters->getParameter( 'firstrevisionsince' );
		$allRevisionsSince = static::$parameters->getParameter( 'allrevisionssince' );

		if ( !is_null( $lastRevisionBefore ) || !is_null( $allRevisionsBefore ) ||
		     !is_null( $firstRevisionSince ) || !is_null( $allRevisionsSince ) ) {
			static::$article->mRevision = static::$dbRow['rev_id'];
			static::$article->mUser = static::$dbRow['rev_user_text'];
			static::$article->mDate = static::$dbRow['rev_timestamp'];
			static::$article->mComment = static::$dbRow['rev_comment'];
		}
	}

	/**
	 * Sets myDate to
	 * 'page_touched' if 'addpagetoucheddate' is true
	 * 'cl_timestamp' if 'addfirstcategorydate' is true
	 * 'rev_timestamp' if 'addeditdate' is true and 'rev_timestamp' exists in db
	 * 'page_touched' if 'addeditdate' is true and 'page_touched' exists in db
	 * Adjusts the Timestamp through $wgLang->userAdjust
	 * Formats the Date to 'userdateformat' if parameter is present
	 *
	 * @return void
	 */
	private static function setTimestampFromDb() {
		global $wgLang;

		$addPageTouchedDate = static::$parameters->getParameter( 'addpagetoucheddate' );
		$addFirstCategoryDate = static::$parameters->getParameter( 'addfirstcategorydate' );
		$addEditDate = static::$parameters->getParameter( 'addeditdate' );

		if ( (bool)$addPageTouchedDate ) {
			$timestamp = static::$dbRow['page_touched'];
		} elseif ( (bool)$addFirstCategoryDate ) {
			$timestamp = static::$dbRow['cl_timestamp'];
		} elseif ( (bool)$addEditDate && isset( $dbRow['rev_timestamp'] ) ) {
			$timestamp = static::$dbRow['rev_timestamp'];
		} elseif ( (bool)$addEditDate && isset( $dbRow['page_touched'] ) ) {
			$timestamp = static::$dbRow['page_touched'];
		}

		if ( isset( $timestamp ) ) {
			static::$article->mDate = $wgLang->userAdjust( $timestamp );;

			// Apply the userdateformat
			$dateFormat = static::$parameters->getParameter( 'userdateformat' );
			if ( !is_null( $dateFormat ) ) {
				$timestamp = gmdate( $dateFormat, wfTimeStamp( TS_UNIX, $timestamp ) );
				static::$article->myDate = $timestamp;
			}
		}
	}

	/**
	 * Sets mContribution, mContributor and mContrib if 'addcontribution' is true
	 *
	 * @return void
	 */
	private static function setContributionAndContributor() {
		$addContribution = static::$parameters->getParameter( 'addcontribution' );

		if ( (bool)$addContribution ) {
			$stars = '*****************';
			$subStringLength = round( log( static::$dbRow['contribution'] ) );

			static::$article->mContribution = static::$dbRow['contribution'];
			static::$article->mContributor = static::$dbRow['contributor'];
			static::$article->mContrib = substr( $stars, 0, $subStringLength );
		}
	}

	/**
	 * Sets mUserLink and mUser to 'rev_user_text' if either 'adduser' or 'addauthor' or
	 * 'addlasteditor' is true
	 * because we are going to do a recursive parse at the end of the output phase
	 * we have to generate wiki syntax for linking to a user´s homepage
	 * Returns empty array if neither 'adduser' nor 'addauthor' nor 'addlasteditor' is set
	 *
	 * @return void
	 */
	private static function setAuthor() {
		$addUser = static::$parameters->getParameter( 'adduser' );
		$addAuthor = static::$parameters->getParameter( 'addauthor' );
		$addLastEditor = static::$parameters->getParameter( 'addlasteditor' );

		if ( (bool)$addUser || (bool)$addAuthor || (bool)$addLastEditor ) {
			$userText = static::$dbRow['rev_user_text'];

			static::$article->mUserLink = "[[User:{$userText}|{$userText}]]";
			static::$article->mUser = $userText;
		}
	}

	/**
	 * Sets mCategoryLinks and mCategoryTexts if 'addcategories' is true
	 *
	 * @return void
	 */
	private static function setCategories() {
		$addCategories = static::$parameters->getParameter( 'addcategories' );

		if ( (bool)$addCategories && isset( static::$dbRow['cats'] ) ) {
			$artCatNames = explode( ' | ', static::$dbRow['cats'] );
			$categories = [
				'links' => [],
				'texts' => [],
			];

			foreach ( $artCatNames as $artCatName ) {
				$categoryName = str_replace( '_', ' ', $artCatName );
				$categories['links'][] = "[[:Category:{$artCatName}|{$categoryName}]]";
				$categories['texts'][] = $categoryName;
			}

			static::$article->mCategoryLinks = $categories['links'];
			static::$article->mCategoryTexts = $categories['texts'];
		}
	}

	/**
	 * PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
	 * Returns Wikitext or null if 'headingmode' is none or 'ordermethod' is not set
	 *
	 * @return void
	 */
	private static function setParentHeadLink() {
		$headingMode = static::$parameters->getParameter( 'headingmode' );
		$orderMethod = static::$parameters->getParameter( 'ordermethod' );

		if ( $headingMode !== 'none' && isset( $orderMethod[0] ) ) {
			switch ( $orderMethod[0] ) {
				case 'category':
					//Count one more page in this heading
					$headingCount = 1;

					if ( isset( self::$headings[static::$dbRow['cl_to']] ) ) {
						$headingCount = intval( self::$headings[static::$dbRow['cl_to']] ) + 1;
					}

					self::$headings[static::$dbRow['cl_to']] = $headingCount;

					//uncategorized page (used if ordermethod=category,...)
					if ( empty( static::$dbRow['cl_to'] ) ) {
						$message = wfMessage( 'uncategorizedpages' );
						$headLink = "[[:Special:Uncategorizedpages|{$message}]]";

						static::$article->mParentHLink = $headLink;
					} else {
						$categoryLinkTo = static::$dbRow['cl_to'];
						$rowName = str_replace( '_', ' ', $categoryLinkTo );
						$headLink = "[[:Category:{$categoryLinkTo}|{$rowName}]]";

						static::$article->mParentHLink = $headLink;
					}
					break;

				case 'user':
					$headingCount = 1;
					if ( isset( self::$headings[static::$dbRow['rev_user_text']] ) ) {
						$headingCount = self::$headings[static::$dbRow['rev_user_text']] + 1;
					}

					self::$headings[static::$dbRow['rev_user_text']] = $headingCount;
					$userText = static::$dbRow['rev_user_text'];
					$headLink = "[[User:{$userText}|{$userText}]]";

					static::$article->mParentHLink = $headLink;
					break;
			}
		}
	}

	/**
	 * Reset dbRow, Parameter Title and Article Object
	 *
	 * @return void
	 */
	private static function resetTemporaryStaticVars() {
		static::$dbRow = null;
		static::$parameters = null;
		static::$title = null;
		static::$pageData = [];
		static::$article = null;
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
