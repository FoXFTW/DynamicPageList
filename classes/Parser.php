<?php
/**
 * DynamicPageList3
 * DPL Parse Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

use DPL\Exceptions\Parser\EmptyParametersException;
use DPL\Exceptions\Parser\ProtectedModeException;
use DPL\Exceptions\Parser\QueryErrorException;
use DPL\Helper\MersenneTwister;
use DynamicPageListHooks;
use MWException;
use Parser as WikiParser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Title;
use Wikimedia\Rdbms\ResultWrapper;

class Parser implements LoggerAwareInterface {
	/**
	 * Mediawiki Database Object
	 *
	 * @var \Wikimedia\Rdbms\Database
	 */
	private $dbr = null;

	/**
	 * Mediawiki Parser Object
	 *
	 * @var \Parser
	 */
	private $parser = null;

	/**
	 * \DPL\Parameters Object
	 *
	 * @var \DPL\Parameters
	 */
	private $parameters = null;

	/**
	 * \DPL\Logger Object
	 *
	 * @var LoggerInterface
	 */
	private $logger = null;

	/**
	 * Array of pre-quoted table names.
	 *
	 * @var array
	 */
	private $tableNames = [];

	/**
	 * Header Output
	 *
	 * @var string
	 */
	private $header = '';

	/**
	 * Footer Output
	 *
	 * @var string
	 */
	private $footer = '';

	/**
	 * Body Output
	 *
	 * @var string
	 */
	private $output = '';

	/**
	 * DynamicPageList Object Holder
	 *
	 * @var \DPL\DynamicPageList
	 */
	private $dpl = null;

	/**
	 * Replacement Variables
	 *
	 * @var array
	 */
	private $replacementVariables = [];

	/**
	 * Array of possible URL arguments.
	 *
	 * @var array
	 */
	private $urlArguments = [
		'DPL_offset',
		'DPL_count',
		'DPL_fromTitle',
		'DPL_findTitle',
		'DPL_toTitle',
	];

	/**
	 * @var \DPL\Query
	 */
	private $query;

	/**
	 * @var \WebRequest
	 */
	private $wgRequest;

	private $parserTagMode = false;

	/**
	 * Reference to End Reset Booleans
	 *
	 * @var array
	 */
	private $resets = [];
	private $eliminates = [];

	/**
	 * Main Constructor
	 *
	 * @param bool $parserTagMode If the Parser should be in Tag Mode
	 */
	public function __construct( $parserTagMode = false ) {
		global $wgRequest;

		$this->dbr = wfGetDB( DB_SLAVE );
		$this->parameters = new Parameters();
		$this->tableNames = Query::getTableNames();
		$this->wgRequest = $wgRequest;
		$this->parserTagMode = $parserTagMode;
	}

	/**
	 * @param \Parser $parser Mediawiki Parser object.
	 */
	public function setWikiParser( WikiParser $parser ) {
		$this->parser = $parser;
	}

	/**
	 * @param array $reset End Reset Booleans
	 * @param array $eliminate End Eliminate Booleans
	 */
	public function setResetAndEliminates( array &$reset, array &$eliminate ) {
		$this->resets = &$reset;
		$this->eliminates = &$eliminate;
	}

	/**
	 * The real callback function for converting the input text to wiki text output
	 *
	 * @param string $input Raw User Input
	 * @return string Wiki/HTML Output
	 * @throws \MWException
	 * @throws \Exception
	 */
	public function parse( $input ) {
		$dplStartTime = microtime( true );

		// Reset headings when being ran more than once in the same page load.
		Article::resetHeadings();

		// Check that we are not in an infinite transclusion loop
//		if ( isset( $this->parser->mTemplatePath[$this->parser->mTitle->getPrefixedText()] ) ) {
//			$this->logger->warning(Error::WARN_TRANSCLUSION_LOOP,
//				[$this->parser->mTitle->getPrefixedText()]);
//
//			return $this->getFullOutput();
//		}
		$input = $this->processUrlArguments( $input );
		$offset = $this->getOffset();

		try {
			$this->checkProtectedMode();
			$this->processUserParameters( $input );
		}
		catch ( EmptyParametersException $e ) {
			return $this->getFullOutput();
		}
		catch ( ProtectedModeException $e ) {
			return $this->getFullOutput();
		}

		// to check if pseudo-category of Uncategorized pages is included
		$this->parameters->setParameter( 'includeuncat', false );

		if ( $this->parameters->execAndExitModeEnabled() ) {
			return $this->getExecAndExitModeData();
		}

		$this->checkSecLabelsMode();

		try {
			$this->doQueryErrorChecks();
		}
		catch ( QueryErrorException $e ) {
			return $this->getFullOutput();
		}


		/*********/
		/* Query */
		/*********/
		try {
			$this->query = new Query( $this->parameters );
			$this->query->setLogger( $this->logger );
			$result = $this->query->buildAndSelect( $this->getRowCalculationMode() );
		}
		catch ( MWException $e ) {
			$this->logger->critical( Error::CRITICAL_SQL_BUILD_ERROR, [ $e->getMessage() ] );

			return $this->getFullOutput();
		}

		$numRows = $this->dbr->numRows( $result );
		$articles = $this->processQueryResults( $result );

		if ( DynamicPageListHooks::getDebugLevel() >= Logger::LEVEL_DEBUG_SQL ) {
			$this->addOutput( $this->query->getSqlQuery() . "\n" );
		}

		$this->addOutput( '{{Extension DPL}}' );

		// Preset these to defaults.
		$this->setVariable( 'TOTALPAGES', 0 );
		$this->setVariable( 'PAGES', 0 );
		$this->setVariable( 'VERSION', DPL_VERSION );

		/*********************/
		/* Handle No Results */
		/*********************/
		if ( $numRows <= 0 || empty( $articles ) ) {
			// Shortcut out since there is no processing to do.
			$this->dbr->freeResult( $result );

			return $this->getFullOutput( 0, false );
		}

		$foundRows = null;

		if ( $this->getRowCalculationMode() ) {
			$foundRows = $this->query->getFoundRows();
		}

		// Backward scrolling: If the user specified only titlelt with descending reverse the
		// output order.
		if ( $this->parameters->getParameter( 'titlelt' ) &&
		     !$this->parameters->getParameter( 'titlegt' ) &&
		     $this->parameters->getParameter( 'order' ) == 'descending' ) {
			$articles = array_reverse( $articles );
		}

		// Special sort for card suits (Bridge)
		if ( $this->parameters->getParameter( 'ordersuitsymbols' ) ) {
			$articles = $this->cardSuitSort( $articles );
		}

		/*******************/
		/* Generate Output */
		/*******************/
		$listMode =
			new ListMode( $this->parameters->getParameter( 'mode' ),
				$this->parameters->getParameter( 'secseparators' ),
				$this->parameters->getParameter( 'multisecseparators' ),
				$this->parameters->getParameter( 'inlinetext' ),
				$this->parameters->getParameter( 'listattr' ),
				$this->parameters->getParameter( 'itemattr' ),
				(array)$this->parameters->getParameter( 'listseparators' ), $offset,
				$this->parameters->getParameter( 'dominantsection' ) );

		$hListMode =
			new ListMode( $this->parameters->getParameter( 'headingmode' ),
				$this->parameters->getParameter( 'secseparators' ),
				$this->parameters->getParameter( 'multisecseparators' ), '',
				$this->parameters->getParameter( 'hlistattr' ),
				$this->parameters->getParameter( 'hitemattr' ),
				(array)$this->parameters->getParameter( 'listseparators' ), $offset,
				$this->parameters->getParameter( 'dominantsection' ) );

		$this->dpl =
			new DynamicPageList( Article::getHeadings(),
				$this->parameters->getParameter( 'headingcount' ),
				$this->parameters->getParameter( 'columns' ),
				$this->parameters->getParameter( 'rows' ),
				$this->parameters->getParameter( 'rowsize' ),
				$this->parameters->getParameter( 'rowcolformat' ), $articles,
				$this->parameters->getParameter( 'ordermethods' )[0], $hListMode, $listMode,
				$this->parameters->getParameter( 'escapelinks' ),
				$this->parameters->getParameter( 'addexternallink' ),
				$this->parameters->getParameter( 'incpage' ),
				$this->parameters->getParameter( 'includemaxlen' ),
				$this->parameters->getParameter( 'seclabels' ),
				$this->parameters->getParameter( 'seclabelsmatch' ),
				$this->parameters->getParameter( 'seclabelsnotmatch' ),
				$this->parameters->getParameter( 'incparsed' ), $this->parser,
				$this->parameters->getParameter( 'replaceintitle' ),
				$this->parameters->getParameter( 'titlemaxlen' ),
				$this->parameters->getParameter( 'defaulttemplatesuffix' ),
				$this->parameters->getParameter( 'tablerow' ),
				$this->parameters->getParameter( 'includetrim' ),
				$this->parameters->getParameter( 'tablesortcol' ),
				$this->parameters->getParameter( 'updaterules' ),
				$this->parameters->getParameter( 'deleterules' ) );
		$this->dpl->setLogger( $this->logger );
		if ( $foundRows === null ) {
			$foundRows = $this->dpl->getRowCount();
		}

		$this->addOutput( $this->dpl->getText() );

		/*******************************/
		/* Replacement Variables       */
		/*******************************/
		// Guaranteed to be an accurate count if SQL_CALC_FOUND_ROWS was used.  Otherwise only
		// accurate if results are less than the SQL LIMIT.
		$this->setVariable( 'TOTALPAGES', $foundRows );

		// This could be different than TOTALPAGES.  PAGES represents the total results within
		// the constraints of SQL LIMIT.
		$this->setVariable( 'PAGES', $this->dpl->getRowCount() );

		// Replace %DPLTIME% by execution time and timestamp in header and footer
		$nowTimeStamp = date( 'Y/m/d H:i:s' );
		$dplElapsedTime = sprintf( '%.3f sec.', microtime( true ) - $dplStartTime );
		$dplTime = "{$dplElapsedTime} ({$nowTimeStamp})";
		$this->setVariable( 'DPLTIME', $dplTime );

		$firstNamespaceFound = '';
		$firstTitleFound = '';
		$lastNamespaceFound = '';
		$lastTitleFound = '';

		// Replace %LASTTITLE% / %LASTNAMESPACE% by the last title found in header and footer
		if ( ( $n = count( $articles ) ) > 0 ) {
			$firstNamespaceFound = str_replace( ' ', '_', $articles[0]->mTitle->getNamespace() );
			$firstTitleFound = str_replace( ' ', '_', $articles[0]->mTitle->getText() );
			$lastNamespaceFound =
				str_replace( ' ', '_', $articles[$n - 1]->mTitle->getNamespace() );
			$lastTitleFound = str_replace( ' ', '_', $articles[$n - 1]->mTitle->getText() );
		}

		$this->setVariable( 'FIRSTNAMESPACE', $firstNamespaceFound );
		$this->setVariable( 'FIRSTTITLE', $firstTitleFound );
		$this->setVariable( 'LASTNAMESPACE', $lastNamespaceFound );
		$this->setVariable( 'LASTTITLE', $lastTitleFound );
		$this->setVariable( 'SCROLLDIR', $this->parameters->getParameter( 'scrolldir' ) );

		/*******************************/
		/* Scroll Variables            */
		/*******************************/
		$scrollVariables = [
			'DPL_firstNamespace' => $firstNamespaceFound,
			'DPL_firstTitle' => $firstTitleFound,
			'DPL_lastNamespace' => $lastNamespaceFound,
			'DPL_lastTitle' => $lastTitleFound,
			'DPL_scrollDir' => $this->parameters->getParameter( 'scrolldir' ),
			'DPL_time' => $dplTime,
			'DPL_count' => $this->parameters->getParameter( 'count' ),
			'DPL_totalPages' => $foundRows,
			'DPL_pages' => $this->dpl->getRowCount(),
		];

		$this->defineScrollVariables( $scrollVariables );

		if ( $this->parameters->getParameter( 'allowcachedresults' ) ) {
			$this->parser->getOutput()
				->updateCacheExpiry( $this->parameters->getParameter( 'cacheperiod' )
					? $this->parameters->getParameter( 'cacheperiod' ) : 3600 );
		} else {
			$this->parser->getOutput()->updateCacheExpiry( 0 );
		}

		$finalOutput = $this->getFullOutput( $foundRows, false );

		$this->triggerEndResets( $finalOutput );

		return $finalOutput;
	}

	/**
	 * Check for URL Arguments in Input
	 *
	 * @param string $input User Input
	 * @return string
	 */
	private function processUrlArguments( $input ) {
		if ( strpos( $input, '{%DPL_' ) >= 0 ) {
			for ( $i = 1; $i <= 5; $i ++ ) {
				$this->urlArguments[] = 'DPL_arg' . $i;
			}
		}

		$this->getUrlArgs();

		return $this->resolveUrlArguments( $input, $this->urlArguments );
	}

	/**
	 * This function uses the Variables extension to provide URL-arguments like &DPL_xyz=abc in the
	 * form of a variable which can be accessed as {{#var:xyz}} if Extension:Variables is installed.
	 *
	 * @return void
	 */
	private function getUrlArgs() {
		$args = $this->wgRequest->getValues();

		foreach ( $args as $argName => $argValue ) {
			if ( strpos( $argName, 'DPL_' ) === false ) {
				continue;
			}

			Variables::setVar( [ $argName, $argValue ] );

			if ( defined( 'ExtVariables::VERSION' ) ) {
				\ExtVariables::get( $this->parser )->setVarValue( $argName, $argValue );
			}
		}
	}

	/**
	 * Resolve arguments in the input that would normally be in the URL.
	 *
	 * @param string $input Raw Uncleaned User Input
	 * @param array $arguments Array of URL arguments to resolve.
	 *  Non-arrays will be casted to an array.
	 * @return string Raw input with variables replaced
	 */
	private function resolveUrlArguments( $input, $arguments ) {
		$arguments = (array)$arguments;

		foreach ( $arguments as $arg ) {
			$dplArg = $this->wgRequest->getVal( $arg, '' );

			if ( $dplArg == '' ) {
				$input = preg_replace( '/\{%' . $arg . ':(.*)%\}/U', '\1', $input );
				$input = str_replace( '{%' . $arg . '%}', '', $input );
			} else {
				$input = preg_replace( '/\{%' . $arg . ':.*%\}/U  ', $dplArg, $input );
				$input = str_replace( '{%' . $arg . '%}', $dplArg, $input );
			}
		}

		return $input;
	}

	/**
	 * If DPL_Offset is set in Request use it, if not use default offset insted
	 *
	 * @return int Offset
	 */
	private function getOffset() {
		$defaultOffset = $this->parameters->getData( 'offset' )['default'];
		$offset = $this->wgRequest->getInt( 'DPL_offset', $defaultOffset );
		$this->parameters->setParameter( 'offset', $offset );

		return $offset;
	}

	/**
	 * Check if DPL shall only be executed from protected pages.
	 * Ideally we would like to allow using a DPL query if the query itself is coded on a
	 * template page which is protected. Then there would be no need for the article to be protected.
	 * However, how can one find out from which wiki source an extension has been invoked???
	 *
	 * @throws ProtectedModeException
	 */
	private function checkProtectedMode() {
		if ( Config::getSetting( 'runFromProtectedPagesOnly' ) === true ) {
			if ( !$this->parser->mTitle->isProtected( 'edit' ) ) {
				$this->logger->error( Error::CRITICAL_NOT_PROTECTED,
					[ $this->parser->mTitle->getPrefixedText() ] );

				throw new ProtectedModeException();
			}
		}
	}

	/**
	 * User Input preparation and parsing.
	 *
	 * @param string $input User Input
	 * @throws MWException
	 * @throws EmptyParametersException
	 */
	private function processUserParameters( $input ) {
		$cleanParameters = $this->prepareUserInput( $input );

		if ( !is_array( $cleanParameters ) ) {
			$this->logger->critical( Error::CRITICAL_NO_SELECTION );

			throw new EmptyParametersException();
		}

		$cleanParameters = $this->parameters->sortByPriority( $cleanParameters );

		foreach ( $cleanParameters as $parameter => $options ) {
			foreach ( $options as $option ) {
				// Parameter functions return true or false.  The full parameter data will be
				// passed into the Query object later.
				if ( $this->parameters->$parameter( $option ) === false ) {
					// Do not build this into the output just yet.  It will be collected at the end.
					$this->logger->error( Error::WARN_WRONG_PARAM, [ $parameter, $option ] );
				}
			}
		}
	}

	/**
	 * Do basic clean up and structuring of raw user input.
	 *
	 * @param string $input Raw User Input
	 * @return array|bool Array of raw text parameter => option.
	 */
	private function prepareUserInput( $input ) {
		// We replace double angle brackets with single angle brackets to avoid premature tag
		// expansion in the input. The ¦ symbol is an alias for |.
		// The combination '²{' and '}²'will be translated to double curly braces; this allows
		// postponed template execution which is crucial for DPL queries which call other DPL queries.
		$input =
			str_replace( [ '«', '»', '¦', '²{', '}²' ], [ '<', '>', '|', '{{', '}}' ], $input );

		// Standard new lines into the standard \n and clean up any hanging new lines.
		$input = str_replace( [ "\r\n", "\r" ], "\n", $input );
		$input = trim( $input, "\n" );
		$rawParameters = explode( "\n", $input );

		$parameters = false;

		foreach ( $rawParameters as $parameterOption ) {
			if ( empty( $parameterOption ) ) {
				//Softly ignore blank lines.
				continue;
			}

			if ( strpos( $parameterOption, '=' ) === false ) {
				$this->logger->warning( Error::WARN_PARAM_NO_OPTION, [ $parameterOption ] );
				continue;
			}

			list( $parameter, $option ) = explode( '=', $parameterOption, 2 );

			$parameter = trim( $parameter );
			$option = trim( $option );

			if ( strpos( $parameter, '<' ) !== false || strpos( $parameter, '>' ) !== false ) {
				// Having the actual less than and greater than symbols is nasty for programmatic
				// look up.  The old parameter is still supported along with the new, but we just fix it here before calling it.
				$parameter = str_replace( '<', 'lt', $parameter );
				$parameter = str_replace( '>', 'gt', $parameter );
			}

			$parameter = strtolower( $parameter ); //Force lower case for ease of use.

			if ( empty( $parameter ) || substr( $parameter, 0, 1 ) == '#' ||
			     ( $this->parameters->exists( $parameter ) &&
			       !$this->parameters->testRichness( $parameter ) ) ) {
				continue;
			}

			if ( !$this->parameters->exists( $parameter ) ) {
				$this->logger->warning( Error::WARN_UNKNOWN_PARAM, [
					$parameter,
					implode( ', ', $this->parameters->getParametersForRichness() ),
				] );
				continue;
			}

			// Ignore parameter settings without argument (except namespace and category).
			if ( !strlen( $option ) ) {
				if ( $parameter != 'namespace' && $parameter != 'notnamespace' &&
				     $parameter != 'category' && $this->parameters->exists( $parameter ) ) {
					continue;
				}
			}
			$parameters[$parameter][] = $option;
		}

		return $parameters;
	}

	/**
	 * Return output optionally including header and footer.
	 *
	 * @param bool $totalResults [Optional] Total results.
	 * @param bool $skipHeaderFooter [Optional] Skip adding the header and footer.
	 * @return string Output
	 */
	private function getFullOutput( $totalResults = false, $skipHeaderFooter = true ) {
		if ( !$skipHeaderFooter ) {
			$header = '';
			$footer = '';
			// Only override header and footers if specified.
			$_headerType = $this->getHeaderFooterType( 'header', $totalResults );

			if ( $_headerType !== false ) {
				$header = $this->parameters->getParameter( $_headerType );
			}

			$_footerType = $this->getHeaderFooterType( 'footer', $totalResults );

			if ( $_footerType !== false ) {
				$footer = $this->parameters->getParameter( $_footerType );
			}

			$this->setHeader( $header );
			$this->setFooter( $footer );
		}

		if ( !$totalResults && !strlen( $this->header ) && !strlen( $this->footer ) ) {
			$this->logger->warning( Error::WARN_NO_RESULTS );
		}

		$messages = $this->logger->getMessages( true );

		return ( count( $messages ) ? implode( "<br/>\n", $messages ) : null ) . $this->header .
		       $this->getOutput() . $this->footer;
	}

	/**
	 * Determine the header/footer type to use based on what output format parameters were chosen and the number of results.
	 *
	 * @param string $position Page position to check: 'header' or 'footer'.
	 * @param int $count Count of pages.
	 * @return string|bool Type to use: 'results', 'oneresult', or 'noresults'.
	 *  False if invalid or none should be used.
	 */
	private function getHeaderFooterType( $position, $count ) {
		$count = intval( $count );

		if ( $position != 'header' && $position != 'footer' ) {
			return false;
		}

		if ( $this->parameters->getParameter( 'results' . $position ) !== null && ( $count >= 2 ||
		                                                                            ( $this->parameters->getParameter( 'oneresult' .
		                                                                                                               $position ) ===
		                                                                              null &&
		                                                                              $count >=
		                                                                              1 ) ) ) {
			$_type = 'results' . $position;
		} elseif ( $count === 1 &&
		           $this->parameters->getParameter( 'oneresult' . $position ) !== null ) {
			$_type = 'oneresult' . $position;
		} elseif ( $count === 0 &&
		           $this->parameters->getParameter( 'noresults' . $position ) !== null ) {
			$_type = 'noresults' . $position;
		} else {
			$_type = false;
		}

		return $_type;
	}

	/**
	 * Set the header text.
	 *
	 * @param string $header Header Text
	 * @return void
	 */
	private function setHeader( $header ) {
		if ( DynamicPageListHooks::getDebugLevel() >= Logger::LEVEL_DEBUG_SQL_NOWIKI ) {
			$header = '<pre><nowiki>' . $header;
		}

		$this->header = $this->replaceVariables( $header );
	}

	/**
	 * Return text with variables replaced.
	 *
	 * @param string $text Text to perform replacements on.
	 * @return string Replaced Text
	 */
	private function replaceVariables( $text ) {
		$text = self::replaceNewLines( $text );

		foreach ( $this->replacementVariables as $variable => $replacement ) {
			$text = str_replace( $variable, $replacement, $text );
		}

		return $text;
	}

	/**
	 * Return text with custom new line characters replaced.
	 *
	 * @param string $text Text
	 * @return string New Lined Text
	 */
	public static function replaceNewLines( $text ) {
		return str_replace( [ '\n', "¶" ], "\n", $text );
	}

	/**
	 * Set the footer text.
	 *
	 * @param string $footer Footer Text
	 * @return void
	 */
	private function setFooter( $footer ) {
		if ( DynamicPageListHooks::getDebugLevel() >= Logger::LEVEL_DEBUG_SQL_NOWIKI ) {
			$footer .= '</nowiki></pre>';
		}

		$this->footer = $this->replaceVariables( $footer );
	}

	/**
	 * Set the output text.
	 *
	 * @return string Output Text
	 */
	private function getOutput() {
		//@TODO: 2015-08-28 Consider calling $this->replaceVariables() here.  Might cause issues with text returned in the results.
		return $this->output;
	}

	/**
	 * The keyword "geturlargs" is used to return the Url arguments and do nothing else.
	 * In all other cases we return the value of the argument which may contain parser function
	 * calls.
	 *
	 * @return mixed|null
	 */
	private function getExecAndExitModeData() {
		$execAndExit = $this->parameters->getParameter( 'execandexit' );

		if ( $execAndExit === 'geturlargs' ) {
			return null;
		}

		return $execAndExit;
	}

	/**
	 * Construct internal keys for TableRow according to the structure of "include".  This will
	 * be needed in the output phase.
	 */
	private function checkSecLabelsMode() {
		$secLabels = $this->parameters->getParameter( 'seclabels' );

		if ( !is_null( $secLabels ) ) {
			$tableRow = $this->parameters->getParameter( 'tablerow' );
			$tableRow = $this->updateTableRowKeys( $tableRow, $secLabels );
			$this->parameters->setParameter( 'tablerow', $tableRow );
		}
	}

	/**
	 * Create keys for TableRow which represent the structure of the "include=" arguments.
	 *
	 * @param array $tableRow Array of 'tablerow' parameter data.
	 * @param array $sectionLabels Array of 'include' parameter data.
	 * @return array Updated 'tablerow' parameter.
	 */
	private static function updateTableRowKeys( $tableRow, $sectionLabels ) {
		$_tableRow = (array)$tableRow;
		$tableRow = [];
		$groupNr = - 1;
		$t = - 1;

		foreach ( $sectionLabels as $label ) {
			$t ++;
			$groupNr ++;
			$cols = explode( '}:', $label );

			if ( count( $cols ) <= 1 ) {
				if ( array_key_exists( $t, $_tableRow ) ) {
					$tableRow[$groupNr] = $_tableRow[$t];
				}
			} else {
				$n = count( explode( ':', $cols[1] ) );
				$colNr = - 1;
				$t --;

				for ( $i = 1; $i <= $n; $i ++ ) {
					$colNr ++;
					$t ++;

					if ( array_key_exists( $t, $_tableRow ) ) {
						$tableRow[$groupNr . '.' . $colNr] = $_tableRow[$t];
					}
				}
			}
		}

		return $tableRow;
	}

	/**
	 * Work through processed parameters and check for potential issues.
	 *
	 * @return bool
	 * @throws QueryErrorException
	 */
	private function doQueryErrorChecks() {
		/**************************/
		/* Parameter Error Checks */
		/**************************/
		$totalCategories = 0;
		if ( is_array( $this->parameters->getParameter( 'category' ) ) ) {
			foreach ( $this->parameters->getParameter( 'category' ) as $comparisonType =>
			          $operatorTypes
			) {
				foreach ( $operatorTypes as $operatorType => $categoryGroups ) {
					foreach ( $categoryGroups as $categories ) {
						$totalCategories += count( $categories );
					}
				}
			}
		}

		if ( is_array( $this->parameters->getParameter( 'notcategory' ) ) ) {
			foreach ( $this->parameters->getParameter( 'notcategory' ) as $comparisonType =>
			          $operatorTypes
			) {
				foreach ( $operatorTypes as $operatorType => $categories ) {
					$totalCategories += count( $categories );
				}
			}
		}

		// Too many categories.
		if ( $totalCategories > Config::getSetting( 'maxCategoryCount' ) &&
		     !Config::getSetting( 'allowUnlimitedCategories' ) ) {
			$this->logger->critical( Error::CRITICAL_TOO_MANY_CATEGORIES, [
				Config::getSetting( 'maxCategoryCount' ),
			] );

			return false;
		}

		// Not enough categories.(Really?)
		if ( $totalCategories < Config::getSetting( 'minCategoryCount' ) ) {
			$this->logger->critical( Error::CRITICAL_TOO_FEW_CATEGORIES,
				[ Config::getSetting( 'minCategoryCount' ) ] );

			return false;
		}

		//Selection criteria needs to be found.
		if ( !$totalCategories && !$this->parameters->isSelectionCriteriaFound() ) {
			$this->logger->critical( Error::CRITICAL_NO_SELECTION );

			return false;
		}

		// ordermethod=sortkey requires ordermethod=category
		// Delayed to the construction of the SQL query, see near line 2211, gs
		// if (in_array('sortkey',$aOrderMethods) && ! in_array('category',$aOrderMethods))
		// $aOrderMethods[] = 'category';
		$orderMethods = (array)$this->parameters->getParameter( 'ordermethod' );

		// Throw an error in no categories were selected when using category sorting modes or
		// requesting category information.
		if ( $totalCategories === 0 ) {
			if ( in_array( 'categoryadd', $orderMethods ) ) {
				$this->logger->critical( Error::CRITICAL_NO_CATEGORIES_FOR_ORDER_METHOD );
			}

			if ( $this->parameters->getParameter( 'addfirstcategorydate' ) === true ) {
				$this->logger->critical( Error::CRITICAL_NO_CATEGORIES_FOR_ADD_DATE );
			}

			return false;
		}

		// No more than one type of date at a time!
		// @TODO: Can this be fixed to allow all three later after fixing the article class?
		if ( ( intval( $this->parameters->getParameter( 'addpagetoucheddate' ) ) +
		       intval( $this->parameters->getParameter( 'addfirstcategorydate' ) ) +
		       intval( $this->parameters->getParameter( 'addeditdate' ) ) ) > 1 ) {
			$this->logger->critical( Error::CRITICAL_MORE_THAN_ONE_TYPE_OF_DATE );

			return false;
		}

		// the dominant section must be one of the sections mentioned in includepage
		if ( $this->parameters->getParameter( 'dominantsection' ) > 0 &&
		     count( $this->parameters->getParameter( 'seclabels' ) ) <
		     $this->parameters->getParameter( 'dominantsection' ) ) {
			$this->logger->critical( Error::CRITICAL_DOMINANT_SECTION_RANGE, [
				count( $this->parameters->getParameter( 'seclabels' ) ),
			] );

			return false;
		}

		// category-style output requested with not compatible order method
		if ( $this->parameters->getParameter( 'mode' ) == 'category' &&
		     !array_intersect( $orderMethods, [ 'sortkey', 'title', 'titlewithoutnamespace' ] ) ) {
			$this->logger->critical( Error::CRITICAL_WRONG_ORDER_METHOD, [
				'mode=category',
				'sortkey | title | titlewithoutnamespace',
			] );

			return false;
		}

		// addpagetoucheddate=true with unappropriate order methods
		if ( $this->parameters->getParameter( 'addpagetoucheddate' ) &&
		     !array_intersect( $orderMethods, [ 'pagetouched', 'title' ] ) ) {
			$this->logger->critical( Error::CRITICAL_WRONG_ORDER_METHOD, [
				'addpagetoucheddate=true',
				'pagetouched | title',
			] );

			return false;
		}

		// addeditdate=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		// firstedit (resp. lastedit) -> add date of first (resp. last) revision
		if ( $this->parameters->getParameter( 'addeditdate' ) &&
		     !array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] ) &&
		     ( $this->parameters->getParameter( 'allrevisionsbefore' ) ||
		       $this->parameters->getParameter( 'allrevisionssince' ) ||
		       $this->parameters->getParameter( 'firstrevisionsince' ) ||
		       $this->parameters->getParameter( 'lastrevisionbefore' ) ) ) {
			$this->logger->critical( Error::CRITICAL_WRONG_ORDER_METHOD, [
				'addeditdate=true',
				'firstedit | lastedit',
			] );

			return false;
		}

		// adduser=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)

		// @TODO allow to add user for other order methods.
		// The fact is a page may be edited by multiple users. Which user(s) should we show? all?
		// the first or the last one?
		// Ideally, we could use values such as 'all', 'first' or 'last' for the adduser parameter.
		if ( $this->parameters->getParameter( 'adduser' ) &&
		     !array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] ) &&
		     !$this->parameters->getParameter( 'allrevisionsbefore' ) &&
		     !$this->parameters->getParameter( 'allrevisionssince' ) &&
		     !$this->parameters->getParameter( 'firstrevisionsince' ) &&
		     !$this->parameters->getParameter( 'lastrevisionbefore' ) ) {
			$this->logger->critical( Error::CRITICAL_WRONG_ORDER_METHOD, [
				'adduser=true',
				'firstedit | lastedit',
			] );

			return false;
		}

		if ( $this->parameters->getParameter( 'minoredits' ) &&
		     !array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] ) ) {
			$this->logger->critical( Error::CRITICAL_WRONG_ORDER_METHOD, [
				'minoredits',
				'firstedit | lastedit',
			] );

			return false;
		}

		// If including the Uncategorized, we need the 'dpl_clview': VIEW of the categorylinks
		// table where we have cl_to='' (empty string) for all uncategorized pages.
		// This VIEW must have been created by the administrator of the mediawiki DB at
		// installation. See the documentation.
		if ( $this->parameters->getParameter( 'includeuncat' ) ) {
			// If the view is not there, we can't perform logical operations on the Uncategorized.
			if ( !$this->dbr->tableExists( 'dpl_clview' ) ) {
				$sql =
					'CREATE VIEW ' . $this->tableNames['dpl_clview'] .
					" AS SELECT IFNULL(cl_from, page_id) AS cl_from, IFNULL(cl_to, '') AS cl_to, cl_sortkey FROM " .
					$this->tableNames['page'] . ' LEFT OUTER JOIN ' .
					$this->tableNames['categorylinks'] . ' ON ' . $this->tableNames['page'] .
					'.page_id=cl_from';
				$this->logger->critical( Error::CRITICAL_NO_CL_VIEW, [
					$this->tableNames['dpl_clview'],
					$sql,
				] );

				return false;
			}
		}

		// add*** parameters have no effect with 'mode=category'
		// (only namespace/title can be viewed in this mode)
		if ( $this->parameters->getParameter( 'mode' ) == 'category' &&
		     ( $this->parameters->getParameter( 'addcategories' ) ||
		       $this->parameters->getParameter( 'addeditdate' ) ||
		       $this->parameters->getParameter( 'addfirstcategorydate' ) ||
		       $this->parameters->getParameter( 'addpagetoucheddate' ) ||
		       $this->parameters->getParameter( 'incpage' ) ||
		       $this->parameters->getParameter( 'adduser' ) ||
		       $this->parameters->getParameter( 'addauthor' ) ||
		       $this->parameters->getParameter( 'addcontribution' ) ||
		       $this->parameters->getParameter( 'addlasteditor' ) ) ) {
			$this->logger->warning( Error::WARN_CAT_OUTPUT_BUT_WRONG_PARAMS );
		}

		// headingmode has effects with ordermethod on multiple components only
		if ( $this->parameters->getParameter( 'headingmode' ) != 'none' &&
		     count( $orderMethods ) < 2 ) {
			$this->logger->warning( Error::WARN_HEADING_MODE_TOO_FEW_ORDER_METHODS, [
				$this->parameters->getParameter( 'headingmode' ),
				'none',
			] );
			$this->parameters->setParameter( 'headingmode', 'none' );
		}

		// The 'openreferences' parameter is incompatible with many other options.
		if ( $this->parameters->isOpenReferencesConflict() &&
		     $this->parameters->openReferencesEnabled() ) {
			$this->logger->critical( Error::CRITICAL_OPEN_REFERENCES );

			return false;
		}

		return true;
	}

	private function getRowCalculationMode() {
		$resultsHeader = $this->parameters->getParameter( 'resultsheader' );
		$noResultsHeader = $this->parameters->getParameter( 'noresultsheader' );
		$resultsFooter = $this->parameters->getParameter( 'resultsfooter' );
		$checkString = $resultsHeader . $noResultsHeader . $resultsFooter;

		if ( !Config::getSetting( 'allowUnlimitedResults' ) &&
		     !$this->parameters->goalParameterEqualsCategories() &&
		     strpos( $checkString, '%TOTALPAGES%' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Process Query Results
	 *
	 * @param \Wikimedia\Rdbms\ResultWrapper $result Mediawiki Result Object
	 * @return Article[] Array of Article objects.
	 * @throws \MWException
	 * @throws \Exception
	 */
	private function processQueryResults( $result ) {
		$randomCount = $this->parameters->getParameter( 'randomcount' );
		$picks = $this->getRandomNumberPicks( $result, $randomCount );

		$articles = [];

		/**********************/
		/* Article Processing */
		/**********************/
		$i = 0;

		while ( $row = $result->fetchRow() ) {
			$i ++;

			// In random mode skip articles which were not chosen.
			if ( $randomCount > 0 && !in_array( $i, $picks ) ) {
				continue;
			}

			if ( $this->parameters->goalParameterEqualsCategories() ) {
				$pageNamespace = NS_CATEGORY;
				$pageTitle = $row['cl_to'];
			} elseif ( $this->parameters->openReferencesEnabled() ) {
				if ( count( $this->parameters->getParameter( 'imagecontainer' ) ) > 0 ) {
					$pageNamespace = NS_FILE;
					$pageTitle = $row['il_to'];
				} else {
					// Maybe non-existing title
					$pageNamespace = $row['pl_namespace'];
					$pageTitle = $row['pl_title'];
				}
			} else {
				// Existing PAGE TITLE
				$pageNamespace = $row['page_namespace'];
				$pageTitle = $row['page_title'];
			}

			// if subpages are to be excluded: skip them
			if ( !$this->parameters->getParameter( 'includesubpages' ) &&
			     strpos( $pageTitle, '/' ) !== false ) {
				continue;
			}

			$title = Title::makeTitle( $pageNamespace, $pageTitle );
			$thisTitle = $this->parser->getTitle();

			// Block recursion from happening by seeing if this result row is the page the DPL
			// query was ran from.
			if ( $this->parameters->getParameter( 'skipthispage' ) &&
			     $thisTitle->equals( $title ) ) {
				continue;
			}

			$articles[] =
				Article::newFromRow( $row, $this->parameters, $title, $pageNamespace, $pageTitle );
		}
		$this->dbr->freeResult( $result );

		return $articles;
	}

	/**
	 * Random Count Pick Generator
	 * If 'randomseed' is set will use a Mesenne Twister Implementation
	 * Else uses random numbers
	 *
	 * @param ResultWrapper $result
	 * @param int $quantity the total count returned
	 * @return array|int[]
	 * @throws MWException
	 * @throws \Exception
	 */
	private function getRandomNumberPicks( ResultWrapper $result, $quantity ) {
		$picks = [];
		$randomSeed = $this->parameters->getParameter( 'randomseed' );

		if ( $quantity > 0 ) {
			$nResults = $this->dbr->numRows( $result );

			if ( $quantity > $nResults ) {
				$quantity = $nResults;
			}

			if ( !is_null( $randomSeed ) ) {
				$picks = $this->getRandomNumbersWithSeed( $randomSeed, $quantity, 1, $nResults );
			} else {
				$picks = $this->getRandomNumbers( $quantity, 1, $nResults );
			}
		}

		return $picks;
	}

	/**
	 * @param string|int $seed the seed to use
	 * @param int $length
	 * @param int $lowerBound
	 * @param int $upperBound
	 * @return int[]
	 * @throws MWException
	 * @throws \Exception
	 */
	private function getRandomNumbersWithSeed( $seed, $length, $lowerBound, $upperBound ) {
		$picks = [];
		$twister = new MersenneTwister();

		if ( is_numeric( $seed ) ) {
			$twister->init_with_integer( $seed );
		} elseif ( is_string( $seed ) ) {
			$twister->init_with_string( $seed );
		} else {
			throw new MWException( __METHOD__ . ' Random seed must be an int or string' );
		}

		while ( count( $picks ) < $length ) {
			$pick = $twister->rangeint( $lowerBound, $upperBound );
			if ( !in_array( $pick, $picks ) ) {
				$picks[] = $pick;
			}
		}

		return $picks;
	}

	/**
	 * @param int $length
	 * @param int $lowerBound
	 * @param int $upperBound
	 * @return int[]
	 */
	private function getRandomNumbers( $length, $lowerBound, $upperBound ) {
		$picks = range( $lowerBound, $upperBound );
		shuffle( $picks );

		return array_slice( $picks, 0, $length );
	}

	/**
	 * Concatenate output
	 *
	 * @param string $output Output to add
	 * @return void
	 */
	private function addOutput( $output ) {
		$this->output .= $output;
	}

	/**
	 * Set a variable to be replaced with the provided text later at the end of the output.
	 *
	 * @param string $variable Variable name, will be transformed to uppercase and have leading and
	 *  trailing percent signs added.
	 * @param string $replacement Text to replace the variable with.
	 * @return void
	 */
	private function setVariable( $variable, $replacement ) {
		$variable = "%" . mb_strtoupper( $variable, "UTF-8" ) . "%";
		$this->replacementVariables[$variable] = $replacement;
	}

	/**
	 * Sort an array of Article objects by the card suit symbol.
	 *
	 * @param array $articles Article objects in an array.
	 * @return array Sorted objects
	 */
	private function cardSuitSort( $articles ) {
		$sortKeys = [];
		$sortedArticles = [];

		foreach ( $articles as $key => $article ) {
			$title = preg_replace( '/.*:/', '', $article->mTitle );
			$tokens = preg_split( '/ - */', $title );
			$newKey = '';

			foreach ( $tokens as $token ) {
				$initial = substr( $token, 0, 1 );
				if ( $initial >= '1' && $initial <= '7' ) {
					$newKey .= $initial;
					$suit = substr( $token, 1 );

					if ( $suit == '♣' ) {
						$newKey .= '1';
					} elseif ( $suit == '♦' ) {
						$newKey .= '2';
					} elseif ( $suit == '♥' ) {
						$newKey .= '3';
					} elseif ( $suit == '♠' ) {
						$newKey .= '4';
					} elseif ( strtolower( $suit ) == 'sa' || strtolower( $suit ) == 'nt' ) {
						$newKey .= '5 ';
					} else {
						$newKey .= $suit;
					}
				} elseif ( strtolower( $initial ) == 'p' ) {
					$newKey .= '0 ';
				} elseif ( strtolower( $initial ) == 'x' ) {
					$newKey .= '8 ';
				} else {
					$newKey .= $token;
				}
			}
			$sortKeys[$key] = $newKey;
		}

		asort( $sortKeys );

		foreach ( $sortKeys as $oldKey => $newKey ) {
			$sortedArticles[] = $articles[$oldKey];
		}

		return $sortedArticles;
	}

	/**
	 * This function uses the Variables extension to provide navigation aids such as DPL_firstTitle, DPL_lastTitle, or DPL_findTitle.
	 * These variables can be accessed as {{#var:DPL_firstTitle}} if Extension:Variables is installed.
	 *
	 * @param array $scrollVariables Array of scroll variables with the key as the variable name
	 *  and the value as the value.  Non-arrays will be casted to arrays.
	 * @return void
	 */
	private function defineScrollVariables( $scrollVariables ) {
		$scrollVariables = (array)$scrollVariables;

		foreach ( $scrollVariables as $variable => $value ) {
			Variables::setVar( [ '', '', $variable, $value ] );
			if ( defined( 'ExtVariables::VERSION' ) ) {
				\ExtVariables::get( $this->parser )->setVarValue( $variable, $value );
			}
		}
	}

	/**
	 * Trigger Resets and Eliminates that run at the end of parsing.
	 *
	 * @param string $output Full output including header, footer, and any warnings.
	 * @return void
	 */
	private function triggerEndResets( $output ) {
		global $wgHooks;

		$localParser = new WikiParser();
		$parserOutput =
			$localParser->parse( $output, $this->parser->mTitle, $this->parser->mOptions );

		$reset = array_merge( $this->resets, (array)$this->parameters->getParameter( 'reset' ) );

		$eliminate =
			array_merge( $this->eliminates, (array)$this->parameters->getParameter( 'eliminate' ) );

		if ( $this->parserTagMode ) {
			// In tag mode 'eliminate' is the same as 'reset' for templates, categories, and images.
			if ( isset( $eliminate['templates'] ) && $eliminate['templates'] ) {
				$reset['templates'] = true;
				$eliminate['templates'] = false;
			}

			if ( isset( $eliminate['categories'] ) && $eliminate['categories'] ) {
				$reset['categories'] = true;
				$eliminate['categories'] = false;
			}

			if ( isset( $eliminate['images'] ) && $eliminate['images'] ) {
				$reset['images'] = true;
				$eliminate['images'] = false;
			}
		} else {
			if ( isset( $reset['templates'] ) && $reset['templates'] ) {
				DynamicPageListHooks::$createdLinks['resetTemplates'] = true;
			}

			if ( isset( $reset['categories'] ) && $reset['categories'] ) {
				DynamicPageListHooks::$createdLinks['resetCategories'] = true;
			}

			if ( isset( $reset['images'] ) && $reset['images'] ) {
				DynamicPageListHooks::$createdLinks['resetImages'] = true;
			}
		}

		if ( ( $this->parserTagMode && isset( $reset['links'] ) ) || !$this->parserTagMode ) {
			if ( isset( $reset['links'] ) ) {
				DynamicPageListHooks::$createdLinks['resetLinks'] = true;
			}

			// Register a hook to reset links which were produced during parsing DPL output.
			if ( !isset( $wgHooks['ParserAfterTidy'] ) ||
			     !is_array( $wgHooks['ParserAfterTidy'] ) ||
			     !in_array( 'DynamicPageListHooks::endReset', $wgHooks['ParserAfterTidy'] ) ) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endReset';
			}
		}

		if ( array_sum( $eliminate ) ) {
			// Register a hook to reset links which were produced during parsing DPL output
			if ( !isset( $wgHooks['ParserAfterTidy'] ) ||
			     !is_array( $wgHooks['ParserAfterTidy'] ) ||
			     !in_array( 'DynamicPageListHooks::endEliminate', $wgHooks['ParserAfterTidy'] ) ) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endEliminate';
			}

			if ( isset( $eliminate['links'] ) && $eliminate['links'] ) {
				// Trigger the mediawiki parser to find links, images, categories etc. which are
				// contained in the DPL output.
				// This allows us to remove these links from the link list later.
				// If the article containing the DPL statement itself uses one of these links they will be thrown away!
				DynamicPageListHooks::$createdLinks[0] = [];

				foreach ( $parserOutput->getLinks() as $nsp => $link ) {
					DynamicPageListHooks::$createdLinks[0][$nsp] = $link;
				}
			}

			if ( isset( $eliminate['templates'] ) && $eliminate['templates'] ) {
				DynamicPageListHooks::$createdLinks[1] = [];

				foreach ( $parserOutput->getTemplates() as $nsp => $tpl ) {
					DynamicPageListHooks::$createdLinks[1][$nsp] = $tpl;
				}
			}

			if ( isset( $eliminate['categories'] ) && $eliminate['categories'] ) {
				DynamicPageListHooks::$createdLinks[2] = $parserOutput->mCategories;
			}

			if ( isset( $eliminate['images'] ) && $eliminate['images'] ) {
				DynamicPageListHooks::$createdLinks[3] = $parserOutput->mImages;
			}
		}
	}

	/**
	 * Sets a logger instance on the object.
	 *
	 * @param LoggerInterface $logger
	 *
	 * @return void
	 */
	public function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}
}
