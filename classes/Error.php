<?php
/**
 * DynamicPageList3
 * DPL ErrorCodes Class
 *
 * @author FoXFTW
 * @license GPL
 * @package DynamicPageList3
 *
 **/

namespace DPL;

abstract class Error {
	// FATAL
	/**
	 * $1: 'namespace' or 'notnamespace'
	 * $2: wrong parameter given by user
	 * $3: list of possible titles of namespaces (except pseudo-namespaces: Media, Special)
	 */
	//const FATAL_WRONG_NAMESPACE = 1001;

	/**
	 * $1: linksto'
	 * $2: the wrong parameter given by user
	 */
	//const FATAL_WRONG_LINKS_TO = 1002;

	/**
	 * $1: max number of categories that can be included
	 * 1003
	 */
	const CRITICAL_TOO_MANY_CATEGORIES = 3;

	/**
	 * $1: min number of categories that have to be included
	 * 1004
	 * 1005
	 * 1007
	 * 1008
	 */
	const CRITICAL_TOO_FEW_CATEGORIES = 4;

	const CRITICAL_NO_SELECTION = 5;

	//const FATAL_CAT_DATE_BUT_NO_INCLUDED_CATS = 1006;

	const CRITICAL_CAT_DATE_BUT_MORE_THAN_ONE_CAT = 7;

	const CRITICAL_MORE_THAN_ONE_TYPE_OF_DATE = 8;

	/**
	 * $1: param=val that is possible only with $1 as last 'ordermethod' parameter
	 * $2: last 'ordermethod' parameter required for $0
	 * 1009
	 */
	const CRITICAL_WRONG_ORDER_METHOD = 9;

	/**
	 * $1: the number of arguments in includepage
	 * 1010
	 */
	const CRITICAL_DOMINANT_SECTION_RANGE = 10;

	/**
	 * $1: prefix_dpl_clview where 'prefix' is the prefix of your mediawiki table names
	 * $2: SQL query to create the prefix_dpl_clview on your mediawiki DB
	 * 1011
	 * 1012
	 * 1022
	 * 1023
	 * 1024
	 */
	const CRITICAL_NO_CL_VIEW = 11;

	const CRITICAL_OPEN_REFERENCES = 12;

	//const FATAL_MISSING_PARAM_FUNCTION = 1022;

	const CRITICAL_NOT_PROTECTED = 23;

	const CRITICAL_SQL_BUILD_ERROR = 24;

	// ERROR

	// WARN
	/**
	 * $1: unknown parameter given by user
	 * $2: list of DPL available parameters separated by ', '
	 * 2013
	 */
	const WARN_UNKNOWN_PARAM = 13;

	/**
	 * $1: Parameter given by user
	 * 2022
	 */
	const WARN_PARAM_NO_OPTION = 22;

	/**
	 * $3: list of valid param values separated by ' | '
	 * 2014
	 */
	const WARN_WRONG_PARAM = 14;

	/**
	 * $1: param name
	 * $2: wrong param value given by user
	 * $3: default param value used instead by program
	 * 2015
	 * 2016
	 * 2017
	 */
	//const WARN_WRONG_PARAM_INT = 15;

	const WARN_NO_RESULTS = 16;

	const WARN_CAT_OUTPUT_BUT_WRONG_PARAMS = 17;

	/**
	 * $1: 'headingmode' value given by user
	 * $2: value used instead by program (which means no heading)
	 * 18
	 */
	const WARN_HEADING_BUT_SIMPLE_ORDER_METHOD = 18;

	/**
	 * $1: 'log' value
	 */
	//const WARN_DEBUG_PARAM_NOT_FIRST = 2019;

	/**
	 * $1: title of page that creates an infinite transclusion loop
	 * 2020
	 */
	const WARN_TRANSCLUSION_LOOP = 20;

	// INFO

	// DEBUG
	/**
	 * $1: SQL query executed to generate the dynamic page list
	 */
	//const DEBUG_QUERY = 3021;
}
