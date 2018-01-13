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

class ErrorCodes {
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
	 */
	const FATAL_TOO_MANY_CATEGORIES = 1003;

	/**
	 * $1: min number of categories that have to be included
	 */
	const FATAL_TOO_FEW_CATEGORIES = 1004;

	const FATAL_NO_SELECTION = 1005;

	//const FATAL_CAT_DATE_BUT_NO_INCLUDED_CATS = 1006;

	const FATAL_CAT_DATE_BUT_MORE_THAN_ONE_CAT = 1007;

	const FATAL_MORE_THAN_ONE_TYPE_OF_DATE = 1008;

	/**
	 * $1: param=val that is possible only with $1 as last 'ordermethod' parameter
	 * $2: last 'ordermethod' parameter required for $0
	 */
	const FATAL_WRONG_ORDER_METHOD = 1009;

	/**
	 * $1: the number of arguments in includepage
	 */
	const FATAL_DOMINANT_SECTION_RANGE = 1010;

	/**
	 * $1: prefix_dpl_clview where 'prefix' is the prefix of your mediawiki table names
	 * $2: SQL query to create the prefix_dpl_clview on your mediawiki DB
	 */
	const FATAL_NO_CL_VIEW = 1011;

	const FATAL_OPEN_REFERENCES = 1012;

	//const FATAL_MISSING_PARAM_FUNCTION = 1022;

	const FATAL_NOT_PROTECTED = 1023;

	const FATAL_SQL_BUILD_ERROR = 1024;

	// ERROR

	// WARN
	/**
	 * $1: unknown parameter given by user
	 * $2: list of DPL available parameters separated by ', '
	 */
	const WARN_UNKNOWN_PARAM = 2013;

	/**
	 * $1: Parameter given by user
	 */
	const WARN_PARAM_NO_OPTION = 2022;

	/**
	 * $3: list of valid param values separated by ' | '
	 */
	const WARN_WRONG_PARAM = 2014;

	/**
	 * $1: param name
	 * $2: wrong param value given by user
	 * $3: default param value used instead by program
	 */
	//const WARN_WRONG_PARAM_INT = 2015;

	const WARN_NO_RESULTS = 2016;

	const WARN_CAT_OUTPUT_BUT_WRONG_PARAMS = 2017;

	/**
	 * $1: 'headingmode' value given by user
	 * $2: value used instead by program (which means no heading)
	 */
	const WARN_HEADING_BUT_SIMPLE_ORDER_METHOD = 2018;

	/**
	 * $1: 'log' value
	 */
	//const WARN_DEBUG_PARAM_NOT_FIRST = 2019;

	/**
	 * $1: title of page that creates an infinite transclusion loop
	 */
	const WARN_TRANSCLUSION_LOOP = 2020;

	// INFO

	// DEBUG
	/**
	 * $1: SQL query executed to generate the dynamic page list
	 */
	//const DEBUG_QUERY = 3021;
}
