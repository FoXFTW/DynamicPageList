<?php
/**
 * DynamicPageList3
 * DPL Variables Class
 *
 * @author IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

use DateInterval;
use DateTime;
use Exception;
use MWException;

class Query {
	/**
	 * Parameters Object
	 *
	 * @var \DPL\Parameters
	 */
	private $parameters;

	/**
	 * Mediawiki DB Object
	 *
	 * @var \Wikimedia\Rdbms\Database
	 */
	private $dbr;

	/**
	 * Array of prefixed and escaped table names.
	 *
	 * @var array
	 */
	private $tableNames = [];

	/**
	 * Parameters that have already been processed.
	 *
	 * @var array
	 */
	private $parametersProcessed = [];

	/**
	 * Select Fields
	 *
	 * @var array
	 */
	private $select = [];

	/**
	 * The generated SQL Query.
	 *
	 * @var string
	 */
	private $sqlQuery = '';

	/**
	 * Selected Fields - An array to look up keys against for speed optimization.
	 *
	 * @var array
	 */
	private $selectedFields = [];

	/**
	 * Prefixed and escaped table names.
	 *
	 * @var array
	 */
	private $tables = [];

	/**
	 * Where Clauses
	 *
	 * @var array
	 */
	private $where = [];

	/**
	 * Group By Clauses
	 *
	 * @var array
	 */
	private $groupBy = [];

	/**
	 * Order By Clauses
	 *
	 * @var array
	 */
	private $orderBy = [];

	/**
	 * Join Clauses
	 *
	 * @var array
	 */
	private $join = [];

	/**
	 * Limit
	 *
	 * @var int
	 */
	private $limit = false;

	/**
	 * Offset
	 *
	 * @var int
	 */
	private $offset = false;

	/**
	 * Order By Direction
	 *
	 * @var string
	 */
	private $direction = 'ASC';

	/**
	 * Distinct Results
	 *
	 * @var bool
	 */
	private $distinct = true;

	/**
	 * Character Set Collation
	 *
	 * @var string
	 */
	private $collation = false;

	/**
	 * Number of Rows Found
	 *
	 * @var int
	 */
	private $foundRows = 0;

	/**
	 * Main Constructor
	 *
	 * @param \DPL\Parameters $parameters Parameters
	 */
	public function __construct( Parameters $parameters ) {
		$this->parameters = $parameters;

		$this->tableNames = self::getTableNames();

		$this->dbr = wfGetDB( DB_SLAVE );
	}

	/**
	 * Return prefixed and quoted tables that are needed.
	 *
	 * @return array Prepared table names.
	 */
	static public function getTableNames() {
		$database = wfGetDB( DB_SLAVE );
		$tableNames = [];

		$tables = [
			'categorylinks',
			'dpl_clview',
			'externallinks',
			'flaggedpages',
			'imagelinks',
			'page',
			'pagelinks',
			'recentchanges',
			'revision',
			'templatelinks',
		];

		foreach ( $tables as $table ) {
			$tableNames[$table] = $database->tableName( $table );
		}

		return $tableNames;
	}

	/**
	 * Recursively get and return an array of subcategories.
	 *
	 * @param string $categoryName Category Name
	 * @param int $depth [Optional] Maximum Depth
	 * @return array Subcategories
	 */
	static public function getSubcategories( $categoryName, $depth = 1 ) {
		$dbr = wfGetDB( DB_SLAVE );

		if ( $depth > 2 ) {
			// Hard constrain depth because lots of recursion is bad.
			$depth = 2;
		}

		$categories = [];

		$result = $dbr->select( [ 'page', 'categorylinks' ], [ 'page_title' ], [
			'page_namespace' => intval( NS_CATEGORY ),
			'categorylinks.cl_to' => str_replace( ' ', '_', $categoryName ),
		], __METHOD__, [ 'DISTINCT' ], [
			'categorylinks' => [
				'INNER JOIN',
				'page.page_id = categorylinks.cl_from',
			],
		] );

		while ( $row = $result->fetchRow() ) {
			$categories[] = $row['page_title'];

			if ( $depth > 1 ) {
				$categories =
					array_merge( $categories,
						self::getSubcategories( $row['page_title'], $depth - 1 ) );
			}
		}

		$categories = array_unique( $categories );
		$dbr->freeResult( $result );

		return $categories;
	}

	/**
	 * Start a query build.
	 *
	 * @param bool $calcRows Calculate Found Rows
	 * @return \Wikimedia\Rdbms\ResultWrapper Mediawiki Result Object or False
	 * @throws \MWException
	 */
	public function buildAndSelect( $calcRows = false ) {
		global $wgNonincludableNamespaces;

		$options = [];

		$parameters = $this->parameters->getAllParameters();

		foreach ( $parameters as $parameter => $option ) {
			$function = "_" . $parameter;
			// Some parameters do not modifiy the query so we check if the function to modify the
			// query exists first.
			$success = true;

			if ( method_exists( $this, $function ) ) {
				$success = $this->$function( $option );
			}

			if ( $success === false ) {
				throw new MWException( __METHOD__ .
				                       ": SQL Build Error returned from {$function} for " .
				                       serialize( $option ) . "." );
			}

			$this->parametersProcessed[$parameter] = true;
		}

		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			// Add things that are always part of the query.
			$this->addTable( 'page', $this->tableNames['page'] );
			$this->addSelect( [
				'page_namespace' => $this->tableNames['page'] . '.page_namespace',
				'page_id' => $this->tableNames['page'] . '.page_id',
				'page_title' => $this->tableNames['page'] . '.page_title',
			] );
		}

		// Always add nonincludeable namespaces.
		if ( is_array( $wgNonincludableNamespaces ) && count( $wgNonincludableNamespaces ) ) {
			$this->addNotWhere( [
				$this->tableNames['page'] . '.page_namespace' => $wgNonincludableNamespaces,
			] );
		}

		if ( $this->offset !== false ) {
			$options['OFFSET'] = $this->offset;
		}

		if ( $this->limit !== false ) {
			$options['LIMIT'] = $this->limit;
		} elseif ( $this->offset !== false && $this->limit === false ) {
			$options['LIMIT'] = $this->parameters->getData( 'count' )['default'];
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			if ( count( $this->parameters->getParameter( 'imagecontainer' ) ) > 0 ) {
				// $sSqlSelectFrom = $sSqlCl_to.'ic.il_to, '.$sSqlSelPage."ic.il_to AS sortkey".'
				// FROM '.$this->tableNames['imagelinks'].' AS ic';
				$tables = [
					'ic' => 'imagelinks',
				];
			} else {
				// $sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct ".$sSqlCl_to
				//.'pl_namespace, pl_title'.$sSqlSelPage.$sSqlSortkey.' FROM '.$this->tableNames['pagelinks'];
				$this->addSelect( [
					'pl_namespace',
					'pl_title',
				] );
				$tables = [
					'pagelinks',
				];
			}
		} else {
			$tables = $this->tables;

			if ( count( $this->groupBy ) ) {
				$options['GROUP BY'] = $this->groupBy;
			}

			if ( count( $this->orderBy ) ) {
				$options['ORDER BY'] = $this->orderBy;

				foreach ( $options['ORDER BY'] as $key => $value ) {
					$options['ORDER BY'][$key] .= " " . $this->direction;
				}
			}
		}

		if ( $this->parameters->getParameter( 'goal' ) == 'categories' ) {
			$categoriesGoal = true;
			$select = [
				$this->tableNames['page'] . '.page_id',
			];
			$options[] = 'DISTINCT';
		} else {
			if ( $calcRows ) {
				$options[] = 'SQL_CALC_FOUND_ROWS';
			}

			if ( $this->distinct ) {
				$options[] = 'DISTINCT';
			}

			$categoriesGoal = false;
			$select = $this->select;
		}

		$queryError = false;
		$pageIds = [];
		$result = '';

		try {
			if ( $categoriesGoal ) {
				$result =
					$this->dbr->select( $tables, $select, $this->where, __METHOD__, $options,
						$this->join );

				while ( $row = $result->fetchRow() ) {
					$pageIds[] = $row['page_id'];
				}

				$sql = $this->dbr->selectSQLText( [
					'clgoal' => 'categorylinks',
				], [
					'clgoal.cl_to',
				], [
					'clgoal.cl_from' => $pageIds,
				], __METHOD__, [
					'ORDER BY' => 'clgoal.cl_to ' . $this->direction,
				] );
			} else {
				$sql =
					$this->dbr->selectSQLText( $tables, $select, $this->where, __METHOD__, $options,
						$this->join );
			}

			$this->sqlQuery = $sql;
			$result = $this->dbr->query( $sql );

			if ( $calcRows ) {
				$calcRowsResult = $this->dbr->query( 'SELECT FOUND_ROWS() AS rowcount' );
				$total = $this->dbr->fetchRow( $calcRowsResult );
				$this->foundRows = intval( $total['rowcount'] );
				$this->dbr->freeResult( $calcRowsResult );
			}
		}
		catch ( Exception $e ) {
			$queryError = true;
		}

		if ( $queryError == true || $result === false ) {
			throw new MWException( __METHOD__ . ": " . wfMessage( 'dpl_query_error', DPL_VERSION,
					$this->dbr->lastError() )->text() );
		}

		return $result;
	}

	/**
	 * Add a table to the output.
	 *
	 * @param string $table Raw Table Name - Will be ran through tableName().
	 * @param string $alias Table Alias
	 * @return bool Success - Added, false if the table alias already exists.
	 * @throws \MWException
	 */
	public function addTable( $table, $alias ) {
		if ( empty( $table ) ) {
			throw new MWException( __METHOD__ . ': An empty table name was passed.' );
		}

		if ( empty( $alias ) || is_numeric( $alias ) ) {
			throw new MWException( __METHOD__ . ': An empty or numeric table alias was passed.' );
		}

		if ( !isset( $this->tables[$alias] ) ) {
			$this->tables[$alias] = $this->dbr->tableName( $table );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Add a field to select.
	 * Will ignore duplicate values if the exact same alias and exact same field are passed.
	 *
	 * @param array $fields Array of fields with the array key being the field alias.
	 *  Leave the array key as a numeric index to not specify an alias.
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addSelect( array $fields ) {
		foreach ( $fields as $alias => $field ) {
			if ( !is_numeric( $alias ) && array_key_exists( $alias, $this->select ) &&
			     $this->select[$alias] != $field ) {
				// In case of a code bug that is overwriting an existing field alias throw an
				// exception.

				throw new MWException( __METHOD__ .
				                       ": Attempted to overwrite existing field alias `{$this->select[$alias]}` AS `{$alias}` with `{$field}` AS `{$alias}`." );
			}

			// String alias and does not exist already.
			if ( !is_numeric( $alias ) && !array_key_exists( $alias, $this->select ) ) {
				$this->select[$alias] = $field;
			}

			// Speed up by not using in_array() or array_key_exists().  Toss the field names into
			// their own array as keys => true to exploit a speedy look up with isset().
			if ( is_numeric( $alias ) && !isset( $this->selectedFields[$field] ) ) {
				$this->select[] = $field;
				$this->selectedFields[$field] = true;
			}
		}

		return true;
	}

	/**
	 * Add a where clause to the output that uses NOT IN or !=.
	 *
	 * @param array $where Field => Value(s)
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addNotWhere( $where ) {
		if ( empty( $where ) ) {
			throw new MWException( __METHOD__ . ': An empty not where clause was passed.' );
		}

		if ( is_array( $where ) ) {
			foreach ( $where as $field => $values ) {
				$this->where[] =
					$field .
					( count( $values ) > 1 ? ' NOT IN(' . $this->dbr->makeList( $values ) . ')'
						: ' != ' . $this->dbr->addQuotes( current( $values ) ) );
			}
		} else {
			throw new MWException( __METHOD__ . ': An invalid not where clause was passed.' );
		}

		return true;
	}

	/**
	 * Return the number of found rows.
	 *
	 * @return int Number of Found Rows
	 */
	public function getFoundRows() {
		return $this->foundRows;
	}

	/**
	 * Returns the generated SQL Query
	 *
	 * @return string SQL Query
	 */
	public function getSqlQuery() {
		return $this->sqlQuery;
	}

	/**
	 * Set SQL for 'addauthor' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addauthor( $option ) {
		// Addauthor can not be used with addlasteditor.
		if ( !$this->parametersProcessed['addlasteditor'] ) {
			$this->addTable( 'revision', 'rev' );
			$this->addWhere( [
				$this->tableNames['page'] . '.page_id = rev.rev_page',
				'rev.rev_timestamp = (SELECT MIN(rev_aux_min.rev_timestamp) FROM ' .
				$this->tableNames['revision'] .
				' AS rev_aux_min WHERE rev_aux_min.rev_page = rev.rev_page)',
			] );
			$this->_adduser( null, 'rev' );
		}
	}

	/**
	 * Add a where clause to the output.
	 * Where clauses get imploded together with AND at the end.
	 * Any custom where clauses should be preformed before placed into here.
	 *
	 * @param array|string $where Where clause
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addWhere( $where ) {
		if ( empty( $where ) ) {
			throw new MWException( __METHOD__ . ': An empty where clause was passed.' );
		}

		if ( is_string( $where ) ) {
			$this->where[] = $where;
		} elseif ( is_array( $where ) ) {
			$this->where = array_merge( $this->where, $where );
		} else {
			throw new MWException( __METHOD__ . ': An invalid where clause was passed.' );
		}

		return true;
	}

	/**
	 * Set SQL for 'adduser' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @param string $tableAlias [Optional] Table Alias
	 * @return void
	 * @throws \MWException
	 */
	private function _adduser( $option, $tableAlias = '' ) {
		$tableAlias = ( !empty( $tableAlias ) ? $tableAlias . '.' : '' );
		$this->addSelect( [
			$tableAlias . 'rev_user',
			$tableAlias . 'rev_user_text',
			$tableAlias . 'rev_comment',
		] );
	}

	/**
	 * Set SQL for 'addcategories' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addcategories( $option ) {
		$this->addTable( 'categorylinks', 'cl_gc' );
		$this->addSelect( [
			'cats' => "GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ')",
		] );
		$this->addJoin( 'cl_gc', [
			'LEFT OUTER JOIN',
			'page_id = cl_gc.cl_from',
		] );
		$this->addGroupBy( $this->tableNames['page'] . '.page_id' );
	}

	/**
	 * Add a JOIN clause to the output.
	 *
	 * @param string $tableAlias Table Alias
	 * @param array $joinConditions Join Conditions in the format of the join type to the on
	 *  where condition. Example: ['JOIN TYPE' => 'this = that']
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addJoin( $tableAlias, $joinConditions ) {
		if ( empty( $tableAlias ) || empty( $joinConditions ) ) {
			throw new MWException( __METHOD__ . ': An empty join clause was passed.' );
		}

		if ( isset( $this->join[$tableAlias] ) ) {
			throw new MWException( __METHOD__ . ': Attempted to overwrite existing join clause.' );
		}

		$this->join[$tableAlias] = $joinConditions;

		return true;
	}

	/**
	 * Add a GROUP BY clause to the output.
	 *
	 * @param string $groupBy Group By Clause
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addGroupBy( $groupBy ) {
		if ( empty( $groupBy ) ) {
			throw new MWException( __METHOD__ . ': An empty group by clause was passed.' );
		}

		$this->groupBy[] = $groupBy;

		return true;
	}

	/**
	 * Set SQL for 'addcontribution' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addcontribution( $option ) {
		$this->addTable( 'recentchanges', 'rc' );
		$this->addSelect( [
			'contribution' => 'SUM(ABS(rc.rc_new_len - rc.rc_old_len))',
			'contributor' => 'rc.rc_user_text',
		] );
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rc.rc_cur_id',
		] );
		$this->addGroupBy( 'rc.rc_cur_id' );
	}

	/**
	 * Set SQL for 'addfirstcategorydate' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addfirstcategorydate( $option ) {
		// @TODO: This should be programmatically determining which categorylink table to use
		// instead of assuming the first one.
		$this->addSelect( [
			'cl_timestamp' => "DATE_FORMAT(cl1.cl_timestamp, '%Y%m%d%H%i%s')",
		] );
	}

	/**
	 * Set SQL for 'addlasteditor' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addlasteditor( $option ) {
		// Addlasteditor can not be used with addauthor.
		if ( !isset( $this->parametersProcessed['addauthor'] ) ||
		     !$this->parametersProcessed['addauthor'] ) {
			$this->addTable( 'revision', 'rev' );
			$this->addWhere( [
				$this->tableNames['page'] . '.page_id = rev.rev_page',
				'rev.rev_timestamp = (SELECT MAX(rev_aux_max.rev_timestamp) FROM ' .
				$this->tableNames['revision'] .
				' AS rev_aux_max WHERE rev_aux_max.rev_page = rev.rev_page)',
			] );
			$this->_adduser( null, 'rev' );
		}
	}

	/**
	 * Set SQL for 'addpagecounter' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addpagecounter( $option ) {
		if ( class_exists( "\\HitCounters\\Hooks" ) ) {
			$this->addTable( 'hit_counter', 'hit_counter' );
			$this->addSelect( [
				"page_counter" => "hit_counter.page_counter",
			] );

			if ( !isset( $this->join['hit_counter'] ) ) {
				$this->addJoin( 'hit_counter', [
					"LEFT JOIN",
					"hit_counter.page_id = " . $this->tableNames['page'] . '.page_id',
				] );
			}
		}
	}

	/**
	 * Set SQL for 'addpagesize' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addpagesize( $option ) {
		$this->addSelect( [
			"page_len" => "{$this->tableNames['page']}.page_len",
		] );
	}

	/**
	 * Set SQL for 'addpagetoucheddate' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _addpagetoucheddate( $option ) {
		$this->addSelect( [
			"page_touched" => "{$this->tableNames['page']}.page_touched",
		] );
	}

	/**
	 * Set SQL for 'allrevisionsbefore' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _allrevisionsbefore( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [
			'rev.rev_id',
			'rev.rev_timestamp',
		] );
		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( 'DESC' );
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp < ' . $this->convertTimestamp( $option ),
		] );
	}

	/**
	 * Add a ORDER BY clause to the output.
	 *
	 * @param string $orderBy Order By Clause
	 * @return bool Success
	 * @throws \MWException
	 */
	public function addOrderBy( $orderBy ) {
		if ( empty( $orderBy ) ) {
			throw new MWException( __METHOD__ . ': An empty order by clause was passed.' );
		}

		$this->orderBy[] = $orderBy;

		return true;
	}

	/**
	 * Set the ORDER BY direction
	 *
	 * @param string $direction SQL direction key word.
	 * @return bool Success
	 */
	public function setOrderDir( $direction ) {
		$this->direction = $direction;

		return true;
	}

	/**
	 * Helper method to handle relative timestamps.
	 *
	 * @param string $inputDate int or string
	 * @return int
	 */
	private function convertTimestamp( $inputDate ) {
		$timestamp = $inputDate;
		$intervalSpec = null;

		switch ( strtolower( $inputDate ) ) {
			case 'today':
				$timestamp = date( 'YmdHis' );
				break;

			case 'last hour':
				$intervalSpec = 'P1H';
				break;

			case 'last day':
				$intervalSpec = 'P1D';
				break;

			case 'last week':
				$intervalSpec = 'P7D';
				break;

			case 'last month':
				$intervalSpec = 'P1M';
				break;

			case 'last year':
				$intervalSpec = 'P1Y';
				break;
		}

		if ( strtolower( $inputDate ) !== 'today' && !is_null( $intervalSpec ) ) {
			$date = new DateTime();
			$date->sub( new DateInterval( $intervalSpec ) );
			$timestamp = $date->format( 'YmdHis' );
		}

		if ( is_numeric( $timestamp ) ) {
			return $this->dbr->addQuotes( $timestamp );
		}

		return 0;
	}

	/**
	 * Set SQL for 'allrevisionssince' parameter.
	 *
	 * @param string $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _allrevisionssince( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [
			'rev.rev_id',
			'rev.rev_timestamp',
		] );
		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( 'DESC' );
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp >= ' . $this->convertTimestamp( $option ),
		] );
	}

	/**
	 * Set SQL for 'articlecategory' parameter.
	 *
	 * @param string $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _articlecategory( $option ) {
		$this->addWhere( "{$this->tableNames['page']}.page_title IN (SELECT p2.page_title FROM {$this->tableNames['page']} p2 INNER JOIN {$this->tableNames['categorylinks']} clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = " .
		                 $this->dbr->addQuotes( $option ) . ") WHERE p2.page_namespace = 0)" );
	}

	/**
	 * Set SQL for 'categoriesminmax' parameter.
	 *
	 * @param array $option First Key min categories Second Key max categories
	 * @return void
	 * @throws \MWException
	 */
	private function _categoriesminmax( $option ) {
		if ( is_numeric( $option[0] ) ) {
			$this->addWhere( intval( $option[0] ) . ' <= (SELECT count(*) FROM ' .
			                 $this->tableNames['categorylinks'] . ' WHERE ' .
			                 $this->tableNames['categorylinks'] . '.cl_from=page_id)' );
		}

		if ( is_numeric( $option[1] ) ) {
			$this->addWhere( intval( $option[1] ) . ' >= (SELECT count(*) FROM ' .
			                 $this->tableNames['categorylinks'] . ' WHERE ' .
			                 $this->tableNames['categorylinks'] . '.cl_from=page_id)' );
		}
	}

	/**
	 * Set SQL for 'category' parameter.  This includes 'category', 'categorymatch', and 'categoryregexp'.
	 *
	 * @param array[][] $option ComparisonType => [ operatorType => [ CategoryGroups ] ]
	 * @return void
	 * @throws \MWException
	 */
	private function _category( $option ) {
		$i = 0;

		foreach ( $option as $comparisonType => $operatorTypes ) {
			foreach ( $operatorTypes as $operatorType => $categoryGroups ) {
				foreach ( $categoryGroups as $categories ) {
					$tableName = ( in_array( '', $categories ) ? 'dpl_clview' : 'categorylinks' );

					if ( $operatorType == 'AND' ) {
						foreach ( $categories as $category ) {
							$i ++;
							$tableAlias = "cl{$i}";
							$this->addTable( $tableName, $tableAlias );
							$this->addJoin( $tableAlias, [
								'INNER JOIN',
								"{$this->tableNames['page']}.page_id = {$tableAlias}.cl_from AND $tableAlias.cl_to {$comparisonType} " .
								$this->dbr->addQuotes( str_replace( ' ', '_', $category ) ),
							] );
						}
					} elseif ( $operatorType == 'OR' ) {
						$i ++;
						$tableAlias = "cl{$i}";
						$this->addTable( $tableName, $tableAlias );

						$joinOn =
							"{$this->tableNames['page']}.page_id = {$tableAlias}.cl_from AND (";
						$ors = [];

						foreach ( $categories as $category ) {
							$ors[] =
								"{$tableAlias}.cl_to {$comparisonType} " .
								$this->dbr->addQuotes( str_replace( ' ', '_', $category ) );
						}

						$joinOn .= implode( " {$operatorType} ", $ors );
						$joinOn .= ')';

						$this->addJoin( $tableAlias, [
							'INNER JOIN',
							$joinOn,
						] );
					}
				}
			}
		}
	}

	/**
	 * Set SQL for 'notcategory' parameter.
	 *
	 * @param array[][] $option ComparisonType => [ operatorType => [ CategoryGroups ] ]
	 * @return void
	 * @throws \MWException
	 */
	private function _notcategory( $option ) {
		$i = 0;

		foreach ( $option as $operatorType => $categories ) {
			foreach ( $categories as $category ) {
				$i ++;

				$tableAlias = "ecl{$i}";
				$this->addTable( 'categorylinks', $tableAlias );

				$this->addJoin( $tableAlias, [
					'LEFT OUTER JOIN',
					"{$this->tableNames['page']}.page_id = {$tableAlias}.cl_from AND {$tableAlias}.cl_to {$operatorType}" .
					$this->dbr->addQuotes( str_replace( ' ', '_', $category ) ),
				] );
				$this->addWhere( [
					"{$tableAlias}.cl_to" => null,
				] );
			}
		}
	}

	/**
	 * Set SQL for 'createdby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _createdby( $option ) {
		$this->addTable( 'revision', 'creation_rev' );
		$this->_adduser( null, 'creation_rev' );
		$this->addWhere( [
			$this->dbr->addQuotes( $option ) . ' = creation_rev.rev_user_text',
			'creation_rev.rev_page = page_id',
			'creation_rev.rev_parent_id = 0',
		] );
	}

	/**
	 * Set SQL for 'distinct' parameter.
	 *
	 * @param string|bool $option Strict/true for distinct, else not distinct
	 * @return void
	 */
	private function _distinct( $option ) {
		if ( $option == 'strict' || $option === true ) {
			$this->distinct = true;
		} else {
			$this->distinct = false;
		}
	}

	/**
	 * Set SQL for 'firstrevisionsince' parameter.
	 *
	 * @param string $option Date Time
	 * @return void
	 * @throws \MWException
	 */
	private function _firstrevisionsince( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [
			'rev.rev_id',
			'rev.rev_timestamp',
		] );
		// tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp >= ' . $this->dbr->addQuotes( $option ),
		] );
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp = (SELECT MIN(rev_aux_snc.rev_timestamp) FROM ' .
			$this->tableNames['revision'] .
			' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= ' .
			$this->convertTimestamp( $option ) . ')',
		] );
	}

	/**
	 * Set SQL for 'goal' parameter.
	 *
	 * @param string $option No limit, no offset if 'categories'
	 * @return void
	 */
	private function _goal( $option ) {
		if ( $option == 'categories' ) {
			$this->setLimit( false );
			$this->setOffset( false );
		}
	}

	/**
	 * Set the limit.
	 *
	 * @param mixed $limit Integer limit or false to unset.
	 * @return bool Success
	 */
	public function setLimit( $limit ) {
		if ( is_numeric( $limit ) ) {
			$this->limit = intval( $limit );
		} else {
			$this->limit = false;
		}

		return true;
	}

	/**
	 * Set the offset.
	 *
	 * @param mixed $offset Integer offset or false to unset.
	 * @return bool Success
	 */
	public function setOffset( $offset ) {
		if ( is_numeric( $offset ) ) {
			$this->offset = intval( $offset );
		} else {
			$this->offset = false;
		}

		return true;
	}

	/**
	 * Set SQL for 'hiddencategories' parameter.
	 *
	 * @param mixed $option Parameter Option
	 * @return void
	 */
	private function _hiddencategories( $option ) {
		// @TODO: Unfinished functionality!  Never implemented by original author.
	}

	/**
	 * Set SQL for 'imagecontainer' parameter.
	 *
	 * @param \Title[][] $option Title Array
	 * @return void
	 * @throws \MWException
	 */
	private function _imagecontainer( $option ) {
		$this->addTable( 'imagelinks', 'ic' );
		$this->addSelect( [
			'sortkey' => 'ic.il_to',
		] );

		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			$where = [
				"{$this->tableNames['page']}.page_namespace = " . intval( NS_FILE ),
				"{$this->tableNames['page']}.page_title = ic.il_to",
			];
		}

		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$ors[] =
						"LOWER(CAST(ic.il_from AS char) = LOWER(" .
						$this->dbr->addQuotes( $link->getArticleID() ) . ')';
				} else {
					$ors[] = "ic.il_from = " . $this->dbr->addQuotes( $link->getArticleID() );
				}
			}
		}

		$where[] = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'imageused' parameter.
	 *
	 * @param array $option array with links
	 * @return void
	 * @throws \MWException
	 */
	private function _imageused( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		$this->addTable( 'imagelinks', 'il' );
		$this->addSelect( [
			'image_sel_title' => 'il.il_to',
		] );
		$where[] = $this->tableNames['page'] . '.page_id = il.il_from';
		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				/** @var $link \Title */
				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$ors[] =
						"LOWER(CAST(il.il_to AS char))=LOWER(" .
						$this->dbr->addQuotes( $link->getDbKey() ) . ')';
				} else {
					$ors[] = "il.il_to=" . $this->dbr->addQuotes( $link->getDbKey() );
				}
			}
		}

		$where[] = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'lastmodifiedby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _lastmodifiedby( $option ) {
		$this->addWhere( $this->dbr->addQuotes( $option ) . ' = (SELECT rev_user_text FROM ' .
		                 $this->tableNames['revision'] . ' WHERE ' . $this->tableNames['revision'] .
		                 '.rev_page=page_id ORDER BY ' . $this->tableNames['revision'] .
		                 '.rev_timestamp DESC LIMIT 1)' );
	}

	/**
	 * Set SQL for 'lastrevisionbefore' parameter.
	 *
	 * @param string $option Date Time
	 * @return void
	 * @throws \MWException
	 */
	private function _lastrevisionbefore( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [ 'rev.rev_id', 'rev.rev_timestamp' ] );
		// tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp < ' . $this->convertTimestamp( $option ),
		] );
		$this->addWhere( [
			$this->tableNames['page'] . '.page_id = rev.rev_page',
			'rev.rev_timestamp = (SELECT MAX(rev_aux_bef.rev_timestamp) FROM ' .
			$this->tableNames['revision'] .
			' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < ' .
			$this->convertTimestamp( $option ) . ')',
		] );
	}

	/**
	 * Set SQL for 'linksfrom' parameter.
	 *
	 * @param \Title[][] $option Title Array
	 * @return void
	 * @throws \MWException
	 */
	private function _linksfrom( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = '(pl_from = ' . $link->getArticleID() . ')';
				}
			}
			$where[] = '(' . implode( ' OR ', $ors ) . ')';
		} else {
			$this->addTable( 'pagelinks', 'plf' );
			$this->addTable( 'page', 'pagesrc' );
			$this->addSelect( [
				'sel_title' => 'pagesrc.page_title',
				'sel_ns' => 'pagesrc.page_namespace',
			] );
			$where = [
				$this->tableNames['page'] . '.page_namespace = plf.pl_namespace',
				$this->tableNames['page'] . '.page_title = plf.pl_title',
				'pagesrc.page_id = plf.pl_from',
			];
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'plf.pl_from = ' . $link->getArticleID();
				}
			}

			$where[] = '(' . implode( ' OR ', $ors ) . ')';
		}
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'linksto' parameter.
	 *
	 * @param \Title[][] $option Title Array
	 * @return void
	 * @throws \MWException
	 */
	private function _linksto( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( count( $option ) > 0 ) {
			$this->addTable( 'pagelinks', 'pl' );
			$this->addSelect( [ 'sel_title' => 'pl.pl_title', 'sel_ns' => 'pl.pl_namespace' ] );

			foreach ( $option as $index => $linkGroup ) {
				if ( $index == 0 ) {
					$where = $this->tableNames['page'] . '.page_id=pl.pl_from AND ';
					$ors = [];

					foreach ( $linkGroup as $link ) {
						$_or = '(pl.pl_namespace=' . intval( $link->getNamespace() );

						if ( strpos( $link->getDbKey(), '%' ) >= 0 ) {
							$operator = 'LIKE';
						} else {
							$operator = '=';
						}

						if ( $this->parameters->getParameter( 'ignorecase' ) ) {
							$_or .= ' AND LOWER(CAST(pl.pl_title AS char)) ' . $operator .
							        ' LOWER(' . $this->dbr->addQuotes( $link->getDbKey() ) . ')';
						} else {
							$_or .= ' AND pl.pl_title ' . $operator . ' ' .
							        $this->dbr->addQuotes( $link->getDbKey() );
						}

						$_or .= ')';
						$ors[] = $_or;
					}

					$where .= '(' . implode( ' OR ', $ors ) . ')';
				} else {
					$where =
						'EXISTS(select pl_from FROM ' . $this->tableNames['pagelinks'] .
						' WHERE (' . $this->tableNames['pagelinks'] . '.pl_from=page_id AND ';
					$ors = [];

					foreach ( $linkGroup as $link ) {
						$_or =
							'(' . $this->tableNames['pagelinks'] . '.pl_namespace=' .
							intval( $link->getNamespace() );

						if ( strpos( $link->getDbKey(), '%' ) >= 0 ) {
							$operator = 'LIKE';
						} else {
							$operator = '=';
						}

						if ( $this->parameters->getParameter( 'ignorecase' ) ) {
							$_or .= ' AND LOWER(CAST(' . $this->tableNames['pagelinks'] .
							        '.pl_title AS char)) ' . $operator . ' LOWER(' .
							        $this->dbr->addQuotes( $link->getDbKey() ) . ')';
						} else {
							$_or .= ' AND ' . $this->tableNames['pagelinks'] . '.pl_title ' .
							        $operator . ' ' . $this->dbr->addQuotes( $link->getDbKey() );
						}

						$_or .= ')';
						$ors[] = $_or;
					}
					$where .= '(' . implode( ' OR ', $ors ) . ')';
					$where .= '))';
				}
				$this->addWhere( $where );
			}
		}
	}

	/**
	 * Set SQL for 'notlinksfrom' parameter.
	 *
	 * @param \Title[][] $option Title Array
	 * @return void
	 * @throws \MWException
	 */
	private function _notlinksfrom( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ands = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ands[] = 'pl_from <> ' . intval( $link->getArticleID() ) . ' ';
				}
			}

			$where = '(' . implode( ' AND ', $ands ) . ')';
		} else {
			$where =
				'CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT(' .
				$this->tableNames['pagelinks'] . '.pl_namespace,' . $this->tableNames['pagelinks'] .
				'.pl_title) FROM ' . $this->tableNames['pagelinks'] . ' WHERE ';
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] =
						$this->tableNames['pagelinks'] . '.pl_from = ' .
						intval( $link->getArticleID() );
				}
			}

			$where .= implode( ' OR ', $ors ) . ')';
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'notlinksto' parameter.
	 *
	 * @param \Title[][] $option Title Array
	 * @return void
	 * @throws \MWException
	 */
	private function _notlinksto( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( count( $option ) ) {
			$where =
				$this->tableNames['page'] . '.page_id NOT IN (SELECT ' .
				$this->tableNames['pagelinks'] . '.pl_from FROM ' . $this->tableNames['pagelinks'] .
				' WHERE ';
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$_or =
						'(' . $this->tableNames['pagelinks'] . '.pl_namespace=' .
						intval( $link->getNamespace() );

					if ( strpos( $link->getDbKey(), '%' ) >= 0 ) {
						$operator = 'LIKE';
					} else {
						$operator = '=';
					}

					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or .= ' AND LOWER(CAST(' . $this->tableNames['pagelinks'] .
						        '.pl_title AS char)) ' . $operator . ' LOWER(' .
						        $this->dbr->addQuotes( $link->getDbKey() ) . '))';
					} else {
						$_or .= ' AND ' . $this->tableNames['pagelinks'] . '.pl_title ' .
						        $operator . ' ' . $this->dbr->addQuotes( $link->getDbKey() ) . ')';
					}

					$ors[] = $_or;
				}
			}
			$where .= '(' . implode( ' OR ', $ors ) . '))';
			$this->addWhere( $where );
		}
	}

	/**
	 * Set SQL for 'linkstoexternal' parameter.
	 *
	 * @param array $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _linkstoexternal( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( count( $option ) > 0 ) {
			$this->addTable( 'externallinks', 'el' );
			$this->addSelect( [ 'el_to' => 'el.el_to' ] );

			foreach ( $option as $index => $linkGroup ) {
				if ( $index == 0 ) {
					$where = $this->tableNames['page'] . '.page_id=el.el_from AND ';
					$ors = [];

					foreach ( $linkGroup as $link ) {
						$ors[] = 'el.el_to LIKE ' . $this->dbr->addQuotes( $link );
					}

					$where .= '(' . implode( ' OR ', $ors ) . ')';
				} else {
					$where =
						'EXISTS(SELECT el_from FROM ' . $this->tableNames['externallinks'] .
						' WHERE (' . $this->tableNames['externallinks'] . '.el_from=page_id AND ';
					$ors = [];
					foreach ( $linkGroup as $link ) {
						$ors[] =
							$this->tableNames['externallinks'] . '.el_to LIKE ' .
							$this->dbr->addQuotes( $link );
					}
					$where .= '(' . implode( ' OR ', $ors ) . ')';
					$where .= '))';
				}

				$this->addWhere( $where );
			}
		}
	}

	/**
	 * Set SQL for 'maxrevisions' parameter.
	 *
	 * @param int $option Max Revisions count
	 * @return void
	 * @throws \MWException
	 */
	private function _maxrevisions( $option ) {
		$this->addWhere( "((SELECT count(rev_aux3.rev_page) FROM {$this->tableNames['revision']} AS rev_aux3 WHERE rev_aux3.rev_page = {$this->tableNames['page']}.page_id) <= {$option})" );
	}

	/**
	 * Set SQL for 'minoredits' parameter.
	 *
	 * @param string $option 'exclude'
	 * @return void
	 * @throws \MWException
	 */
	private function _minoredits( $option ) {
		if ( isset( $option ) && $option == 'exclude' ) {
			$this->addWhere( "rev_minor_edit = 0" );
		}
	}

	/**
	 * Set SQL for 'minrevisions' parameter.
	 *
	 * @param int $option Min Revisions count
	 * @return void
	 * @throws \MWException
	 */
	private function _minrevisions( $option ) {
		$this->addWhere( "((SELECT count(rev_aux2.rev_page) FROM {$this->tableNames['revision']} AS rev_aux2 WHERE rev_aux2.rev_page = {$this->tableNames['page']}.page_id) >= {$option})" );
	}

	/**
	 * Set SQL for 'modifiedby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _modifiedby( $option ) {
		$this->addTable( 'revision', 'change_rev' );
		$this->addWhere( $this->dbr->addQuotes( $option ) .
		                 ' = change_rev.rev_user_text AND change_rev.rev_page = page_id' );
	}

	/**
	 * Set SQL for 'namespace' parameter.
	 *
	 * @param array $option Namespaces
	 * @return void
	 * @throws \MWException
	 */
	private function _namespace( $option ) {
		if ( is_array( $option ) && count( $option ) ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$this->addWhere( [
					"{$this->tableNames['pagelinks']}.pl_namespace" => $option,
				] );
			} else {
				$this->addWhere( [
					"{$this->tableNames['page']}.page_namespace" => $option,
				] );
			}
		}
	}

	/**
	 * Set SQL for 'notcreatedby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _notcreatedby( $option ) {
		$this->addTable( 'revision', 'no_creation_rev' );
		$this->addWhere( $this->dbr->addQuotes( $option ) .
		                 ' != no_creation_rev.rev_user_text AND no_creation_rev.rev_page = page_id AND no_creation_rev.rev_parent_id = 0' );
	}

	/**
	 * Set SQL for 'notlastmodifiedby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _notlastmodifiedby( $option ) {
		$this->addWhere( $this->dbr->addQuotes( $option ) . ' != (SELECT rev_user_text FROM ' .
		                 $this->tableNames['revision'] . ' WHERE ' . $this->tableNames['revision'] .
		                 '.rev_page=page_id ORDER BY ' . $this->tableNames['revision'] .
		                 '.rev_timestamp DESC LIMIT 1)' );
	}

	/**
	 * Set SQL for 'notmodifiedby' parameter.
	 *
	 * @param string $option Name
	 * @return void
	 * @throws \MWException
	 */
	private function _notmodifiedby( $option ) {
		$this->addWhere( 'NOT EXISTS (SELECT 1 FROM ' . $this->tableNames['revision'] . ' WHERE ' .
		                 $this->tableNames['revision'] . '.rev_page=page_id AND ' .
		                 $this->tableNames['revision'] . '.rev_user_text = ' .
		                 $this->dbr->addQuotes( $option ) . ' LIMIT 1)' );
	}

	/**
	 * Set SQL for 'notnamespace' parameter.
	 *
	 * @param array $option Namespace Array
	 * @return void
	 * @throws \MWException
	 */
	private function _notnamespace( $option ) {
		if ( is_array( $option ) && count( $option ) ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$this->addNotWhere( [
					"{$this->tableNames['pagelinks']}.pl_namespace" => $option,
				] );
			} else {
				$this->addNotWhere( [
					"{$this->tableNames['page']}.page_namespace" => $option,
				] );
			}
		}
	}

	/**
	 * Set SQL for 'count' parameter.
	 *
	 * @param int $option Limit Count
	 * @return void
	 */
	private function _count( $option ) {
		$this->setLimit( $option );
	}

	/**
	 * Set SQL for 'offset' parameter.
	 *
	 * @param int $option Offset
	 * @return void
	 */
	private function _offset( $option ) {
		$this->setOffset( $option );
	}

	/**
	 * Set SQL for 'order' parameter.
	 *
	 * @param string $option 'descending' / 'desc' for DESC, ASC otherwise
	 * @return void
	 */
	private function _order( $option ) {
		$orderMethod = $this->parameters->getParameter( 'ordermethod' );

		if ( !empty( $orderMethod ) && is_array( $orderMethod ) && $orderMethod[0] !== 'none' ) {
			if ( $option === 'descending' || $option === 'desc' ) {
				$this->setOrderDir( 'DESC' );
			} else {
				$this->setOrderDir( 'ASC' );
			}
		}
	}

	/**
	 * Set SQL for 'ordercollation' parameter.
	 *
	 * @param string $option Parameter Option
	 * @return bool
	 */
	private function _ordercollation( $option ) {
		$option = mb_strtolower( $option );

		$results = $this->dbr->query( 'SHOW CHARACTER SET' );

		if ( !$results ) {
			return false;
		}

		while ( $row = $results->fetchRow() ) {
			if ( $option == $row['Default collation'] ) {
				$this->setCollation( $option );
				break;
			}
		}

		return true;
	}

	/**
	 * Set the character set collation.
	 *
	 * @param string $collation Collation
	 * @return void
	 */
	public function setCollation( $collation ) {
		$this->collation = $collation;
	}

	/**
	 * Set SQL for 'ordermethod' parameter.
	 *
	 * @param array $option Parameter Option
	 * @return bool|void
	 * @throws \MWException
	 */
	private function _ordermethod( $option ) {
		global $wgContLang;

		if ( $this->parameters->getParameter( 'goal' ) == 'categories' ) {
			// No order methods for returning categories.
			return true;
		}

		$namespaces = $wgContLang->getNamespaces();
		// $aStrictNs = array_slice((array) Config::getSetting('allowedNamespaces'), 1, count
		//(Config::getSetting('allowedNamespaces')), true);
		$namespaces = array_slice( $namespaces, 3, count( $namespaces ), true );
		$_namespaceIdToText = "CASE {$this->tableNames['page']}.page_namespace";

		foreach ( $namespaces as $id => $name ) {
			$_namespaceIdToText .= ' WHEN ' . intval( $id ) . " THEN " .
			                       $this->dbr->addQuotes( $name . ':' );
		}

		$_namespaceIdToText .= ' END';

		$revisionAuxWhereAdded = false;
		$option = (array)$option;

		foreach ( $option as $orderMethod ) {
			switch ( $orderMethod ) {
				case 'category':
					$this->addOrderBy( 'cl_head.cl_to' );
					// Gives category headings in the result.
					$this->addSelect( [ 'cl_head.cl_to' ] );

					if ( ( is_array( $this->parameters->getParameter( 'catheadings' ) ) &&
					       in_array( '', $this->parameters->getParameter( 'catheadings' ) ) ) ||
					     ( is_array( $this->parameters->getParameter( 'catnotheadings' ) ) &&
					       in_array( '', $this->parameters->getParameter( 'catnotheadings' ) ) ) ) {
						$_clTableName = 'dpl_clview';
						$_clTableAlias = $_clTableName;
					} else {
						$_clTableName = 'categorylinks';
						$_clTableAlias = 'cl_head';
					}

					$this->addTable( $_clTableName, $_clTableAlias );
					$this->addTable( 'revision', 'rev' );
					$this->addJoin( $_clTableAlias, [
						"LEFT OUTER JOIN",
						"page_id = cl_head.cl_from",
					] );

					if ( is_array( $this->parameters->getParameter( 'catheadings' ) ) &&
					     count( $this->parameters->getParameter( 'catheadings' ) ) ) {
						$this->addWhere( [
							"cl_head.cl_to" => $this->parameters->getParameter( 'catheadings' ),
						] );
					}

					if ( is_array( $this->parameters->getParameter( 'catnotheadings' ) ) &&
					     count( $this->parameters->getParameter( 'catnotheadings' ) ) ) {
						$this->addNotWhere( [
							'cl_head.cl_to' => $this->parameters->getParameter( 'catnotheadings' ),
						] );
					}
					break;

				case 'categoryadd':
					// @TODO: See TODO in __addfirstcategorydate().
					$this->addOrderBy( 'cl1.cl_timestamp' );
					break;

				case 'counter':
					if ( class_exists( "\\HitCounters\\Hooks" ) ) {
						// If the "addpagecounter" parameter was not used the table and join need
						// to be added now.
						if ( !array_key_exists( 'hit_counter', $this->tables ) ) {
							$this->addTable( 'hit_counter', 'hit_counter' );

							if ( !isset( $this->join['hit_counter'] ) ) {
								$this->addJoin( 'hit_counter', [
									"LEFT JOIN",
									"hit_counter.page_id = " . $this->tableNames['page'] .
									'.page_id',
								] );
							}
						}

						$this->addOrderBy( 'hit_counter.page_counter' );
					}
					break;

				case 'firstedit':
					$this->addOrderBy( 'rev.rev_timestamp' );
					$this->addTable( 'revision', 'rev' );
					$this->addSelect( [
						'rev.rev_timestamp',
					] );

					if ( !$revisionAuxWhereAdded ) {
						$this->addWhere( [
							"{$this->tableNames['page']}.page_id = rev.rev_page",
							"rev.rev_timestamp = (SELECT MIN(rev_aux.rev_timestamp) FROM {$this->tableNames['revision']} AS rev_aux WHERE rev_aux.rev_page=rev.rev_page)",
						] );
					}

					$revisionAuxWhereAdded = true;
					break;

				case 'lastedit':
					if ( \DynamicPageListHooks::isLikeIntersection() ) {
						$this->addOrderBy( 'page_touched' );
						$this->addSelect( [
							"page_touched" => "{$this->tableNames['page']}.page_touched",
						] );
					} else {
						$this->addOrderBy( 'rev.rev_timestamp' );
						$this->addTable( 'revision', 'rev' );
						$this->addSelect( [ 'rev.rev_timestamp' ] );

						if ( !$revisionAuxWhereAdded ) {
							$this->addWhere( [
								"{$this->tableNames['page']}.page_id = rev.rev_page",
								"rev.rev_timestamp = (SELECT MAX(rev_aux.rev_timestamp) FROM {$this->tableNames['revision']} AS rev_aux WHERE rev_aux.rev_page = rev.rev_page)",
							] );
						}

						$revisionAuxWhereAdded = true;
					}
					break;

				case 'pagesel':
					$this->addOrderBy( 'sortkey' );
					$this->addSelect( [
						'sortkey' => 'CONCAT(pl.pl_namespace, pl.pl_title) ' .
						             $this->getCollateSQL(),
					] );
					break;

				case 'pagetouched':
					$this->addOrderBy( 'page_touched' );
					$this->addSelect( [
						"page_touched" => "{$this->tableNames['page']}.page_touched",
					] );
					break;

				case 'size':
					$this->addOrderBy( 'page_len' );
					break;

				case 'sortkey':
					$this->addOrderBy( 'sortkey' );
					// If cl_sortkey is null (uncategorized page), generate a sortkey in the usual way (full page name, underscores replaced with spaces).
					// UTF-8 created problems with non-utf-8 MySQL databases
					$replaceConcat =
						"REPLACE(CONCAT({$_namespaceIdToText}, " . $this->tableNames['page'] .
						".page_title), '_', ' ')";

					if ( count( $this->parameters->getParameter( 'category' ) ) +
					     count( $this->parameters->getParameter( 'notcategory' ) ) > 0 ) {
						if ( in_array( 'category',
							$this->parameters->getParameter( 'ordermethod' ) ) ) {
							$this->addSelect( [
								'sortkey' => "IFNULL(cl_head.cl_sortkey, {$replaceConcat}) " .
								             $this->getCollateSQL(),
							] );
						} else {
							// This runs on the assumption that at least one category parameter
							// was used and that numbering starts at 1.
							$this->addSelect( [
								'sortkey' => "IFNULL(cl1.cl_sortkey, {$replaceConcat}) " .
								             $this->getCollateSQL(),
							] );
						}
					} else {
						$this->addSelect( [
							'sortkey' => $replaceConcat,
						] );
					}
					break;

				case 'titlewithoutnamespace':
					if ( $this->parameters->getParameter( 'openreferences' ) ) {
						$this->addOrderBy( "pl_title" );
					} else {
						$this->addOrderBy( "page_title" );
					}

					$this->addSelect( [
						'sortkey' => "{$this->tableNames['page']}.page_title " .
						             $this->getCollateSQL(),
					] );
					break;

				case 'title':
					$this->addOrderBy( 'sortkey' );

					if ( $this->parameters->getParameter( 'openreferences' ) ) {
						$this->addSelect( [
							'sortkey' => "REPLACE(CONCAT(IF(pl_namespace  =0, '', CONCAT(" .
							             $_namespaceIdToText . ", ':')), pl_title), '_', ' ') " .
							             $this->getCollateSQL(),
						] );
					} else {
						// Generate sortkey like for category links. UTF-8 created problems with
						// non-utf-8 MySQL databases.
						$this->addSelect( [
							'sortkey' => "REPLACE(CONCAT(IF(" . $this->tableNames['page'] .
							             ".page_namespace = 0, '', CONCAT(" . $_namespaceIdToText .
							             ", ':')), " . $this->tableNames['page'] .
							             ".page_title), '_', ' ') " . $this->getCollateSQL(),
						] );
					}
					break;

				case 'user':
					$this->addOrderBy( 'rev.rev_user_text' );
					$this->addTable( 'revision', 'rev' );
					$this->_adduser( null, 'rev' );
					break;

				case 'none':
					break;
			}
		}
	}

	/**
	 * Return SQL prefixed collation.
	 *
	 * @return string SQL Collation
	 */
	public function getCollateSQL() {
		return ( $this->collation !== false ? 'COLLATE ' . $this->collation : null );
	}

	/**
	 * Set SQL for 'redirects' parameter.
	 *
	 * @param string $option 'only' / 'exclude'
	 * @return void
	 * @throws \MWException
	 */
	private function _redirects( $option ) {
		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			switch ( $option ) {
				case 'only':
					$this->addWhere( [
						$this->tableNames['page'] . ".page_is_redirect" => 1,
					] );
					break;

				case 'exclude':
					$this->addWhere( [
						$this->tableNames['page'] . ".page_is_redirect" => 0,
					] );
					break;
			}
		}
	}

	/**
	 * Set SQL for 'stablepages' parameter.
	 *
	 * @param string $option 'only' / 'exclude'
	 * @return void
	 * @throws \MWException
	 */
	private function _stablepages( $option ) {
		if ( function_exists( 'efLoadFlaggedRevs' ) ) {
			// Do not add this again if 'qualitypages' has already added it.
			if ( !$this->parametersProcessed['qualitypages'] ) {
				$this->addJoin( 'flaggedpages', [
					"LEFT JOIN",
					"page_id = fp_page_id",
				] );
			}

			switch ( $option ) {
				case 'only':
					$this->addWhere( [
						'fp_stable IS NOT NULL',
					] );
					break;

				case 'exclude':
					$this->addWhere( [
						'fp_stable' => null,
					] );
					break;
			}
		}
	}

	/**
	 * Set SQL for 'qualitypages' parameter.
	 *
	 * @param string $option 'only' / 'exclude'
	 * @return void
	 * @throws \MWException
	 */
	private function _qualitypages( $option ) {
		if ( function_exists( 'efLoadFlaggedRevs' ) ) {
			// Do not add this again if 'stablepages' has already added it.
			if ( !$this->parametersProcessed['stablepages'] ) {
				$this->addJoin( 'flaggedpages', [
					"LEFT JOIN",
					"page_id = fp_page_id",
				] );
			}

			switch ( $option ) {
				case 'only':
					$this->addWhere( 'fp_quality >= 1' );
					break;

				case 'exclude':
					$this->addWhere( 'fp_quality = 0' );
					break;
			}
		}
	}

	/**
	 * Set SQL for 'title' parameter.
	 *
	 * @param \Title[][] $option Array with Titles
	 * @return void
	 * @throws \MWException
	 */
	private function _title( $option ) {
		$ors = [];

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				if ( $this->parameters->getParameter( 'openreferences' ) ) {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or =
							"LOWER(CAST(pl_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "pl_title {$comparisonType} " . $this->dbr->addQuotes( $title );
					}
				} else {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or =
							"LOWER(CAST({$this->tableNames['page']}.page_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or =
							"{$this->tableNames['page']}.page_title {$comparisonType}" .
							$this->dbr->addQuotes( $title );
					}
				}

				$ors[] = $_or;
			}
		}

		$where = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'nottitle' parameter.
	 *
	 * @param \Title[][] $option Array with Titles zo exclude
	 * @return void
	 * @throws \MWException
	 */
	private function _nottitle( $option ) {
		$ors = [];

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				if ( $this->parameters->getParameter( 'openreferences' ) ) {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or =
							"LOWER(CAST(pl_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "pl_title {$comparisonType} " . $this->dbr->addQuotes( $title );
					}
				} else {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or =
							"LOWER(CAST({$this->tableNames['page']}.page_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or =
							"{$this->tableNames['page']}.page_title {$comparisonType}" .
							$this->dbr->addQuotes( $title );
					}
				}

				$ors[] = $_or;
			}
		}

		$where = 'NOT (' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'titlegt' parameter.
	 *
	 * @param string $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _titlegt( $option ) {
		$where = '(';

		if ( substr( $option, 0, 2 ) == '=_' ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$where .= 'pl_title >= ' . $this->dbr->addQuotes( substr( $option, 2 ) );
			} else {
				$where .= $this->tableNames['page'] . '.page_title >= ' .
				          $this->dbr->addQuotes( substr( $option, 2 ) );
			}
		} else {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$where .= 'pl_title > ' . $this->dbr->addQuotes( $option );
			} else {
				$where .= $this->tableNames['page'] . '.page_title > ' .
				          $this->dbr->addQuotes( $option );
			}
		}

		$where .= ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'titlelt' parameter.
	 *
	 * @param string $option Parameter Option
	 * @return void
	 * @throws \MWException
	 */
	private function _titlelt( $option ) {
		$where = '(';

		if ( substr( $option, 0, 2 ) == '=_' ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$where .= 'pl_title <= ' . $this->dbr->addQuotes( substr( $option, 2 ) );
			} else {
				$where .= $this->tableNames['page'] . '.page_title <= ' .
				          $this->dbr->addQuotes( substr( $option, 2 ) );
			}
		} else {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$where .= 'pl_title < ' . $this->dbr->addQuotes( $option );
			} else {
				$where .= $this->tableNames['page'] . '.page_title < ' .
				          $this->dbr->addQuotes( $option );
			}
		}

		$where .= ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'usedby' parameter.
	 *
	 * @param \Title[][] $option Array with Titles
	 * @return void
	 * @throws \MWException
	 */
	private function _usedby( $option ) {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'tpl_from = ' . intval( $link->getArticleID() );
				}
			}
			$where = '(' . implode( ' OR ', $ors ) . ')';
		} else {
			$this->addTable( 'templatelinks', 'tpl' );
			$this->addTable( 'page', 'tplsrc' );
			$this->addSelect( [
				'tpl_sel_title' => 'tplsrc.page_title',
				'tpl_sel_ns' => 'tplsrc.page_namespace',
			] );
			$where =
				$this->tableNames['page'] .
				'.page_title = tpl.tl_title AND tplsrc.page_id = tpl.tl_from AND ';
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'tpl.tl_from = ' . intval( $link->getArticleID() );
				}
			}

			$where .= '(' . implode( ' OR ', $ors ) . ')';
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'uses' parameter.
	 *
	 * @param \Title[][] $option Array with Titles
	 * @return void
	 * @throws \MWException
	 */
	private function _uses( $option ) {
		$ors = [];

		$this->addTable( 'templatelinks', 'tl' );
		$where = $this->tableNames['page'] . '.page_id=tl.tl_from AND (';

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$_or = '(tl.tl_namespace=' . intval( $link->getNamespace() );

				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$_or .= " AND LOWER(CAST(tl.tl_title AS char))=LOWER(" .
					        $this->dbr->addQuotes( $link->getDbKey() ) . '))';
				} else {
					$_or .= " AND tl.tl_title=" . $this->dbr->addQuotes( $link->getDbKey() ) . ')';
				}

				$ors[] = $_or;
			}
		}

		$where .= implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'notuses' parameter.
	 *
	 * @param \Title[][] $option Array with Titles
	 * @return void
	 * @throws \MWException
	 */
	private function _notuses( $option ) {
		$ors = [];

		if ( count( $option ) > 0 ) {
			$where =
				$this->tableNames['page'] . '.page_id NOT IN (SELECT ' .
				$this->tableNames['templatelinks'] . '.tl_from FROM ' .
				$this->tableNames['templatelinks'] . ' WHERE (';

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$_or =
						'(' . $this->tableNames['templatelinks'] . '.tl_namespace=' .
						intval( $link->getNamespace() );

					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or .= ' AND LOWER(CAST(' . $this->tableNames['templatelinks'] .
						        '.tl_title AS char))=LOWER(' .
						        $this->dbr->addQuotes( $link->getDbKey() ) . '))';
					} else {
						$_or .= ' AND ' . $this->tableNames['templatelinks'] . '.tl_title=' .
						        $this->dbr->addQuotes( $link->getDbKey() ) . ')';
					}

					$ors[] = $_or;
				}
			}

			$where .= implode( ' OR ', $ors ) . '))';
			$this->addWhere( $where );
		}
	}
}
